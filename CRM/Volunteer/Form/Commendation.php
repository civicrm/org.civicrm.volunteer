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

    if ($this->_aid) {
      $commendations = CRM_Volunteer_BAO_Commendation::retrieve(array('id' => $this->_aid));
      $commendation = $commendations[$this->_aid] ?? NULL;
      if (!$commendation) {
        throw new CRM_Core_Exception(ts('The volunteer commendation does not exist.', array('domain' => 'org.civicrm.volunteer')));
      }
      $commendationProjectId = (int) ($commendation['volunteer_project_id'] ?? 0);
      $commendationContactId = (int) ($commendation['volunteer_contact_id'] ?? 0);
      if (($this->_vid && (int) $this->_vid !== $commendationProjectId)
        || ($this->_cid && (int) $this->_cid !== $commendationContactId)) {
        throw new CRM_Core_Exception(ts('The supplied project or contact does not match this commendation.', array('domain' => 'org.civicrm.volunteer')));
      }
      $this->_vid = $commendationProjectId;
      $this->_cid = $commendationContactId;
    }

    if (!$this->_aid && !($this->_cid && $this->_vid)) {
      throw new CRM_Core_Exception(ts('The commendation form requires an activity ID or both a contact and volunteer project ID.', array('domain' => 'org.civicrm.volunteer')));
    }

    CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $this->_vid);

    $check = array(
      'Activity' => $this->_aid,
      'Contact' => $this->_cid,
      'VolunteerProject' => $this->_vid,
    );
    $errors = array();
    foreach ($check as $entityType => $entityID) {
      if ($entityID && !$this->entityExists($entityType, $entityID)) {
        $errors[] = "No $entityType with ID $entityID exists.";
      }
    }
    if (count($errors)) {
      throw new CRM_Core_Exception(ts('Invalid parameters were passed to the commendation form: %1', array(1 => implode(' ', $errors), 'domain' => 'org.civicrm.volunteer')));
    }

    $contact_display_name = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('display_name')
      ->addWhere('id', '=', $this->_cid)
      ->execute()
      ->first()['display_name'] ?? '';
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
    // API4 has no getcount action; a row_count select is its equivalent.
    $result = civicrm_api4($entityType, 'get', array(
      'checkPermissions' => FALSE,
      'select' => array('row_count'),
      'where' => array(array('id', '=', $entityID)),
    ));
    return $result->count() > 0;
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
      // preProcess() already asserted project authority for this commendation.
      \Civi\Api4\VolunteerCommendation::delete(FALSE)
        ->addWhere('id', '=', $this->_aid)
        ->execute();
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
