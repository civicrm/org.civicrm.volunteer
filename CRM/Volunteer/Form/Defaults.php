<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Volunteer_Form_Defaults extends CRM_Core_Form {

  function buildQuickForm() {
    // add form elements

    $this->add(
      'text',
      'volunteer_default_profiles',
      ts('Default Profiles'),
      array("size" => 35),
      false // is required,
    );

    $campaigns = civicrm_api3('VolunteerUtil', 'getcampaigns', array());
    $campaignList = array();
    foreach($campaigns['values'] as $campaign) {
      $campaignList[$campaign['id']] = $campaign['title'];
    }
    $this->add(
      'select',
      'volunteer_default_campaign',
      ts('Default Campaign'),
      $campaignList,
      false, // is required,
      array("placeholder" => true)
    );

    $this->add(
      'text',
      'volunteer_default_locblock',
      ts('Default Location'),
      array("size" => 35),
      false // is required,
    );

    $this->add(
      'checkbox',
      'volunteer_default_is_active',
      ts('Are new Projects active by Default?'),
      null,
      false
    );

    /*
    $this->add(
      'text',
      'volunteer_default_contacts',
      ts('Default Associated Contacts'),
      array("size" => 35),
      true // is required,
    );
    */

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save Default Settings'),
        'isDefault' => TRUE,
      ),
    ));
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }


  function setDefaultValues() {
    $defaults = CRM_Core_BAO_Setting::getItem("volunteer_defaults");
    return $defaults;
  }


  function validate() {
    //CRM_Core_Session::setStatus(ts(""), "Error", "error");
    return true;
  }

  function postProcess() {
    $values = $this->exportValues();

    CRM_Core_BAO_Setting::setItem($values['volunteer_default_profiles'],"volunteer_defaults", "volunteer_default_profiles");
    CRM_Core_BAO_Setting::setItem($values['volunteer_default_campaign'],"volunteer_defaults", "volunteer_default_campaign");
    CRM_Core_BAO_Setting::setItem($values['volunteer_default_locblock'],"volunteer_defaults", "volunteer_default_locblock");

    $isActive = array_key_exists("volunteer_default_is_active", $values) ? $values['volunteer_default_is_active'] : 0;
    CRM_Core_BAO_Setting::setItem($isActive, "volunteer_defaults", "volunteer_default_is_active");

    CRM_Core_BAO_Setting::setItem($values['volunteer_default_contacts'],"volunteer_defaults", "volunteer_default_contacts");

    CRM_Core_Session::setStatus(ts("Changed Saved"), "Saved", "success");
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
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}