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
   * Validated hash-relative opportunity-browser state for the Back action.
   *
   * @var string
   */
  protected $_returnContext = self::RETURN_CONTEXT_PATH;

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
      $newURL = CRM_Utils_System::url($path, '', FALSE, $fragment, TRUE, FALSE);
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
    // preProcessNeeds() indexes by need ID, so keep the API3 result keying.
    $this->_needs = \Civi\Api4\VolunteerNeed::get()
      ->addSelect('*')
      ->addWhere('id', 'IN', $validNeedIds)
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();

    foreach ($this->_needs as $need) {
      $this->_projects[$need['project_id']] = array();
    }
    $this->fetchProjectDetails();

    $this->preProcessNeeds();

    $this->setReturnContext();
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
      // SR-005 (security review 2026-08-23): a project with no usable
      // primary registration profile would render an empty form whose
      // submission created a volunteer activity with no contact behind
      // it -- silently consuming shift capacity. The Angular editor
      // enforces the one-profile rule client-side only, so API-created
      // projects can reach this state. Refuse them here, before any
      // form is built or any write can happen.
      if (!$this->projectHasPrimaryProfile($projectArr)) {
        $this->preProcessErrors[3] = ts('One or more of the specified volunteer opportunities belongs to a project that has not finished setup (no registration form is configured). Please contact the site administrator.', array('domain' => 'org.civicrm.volunteer'));
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

      $needArr['quantity_available'] = !empty($needArr['is_flexible'])
        ? NULL
        : $openNeeds[$needId]['quantity'] - $openNeeds[$needId]['quantity_assigned'];
    }
  }

  /**
   * Whether the project row carries at least one active primary-audience
   * registration profile.
   *
   * The public project read has already filtered the profiles list to
   * active UFJoins; this only checks that one of them serves primary
   * volunteers (audience 'primary' or 'both').
   *
   * @param array $projectArr
   *   A row from $this->_projects.
   * @return bool
   */
  private function projectHasPrimaryProfile(array $projectArr) {
    foreach (($projectArr['profiles'] ?? array()) as $profile) {
      if (!is_array($profile)) {
        continue;
      }
      if ($this->getProfileAudience($profile) !== 'additional') {
        return TRUE;
      }
    }
    return FALSE;
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

    $moduleData = json_decode($profile['module_data'] ?? '');
    if (is_object($moduleData) && property_exists($moduleData, 'audience') && in_array($moduleData->audience, $allowedValues, TRUE)) {
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
      // The public project read supplies the `profiles` payload that
      // getProfileAudience() reads, which a plain DAO get cannot. It also
      // filtered on is_active, so a disabled project cannot supply signup copy.
      $projects = \Civi\Api4\VolunteerProject::search(FALSE)
        ->setFilters(array('id' => $projectId, 'is_active' => 1))
        ->execute()
        ->getArrayCopy();
      $projectDetails = CRM_Utils_Array::single($projects, 'VolunteerProject record');

      $projectDetails['description'] = CRM_Utils_String::purifyHTML($projectDetails['description'] ?? '');
      $projectDetails['beneficiaries'] = self::getContactDisplayNames(
        CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, 'volunteer_beneficiary')
      );
    }
    unset($projectDetails);
  }

  /**
   * Resolve display names for the given contacts, preserving their order.
   *
   * Replaces the former api.Contact.getvalue chain hung off each project
   * contact: one read covers the whole set.
   *
   * @param array $contactIds
   * @return array
   */
  private static function getContactDisplayNames(array $contactIds) {
    if (!$contactIds) {
      return array();
    }
    $contacts = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', 'display_name')
      ->addWhere('id', 'IN', $contactIds)
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();
    $displayNames = array();
    foreach ($contactIds as $contactId) {
      if (!empty($contacts[$contactId]['display_name'])) {
        $displayNames[] = $contacts[$contactId]['display_name'];
      }
    }
    return $displayNames;
  }

  /**
   * Resolve name and primary contact details, preserving the given order.
   *
   * Replaces the former api.contact.get chain hung off each project contact.
   * APIv3's Contact.get flattened the primary email and phone onto the record;
   * API4 exposes them through the email_primary/phone_primary joins.
   *
   * @param array $contactIds
   * @return array
   */
  private static function getContactSummaries(array $contactIds) {
    if (!$contactIds) {
      return array();
    }
    $contacts = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', 'display_name', 'email_primary.email', 'phone_primary.phone')
      ->addWhere('id', 'IN', $contactIds)
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();
    $summaries = array();
    foreach ($contactIds as $contactId) {
      if (!isset($contacts[$contactId])) {
        continue;
      }
      $contact = $contacts[$contactId];
      $summaries[] = array(
        'id' => (int) $contactId,
        'display_name' => $contact['display_name'] ?? '',
        'email' => $contact['email_primary.email'] ?? '',
        'phone' => $contact['phone_primary.phone'] ?? '',
      );
    }
    return $summaries;
  }

  function buildQuickForm() {
    if (count($this->preProcessErrors)) {
      $this->buildErrorPage();
      return;
    }

    CRM_Utils_System::setTitle(ts('Sign Up to Volunteer', array('domain' => 'org.civicrm.volunteer')));

    $contactID = $_SESSION['CiviCRM']['userID'] ?? NULL;
    $profiles = $this->buildCustom($this->getPrimaryVolunteerProfileIDs(), $contactID);
    $this->assign('customProfiles', $profiles);

    // Order by project name (alphabetical); within a project, dated needs by
    // start time and flexible needs last.
    $projects = $this->_projects;
    usort($this->_needs, function ($needA, $needB) use ($projects) {
      $titleA = $projects[$needA['project_id']]['title'] ?? '';
      $titleB = $projects[$needB['project_id']]['title'] ?? '';
      if ($titleA !== $titleB) {
        return ($titleA < $titleB) ? -1 : 1;
      }
      return self::compareNeedsForDisplay($needA, $needB);
    });

    $this->assign('volunteerNeeds', $this->_needs);
    $this->assign('commitmentGroups', self::compileCommitmentGroups($this->_needs, $this->_projects));
    $this->assign('backToShiftsUrl', $this->buildBackToShiftsUrl());

    $this->addButtons(array(
      array(
        'type' => 'done',
        'name' => ts('Confirm my sign-up', array('domain' => 'org.civicrm.volunteer')),
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
    $bringingAdditionalVolunteers = !empty($this->_submitValues['bringingAdditionalVolunteers']);
    $this->assign('allowAdditionalVolunteers', $allowAdditionalVolunteers);
    if ($allowAdditionalVolunteers) {
      // Disclosure: the quantity control and the per-person Profiles stay
      // hidden until the volunteer confirms they are bringing other people.
      $this->add(
        'checkbox',
        'bringingAdditionalVolunteers',
        ts('I am bringing other people', array('domain' => 'org.civicrm.volunteer')),
        NULL,
        FALSE,
        array(
          'aria-controls' => self::ADDITIONAL_PEOPLE_ID,
          'aria-expanded' => $bringingAdditionalVolunteers ? 'true' : 'false',
        )
      );
      $this->add("text", "additionalVolunteerQuantity", ts("How many people are you bringing?", array('domain' => 'org.civicrm.volunteer')), array("size" => 3));
      // Rendered collapsed unless the volunteer has already opted in, so the
      // quantity control does not flash on load and stays hidden without JS.
      $this->assign('bringingAdditionalVolunteers', $bringingAdditionalVolunteers);
      $this->assign('additionalPeopleId', self::ADDITIONAL_PEOPLE_ID);

      // VOL-282: Cap how many additional volunteers can be added based on the
      // opp with the fewest openings. Flexible-only selections have no finite
      // shift to cap against, so no client-side maximum is imposed.
      CRM_Core_Resources::singleton()->addVars('org.civicrm.volunteer', array(
        'maxAddtlReg' => $this->getMaxAdditionalVolunteers(),
        'projectId' => key($this->_projects),
      ));

      $additionalVolunteerQuantity = $this->getAdditionalVolunteerQuantity($this->_submitValues);
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
      CRM_Core_Resources::singleton()->addScriptFile('org.civicrm.volunteer', 'js/VolunteerSignUp.js', 12);
      CRM_Core_Resources::singleton()->addStyleFile('org.civicrm.volunteer', 'css/additional_volunteers.css');
    }
    CRM_Core_Resources::singleton()->addStyleFile('org.civicrm.volunteer', 'css/volunteer-tokens.css');
    CRM_Core_Resources::singleton()->addStyleFile('org.civicrm.volunteer', 'css/public_workflow.css');
    CRM_Core_Resources::singleton()->addStyleFile('org.civicrm.volunteer', 'css/signup.css');
  }

  /**
   * Sort needs for display: dated needs first by start time, then ongoing,
   * with flexible (general availability) commitments last.
   *
   * @return int
   */
  private static function compareNeedsForDisplay(array $needA, array $needB) {
    $flexibleA = !empty($needA['is_flexible']);
    $flexibleB = !empty($needB['is_flexible']);
    if ($flexibleA !== $flexibleB) {
      return $flexibleA ? 1 : -1;
    }
    $startA = $needA['start_time'] ?? '';
    $startB = $needB['start_time'] ?? '';
    if ($startA === $startB) {
      return ((int) $needA['id']) <=> ((int) $needB['id']);
    }
    return ($startA < $startB) ? -1 : 1;
  }

  /**
   * Classify how a need's schedule is booked, for summaries and grouping.
   *
   * @param array $need
   * @return string
   *   One of 'fixed', 'window', 'ongoing', or 'flexible'.
   */
  public static function getNeedScheduleType(array $need) {
    if (!empty($need['is_flexible'])) {
      return 'flexible';
    }
    if (empty($need['start_time'])) {
      // Defensive: a non-flexible need without a start cannot be summarized
      // more finely than "no fixed time".
      return 'ongoing';
    }
    if (!empty($need['end_time'])) {
      return 'window';
    }
    if (!empty($need['duration'])) {
      return 'fixed';
    }
    return 'ongoing';
  }

  /**
   * One-line schedule summary for the commitments sidebar.
   *
   * @param array $need
   * @return string
   */
  public static function getNeedScheduleSummary(array $need) {
    $displayTime = $need['display_time'] ?? '';
    if (!is_string($displayTime)) {
      $displayTime = '';
    }
    switch (self::getNeedScheduleType($need)) {
      case 'flexible':
        return ts('General availability', array('domain' => 'org.civicrm.volunteer'));

      case 'ongoing':
        return ($displayTime === '')
          ? ts('No fixed time', array('domain' => 'org.civicrm.volunteer'))
          : ts('Ongoing from %1', array(1 => $displayTime, 'domain' => 'org.civicrm.volunteer'));

      default:
        // Fixed and window shifts both summarize through their formatted
        // start - end range.
        return $displayTime;
    }
  }

  /**
   * Group the selected needs by project for the commitments sidebar.
   *
   * @param array $needs
   *   Need rows; enriched with display fields by the need reads.
   * @param array $projects
   *   Project details keyed by project ID, as fetchProjectDetails() builds.
   * @return array
   *   Ordered groups: title, organizers, description, and commitment rows
   *   each carrying role and schedule summaries.
   */
  public static function compileCommitmentGroups(array $needs, array $projects) {
    $groups = array();
    foreach ($needs as $need) {
      $projectId = (int) ($need['project_id'] ?? 0);
      if (!isset($groups[$projectId])) {
        $project = $projects[$projectId] ?? array();
        $beneficiaries = is_array($project['beneficiaries'] ?? NULL) ? $project['beneficiaries'] : array();
        // SR-003 (security review 2026-08-23): display names arrive
        // HTML-decoded from Contact::get, and the template interpolates
        // this string through a {ts 1=...} placeholder -- which, unlike a
        // direct {$var} output, applies no escaping. Escape here so a
        // crafted beneficiary name cannot become markup on the public
        // signup page.
        $organizers = array_map(
          static function ($name) {
            return htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
          },
          $beneficiaries
        );
        $groups[$projectId] = array(
          'project_id' => $projectId,
          'title' => (string) ($project['title'] ?? ''),
          'organizers' => implode(', ', $organizers),
          'description' => (string) ($project['description'] ?? ''),
          'commitments' => array(),
        );
      }
      $groups[$projectId]['commitments'][] = array(
        'id' => (int) ($need['id'] ?? 0),
        'role_label' => (string) ($need['role_label'] ?? ''),
        'role_description' => (string) ($need['role_description'] ?? ''),
        'is_flexible' => !empty($need['is_flexible']),
        'schedule_type' => self::getNeedScheduleType($need),
        'schedule_summary' => self::getNeedScheduleSummary($need),
        'start_time' => $need['start_time'] ?? NULL,
      );
    }

    // $needs arrives ordered by project then schedule; re-key to a list.
    return array_values($groups);
  }

  /**
   * Additional volunteers requested in a submission, if any.
   *
   * The disclosure checkbox is authoritative: an unchecked disclosure can
   * never yield additional volunteers, no matter what else was submitted.
   *
   * @param array $submittedValues
   * @return int
   */
  public function getAdditionalVolunteerQuantity(array $submittedValues) {
    if (empty($submittedValues['bringingAdditionalVolunteers'])) {
      return 0;
    }
    $qty = CRM_Utils_Type::validate($submittedValues['additionalVolunteerQuantity'] ?? 0, 'Integer', FALSE);
    $qty = max(0, (int) ($qty ?? 0));

    // Bound the request before anything acts on it. buildQuickForm() builds one
    // Profile per person and postProcess() creates one contact per person, so
    // an unbounded quantity is an invitation to burn the request on a number
    // nobody could honour. assertSignupCapacity() still refuses anything a
    // finite shift cannot seat; this only stops the work being done first, and
    // matters most for flexible-only selections, which have no finite shift to
    // check against.
    return min($qty, self::MAX_ADDITIONAL_VOLUNTEERS);
  }

  /**
   * Client-side maximum on additional volunteers for this selection.
   *
   * VOL-282: capped by the finite shift with the fewest remaining places,
   * less the primary volunteer. Flexible-only selections have no finite
   * shift, so no maximum applies.
   *
   * @return int|null
   */
  public function getMaxAdditionalVolunteers() {
    $finite = array();
    foreach (array_column($this->_needs, 'quantity_available') as $quantity) {
      if ($quantity !== NULL && $quantity !== FALSE) {
        $finite[] = (int) $quantity;
      }
    }
    if (!$finite) {
      return NULL;
    }
    return max(0, min($finite) - 1);
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

  function postProcess() {
    $cid = $_SESSION['CiviCRM']['userID'] ?? NULL;
    $values = $this->controller->exportValues();

    $profileFields = $this->getProfileFields($this->getPrimaryVolunteerProfileIDs());
    $profileFieldsByType = array_reduce($profileFields, array($this, 'reduceByType'), array());
    $activityFields = $profileFieldsByType['Activity'] ?? [];
    $activityValues = array_intersect_key($values, $activityFields);
    $contactValues = array_diff_key($values, $activityValues);

    $transaction = CRM_Core_Transaction::create(TRUE);
    try {
      $additionalQuantity = $this->getAdditionalVolunteerQuantity($values);
      $this->assertSignupCapacity(1 + max(0, $additionalQuantity));

      $this->_primary_volunteer_id = $this->processContactProfileData($contactValues, $profileFields, $cid);
      $projectNeeds = $this->createVolunteerActivity($this->_primary_volunteer_id, $activityValues);
      $additionalConfirmations = $this->processAdditionalVolunteers($values);
      $transaction->commit();
    }
    catch (Throwable $e) {
      $transaction->rollback()->commit();
      Civi::log()->error('Volunteer signup transaction failed ({exception_class}, code {code}): {message} (need IDs {need_ids}).', array(
        'exception' => $e,
        'exception_class' => get_class($e),
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'need_ids' => implode(',', $this->getSelectedNeedIds()),
      ));
      throw new CRM_Core_Exception(
        ts('Your volunteer signup could not be completed. No changes were saved; please review availability and try again.', array('domain' => 'org.civicrm.volunteer')),
        0,
        array(),
        $e
      );
    }

    $emailFailed = FALSE;
    try {
      $this->sendVolunteerConfirmationEmail($this->_primary_volunteer_id, $projectNeeds);
      foreach ($additionalConfirmations as $confirmation) {
        $this->sendVolunteerConfirmationEmail($confirmation['contact_id'], $confirmation['project_needs']);
      }
    }
    catch (Throwable $e) {
      $emailFailed = TRUE;
      Civi::log()->error('Volunteer signup confirmation failed after commit ({exception_class}, code {code}).', array(
        'exception' => $e,
        'exception_class' => get_class($e),
        'code' => $e->getCode(),
      ));
    }

    $statusMsg = ts('You are scheduled to volunteer. Thank you!', array('domain' => 'org.civicrm.volunteer'));
    CRM_Core_Session::setStatus($statusMsg, '', 'success');
    if ($emailFailed) {
      CRM_Core_Session::setStatus(
        ts('Your signup was saved, but a confirmation email could not be sent.', array('domain' => 'org.civicrm.volunteer')),
        '',
        'warning'
      );
    }
    CRM_Core_Session::singleton()->pushUserContext($this->_destination);
  }

  /**
   * Need IDs currently selected for signup, as sorted unique integers.
   *
   * `$this->_needs` is keyed by need ID when preProcess() populates it, but
   * buildQuickForm() re-sorts it with usort() to order by project title, which
   * discards those keys. Anything running after the form is built (i.e. all of
   * postProcess) must therefore read the IDs out of the records themselves.
   *
   * Sorting keeps concurrent signups that share needs from taking row locks in
   * different orders, which would deadlock.
   *
   * @return int[]
   */
  private function getSelectedNeedIds() {
    $needIds = array_map('intval', array_column($this->_needs, 'id'));
    $needIds = array_values(array_unique(array_filter($needIds)));
    sort($needIds);
    return $needIds;
  }

  /**
   * Lock selected needs and verify that the complete signup fits.
   *
   * @param int $requestedPlaces
   *   Primary plus additional volunteers.
   *
   * @throws CRM_Core_Exception
   */
  private function assertSignupCapacity($requestedPlaces) {
    $publicVisibility = CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public');
    foreach ($this->getSelectedNeedIds() as $needId) {
      $dao = CRM_Core_DAO::executeQuery(
        'SELECT n.id, n.project_id, n.quantity, n.is_active, n.visibility_id, p.is_active AS project_is_active
          FROM civicrm_volunteer_need n
          INNER JOIN civicrm_volunteer_project p ON p.id = n.project_id
          WHERE n.id = %1
          FOR UPDATE',
        array(1 => array((int) $needId, 'Integer'))
      );
      if (!$dao->fetch() || !$dao->is_active || !$dao->project_is_active || (int) $dao->visibility_id !== (int) $publicVisibility) {
        throw new CRM_Core_Exception(ts('One of the selected volunteer opportunities is no longer available.', array('domain' => 'org.civicrm.volunteer')));
      }

      if ($dao->quantity !== NULL) {
        // Locking read; see getAssignmentCountForUpdate().
        $assigned = CRM_Volunteer_BAO_Need::getAssignmentCountForUpdate($needId);
        if ($assigned + $requestedPlaces > (int) $dao->quantity) {
          throw new CRM_Core_Exception(ts('There are not enough remaining places for one of the selected volunteer opportunities.', array('domain' => 'org.civicrm.volunteer')));
        }
      }
    }
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
        foreach (($data['contacts'] ?? array()) as $manager) {
          if (!empty($manager['email']) && CRM_Utils_Rule::email($manager['email'])) {
            $bcc[$manager['contact_id']] = CRM_Utils_Mail::formatRFC822Email($manager['display_name'] ?? '', $manager['email']);
          }
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
    $activity_statuses = array_column(
      \Civi::entity('Activity')->getOptions('status_id', $activityValues, FALSE, TRUE) ?? array(),
      'name',
      'id'
    );

    foreach($this->_needs as $need) {
      $activityValues['volunteer_need_id'] = $need['id'];
      $activityValues['activity_date_time'] = $need['start_time'] ?? NULL;
      $activityValues['assignee_contact_id'] = $cid;
      $activityValues['is_test'] = ($this->_mode === 'test' ? 1 : 0);
      $activityValues['source_contact_id'] = $this->_primary_volunteer_id;
      $activityValues['check_permissions'] = FALSE;

      // Set status to Available if user selected Flexible Need, else set to Scheduled.
      if (!empty($need['is_flexible'])) {
        $activityValues['status_id'] = CRM_Utils_Array::key('Available', $activity_statuses);
      } else {
        $activityValues['status_id'] = CRM_Utils_Array::key('Scheduled', $activity_statuses);
      }

      $activityValues['time_scheduled_minutes'] = $need['duration'] ?? NULL;
      CRM_Volunteer_Permission::withInternalBypass(function() use ($activityValues) {
        CRM_Volunteer_BAO_Assignment::createVolunteerActivity($activityValues);
      });

      if(!array_key_exists($need['project_id'], $projectNeeds)) {
        $projectNeeds[$need['project_id']] = array();
      }

      $need['role'] = $need['role_label'] ?? NULL;
      $need['description'] = $need['role_description'] ?? NULL;
      $need['duration'] = $need['duration'] ?? NULL;
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
      $dedupeValues = $this->formatDedupeValues($profileValues);
      if ($dedupeValues) {
        $matches = \Civi\Api4\Contact::getDuplicates(FALSE)
          ->setDedupeRule('Individual.Unsupervised')
          ->setValues($dedupeValues)
          ->execute();
        if (count($matches)) {
          $cid = (int) $matches->first()['id'];
        }
      }
    }

    return CRM_Contact_BAO_Contact::createProfileContact(
      $profileValues,
      $profileFields,
      $cid
    );
  }

  /**
   * Convert legacy profile element names to fields accepted by API4 dedupe.
   *
   * Profile elements use names such as "email-Primary" while API4 uses
   * "email_primary.email". Unsupported display-only profile values are
   * intentionally omitted instead of being passed as unknown API4 fields.
   */
  private function formatDedupeValues(array $profileValues): array {
    static $availableFields;
    if ($availableFields === NULL) {
      $availableFields = array_fill_keys(
        \Civi\Api4\Contact::getFields(FALSE)
          ->setAction('getDuplicates')
          ->execute()
          ->column('name'),
        TRUE
      );
    }

    $values = array();
    foreach ($profileValues as $legacyName => $value) {
      if ($value === NULL || $value === '' || (is_array($value) && !$value)) {
        continue;
      }

      $candidates = array((string) $legacyName);
      if (preg_match('/^custom_\d+$/', (string) $legacyName)) {
        $longName = CRM_Core_BAO_CustomField::getLongNameFromShortName($legacyName);
        if ($longName) {
          $candidates[] = $longName;
        }
      }

      $baseName = explode('-', (string) $legacyName)[0];
      $candidates[] = $baseName;
      foreach (array('email', 'phone', 'address', 'im') as $locationEntity) {
        $candidates[] = $locationEntity . '_primary.' . $baseName;
      }

      foreach (array_unique($candidates) as $candidate) {
        if (isset($availableFields[$candidate])) {
          $values[$candidate] = $value;
          break;
        }
      }
    }

    return $values;
  }


  /**
   * Saves the contact and activity records for additional volunteers and sends
   * confirmation email.
   *
   * @param array $data
   *   The form data that was submitted
   */
  function processAdditionalVolunteers(array $data) {
    $qty = $this->getAdditionalVolunteerQuantity($data);

    if ($qty < 1) {
      return array();
    }

    $profileFields = $this->getProfileFields($this->getAdditionalVolunteerProfileIDs());
    $profileFieldsByType = array_reduce($profileFields, array($this, 'reduceByType'), array());
    $activityProfileFields = $profileFieldsByType['Activity'] ?? [];

    $index = 0;
    $confirmations = array();
    while ($index < $qty) {
      $profileData = $data['additionalVolunteers_' . $index] ?? [];
      $activityData = array_intersect_key($profileData, $activityProfileFields);
      $contactData = array_diff_key($profileData, $activityData);

      $cid = $this->processContactProfileData($contactData, $profileFields);
      $projectNeeds = $this->createVolunteerActivity($cid, $activityData);
      $confirmations[] = array(
        'contact_id' => $cid,
        'project_needs' => $projectNeeds,
      );

      $index++;
    }
    return $confirmations;
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

    foreach ($projectNeeds as $projectId => $needs) {
      // APIv3's public project read filtered on is_active; keep that here.
      $projects = \Civi\Api4\VolunteerProject::search(FALSE)
        ->setFilters(array('id' => $projectId, 'is_active' => 1))
        ->execute()
        ->getArrayCopy();
      $project = reset($projects);

      if (!$project) {
        continue;
      }
      $project['description'] = CRM_Utils_String::purifyHTML($project['description'] ?? '');

      // Move the data around so it makes sense for template use. The former
      // api.LocBlock.get chain resolved the project's own loc_block_id, which
      // is what getLocationData() validates and nests.
      if (!empty($project['loc_block_id'])) {
        $locations = CRM_Volunteer_BAO_Project::getLocationData(
          $project['loc_block_id'],
          $projectId,
          FALSE
        );
        if (count($locations) === 1) {
          $project['location'] = reset($locations);
          $project['location']['email'] = $project['location']['email']['email'] ?? '';
          $project['location']['email2'] = $project['location']['email_2']['email'] ?? '';
          $project['location']['phone'] = $project['location']['phone']['phone'] ?? '';
          $project['location']['phone2'] = $project['location']['phone_2']['phone'] ?? '';
        }
      }
      $project['contacts'] = self::getContactSummaries(
        CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, 'volunteer_manager')
      );

      $project['opportunities'] = $needs;
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
    $path = NULL;
    // CRM_Utils_System::url() types $query as array|string, so '' rather than
    // NULL for the no-query case.
    $query = '';
    $fragment = NULL;

    $dest = CRM_Utils_Request::retrieve('dest', 'String', $this, FALSE);
    switch ($dest) {
      case 'event':
        // If only one project is associated with the form, send the user back
        // to that event form; otherwise, default to the vol opps page.
        $eventId = count($this->_projects) === 1
          ? self::getAssociatedEventId(key($this->_projects))
          : NULL;
        if ($eventId) {
          $path = 'civicrm/event/info';
          $query = "reset=1&id={$eventId}";
          break;
        }
      case 'list':
      default:
        $path = 'civicrm/vol/';
        $fragment = '/volunteer/opportunities';
    }

    $this->_destination = CRM_Utils_System::url($path, $query, FALSE, $fragment, TRUE, FALSE);
  }

  /**
   * ID of the event a project is associated with, or NULL if there is not one.
   *
   * $this->_projects is populated by a *public* project read, which reduces
   * every row to the signup-facing allowlist in
   * CRM_Volunteer_BAO_Project::searchProjects(). That allowlist deliberately
   * excludes entity_table and entity_id, so reading $project['entity_id'] off
   * the cached row yields an undefined-key warning and an event-less
   * `civicrm/event/info?reset=1&id=` redirect. Widening the allowlist is not
   * the fix: it is the public read contract, and two tests pin its exact
   * contents.
   *
   * The visitor's authorization to sign up for these needs was established in
   * preProcess(); resolving which event they arrived from, in order to send
   * them back to it, discloses nothing further.
   *
   * @param int|string|null $projectId
   * @return int|null
   */
  private static function getAssociatedEventId($projectId) {
    // Civi\Api4\Event and CRM_Event_DAO_Event ship in the civi_event
    // extension, not core, so neither is safe to touch unconditionally.
    if (!$projectId || !CRM_Core_Component::isEnabled('CiviEvent')) {
      return NULL;
    }

    $projects = CRM_Volunteer_Permission::withInternalBypass(function() use ($projectId) {
      return CRM_Volunteer_BAO_Project::retrieve(array(
        'check_permissions' => FALSE,
        'id' => (int) $projectId,
      ));
    });
    $project = current($projects);
    if (!$project || $project->entity_table !== CRM_Event_DAO_Event::getTableName()) {
      return NULL;
    }

    return empty($project->entity_id) ? NULL : (int) $project->entity_id;
  }

  /**
   * DOM id of the panel the "I am bringing other people" disclosure controls.
   */
  const ADDITIONAL_PEOPLE_ID = 'crm-vol-additional-people';

  /**
   * Hard ceiling on additional volunteers in one submission.
   *
   * Not a policy limit -- a finite shift's own capacity is almost always far
   * lower, and assertSignupCapacity() enforces it. This exists so a posted
   * quantity cannot make the form build and then create an arbitrary number of
   * Profiles and contacts.
   */
  const MAX_ADDITIONAL_VOLUNTEERS = 100;

  /**
   * The opportunities route whose hash-relative state Back to shifts restores.
   */
  const RETURN_CONTEXT_PATH = '/volunteer/opportunities';

  /**
   * Quick filters the opportunities browser can place in its URL.
   */
  const RETURN_CONTEXT_TIME_FILTERS = array('all', 'weekends', 'evenings', 'no_fixed_time');

  /**
   * Captures the hash-relative opportunities state for the Back action.
   *
   * Passing $this as the retrieve() store parks the raw value in this form's
   * QuickForm session scope, so it survives validation round-trips even though
   * the form action URL does not repeat the query string. The value is
   * re-validated on every request; only the sanitized fragment is ever used.
   */
  protected function setReturnContext() {
    $return = CRM_Utils_Request::retrieve('return', 'String', $this, FALSE);
    // ?return[]=x makes this an array, and CRM_Utils_Type::validate() hands a
    // 'String' array straight back, so casting it raises "Array to string
    // conversion". A non-scalar return is never valid anyway.
    if (!is_scalar($return)) {
      $return = '';
    }
    $this->_returnContext = self::sanitizeReturnContext((string) $return);
  }

  /**
   * Reduce a submitted return value to a safe hash-relative context.
   *
   * Only the opportunities route followed by a whitelist of browser filter
   * parameters is accepted. Anything else -- foreign paths, script URLs,
   * unexpected keys -- collapses to the bare opportunities route, so the
   * context can never be turned into a link to somewhere else.
   *
   * @param string $value
   * @return string
   *   E.g. "/volunteer/opportunities?timeFilter=weekends&selected[]=4".
   */
  public static function sanitizeReturnContext($value) {
    $value = trim((string) $value);
    // Fragments are never sent by the browser; strip any defensively.
    $value = strtok($value, '#') ?: '';

    $parts = explode('?', $value, 2);
    if ($parts[0] !== self::RETURN_CONTEXT_PATH) {
      return self::RETURN_CONTEXT_PATH;
    }
    if (!isset($parts[1]) || $parts[1] === '') {
      return self::RETURN_CONTEXT_PATH;
    }

    $params = array();
    parse_str($parts[1], $params);
    $filtered = self::filterReturnContextParams($params);
    if (!$filtered) {
      return self::RETURN_CONTEXT_PATH;
    }
    return self::RETURN_CONTEXT_PATH . '?' . self::buildReturnContextQuery($filtered);
  }

  /**
   * Keep only known opportunities-browser parameters with sane values.
   *
   * @param array $params
   *   Parsed query parameters from a return context.
   * @return array
   */
  private static function filterReturnContextParams(array $params) {
    $clean = array();

    if (isset($params['project']) && !is_array($params['project'])) {
      $projectId = CRM_Utils_Type::validate($params['project'], 'Positive', FALSE);
      if ($projectId !== NULL) {
        $clean['project'] = (int) $projectId;
      }
    }
    if (isset($params['dest']) && in_array($params['dest'], array('event', 'list'), TRUE)) {
      $clean['dest'] = $params['dest'];
    }
    if (isset($params['hideSearch']) && in_array($params['hideSearch'], array('1', 'always'), TRUE)) {
      $clean['hideSearch'] = $params['hideSearch'];
    }
    if (isset($params['timeFilter']) && in_array($params['timeFilter'], self::RETURN_CONTEXT_TIME_FILTERS, TRUE)) {
      $clean['timeFilter'] = $params['timeFilter'];
    }
    foreach (array('date_start', 'date_end') as $dateParam) {
      if (isset($params[$dateParam])
        && is_string($params[$dateParam])
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $params[$dateParam])
      ) {
        $clean[$dateParam] = $params[$dateParam];
      }
    }

    // role_id[] and selected[] arrive as lists; keep positive integer IDs.
    foreach (array('role_id', 'selected') as $listParam) {
      if (!isset($params[$listParam])) {
        continue;
      }
      $list = is_array($params[$listParam]) ? $params[$listParam] : array($params[$listParam]);
      $ids = array();
      foreach ($list as $entry) {
        if (count($ids) >= 100) {
          break;
        }
        if (is_array($entry)) {
          continue;
        }
        $id = CRM_Utils_Type::validate($entry, 'Positive', FALSE);
        if ($id !== NULL) {
          $ids[] = (int) $id;
        }
      }
      if ($ids) {
        $clean[$listParam] = array_values(array_unique($ids));
      }
    }

    // The beneficiary widget exchanges a CSV of contact IDs.
    if (isset($params['beneficiary']) && is_string($params['beneficiary']) && $params['beneficiary'] !== '') {
      $ids = array();
      foreach (explode(',', $params['beneficiary']) as $entry) {
        $id = CRM_Utils_Type::validate($entry, 'Positive', FALSE);
        if ($id !== NULL) {
          $ids[] = (int) $id;
        }
      }
      if ($ids) {
        $clean['beneficiary'] = implode(',', array_values(array_unique($ids)));
      }
    }

    if (isset($params['proximity']) && is_array($params['proximity'])) {
      $proximity = array();
      if (in_array(($params['proximity']['unit'] ?? NULL), array('km', 'miles'), TRUE)) {
        $proximity['unit'] = $params['proximity']['unit'];
      }
      if (isset($params['proximity']['radius']) && is_numeric($params['proximity']['radius'])) {
        $proximity['radius'] = $params['proximity']['radius'] + 0;
      }
      foreach (array('street_address', 'city', 'postal_code', 'country') as $textField) {
        $value = $params['proximity'][$textField] ?? NULL;
        if (is_string($value) && $value !== '') {
          $proximity[$textField] = $value;
        }
      }
      if ($proximity) {
        $clean['proximity'] = $proximity;
      }
    }

    return $clean;
  }

  /**
   * Point a validated return context at the given need selection.
   *
   * Used by Back to shifts, which must restore the filters but only carry
   * selected[] IDs that are still available on this form.
   *
   * @param string $context
   *   A (previously sanitized) hash-relative return context.
   * @param int[] $needIds
   * @return string
   */
  public static function withSelectedNeeds($context, array $needIds) {
    $context = self::sanitizeReturnContext($context);
    $parts = explode('?', $context, 2);
    $path = $parts[0];
    $params = array();
    if (isset($parts[1]) && $parts[1] !== '') {
      parse_str($parts[1], $params);
    }

    $ids = array();
    foreach ($needIds as $needId) {
      $id = CRM_Utils_Type::validate($needId, 'Positive', FALSE);
      if ($id !== NULL) {
        $ids[] = (int) $id;
      }
    }
    if ($ids) {
      $params['selected'] = array_values(array_unique($ids));
    }
    else {
      unset($params['selected']);
    }

    return $path . ($params ? '?' . self::buildReturnContextQuery($params) : '');
  }

  /**
   * Encode filtered return-context parameters the way the opportunities
   * browser does: repeated list keys ("selected[]=4") and bracketed
   * proximity keys, which the Angular router and parse_str both read back
   * losslessly.
   *
   * @param array $params
   * @return string
   */
  private static function buildReturnContextQuery(array $params) {
    $pairs = array();
    foreach ($params as $name => $value) {
      if (!is_array($value)) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
        continue;
      }
      if ($name === 'proximity') {
        foreach ($value as $subName => $subValue) {
          $pairs[] = rawurlencode("proximity[{$subName}]") . '=' . rawurlencode((string) $subValue);
        }
        continue;
      }
      foreach ($value as $entry) {
        $pairs[] = rawurlencode($name . '[]') . '=' . rawurlencode((string) $entry);
      }
    }
    return implode('&', $pairs);
  }

  /**
   * URL for the Back to shifts action: the Angular opportunities route with
   * the stored filter state and this form's (still-available) selection.
   *
   * @return string
   */
  protected function buildBackToShiftsUrl() {
    $fragment = self::withSelectedNeeds($this->_returnContext, $this->getSelectedNeedIds());
    return CRM_Utils_System::url('civicrm/vol/', '', FALSE, $fragment, TRUE, FALSE);
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
