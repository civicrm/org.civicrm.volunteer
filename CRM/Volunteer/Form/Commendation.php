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

  /**
   * TODO: How many checks do we need to do? Should we check to make sure the
   * activity is the right type? That the cid and aid are associated? Seems like
   * if you are messing with URL params you are kind of asking for trouble...
   */
  function preProcess() {
    $this->_aid = CRM_Utils_Request::retrieve('aid', 'Positive', $this, FALSE);
    $this->_cid = CRM_Utils_Request::retrieve('cid', 'Positive', $this, FALSE);
    $this->_vid = CRM_Utils_Request::retrieve('vid', 'Positive', $this, FALSE);

    if (!CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $this->_vid)) {
      CRM_Utils_System::permissionDenied();
    }

    if (!$this->_aid && !($this->_cid && $this->_vid)) {
      CRM_Core_Error::fatal("Form expects an activity ID or both a contact and a volunteer project ID.");
    }

    $check = array(
      'Activity' => $this->_aid,
      'Contact' => $this->_cid,
      'VolunteerProject' => $this->_vid,
    );
    $errors = array();
    foreach ($check as $entityType => $entityID) {
      if (!$this->entityExists($entityType, $entityID)) {
        $errors[] = "No $entityType with ID $entityID exists.";
      }
    }
    if (count($errors)) {
      CRM_Core_Error::fatal("Invalid parameter(s) passed to commendation form: " . implode(' ', $errors));
    }

    $contact_display_name = civicrm_api3('Contact', 'getvalue', array(
      'id' => $this->_cid,
      'return' => 'display_name',
    ));
    CRM_Utils_System::setTitle(
      ts('Commend %1', array(1 => $contact_display_name, 'domain' => 'org.civicrm.volunteer'))
    );
    parent::preProcess();
  }

  /**
   * Checks if an entity exists
   *
   * Used to make sure params passed via the URL are valid
   *
   * @param string $entityType e.g., Contact, Activity, etc.
   * @param int $entityID Or int-like string
   * @return boolean
   */
  private function entityExists($entityType, $entityID) {
    $cnt = civicrm_api3($entityType, 'getcount', array(
      'id' => $entityID,
    ));
    return ($cnt > 0);
  }

  /**
   * Set default values for the form. For edit/view mode
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
    );
    if (isset($this->_aid)) {
      $buttons[0]['name'] = ts('Update', array('domain' => 'org.civicrm.volunteer'));
      $buttons[] = array(
        'name' => ts('Delete', array('domain' => 'org.civicrm.volunteer')),
        'type' => 'submit',
        'subName' => 'delete'
      );
    } else {
      $buttons[0]['name'] = ts('Save', array('domain' => 'org.civicrm.volunteer'));
    }
    $buttons[] = array(
      'type' => 'cancel',
      'name' => ts('Cancel', array('domain' => 'org.civicrm.volunteer')),
    );
    $this->addButtons($buttons);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();

    if (array_key_exists('_qf_Commendation_submit_delete', $values)) {
      // this is our delete condition
      civicrm_api3('Activity', 'delete', array(
        'id' => $this->_aid,
      ));
      $this->_action = CRM_Core_Action::DELETE;
    } else {
      // this is our create/update condition
      CRM_Volunteer_BAO_Commendation::create(array(
        'aid' => $this->_aid,
        'cid' => $this->_cid,
        'details' => $values['details'],
        'vid' => $this->_vid,
      ));

      $this->_action = $this->_aid ? CRM_Core_Action::UPDATE : CRM_Core_Action::ADD;
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