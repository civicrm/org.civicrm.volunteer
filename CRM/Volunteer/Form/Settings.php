<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Volunteer_Form_Settings extends CRM_Core_Form {

  protected $_fieldDescriptions = array();
  protected $_helpIcons = array();

  /**
   * All settings, as fetched via API, keyed by setting name.
   *
   * @var array
   */
  protected $_settings = array();
  protected $_settingsMetadata = array();

  function preProcess() {
    parent::preProcess();

    $result = civicrm_api3('Setting', 'getfields');
    $this->_settingsMetadata = ($result['count'] > 0) ? $result['values'] : array();

    $currentDomainId = civicrm_api3('Domain', 'getvalue', array(
      'return' => 'id',
      'current_domain' => 1,
    ));
    $setting = civicrm_api3('Setting', 'get');
    $this->_settings = $setting['values'][$currentDomainId];
  }

  function buildQuickForm() {
    // add form elements

    $this->_fieldDescriptions = array();
    $this->_helpIcons = array();

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
        array(
          "placeholder" => ts("- none -", array('domain' => 'org.civicrm.volunteer')),
          "multiple" => "multiple",
          "class" => "crm-select2",
          "fieldGroup" => "Default Project Settings"
        )
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
        'name' => ts('Save Volunteer Settings', array('domain' => 'org.civicrm.volunteer')),
        'isDefault' => TRUE,
      ),
    ));
    // export form elements
    $this->assign('elementGroups', $this->getRenderableElementNames());
    $this->buildHelpText();
    $this->buildFieldDescriptions();
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

  private function buildFieldDescriptions() {

    foreach ($this->_elements as $element) {
      $name = $element->getName();
      $helpText = $this->getSettingMetadata($name, "help_text");
      if ($helpText && !array_key_exists($name, $this->_fieldDescriptions)) {
        $this->_fieldDescriptions[$name] = $helpText;
      }
    }

    $this->assign('fieldDescriptions', $this->_fieldDescriptions);
  }

  function setDefaultValues() {
    $defaults = array();

    $defaults['volunteer_project_default_is_active'] = CRM_Utils_Array::value('volunteer_project_default_is_active', $this->_settings);
    $defaults['volunteer_project_default_campaign'] = CRM_Utils_Array::value('volunteer_project_default_campaign', $this->_settings);
    $defaults['volunteer_project_default_locblock'] = CRM_Utils_Array::value('volunteer_project_default_locblock', $this->_settings);

    //Break the profiles out into their own fields
    $profiles = CRM_Utils_Array::value('volunteer_project_default_profiles', $this->_settings);
    foreach(CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
      $defaults["volunteer_project_default_profiles_" . $audience['type']] = CRM_Utils_Array::value($audience['type'], $profiles, array());
    }

    //General Settings
    $defaults['volunteer_general_campaign_filter_type'] = CRM_Utils_Array::value('volunteer_general_campaign_filter_type', $this->_settings);
    $defaults['volunteer_general_campaign_filter_list'] = CRM_Utils_Array::value('volunteer_general_campaign_filter_list', $this->_settings);

    return $defaults;
  }

  function validate() {
    parent::validate();

    $values = $this->exportValues();
    if($values['volunteer_general_campaign_filter_type'] == "whitelist" &&
      empty($values['volunteer_general_campaign_filter_list'])) {
      CRM_Core_Session::setStatus(ts("Your whitelist of campaign types is empty. As a result, no campaigns will be available for Volunteer Projects.", array('domain' => 'org.civicrm.volunteer')), "Warning", "warning");
    }

    return TRUE;
  }

  function postProcess() {
    $values = $this->exportValues();

    //Compose the profiles before we save tem.
    $profiles = array();

    foreach(CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
      $profiles[$audience['type']] = CRM_Utils_Array::value('volunteer_project_default_profiles_' . $audience['type'], $values);
    }

    civicrm_api3('Setting', 'create', array(
      "volunteer_project_default_profiles" => $profiles,
    ));

    civicrm_api3('Setting', 'create', array(
      "volunteer_project_default_campaign" => CRM_Utils_Array::value('volunteer_project_default_campaign', $values)
    ));
    civicrm_api3('Setting', 'create', array(
      "volunteer_project_default_locblock" => CRM_Utils_Array::value('volunteer_project_default_locblock', $values)
    ));

    civicrm_api3('Setting', 'create', array(
      "volunteer_project_default_is_active" => CRM_Utils_Array::value('volunteer_project_default_is_active', $values, 0)
    ));

    // Todo: Create Composite data structure like we do for profiles
    civicrm_api3('Setting', 'create', array(
      "volunteer_project_default_contacts" => CRM_Utils_Array::value('volunteer_project_default_contacts', $values)
    ));

    //Whitelist/Blacklist settings
    civicrm_api3('Setting', 'create', array(
      "volunteer_general_campaign_filter_type" => CRM_Utils_Array::value('volunteer_general_campaign_filter_type', $values)
    ));
    civicrm_api3('Setting', 'create', array(
      "volunteer_general_campaign_filter_list" => CRM_Utils_Array::value('volunteer_general_campaign_filter_list', $values, array())
    ));

    CRM_Core_Session::setStatus(ts("Changes Saved", array('domain' => 'org.civicrm.volunteer')), "Saved", "success");
    parent::postProcess();
  }

  /**
   * This function fetches individual attributes from
   * the Settings Metadata.
   *
   * @param string $settingName
   * @param string $attr
   * @return bool|mixed
   */
  function getSettingMetadata($settingName, $attr) {
    if (!$settingName || !$attr) { return false; }
    $setting = CRM_Utils_Array::value($settingName, $this->_settingsMetadata, array());
    return CRM_Utils_Array::value($attr, $setting);
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
      $groupName = $element->getAttribute("fieldGroup");

      if (empty($groupName)) {
        $groupName = CRM_Utils_Array::value($name, $this->_settingsMetadata);
        $groupName = $groupName['group_name'];
      }

      $label = $element->getLabel();
      if (!empty($label)) {

        if(!array_key_exists($groupName, $elementGroups)) {
          $elementGroups[$groupName] = array();
        }

        $elementGroups[$groupName][] = $name;
      }
    }

    return $elementGroups;
  }

}
