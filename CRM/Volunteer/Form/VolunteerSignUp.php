<?php
/**
 * @todo Add JS to make the Volunteer Role select box act as a filter for the
 * Shift select box, which contains the information we're really interested in
 */

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Volunteer_Form_VolunteerSignUp extends CRM_Core_Form {

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
   * the id of the project we are proceessing
   *
   * @var int
   * @protected
   */
  protected $_projectId;

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
   * i.e. Need_ID => 'Formatted start time - end time'
   *
   * @var array
   * @protected
   */
  protected $_shifts = array();

  /**
   * This function sets the default values for the form.
   *
   * @access public
   */
  function setDefaultValues() {
    /**
     * @todo default to a flexible need
     */
  }

  /**
   * Function to set variables up before form is built
   *
   * @access public
   */
  function preProcess() {
    $this->_projectId = CRM_Utils_Request::retrieve('vid', 'Positive', $this, TRUE);
    $this->assign('vid', $this->_projectId);
    if ($this->getVolunteerNeeds() === 0) {
      CRM_Core_Error::fatal('Project does not exist or has no volunteer needs');
    }
    $this->getVolunteerRoles();
    $this->getVolunteerShifts();
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE);

    // current mode
    $this->_mode = ($this->_action == CRM_Core_Action::PREVIEW) ? 'test' : 'live';
  }

  function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('TODO: Should this read something to the effect of "Sign Up to Volunteer for $nameOfEvent"?'));

    /**
     * @todo We probably need to create our own profile, and we shouldn't
     * hardcode the ID.
     *
     */

    $this->buildCustom(12, 'volunteerProfile');

    // better UX not to display a select box with only one possible selection
    if (count($this->_roles) > 1) {
      $this->add(
        'select',               // field type
        'volunteer_role',       // field name
        ts('Volunteer Role'),   // field label
        $this->_roles,          // list of options
        true                    // is required
      );
    }

    // better UX not to display a select box with only one possible selection
    if (count($this->_shifts) > 1) {
      $this->add(
        'select',               // field type
        'volunteer_need',       // field name
        ts('Shift'),            // field label
        $this->_shifts,         // list of options
        true                    // is required
      );
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

    parent::buildQuickForm();
  }

  function postProcess() {
    /**
     * @todo Handle form submission
     */
    // $values = $this->exportValues();
    // parent::postProcess();
  }

  /**
   * Function to assign profiles to a Smarty template
   *
   * @access public
   */
  function buildCustom($id, $name) {
    $fields = array();
    $session   = CRM_Core_Session::singleton();
    $contactID = $session->get('userID');

    if ($id && $contactID) {
      if (CRM_Core_BAO_UFGroup::filterUFGroups($id, $contactID)) {
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
  }

  /**
   * Retrieves the Needs associated with this Project via API
   *
   * @return int Number of Needs associated with this Project
   */
  function getVolunteerNeeds() {
    $params = array(
      'is_active' => '1',
      'project_id' => $this->_projectId,
      'version' => 3,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    );
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

    foreach ($this->_needs as $id => $need) {
      $role_id = CRM_Utils_Array::value('role_id', $need);
      if (CRM_Utils_Array::value('is_flexible', $need) == '1') {
        $roles[$role_id] = CRM_Volunteer_BAO_Need::getFlexibleRoleLabel();
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
        $shifts[$id] = CRM_Volunteer_BAO_Need::getTimes($need['start_time'], $need['duration']);
      }
    }

    $this->_shifts = $shifts;
    return count($shifts);
  }
}
