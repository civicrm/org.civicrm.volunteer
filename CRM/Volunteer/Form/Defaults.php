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
        $audience['description'],
        $profileList,
        false, // is required,
        array("placeholder" => ts("- none -", array('domain' => 'org.civicrm.volunteer')), "multiple" => "multiple", "class" => "crm-select2")
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
      ts('Campaign', array('domain' => 'org.civicrm.volunteer')),
      $campaignList,
      false, // is required,
      array("placeholder" => true)
    );

    $locBlocks = civicrm_api3('VolunteerProject', 'locations', array());
    $this->add(
      'select',
      'volunteer_project_default_locblock',
      ts('Location', array('domain' => 'org.civicrm.volunteer')),
      $locBlocks['values'],
      false, // is required,
      array("placeholder" => ts("- none -", array('domain' => 'org.civicrm.volunteer')))
    );

    $this->add(
      'checkbox',
      'volunteer_project_default_is_active',
      ts('Are new Projects active by default?', array('domain' => 'org.civicrm.volunteer')),
      null,
      false
    );

    /*
        $this->add(
          'text',
          'volunteer_project_default_contacts',
          ts('Default Associated Contacts', array('domain' => 'org.civicrm.volunteer')),
          array("size" => 35),
          true // is required,
        );
        */


    /*** Fields for Campaign Whitelist/Blacklist ***/
    $this->add(
      'select',
      'volunteer_general_campaign_filter_type',
      ts('Campaign Filter Whitelist/Blacklist', array('domain' => 'org.civicrm.volunteer')),
      array(
        "blacklist" => ts("Blacklist", array('domain' => 'org.civicrm.volunteer')),
        "whitelist" => ts("Whitelist", array('domain' => 'org.civicrm.volunteer')),
      ),
      true
    );


    $results = civicrm_api3('OptionValue', 'get', array(
      'sequential' => 1,
      'option_group_id' => "campaign_type",
      'return' => "value,label",
    ));
    $campaignTypes = array();
    foreach($results['values'] as $campaignType) {
      $campaignTypes[$campaignType['value']] = $campaignType['label'];
    }

    $this->add(
      'select',
      'volunteer_general_campaign_filter_list',
      ts('Campaign Type(s)', array('domain' => 'org.civicrm.volunteer')),
      $campaignTypes,
      false, // is required,
      array("placeholder" => ts("- none -", array('domain' => 'org.civicrm.volunteer')), "multiple" => "multiple", "class" => "crm-select2")
    );


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save Default Settings', array('domain' => 'org.civicrm.volunteer')),
        'isDefault' => TRUE,
      ),
    ));
    // export form elements
    $this->assign('elementGroups', $this->getRenderableElementNames());
    $this->buildHelpText();
    parent::buildQuickForm();
  }

  /**
   * Assigns help text to the form object for use in the template layer.
   */
  private function buildHelpText() {
    $newProjectUrl = CRM_Utils_System::url('civicrm/vol/', NULL, FALSE, 'volunteer/manage/0');
    $this->assign('helpText', array(
      'Default Project Settings' => ts('Streamline creating new volunteer projects by selecting the options you choose most. The <a href="%1">New Project screen</a> will open with these settings already selected.', array(1 => $newProjectUrl, 'domain' => 'org.civicrm.volunteer')),
    ));
  }

  function setDefaultValues() {
    $defaults = array();

    $defaults['volunteer_project_default_is_active'] = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_project_default_is_active");
    $defaults['volunteer_project_default_campaign'] = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_project_default_campaign");
    $defaults['volunteer_project_default_locblock'] = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_project_default_locblock");

    //Break the profiles out into their own fields
    $profiles = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_project_default_profiles");
    foreach(CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
      $defaults["volunteer_project_default_profiles_" . $audience['type']] = CRM_Utils_Array::value($audience['type'], $profiles, array());
    }

    //General Settings
    $defaults['volunteer_general_campaign_filter_type'] = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_general_campaign_filter_type");
    $defaults['volunteer_general_campaign_filter_list'] = CRM_Core_BAO_Setting::getItem("org.civicrm.volunteer", "volunteer_general_campaign_filter_list");

    return $defaults;
  }


  function validate() {
    //CRM_Core_Session::setStatus(ts("", array('domain' => 'org.civicrm.volunteer')), "Error", "error");
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
      "org.civicrm.volunteer",
      "volunteer_project_default_profiles"
    );


    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_project_default_campaign', $values),"org.civicrm.volunteer", "volunteer_project_default_campaign");
    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_project_default_locblock', $values),"org.civicrm.volunteer", "volunteer_project_default_locblock");
    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_project_default_is_active', $values, 0), "org.civicrm.volunteer", "volunteer_project_default_is_active");

    //Todo: Create Composit data structure like we do for profiles
    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_project_default_contacts', $values),"org.civicrm.volunteer", "volunteer_project_default_contacts");


    //Whitelist/Blacklist settings
    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_general_campaign_filter_type', $values), "org.civicrm.volunteer", "volunteer_general_campaign_filter_type");
    CRM_Core_BAO_Setting::setItem(CRM_Utils_Array::value('volunteer_general_campaign_filter_list', $values, 0), "org.civicrm.volunteer", "volunteer_general_campaign_filter_list");


    CRM_Core_Session::setStatus(ts("Changes Saved", array('domain' => 'org.civicrm.volunteer')), "Saved", "success");
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
      $entity = preg_replace("/volunteer_([a-zA-Z0-9]*)_.*/", "$1", $name);
      $includeDefault = strpos($name, "default") ? "Default " : "";
      $group = ts($includeDefault . ucfirst($entity) . " Settings", array('domain' => 'org.civicrm.volunteer'));

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