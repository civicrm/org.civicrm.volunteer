<?php
/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Volunteer_Form_Commendation extends CRM_Core_Form {

  /**
   * The activity ID for this commendation
   *
   * @var int
   */
  private $_aid;

  /**
   * The contact ID of the contact to be commended
   *
   * @var int
   */
  private $_cid;

  /**
   * The ID of the volunteer project with which this commendation is associated
   *
   * @var int
   */
  private $_vid;

  function preProcess() {
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE);
    $this->_aid = CRM_Utils_Request::retrieve('aid', 'Positive', $this, FALSE);
    $this->_cid = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
    $this->_vid = CRM_Utils_Request::retrieve('vid', 'Positive', $this, TRUE);

    if (in_array($this->_action, array(CRM_Core_Action::DELETE, CRM_Core_Action::UPDATE))
      && !CRM_Utils_Type::validate($this->_aid, 'Positive', FALSE)
    ) {
      CRM_Core_Error::fatal("Parameter 'aid' is required for delete and update operations");
    }

    parent::preProcess();
  }

  /**
   * This function sets the default values for the form. For edit/view mode
   * the default values are retrieved from the database. It's called after
   * $this->preProcess().
   *
   * @access public
   *
   * @return array
   */
  function setDefaultValues() {
    $defaults = array();

    if ($this->_aid) {
      $commendations = CRM_Volunteer_BAO_Commendation::retrieve(array(
        'id' => $this->_aid,
      ));

      $defaults['details'] = $commendations[$this->_aid]['details'];
    }

    return $defaults;
   }

  function buildQuickForm() {
    $this->add(
      'textarea', // field type
      'details', // field name
      ts('Why does this volunteer merit a commendation?', array('domain' => 'org.civicrm.volunteer')) // field label
    );

    $buttons = array(
      array(
        'type' => 'submit',
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel', array('domain' => 'org.civicrm.volunteer')),
      )
    );
    switch ($this->_action) {
      case CRM_Core_Action::DELETE :
        $buttons[0]['name'] = ts('Delete', array('domain' => 'org.civicrm.volunteer'));
        break;
      case CRM_Core_Action::UPDATE :
        $buttons[0]['name'] = ts('Update', array('domain' => 'org.civicrm.volunteer'));
        break;
      default :
        $buttons[0]['name'] = ts('Save', array('domain' => 'org.civicrm.volunteer'));
        break;
    }
    $this->addButtons($buttons);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();

    switch ($this->_action) {
      case CRM_Core_Action::DELETE :
        civicrm_api3('Activity', 'delete', array(
          'id' => $this->_aid,
        ));
        $statusMsg = ts('Commendation record deleted.', array('domain' => 'org.civicrm.volunteer'));
        CRM_Core_Session::setStatus($statusMsg, '', 'success');
        break;
      default :
        // @TODO: this is probably going to turn into too much business logic for
        // the form layer.... might move much of this to the BAO
        $project = CRM_Volunteer_BAO_Project::retrieveByID($this->_vid);
        $activity_statuses = CRM_Activity_BAO_Activity::buildOptions('status_id', 'create');

        $customFieldSpec = CRM_Volunteer_BAO_Commendation::getCustomFields();
        $volunteer_project_id_field_name = 'custom_' . $customFieldSpec['volunteer_project_id']['id'];

        $params = array(
          'activity_type_id' => CRM_Volunteer_BAO_Commendation::getActivityTypeId(),
          'id' => $this->_aid,
          'details' => $values['details'],
          'subject' => ts('Volunteer Commendation for %1', array('1' => $project->title, 'domain' => 'org.civicrm.volunteer')),
          'status_id' => CRM_Utils_Array::key('Completed', $activity_statuses),
          'target_contact_id' => $this->_cid,
          $volunteer_project_id_field_name => $this->_vid,
        );

        civicrm_api3('Activity', 'create', $params);
        $statusMsg = ts('Commendation record saved.', array('domain' => 'org.civicrm.volunteer'));
        CRM_Core_Session::setStatus($statusMsg, '', 'success');
        break;
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
