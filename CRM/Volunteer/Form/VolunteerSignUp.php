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

  const FLEXIBLE_ROLE_ID = -1;

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
   * ID-indexed array of the needs to be filled for this volunteer project
   *
   * @var array
   * @protected
   */
  protected $_needs = array();

  /**
   * the project we are processing
   *
   * @var CRM_Volunteer_BAO_Project
   * @protected
   */
  protected $_project;

  /**
   * ID-indexed array of the roles associated with this volunteer project
   *
   * @var array
   * @protected
   */
  protected $_roles = array();

  /**
   * ID-indexed array of the shifts associated with this volunteer project
   *
   * i.e. Need_ID => array('label' => 'Formatted start time - end time', 'role_id' => '3')
   *
   * @var array
   * @protected
   */
  protected $_shifts = array();

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
    $defaults['volunteer_role_id'] = self::FLEXIBLE_ROLE_ID;

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
    $vid = CRM_Utils_Request::retrieve('vid', 'Positive', $this, TRUE);
    $projects = CRM_Volunteer_BAO_Project::retrieve(array('id' => $vid));

    if (!count($projects)) {
      CRM_Core_Error::fatal('Project does not exist');
    }

    $this->_project = $projects[$vid];
    $this->setDestination();
    $this->assign('vid', $this->_project->id);
    if ($this->getVolunteerNeeds() === 0) {
      CRM_Core_Error::fatal('Project has no volunteer needs defined');
    }
    $this->getVolunteerRoles();
    $this->getVolunteerShifts();
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE);

    // current mode
    $this->_mode = ($this->_action == CRM_Core_Action::PREVIEW) ? 'test' : 'live';

    // get profile id
    $params = array(
      'version' => 3,
      'name' => 'volunteer_sign_up',
      'return' => 'id',
    );
    $result = civicrm_api('UFGroup', 'get', $params);

    if (CRM_Utils_Array::value('is_error', $result)) {
      CRM_Core_Error::fatal('CiviVolunteer custom profile could not be found');
    }
    $values = $result['values'];
    $ufgroup = current($values);
    $this->_ufgroup_id = $ufgroup['id'];
  }

  function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Sign Up to Volunteer for ') . $this->_project->title);
    CRM_Core_Resources::singleton()->addScriptFile('org.civicrm.volunteer',
      'templates/CRM/Volunteer/Form/VolunteerSignUp.js');

    $this->buildCustom('volunteerProfile');

    // better UX not to display a select box with only one possible selection
    if (count($this->_roles) > 1) {
      $this->add(
        'select',               // field type
        'volunteer_role_id',    // field name
        ts('Volunteer Role'),   // field label
        $this->_roles,          // list of options
        true                    // is required
      );
    }

    // better UX not to display a select box with only one possible selection
    if (count($this->_shifts) > 1) {
      $select = $this->add(
        'select',               // field type
        'volunteer_need_id',    // field name
        ts('Shift'),            // field label
        array(),                // list of options
        true                    // is required
      );
      foreach ($this->_shifts as $id => $data) {
        $select->addOption($data['label'], $id, array('data-role' => $data['role_id']));
      }

    }

    $this->add(
      'textarea',                   // field type
      'details',                    // field name
      ts('Additional Information')  // field label
    );

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
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

    // Role id is not present in form $values when the only public need is the flexible need.
    // So if role id is net set OR if it matches flexible role id constant then use the flexible need id
    if (! isset($values['volunteer_role_id']) || (int) CRM_Utils_Array::value('volunteer_role_id', $values) === self::FLEXIBLE_ROLE_ID) {
      foreach ($this->_needs as $n) {
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
    $builtin_values['status_id'] = CRM_Utils_Array::key('Available', $activity_statuses);
    $builtin_values['subject'] = $this->_project->title;
    $builtin_values['time_scheduled_minutes'] = CRM_Utils_Array::value('duration', $need);
    CRM_Volunteer_BAO_Assignment::createVolunteerActivity($builtin_values);

    $statusMsg = ts('You are scheduled to volunteer. Thank you!');
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
   * Retrieves the Needs associated with this Project via API
   *
   * @return int Number of Needs associated with this Project
   */
  function getVolunteerNeeds() {
    $params = array(
      'is_active' => '1',
      'project_id' => $this->_project->id,
      'version' => 3,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    );
    $params['options'] = array('sort' => 'start_time');

    $result = civicrm_api('VolunteerNeed', 'get', $params);

    if (CRM_Utils_Array::value('is_error', $result) === 0) {
      $this->_needs = $result['values'];
    }

    return CRM_Utils_Array::value('count', $result, 0);
  }

  /**
   * Sets $this->_roles
   *
   * @return int Number of Roles associated with this Project
   */
  function getVolunteerRoles() {
    $roles = array();

    if (empty($this->_needs)) {
      $this->getVolunteerNeeds();
    }

    foreach ($this->_needs as $need) {
      $role_id = CRM_Utils_Array::value('role_id', $need);
      if (CRM_Utils_Array::value('is_flexible', $need) == '1') {
        $roles[self::FLEXIBLE_ROLE_ID] = CRM_Volunteer_BAO_Need::getFlexibleRoleLabel();
      } else {
        $roles[$role_id] = CRM_Core_OptionGroup::getLabel(
          CRM_Volunteer_Upgrader::customOptionGroupName,
          $role_id
        );
      }
    }
    asort($roles);
    $this->_roles = $roles;
    return count($roles);
  }

  /**
   * Set $this->_shifts
   *
   * @return int Number of shifts associated with this Project
   */
  function getVolunteerShifts() {
    $shifts = array();

    if (empty($this->_needs)) {
      $this->getVolunteerNeeds();
    }

    foreach ($this->_needs as $id => $need) {
      if (CRM_Utils_Array::value('start_time', $need)) {
        $shifts[$id] = array(
          'label' => CRM_Volunteer_BAO_Need::getTimes($need['start_time'], $need['duration']),
          'role_id' => $need['role_id'],
        );
      }
    }

    $this->_shifts = $shifts;
    return count($shifts);
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
