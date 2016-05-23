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

    foreach(CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
      $this->add(
        'select',
        'volunteer_project_default_profiles_' . $audience['type'],
        ts($audience['description']),
        $profileList,
        false, // is required,
        array("placeholder" => ts("-- No Default profiles --"), "multiple" => "multiple", "class" => "crm-select2")
      );
    }

    $campaigns = civicrm_api3('VolunteerUtil', 'getcampaigns', array());
    $campaignList = array();
    foreach($campaigns['values'] as $campaign) {
      $campaignList[$campaign['id']] = $campaign['title'];
    }
    $this->add(
      'select',
      'volunteer_project_default_campaign',
      ts('Default Campaign'),
      $campaignList,
      false, // is required,
      array("placeholder" => true)
    );

    $locBlocks = civicrm_api3('VolunteerProject', 'locations', array());
    $this->add(
      'select',
      'volunteer_project_default_locblock',
      ts('Default Location'),
      $locBlocks['values'],
      false, // is required,
      array("placeholder" => ts("-- No Default Location --"))
    );

    $this->add(
      'checkbox',
      'volunteer_project_default_is_active',
      ts('Are new Projects active by Default?'),
      null,
      false
    );

    /*
    $this->add(
      'text',
      'volunteer_project_default_contacts',
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
    $this->assign('elementGroups', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }


  function setDefaultValues() {
    $defaults = array();

    $defaults['is_active'] = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_project_default_is_active");
    $defaults['campaign_id'] = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_project_default_campaign");
    $defaults['loc_block_id'] = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_project_default_locblock");

    //Break the profiles out into their own fields
    $profiles = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_project_default_profiles");
    foreach(CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
      $defaults["volunteer_project_default_profiles_" . $audience['type']] = CRM_Utils_Array::value($audience['type'], $profiles, array());
    }

    return $defaults;
  }


  function validate() {
    //CRM_Core_Session::setStatus(ts(""), "Error", "error");
    return true;
  }

  function postProcess() {
    $values = $this->exportValues();


    //Compose the profiles before we save tem.
    $profiles = array();

    foreach(CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
      $profiles[$audience['type']] = CRM_Utils_Array::value('volunteer_project_default_profiles_' . $audience['type'], $values);
    }

    CRM_Core_BAO_Setting::setItem(
      $profiles,
      "volunteer_defaults",
      "volunteer_project_default_profiles"
    );


    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_project_default_campaign', $values),"volunteer_defaults", "volunteer_project_default_campaign");
    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_project_default_locblock', $values),"volunteer_defaults", "volunteer_project_default_locblock");
    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_project_default_is_active', $values, 0), "volunteer_defaults", "volunteer_project_default_is_active");

    //Todo: Create Composit data structure like we do for profiles
    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_project_default_contacts', $values),"volunteer_defaults", "volunteer_project_default_contacts");

    CRM_Core_Session::setStatus(ts("Changes Saved"), "Saved", "success");
    parent::postProcess();
  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    $elementGroups = array();

    foreach ($this->_elements as $element) {
      $name = $element->getName();
      $entity = preg_replace("/volunteer_(.*)_default_.*/", "$1", $name);
      $group = ts("Default " . ucfirst($entity) . " Settings");

      $label = $element->getLabel();
      if (!empty($label)) {

        if(!array_key_exists($group, $elementGroups)) {
          $elementGroups[$group] = array();
        }

        $elementGroups[$group][] = $name;
      }
    }

    return $elementGroups;
  }

}