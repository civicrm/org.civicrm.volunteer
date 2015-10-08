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
   * The profile IDs associated with this form.
   *
   * @var array
   * @protected
   */
  protected $_profile_ids = array();

  /**
   * The project IDs associated with this form.
   *
   * @var array
   * @protected
   */
  protected $projectIds = array();

  /**
   * Set default values for the form.
   *
   * @access public
   */
  function setDefaultValues() {
    $defaults = array();

    if (key_exists('userID', $_SESSION['CiviCRM'])) {
      foreach($this->getProfileIDs() as $profileID) {
        $fields = array_flip(array_keys(CRM_Core_BAO_UFGroup::getFields($profileID)));
        CRM_Core_BAO_UFGroup::setProfileDefaults($_SESSION['CiviCRM']['userID'], $fields, $defaults);
      }
    }

    return $defaults;
  }

  /**
   * set variables up before form is built
   *
   * @access public
   */
  function preProcess() {
    // VOL-71: permissions check is moved from XML to preProcess function to support
    // permissions-challenged Joomla instances
    if (CRM_Core_Config::singleton()->userPermissionClass->isModulePermissionSupported()
      && !CRM_Volunteer_Permission::check('register to volunteer')
    ) {
      CRM_Utils_System::permissionDenied();
    }

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
      $this->projectIds[] = $need['project_id'];
    }
    $this->projectIds = array_unique($this->projectIds);

    $this->setDestination();
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE);

    // current mode
    $this->_mode = ($this->_action == CRM_Core_Action::PREVIEW) ? 'test' : 'live';
  }

  /**
   * Search for profiles
   *
   * @return array
   *   UFGroup (Profile) Ids
   */
  function getProfileIDs() {
    if (empty($this->_profile_ids)) {
      $profileIds = array();

      foreach ($this->projectIds as $projectId) {
        $dao = new CRM_Core_DAO_UFJoin();
        $dao->entity_table = CRM_Volunteer_BAO_Project::$_tableName;
        $dao->entity_id = $projectId;
        $dao->orderBy('weight asc');
        $dao->find();

        while ($dao->fetch()) {
          $profileIds[] = $dao->uf_group_id;
        }
      }

      $this->_profile_ids = array_unique($profileIds);
    }

    return $this->_profile_ids;
  }

  function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Sign Up to Volunteer'));

    $this->buildCustom();

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit', array('domain' => 'org.civicrm.volunteer')),
        'isDefault' => TRUE,
      ),
    ));
  }

  /**
   * @todo per totten's suggestion, wrap all these writes in a transaction;
   * see http://wiki.civicrm.org/confluence/display/CRMDOC43/Transaction+Reference
   */
  function postProcess() {
    $cid = CRM_Utils_Array::value('userID', $_SESSION['CiviCRM'], NULL);
    $values = $this->controller->exportValues();

    $profileFields = array();
    foreach ($this->getProfileIDs() as $profileID) {
      $profileFields += CRM_Core_BAO_UFGroup::getFields($profileID);
    }
    $profileValues = array_intersect_key($values, $profileFields);
    $activityValues = array_diff_key($values, $profileValues);

    // Search for duplicate
    if (!$cid) {
      $dedupeParams = CRM_Dedupe_Finder::formatParams($profileValues, 'Individual');
      $dedupeParams['check_permission'] = FALSE;
      $ids = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual');
      if ($ids) {
        $cid = $ids[0];
      }
    }

    $cid = CRM_Contact_BAO_Contact::createProfileContact(
      $profileValues,
      $profileFields,
      $cid
    );

    $activity_statuses = CRM_Activity_BAO_Activity::buildOptions('status_id', 'create');
    $projectNeeds = array();
    foreach($this->_needs as $need) {
      $activityValues['volunteer_need_id'] = $need['id'];
      $activityValues['activity_date_time'] = CRM_Utils_Array::value('start_time', $need);
      $activityValues['assignee_contact_id'] = $cid;
      $activityValues['is_test'] = ($this->_mode === 'test' ? 1 : 0);
      // below we assume that volunteers are always signing up only themselves;
      // for now this is a safe assumption, but we may need to revisit this.
      $activityValues['source_contact_id'] = $cid;

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

    // Send confirmation email to volunteer 
    list($displayName, $email) = CRM_Contact_BAO_Contact_Location::getEmailDetails($cid);
    list($domainEmailName, $domainEmailAddress) = CRM_Core_BAO_Domain::getNameAndEmail();
    if($email){


      $tplParams = array();
      foreach(array_keys($projectNeeds) as $projectId) {
        //Fetch the Projects
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

          //Move the data around so it makes sense for template use
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

          $project['opportunities'] = $projectNeeds[$project['id']];
        }
        $tplParams[] = $project;
      }
      $sendTemplateParams = array(
        'groupName' => 'msg_tpl_workflow_volunteer',
        'valueName' => 'volunteer_registration',
        'tplParams' => array("volunteer_projects" => $tplParams),

        'contactId' => $cid,
        'isTest' => false,
      );

      $sendTemplateParams['from'] = "$domainEmailName <".$domainEmailAddress.">";
      //$sendTemplateParams['cc'] = "$domainEmailName <".$domainEmailAddress.">";
      $sendTemplateParams['toName'] = $displayName;
      $sendTemplateParams['toEmail'] = $email;

      list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplate::sendTemplate($sendTemplateParams);
      $something = "Just testing";
    }

    $statusMsg = ts('You are scheduled to volunteer. Thank you!', array('domain' => 'org.civicrm.volunteer'));
    CRM_Core_Session::setStatus($statusMsg, '', 'success');
    CRM_Utils_System::redirect($this->_destination);
  }

  /**
   * assign profiles to a Smarty template
   *
   * @param string $name The name to give the Smarty variable
   * @access public
   */
  function buildCustom() {
    $contactID = CRM_Utils_Array::value('userID', $_SESSION['CiviCRM']);
    $profiles = array();
    $fieldList = array(); // master field list

    foreach($this->getProfileIDs() as $profileID) {
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
          TRUE
        );
        $profiles[$profileID][$key] = $fieldList[$key] = $field;
      }
    }
    $this->assign('customProfiles', $profiles);
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
        if (count($this->projectIds) === 1) {
          $eventId = CRM_Volunteer_BAO_Project::retrieveByID($this->projectIds[0])->entity_id;
          $path = 'civicrm/event/info';
          $query = "reset=1&id={$eventId}";
          break;
        }
      case 'list':
      default:
        $path = 'civicrm/a/';
        $fragment = '/volunteer/opportunities';
    }

    $this->_destination = CRM_Utils_System::url($path, $query, FALSE, $fragment);
  }
}
