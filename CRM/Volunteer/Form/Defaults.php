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

    $profiles = civicrm_api3('UFGroup', 'get', array("return" => "title", "sequential" => 1, 'options' => array('limit' => 0)));
    $profileList = array();
    foreach($profiles['values'] as $profile) {
      $profileList[$profile['id']] = $profile['title'];
    }

    $this->add(
      'select',
      'volunteer_default_profiles_individual',
      ts('Default Profile(s) for Individual Registration'),
      $profileList,
      false, // is required,
      array("placeholder" => ts("-- No Default profiles --"), "multiple" => "multiple", "class" => "crm-select2")
    );

    $this->add(
      'select',
      'volunteer_default_profiles_group',
      ts('Default Profile(s) for Group Registration'),
      $profileList,
      false, // is required,
      array("placeholder" => ts("-- No Default profiles --"), "multiple" => "multiple", "class" => "crm-select2")
    );

    $this->add(
      'select',
      'volunteer_default_profiles_both',
      ts('Default Profile(s) for Both Individual and Group Registration'),
      $profileList,
      false, // is required,
      array("placeholder" => ts("-- No Default profiles --"), "multiple" => "multiple", "class" => "crm-select2")
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

    $locBlocks = civicrm_api3('VolunteerProject', 'locations', array());
    $this->add(
      'select',
      'volunteer_default_locblock',
      ts('Default Location'),
      $locBlocks['values'],
      false, // is required,
      array("placeholder" => ts("-- No Default Location --"))
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

    CRM_Core_BAO_Setting::setItem($values['volunteer_default_profiles_individual'],"volunteer_defaults", "volunteer_default_profiles_individual");
    CRM_Core_BAO_Setting::setItem($values['volunteer_default_profiles_group'],"volunteer_defaults", "volunteer_default_profiles_group");
    CRM_Core_BAO_Setting::setItem($values['volunteer_default_profiles_both'],"volunteer_defaults", "volunteer_default_profiles_both");
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