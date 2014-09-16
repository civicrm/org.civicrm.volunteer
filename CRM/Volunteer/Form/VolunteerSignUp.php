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
   * The URL to which the user should be redirected after successfully
   * submitting the sign-up form
   *
   * @var string
   * @protected
   */
  protected $_destination;

  /**
   * The fields involved in this volunteer project sign-up page
   *
   * @var array
   * @public
   */
  public $_fields = array();

  /**
   * the mode that we are in
   *
   * @var string
   * @protected
   */
  protected $_mode;

  /**
   * the project we are processing
   *
   * @var CRM_Volunteer_BAO_Project
   * @protected
   */
  protected $_project;

  /**
   * ID of profile used in this form
   *
   * @var int
   * @protected
   */
  protected $_ufgroup_id;

  /**
   * This function sets the default values for the form.
   *
   * @access public
   */
  function setDefaultValues() {
    $defaults = array();
    $defaults['volunteer_role_id'] = CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID;

    $cid = CRM_Utils_Array::value('userID', $_SESSION['CiviCRM'], NULL);
    if ($cid) {
      $fields = array_flip(array_keys(CRM_Core_BAO_UFGroup::getFields($this->_ufgroup_id)));
      CRM_Core_BAO_UFGroup::setProfileDefaults($cid, $fields, $defaults);
    }

    return $defaults;
  }

  /**
   * Function to set variables up before form is built
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

    $vid = CRM_Utils_Request::retrieve('vid', 'Positive', $this, TRUE);
    $this->_project = CRM_Volunteer_BAO_Project::retrieveByID($vid);

    $this->setDestination();
    $this->assign('vid', $this->_project->id);
    if (empty($this->_project->needs)) {
      CRM_Core_Error::fatal('Project has no public volunteer needs enabled');
    }
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE);

    // current mode
    $this->_mode = ($this->_action == CRM_Core_Action::PREVIEW) ? 'test' : 'live';

    // get profile id
    try {
      $this->_ufgroup_id = civicrm_api3('UFGroup', 'getvalue', array(
        'name' => 'volunteer_sign_up',
        'return' => 'id',
      ));
    } catch (Exception $e) {
      CRM_Core_Error::fatal('CiviVolunteer custom profile could not be found');
    }
  }

  function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Sign Up to Volunteer for %1', array(1 => $this->_project->title)));
    CRM_Core_Resources::singleton()->addScriptFile('org.civicrm.volunteer',
      'templates/CRM/Volunteer/Form/VolunteerSignUp.js');

    $this->buildCustom('volunteerProfile');

    // don't show the roles dropdown if the flexible need is the only open need
    if (count($this->_project->open_needs)) {

      $role_options = array();
      // special treatment for the flexible need
      if (array_key_exists(CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID, $this->_project->roles)) {
        $role_options[CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID] =
          $this->_project->roles[CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID];
      }
      // add open needs to the option list
      foreach ($this->_project->open_needs as $open) {
        $role_id = $open['role_id'];
        $role_options[$role_id] = $this->_project->roles[$role_id];
      }

      $this->add(
        'select',               // field type
        'volunteer_role_id',    // field name
        ts('Volunteer Role', array('domain' => 'org.civicrm.volunteer')),   // field label
        $role_options, // list of options
        true                    // is required
      );
    }

    // don't show the dropdown if the flexible need is the only need
    $role_ids = array_keys($this->_project->roles);
    $first_role_id = $role_ids[0];
    if (count($this->_project->open_needs) > 1
      || $first_role_id !== CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID
    ) {
      $select = $this->add(
        'select',               // field type
        'volunteer_need_id',    // field name
        ts('Time', array('domain' => 'org.civicrm.volunteer')),            // field label
        array(),                // list of options
        false                    // is required
      );

      foreach ($this->_project->open_needs as $id => $data) {
        $select->addOption($data['label'], $id, array('data-role' => $data['role_id']));
      }

    }

    $this->add(
      'textarea',                   // field type
      'details',                    // field name
      ts('Additional Information', array('domain' => 'org.civicrm.volunteer'))  // field label
    );

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
    $isFlexible = FALSE;

    // Role id is not present in form $values when the only public need is the flexible need.
    // So if role id is not set OR if it matches flexible role id constant then use the flexible need id
    if (! isset($values['volunteer_role_id']) || (int) CRM_Utils_Array::value('volunteer_role_id', $values) === CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID) {
      $isFlexible = TRUE;
      foreach ($this->_project->needs as $n) {
        if ($n['is_flexible'] === '1') {
          $values['volunteer_need_id'] = $n['id'];
          break;
        }
      }
    }
    unset($values['volunteer_role_id']); // we don't need this anymore

    $params = array(
      'id' => CRM_Utils_Array::value('volunteer_need_id', $values),
      'version' => 3,
    );
    $need = civicrm_api('VolunteerNeed', 'getsingle', $params);

    $profile_fields = CRM_Core_BAO_UFGroup::getFields($this->_ufgroup_id);
    $profile_values = array_intersect_key($values, $profile_fields);
    $builtin_values = array_diff_key($values, $profile_values);

    // Search for duplicate
    if (!$cid) {
      $dedupeParams = CRM_Dedupe_Finder::formatParams($profile_values, 'Individual');
      $dedupeParams['check_permission'] = FALSE;
      $ids = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual');
      if ($ids) {
        $cid = $ids[0];
      }
    }

    $cid = CRM_Contact_BAO_Contact::createProfileContact(
      $profile_values,
      $profile_fields,
      $cid,
      NULL,
      $this->_ufgroup_id
    );

    $activity_statuses = CRM_Activity_BAO_Activity::buildOptions('status_id', 'create');

    $builtin_values['activity_date_time'] = CRM_Utils_Array::value('start_time', $need);
    $builtin_values['assignee_contact_id'] = $cid;
    $builtin_values['is_test'] = ($this->_mode === 'test' ? 1 : 0);
    // below we assume that volunteers are always signing up only themselves;
    // for now this is a safe assumption, but we may need to revisit this.
    $builtin_values['source_contact_id'] = $cid;

    // Set status to Available if user selected Flexible Need, else set to Scheduled.
    if ($isFlexible) {
      $builtin_values['status_id'] = CRM_Utils_Array::key('Available', $activity_statuses);
    } else {
      $builtin_values['status_id'] = CRM_Utils_Array::key('Scheduled', $activity_statuses);
    }
    $builtin_values['subject'] = $this->_project->title;
    $builtin_values['time_scheduled_minutes'] = CRM_Utils_Array::value('duration', $need);
    CRM_Volunteer_BAO_Assignment::createVolunteerActivity($builtin_values);

    $statusMsg = ts('You are scheduled to volunteer. Thank you!', array('domain' => 'org.civicrm.volunteer'));
    CRM_Core_Session::setStatus($statusMsg, '', 'success');
    CRM_Utils_System::redirect($this->_destination);
  }

  /**
   * Function to assign profiles to a Smarty template
   *
   * @param string $name The name to give the Smarty variable
   * @access public
   */
  function buildCustom($name) {
    $fields = array();
    $session   = CRM_Core_Session::singleton();
    $contactID = $session->get('userID');

    $id = $this->_ufgroup_id;

    if ($id) {
      $fields = CRM_Core_BAO_UFGroup::getFields($id, FALSE, CRM_Core_Action::ADD,
        NULL, NULL, FALSE, NULL,
        FALSE, NULL, CRM_Core_Permission::CREATE,
        'field_name', TRUE
      );

      foreach ($fields as $key => $field) {
        CRM_Core_BAO_UFGroup::buildProfile(
          $this,
          $field,
          CRM_Profile_Form::MODE_CREATE,
          $contactID,
          TRUE
        );
        $this->_fields[$key] = $field;
      }

      $this->assign($name, $fields);
    }
  }

  /**
   * Set $this->_destination, the URL to which the user should be redirected
   * after successfully submitting the sign-up form
   */
  protected function setDestination() {
    switch ($this->_project->entity_table) {
      case 'civicrm_event':
        $path = 'civicrm/event/info';
        $query = "reset=1&id={$this->_project->entity_id}";
        break;
    }

    $this->_destination = CRM_Utils_System::url($path, $query);
  }
}