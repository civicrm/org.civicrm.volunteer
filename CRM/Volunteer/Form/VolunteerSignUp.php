<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Volunteer_Form_VolunteerSignUp extends CRM_Core_Form {

  /**
   * Determines whether or not slider-widget-enabled fields (e.g., skill level assessments)
   * should be rendered as slider widgets (TRUE) or multi-selects (FALSE).
   *
   * @var boolean
   */
  public $allowVolunteerSliderWidget = TRUE;

  /**
   * The URL to which the user should be redirected after successfully
   * submitting the sign-up form
   *
   * @var string
   * @protected
   */
  protected $_destination;

  /**
   * the mode that we are in
   *
   * @var string
   * @protected
   */
  protected $_mode;

  /**
   * The needs the volunteer is signing up for.
   *
   * @var array
   *   need_id => api.VolunteerNeed.getsingle
   * @protected
   */
  protected $_needs = array();

  /**
   * Error messages to display, related to the need IDs passed to the form via URL.
   *
   * @var array
   */
  protected $preProcessErrors = array();

  /**
   * The profile IDs associated with this form and marked
   * for use with the primary contact.
   *
   * Do not use directly; access via $this->getPrimaryVolunteerProfileIDs().
   *
   * @var array
   * @protected
   */
  protected $_primary_volunteer_profile_ids = array();

  /**
   * The profile IDs associated with this form and marked
   * for use with additional volunteers.
   *
   * Do not use directly; access via $this->getAdditionalVolunteerProfileIDs().
   *
   * @var array
   * @protected
   */
  protected $_additional_volunteer_profile_ids = array();

  /**
   * The contact ID of the primary volunteer.
   *
   * @var int
   */
  protected $_primary_volunteer_id;

  /**
   * The volunteer projects associated with this form, keyed by project ID.
   *
   * @var array
   * @protected
   */
  protected $_projects = array();

  /**
   * Set default values for the form.
   *
   * @access public
   */
  function setDefaultValues() {
    $defaults = array();

    if (key_exists('userID', $_SESSION['CiviCRM'])) {
      foreach($this->getPrimaryVolunteerProfileIDs() as $profileID) {
        $fields = array_flip(array_keys(CRM_Core_BAO_UFGroup::getFields($profileID)));
        CRM_Core_BAO_UFGroup::setProfileDefaults($_SESSION['CiviCRM']['userID'], $fields, $defaults);
      }
    }

    return $defaults;
  }

  /**
   * The "vid" URL parameter for this form was deprecated in CiviVolunteer 2.0.
   *
   * This redirect preserves backward compatibility for links from the Event
   * Info page associated with a Volunteer Project. See VOL-180 for more info.
   */
  function redirectLegacyRequests() {
    $vid = CRM_Utils_Request::retrieve('vid', 'Int', $this, FALSE, NULL, 'GET');

    if($vid != NULL) {
      $path = "civicrm/vol/";
      $fragment =  "/volunteer/opportunities?project=$vid&dest=event";
      $newURL = CRM_Utils_System::url($path, NULL, FALSE, $fragment, FALSE, TRUE);
      CRM_Utils_System::redirect($newURL);
    }
  }

  /**
   * set variables up before form is built
   *
   * @access public
   */
  function preProcess() {
    $this->redirectLegacyRequests();

    CRM_Core_Resources::singleton()
        ->addScriptFile('org.civicrm.volunteer', 'js/CRM_Volunteer_Form_VolunteerSignUp.js')
        ->addScriptFile('civicrm.packages', 'jquery/plugins/jquery.notify.min.js', -9990, 'html-header', FALSE);

    $validNeedIds = array();
    $needs = CRM_Utils_Request::retrieve('needs', 'String', $this, TRUE);
    if (!is_array($needs)) {
      $needs = explode(',', $needs);
    }

    foreach($needs as $need) {
      if (CRM_Utils_Type::validate($need, 'Positive', FALSE)) {
        $validNeedIds[] = $need;
      }
    }
    $api = civicrm_api3('VolunteerNeed', 'get', array(
      'id' => array('IN' => $validNeedIds),
    ));
    $this->_needs = $api['values'];

    foreach ($this->_needs as $need) {
      $this->_projects[$need['project_id']] = array();
    }
    $this->fetchProjectDetails();

    $this->preProcessNeeds();

    $this->setDestination();
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE);

    // current mode
    $this->_mode = ($this->_action == CRM_Core_Action::PREVIEW) ? 'test' : 'live';
  }

  /**
   * Preprocesses needs passed via URL.
   *
   * Checks that the supplied needs are valid for registration (e.g., is the
   * project or need enabled? has the need already been filled?).
   */
  private function preProcessNeeds() {
    $invalidatedProjects = array();
    $openNeeds = array();
    foreach ($this->_projects as $projectId => $projectArr) {
      if (!$projectArr['is_active']) {
        $this->preProcessErrors[0] = ts('One or more of the specified volunteer opportunities is associated with a project which has been deleted or disabled.', array('domain' => 'org.civicrm.volunteer'));
        $invalidatedProjects[$projectId] = $projectArr;
        continue;
      }
      $openNeeds += CRM_Volunteer_BAO_Project::retrieveByID($projectId)->open_needs;
    }

    foreach ($this->_needs as $needId => &$needArr) {
      // Don't bother checking for need validity if the project has been invalidated.
      if (array_key_exists($needArr['project_id'], $invalidatedProjects)) {
        continue;
      }

      if (!$needArr['is_active']) {
        $this->preProcessErrors[1] = ts('One or more specified volunteer opportunities has been deleted or disabled.', array('domain' => 'org.civicrm.volunteer'));
        continue;
      }

      if (!array_key_exists($needId, $openNeeds) && !$needArr['is_flexible']) {
        $this->preProcessErrors[2] = ts('One or more volunteer opportunities is at maximum capacity or is in the past.', array('domain' => 'org.civicrm.volunteer'));
        continue;
      }

      $needArr['quantity_available'] = $openNeeds[$needId]['quantity'] - $openNeeds[$needId]['quantity_assigned'];
    }
  }

  /**
   * Returns the audience for a given profile.
   *
   * @param array $profile
   *   In the format of api.UFJoin.get.values.
   * @return string
   *   One of 'primary' (the default), 'additional', or 'both.'
   */
  private function getProfileAudience(array $profile) {
    $allowedValues = array('primary', 'additional', 'both');
    $audience = 'primary';

    $moduleData = json_decode(CRM_Utils_Array::value("module_data", $profile));
    if (property_exists($moduleData, 'audience') && in_array($moduleData->audience, $allowedValues)) {
      $audience = $moduleData->audience;
    }

    return $audience;
  }

  /**
   * Return profiles used for Primary Volunteers
   *
   * @return array
   *   UFGroup (Profile) Ids
   */
  function getPrimaryVolunteerProfileIDs() {
    if (empty($this->_primary_volunteer_profile_ids)) {
      $profileIds = array();

      foreach ($this->_projects as $project) {
        foreach ($project['profiles'] as $profile) {
          if ($this->getProfileAudience($profile) !== "additional") {
            $profileIds[] = $profile['uf_group_id'];
          }
        }
      }

      $this->_primary_volunteer_profile_ids = array_unique($profileIds);
    }

    return $this->_primary_volunteer_profile_ids;
  }

  /**
   * Return profiles used for Additional Volunteers
   *
   * @return array
   *   UFGroup (Profile) Ids
   */
  function getAdditionalVolunteerProfileIDs() {
    if (empty($this->_additional_volunteer_profile_ids)) {
      $profileIds = array();

      foreach ($this->_projects as $project) {
        foreach ($project['profiles'] as $profile) {
          if ($this->getProfileAudience($profile) !== "primary") {
            $profileIds[] = $profile['uf_group_id'];
          }
        }
      }

      $this->_additional_volunteer_profile_ids = array_unique($profileIds);
    }

    return $this->_additional_volunteer_profile_ids;
  }

  /**
   * Retrieves project details and caches them in $this->_projects.
   */
  function fetchProjectDetails() {
    foreach ($this->_projects as $projectId => &$projectDetails) {
      $projectDetails = civicrm_api3('VolunteerProject', 'getsingle', array(
        'id' => $projectId,
        'api.VolunteerProjectContact.get' => array(
          'relationship_type_id' => 'volunteer_beneficiary',
          'api.Contact.getvalue' => array(
            'return' => 'display_name',
          ),
        ),
      ));

      $projectDetails['beneficiaries'] = array();
      foreach ($projectDetails['api.VolunteerProjectContact.get']['values'] as $beneficiary) {
        $projectDetails['beneficiaries'][] = $beneficiary['api.Contact.getvalue'];
      }
      unset($projectDetails['api.VolunteerProjectContact.get']);
    }
  }

  function buildQuickForm() {
    if (count($this->preProcessErrors)) {
      $this->buildErrorPage();
      return;
    }

    CRM_Utils_System::setTitle(ts('Sign Up to Volunteer', array('domain' => 'org.civicrm.volunteer')));

    $contactID = CRM_Utils_Array::value('userID', $_SESSION['CiviCRM']);
    $profiles = $this->buildCustom($this->getPrimaryVolunteerProfileIDs(), $contactID);
    $this->assign('customProfiles', $profiles);

    foreach ($this->_needs as &$need) {
      $projectId = (int) $need['project_id'];
      $need['project'] = array();
      $need['project']['beneficiaries'] = implode('<br />', $this->_projects[$projectId]['beneficiaries']);
      $need['project']['description'] = $this->_projects[$projectId]['description'];
      $need['project']['title'] = $this->_projects[$projectId]['title'];
    }

    // Order by project name (alphabetical)
    usort($this->_needs, function ($volunteerNeedA, $volunteerNeedB){
      if ($volunteerNeedA['project']['title'] == $volunteerNeedB['project']['title']) {
        return 0;
      }
      return ($volunteerNeedA['project']['title'] < $volunteerNeedB['project']['title']) ? -1 : 1;
    });

    $this->assign('volunteerNeeds', $this->_needs);

    $this->addButtons(array(
      array(
        'type' => 'upload',
        'name' => ts('Submit', array('domain' => 'org.civicrm.volunteer')),
        'isDefault' => TRUE,
      ),
    ));

    $additionalVolunteerProfiles = $this->buildAdditionalVolunteerTemplate();

    // Only display profiles for additional volunteers (also referred to as
    // group registrations) if such profiles exist and if exactly one project is
    // in play. The reason for the restriction by project quantity is that some
    // projects may opt to disable group registration; allowing group sign-ups
    // when multiple projects are in play creates some ambiguity about which
    // projects the additional volunteers should be assigned to.
    $allowAdditionalVolunteers = (!empty($additionalVolunteerProfiles) && count($this->_projects) === 1);
    $this->assign('allowAdditionalVolunteers', $allowAdditionalVolunteers);
    if ($allowAdditionalVolunteers) {
      //Give the volunteer a box to select how many friends they are bringing
      $this->add("text", "additionalVolunteerQuantity", ts("Number of Additional Volunteers", array('domain' => 'org.civicrm.volunteer')), array("size" => 3));

      // VOL-282: Cap how many additional volunteers can be added based on the opp with the fewest openings
      $quantitiesAvailable = array_column($this->_needs, 'quantity_available');
      reset($this->_projects);
      CRM_Core_Resources::singleton()->addVars('org.civicrm.volunteer', array(
        // subtract 1 to account for the primary volunteer
        'maxAddtlReg' => min($quantitiesAvailable) - 1,
        'projectId' => key($this->_projects),
      ));

      if(!empty($this->_submitValues)) {

        $additionalVolunteerQuantity = CRM_Utils_Array::value("additionalVolunteerQuantity", $this->_submitValues, 0);
        if ($additionalVolunteerQuantity > 0) {
          $i = 0;
          $additionalVolunteerProfiles = array();
          while ($i < $additionalVolunteerQuantity) {
            $additionalVolunteerProfiles[$i] = array();
            $additionalVolunteerProfiles[$i]['prefix'] = "additionalVolunteers_$i";
            $additionalVolunteerProfiles[$i]['profiles'] = $this->buildAdditionalVolunteerTemplate($additionalVolunteerProfiles[$i]['prefix'], false);
            $i++;
          }
          $this->assign('additionalVolunteerProfiles', $additionalVolunteerProfiles);
        }
      }
      $profileFields = array();
      foreach ($this->getAdditionalVolunteerProfileIDs() as $profileID) {
        $profileFields += CRM_Core_BAO_UFGroup::getFields($profileID);
      }
      //styling for additional volunteers form
      CRM_Core_Resources::singleton()->addScriptFile('org.civicrm.volunteer', 'js/VolunteerSignUp.js', 12);
      CRM_Core_Resources::singleton()->addStyleFile('org.civicrm.volunteer', 'css/additional_volunteers.css');
    }
    CRM_Core_Resources::singleton()->addStyleFile('org.civicrm.volunteer', 'css/signup.css');
  }

  /**
   * Validates the user submission.
   *
   * Overrides the default validation, ignoring validation errors on additional
   * volunteers.
   *
   * @return boolean
   *   Returns TRUE if no errors found.
   */
  function validate() {
    parent::validate();

    foreach($this->_errors as $name => $msg) {
      if(substr($name, 0, strlen("additionalVolunteersTemplate")) == "additionalVolunteersTemplate") {
        unset($this->_errors[$name]);
      }
    }

    return (0 == count($this->_errors));
  }

  /**
   * @todo per totten's suggestion, wrap all these writes in a transaction;
   * see http://wiki.civicrm.org/confluence/display/CRMDOC43/Transaction+Reference
   */
  function postProcess() {
    $cid = CRM_Utils_Array::value('userID', $_SESSION['CiviCRM'], NULL);
    $values = $this->controller->exportValues($this->_name);

    $profileFields = $this->getProfileFields($this->getPrimaryVolunteerProfileIDs());
    $profileFieldsByType = array_reduce($profileFields, array($this, 'reduceByType'), array());
    $activityFields = CRM_Utils_Array::value('Activity', $profileFieldsByType, array());
    $activityValues = array_intersect_key($values, $activityFields);
    $contactValues = array_diff_key($values, $activityValues);

    $this->_primary_volunteer_id = $this->processContactProfileData($contactValues, $profileFields, $cid);
    $projectNeeds = $this->createVolunteerActivity($this->_primary_volunteer_id, $activityValues);
    $this->sendVolunteerConfirmationEmail($this->_primary_volunteer_id, $projectNeeds);
    $this->processAdditionalVolunteers($values);

    $statusMsg = ts('You are scheduled to volunteer. Thank you!', array('domain' => 'org.civicrm.volunteer'));
    CRM_Core_Session::setStatus($statusMsg, '', 'success');
    CRM_Core_Session::singleton()->pushUserContext($this->_destination);
  }

  /**
   * @param array $profileIds
   *   An array of IDs
   * @return array
   *   An array of fieldData, keyed by fieldName
   */
  private function getProfileFields(array $profileIds) {
    $profileFields = array();
    foreach ($profileIds as $profileID) {
      $profileFields += CRM_Core_BAO_UFGroup::getFields($profileID);
    }
    return $profileFields;
  }

  /**
   * Callback for array_reduce.
   *
   * @link http://php.net/manual/en/function.array-reduce.php
   */
  private function reduceByType($carry, $item) {
    $fieldName = $item['name'];
    $fieldType = $item['field_type'];
    $carry[$fieldType][$fieldName] = $item;
    return $carry;
  }

  /**
   * This function sends a confirmation email to a signed up volunteer
   *
   * @param $cid - ContactID of volunteer
   * @param $projectNeeds - The project needs this person has been signed up for.
   */
  function sendVolunteerConfirmationEmail($cid, $projectNeeds) {

    list($displayName, $email) = CRM_Contact_BAO_Contact_Location::getEmailDetails($cid);
    list($domainEmailName, $domainEmailAddress) = CRM_Core_BAO_Domain::getNameAndEmail();

    if ($email) {
      $tplParams = $this->prepareTplParams($projectNeeds);
      $sendTemplateParams = array(
        'contactId' => $cid,
        'from' => "$domainEmailName <" . $domainEmailAddress . ">",
        'groupName' => 'msg_tpl_workflow_volunteer',
        'isTest' => ($this->_mode === 'test'),
        'toName' => $displayName,
        'toEmail' => $email,
        'tplParams' => array("volunteer_projects" => $tplParams),
        'valueName' => 'volunteer_registration',
      );

      $bcc = array();
      foreach ($tplParams as $data) {
        foreach ($data['contacts'] as $manager) {
          $bcc[$manager['contact_id']] = "{$manager['display_name']} <{$manager['email']}>";
        }
      }

      if (count($bcc)) {
        $sendTemplateParams['bcc'] = implode(', ', $bcc);
      }

      CRM_Core_BAO_MessageTemplate::sendTemplate($sendTemplateParams);
    }
  }

  /**
   * This function Loops through the needs the user is signing up for
   * and creates activity records for them.
   *
   * @param int $cid
   *   The contact ID for whom this activity is to be created
   * @param array $activityValues
   *   An array of values corresponding to the data the user submitted minus the profile fields
   * @return array
   *   Project needs data for use in sending confirmation email.
   */
  private function createVolunteerActivity($cid, array $activityValues) {
    $projectNeeds = array();
    $activity_statuses = CRM_Activity_BAO_Activity::buildOptions('status_id', 'create');

    foreach($this->_needs as $need) {
      $activityValues['volunteer_need_id'] = $need['id'];
      $activityValues['activity_date_time'] = CRM_Utils_Array::value('start_time', $need);
      $activityValues['assignee_contact_id'] = $cid;
      $activityValues['is_test'] = ($this->_mode === 'test' ? 1 : 0);
      $activityValues['source_contact_id'] = $this->_primary_volunteer_id;

      // Set status to Available if user selected Flexible Need, else set to Scheduled.
      if (CRM_Utils_Array::value('is_flexible', $need)) {
        $activityValues['status_id'] = CRM_Utils_Array::key('Available', $activity_statuses);
      } else {
        $activityValues['status_id'] = CRM_Utils_Array::key('Scheduled', $activity_statuses);
      }

      $activityValues['time_scheduled_minutes'] = CRM_Utils_Array::value('duration', $need);
      CRM_Volunteer_BAO_Assignment::createVolunteerActivity($activityValues);

      if(!array_key_exists($need['project_id'], $projectNeeds)) {
        $projectNeeds[$need['project_id']] = array();
      }

      $need['role'] = $need['role_label'];
      $need['description'] = $need['role_description'];
      $need['duration'] = CRM_Utils_Array::value('duration', $need);
      $projectNeeds[$need['project_id']][$need['id']] = $need;
    }
    return $projectNeeds;
  }

  /**
   * Process the data returned by a completed profile
   *
   * @param array $profileValues
   *   The data the user submitted to the Signup page for a given profile
   * @param array $profileFields
   *   A list of field definitions for this profile
   * @param int $cid
   *   The Contact ID of the user for whom this profile is being processed
   *
   * @return int
   *   The contact id of the user for whom this data was saved (This can be a new contact)
   */
  private function processContactProfileData(array $profileValues, array $profileFields, $cid = null) {
    // Search for duplicate
    if (!$cid) {
      $dedupeParams = CRM_Dedupe_Finder::formatParams($profileValues, 'Individual');
      $dedupeParams['check_permission'] = FALSE;
      $ids = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual');
      if ($ids) {
        $cid = $ids[0];
      }
    }

    return CRM_Contact_BAO_Contact::createProfileContact(
      $profileValues,
      $profileFields,
      $cid
    );
  }


  /**
   * Saves the contact and activity records for additional volunteers and sends
   * confirmation email.
   *
   * @param array $data
   *   The form data that was submitted
   */
  function processAdditionalVolunteers(array $data) {
    $qty = CRM_Utils_Array::value('additionalVolunteerQuantity', $data, 0);
    $qty = CRM_Utils_Type::validate($qty, 'Integer', FALSE);

    if ($qty === NULL) {
      return;
    }

    $profileFields = $this->getProfileFields($this->getAdditionalVolunteerProfileIDs());
    $profileFieldsByType = array_reduce($profileFields, array($this, 'reduceByType'), array());
    $activityProfileFields = CRM_Utils_Array::value('Activity', $profileFieldsByType, array());

    $index = 0;
    while ($index < $qty) {
      $profileData = CRM_Utils_Array::value('additionalVolunteers_' . $index, $data, array());
      $activityData = array_intersect_key($profileData, $activityProfileFields);
      $contactData = array_diff_key($profileData, $activityData);

      $cid = $this->processContactProfileData($contactData, $profileFields);
      $projectNeeds = $this->createVolunteerActivity($cid, $activityData);
      $this->sendVolunteerConfirmationEmail($cid, $projectNeeds);

      $index++;
    }
  }

  /**
   * Fetches project data and formats it, along with need data, for the message template.
   *
   * @param array $projectNeeds
   *   The needs the volunteer is signing up for, in this format: $projectId => array($needId => $needDetails, ...)
   * @return array
   */
  function prepareTplParams(array $projectNeeds) {
    $tplParams = array();

    // The foreach loop is a workaround for api.volunteer_project.get's inability to
    // handle advanced operators, i.e., 'id' => array('IN' => array(1,2,3)).
    foreach($projectNeeds as $projectId => $needs) {
      $result = civicrm_api3('VolunteerProject', 'get', array(
        'return' => "title,description",
        'sequential' => 1,
        'api.VolunteerProjectContact.get' => array(
          'relationship_type_id' => "volunteer_manager",
          'return' => "contact_id",
          'api.contact.get' => array(
            'return' => "display_name,email,phone"
          )
        ),
        'api.LocBlock.get' => array('return' => "all"),
        'id' => $projectId
      ));

      if ($result['count'] > 0) {
        $project = $result['values'][0];

        // Move the data around so it makes sense for template use
        if ($project['api.LocBlock.get']['count'] == 1) {
          $project['location'] = $project['api.LocBlock.get']['values'][0];
          $project['location']['email'] = (array_key_exists("email", $project['location'])) ? $project['location']['email']['email'] : "";
          $project['location']['email2'] = (array_key_exists("email2", $project['location'])) ? $project['location']['email2']['email'] : "";
          $project['location']['phone'] = (array_key_exists("phone", $project['location'])) ? $project['location']['phone']['phone'] : "";
          $project['location']['phone2'] = (array_key_exists("phone2", $project['location'])) ? $project['location']['phone2']['phone'] : "";
        }
        $project['contacts'] = array();
        foreach ($project['api.VolunteerProjectContact.get']['values'] as $contact) {
          $project['contacts'][] = $contact['api.contact.get']['values'][0];
        }

        $project['opportunities'] = $needs;
      }
      $tplParams[] = $project;
    }

    return $tplParams;
  }

  /**
   * Adds profiles to the form.
   *
   * @param array $profileIds
   *   The profiles to prepare for the template.
   * @param int $contactID
   *   The contact whose information will be input into/displayed in the profiles.
   * @param type $prefix
   *   The prefix to give to the field names in the profiles.
   * @return array
   *   Returns an array of field definitions that have been added to the form.
   *   This result can be passed to a Smarty template as a variable.
   */
  function buildCustom(array $profileIds = array(), $contactID = null, $prefix = '') {
    $profiles = array();
    $fieldList = array(); // master field list

    foreach($profileIds as $profileID) {
      $fields = CRM_Core_BAO_UFGroup::getFields($profileID, FALSE, CRM_Core_Action::ADD,
        NULL, NULL, FALSE, NULL,
        FALSE, NULL, CRM_Core_Permission::CREATE,
        'field_name', TRUE
      );

      foreach ($fields as $key => $field) {
        if (array_key_exists($key, $fieldList)) continue;

        CRM_Core_BAO_UFGroup::buildProfile(
          $this,
          $field,
          CRM_Profile_Form::MODE_CREATE,
          $contactID,
          TRUE,
          null,
          null,
          $prefix
        );
        $profiles[$profileID][$key] = $fieldList[$key] = $field;
      }
    }
    return $profiles;
  }


  /**
   * Compiles the Additional Volunteer Profiles.
   *
   * @param string $prefix
   *   The prefix for the form elements as well as the name of the Smarty
   *   array which contains them all.
   * @param boolean $assign
   *   If TRUE, a Smarty variable named $prefix is added to the form.
   * @return array
   *   An array of the additional volunteer profiles. The array is empty if
   *   there are none.
   */
  function buildAdditionalVolunteerTemplate($prefix = "additionalVolunteersTemplate", $assign = true) {
    $profiles = $this->buildCustom($this->getAdditionalVolunteerProfileIDs(), 0, $prefix);

    if($assign) {
      $this->assign($prefix, $profiles);
    }

    return $profiles;
  }

  /**
   * Set $this->_destination, the URL to which the user should be redirected
   * after successfully submitting the sign-up form
   */
  protected function setDestination() {
    $path = $query = $fragment = NULL;

    $dest = CRM_Utils_Request::retrieve('dest', 'String', $this, FALSE);
    switch ($dest) {
      case 'event':
        // If only one project is associated with the form, send the user back
        // to that event form; otherwise, default to the vol opps page.
        if (count($this->_projects) === 1) {
          $project = reset($this->_projects);
          $eventId = $project['entity_id'];
          $path = 'civicrm/event/info';
          $query = "reset=1&id={$eventId}";
          break;
        }
      case 'list':
      default:
        $path = 'civicrm/vol/';
        $fragment = '/volunteer/opportunities';
    }

    $this->_destination = CRM_Utils_System::url($path, $query, FALSE, $fragment);
  }

  /**
   * Subroutine of buildQuickForm. Used to display preProcessing validation
   * errors to the user. Prevents the display of form elements.
   */
  private function buildErrorPage() {
    CRM_Utils_System::setTitle(ts('Please select a different volunteer opportunity', array('domain' => 'org.civicrm.volunteer')));
    $region = CRM_Core_Region::instance('page-body');
    $region->update('default', array(
      'disabled' => TRUE,
    ));
    $region->add(array(
      'template' => 'CRM/Volunteer/Form/Error.tpl',
    ));
    $this->assign('errors', $this->preProcessErrors);
  }

}
