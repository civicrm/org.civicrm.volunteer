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
   * The configured volunteer project relationship types.
   *
   * Do not access this property directly. Use $this->getProjectRelationshipTypes
   * instead.
   *
   * @var array
   */
  private $projectRelationshipTypes = array();

  /**
   * All settings, as fetched via API, keyed by setting name.
   *
   * @var array
   */
  protected $_settings = array();
  protected $_settingsMetadata = array();

  /**
   * Relationships that can be made with an Individual on one end or the other.
   *
   * Do not access this property directly. Use $this->getValidRelationshipTypes()
   * instead.
   *
   * @var array
   */
  private $validRelationshipTypes = array();

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
    $ccr = CRM_Core_Resources::singleton();
    $ccr->addScriptFile('org.civicrm.volunteer', 'js/CRM_Volunteer_Form_Settings.js');
    $ccr->addStyleFile('org.civicrm.volunteer', 'css/CRM_Volunteer_Form_Settings.css');

    $this->_fieldDescriptions = array();
    $this->_helpIcons = array();

    $profiles = civicrm_api3('UFGroup', 'get', array("return" => "title", "sequential" => 1, 'options' => array('limit' => 0)));
    $profileList = array();
    foreach ($profiles['values'] as $profile) {
      $profileList[$profile['id']] = $profile['title'];
    }

    foreach (CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
      $this->add(
        'select',
        'volunteer_project_default_profiles_' . $audience['type'],
        $audience['description'],
        $profileList,
        FALSE, // is required,
        array(
          "placeholder" => ts("- none -", array('domain' => 'org.civicrm.volunteer')),
          "multiple" => "multiple",
          "class" => "crm-select2",
          "data-fieldgroup" => "Default Project Settings"
        )
      );
    }

    $campaigns = civicrm_api3('VolunteerUtil', 'getcampaigns', array());
    $campaignList = array();
    foreach ($campaigns['values'] as $campaign) {
      $campaignList[$campaign['id']] = $campaign['title'];
    }
    $this->add(
      'select',
      'volunteer_project_default_campaign',
      ts('Campaign', array('domain' => 'org.civicrm.volunteer')),
      $campaignList,
      FALSE, // is required,
      array("placeholder" => TRUE)
    );

    $locBlocks = civicrm_api3('VolunteerProject', 'locations', array());
    $this->add(
      'select',
      'volunteer_project_default_locblock',
      ts('Location', array('domain' => 'org.civicrm.volunteer')),
      $locBlocks['values'],
      FALSE, // is required,
      array("placeholder" => ts("- none -", array('domain' => 'org.civicrm.volunteer')))
    );

    $this->add(
      'checkbox',
      'volunteer_project_default_is_active',
      ts('Are new Projects active by default?', array('domain' => 'org.civicrm.volunteer')),
      NULL,
      FALSE
    );

    $this->addProjectRelationshipFields();

    /*     * * Fields for Campaign Whitelist/Blacklist ** */
    $this->add(
      'select',
      'volunteer_general_campaign_filter_type',
      ts('Campaign Filter Whitelist/Blacklist', array('domain' => 'org.civicrm.volunteer')),
      array(
        "blacklist" => ts("Blacklist", array('domain' => 'org.civicrm.volunteer')),
        "whitelist" => ts("Whitelist", array('domain' => 'org.civicrm.volunteer')),
      ),
      TRUE
    );

    $results = civicrm_api3('OptionValue', 'get', array(
      'sequential' => 1,
      'option_group_id' => "campaign_type",
      'return' => "value,label",
    ));
    $campaignTypes = array();
    foreach ($results['values'] as $campaignType) {
      $campaignTypes[$campaignType['value']] = $campaignType['label'];
    }

    $this->add(
      'select',
      'volunteer_general_campaign_filter_list',
      ts('Campaign Type(s)', array('domain' => 'org.civicrm.volunteer')),
      $campaignTypes,
      FALSE, // is required,
      array(
        "placeholder" => ts("- none -", array('domain' => 'org.civicrm.volunteer')),
        "multiple" => "multiple",
        "class" => "crm-select2"
      )
    );


    $this->add(
        'wysiwyg',
        'volunteer_general_project_settings_help_text',
        ts('Help text for the project settings screen', array('domain' => 'org.civicrm.volunteer')),
        array()
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

  private function addProjectRelationshipFields() {
    foreach ($this->getProjectRelationshipTypes() as $data) {
      $name = $data['name'];
      $this->addRadio(
        "volunteer_project_default_contacts_mode_$name",
        $data['label'],
        $this->getProjectRelationshipSettingModes(),
        array("data-fieldgroup" => "Default Project Settings",),
        NULL,
        TRUE
      );

      // EntityRef is not used because a select list not easily obtainable through a single API call is needed
      $this->add(
        'select',
        "volunteer_project_default_contacts_relationship_$name",
        ts('Default to Contact(s) Having this Relationship with the Acting Contact', array('domain' => 'org.civicrm.volunteer')),
        $this->getValidRelationshipTypes(),
        FALSE, // is required,
        array(
          'class' => 'crm-select2',
          'data-fieldgroup' => 'Default Project Settings',
          'placeholder' => TRUE,
        )
      );

      $this->addEntityRef(
        "volunteer_project_default_contacts_contact_$name",
        ts('Default to Selected Contact(s)', array('domain' => 'org.civicrm.volunteer')),
        array(
          'multiple' => TRUE,
          'data-fieldgroup' => "Default Project Settings",
        )
      );
    }
  }

  /**
   * Assigns help text to the form object for use in the template layer.
   */
  private function buildHelpText() {
    $newProjectUrl = CRM_Utils_System::url('civicrm/vol/', NULL, FALSE, 'volunteer/manage/0');
    $helpText = '<p>' . ts('The values set in this section will be used as defaults for volunteer projects in both the form and data layers.', array('domain' => 'org.civicrm.volunteer')) . '</p>';
    $helpText .= '<p>' . ts('Streamline creation of new volunteer projects by selecting the options you choose most. The <a href="%1">New Project screen</a> will open with these settings already selected. These values will also be used for projects created through API unless other values are specified.', array(1 => $newProjectUrl, 'domain' => 'org.civicrm.volunteer')) . '</p>';
    $helpText .= '<p>' . ts('Note: Projects created by users who do not have the "edit volunteer project relationships" or "edit volunteer registration profiles" permissions will always use the defaults for those fields.', array('domain' => 'org.civicrm.volunteer')) . '</p>';
    $this->assign('helpText', array(
      'Default Project Settings' => $helpText,
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

    // Break the profiles out into their own fields
    $profiles = CRM_Utils_Array::value('volunteer_project_default_profiles', $this->_settings);
    foreach (CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
      $defaults["volunteer_project_default_profiles_" . $audience['type']] = CRM_Utils_Array::value($audience['type'], $profiles, array());
    }

    // Break out contact defaults into their own fields
    $defaultContacts = CRM_Utils_Array::value('volunteer_project_default_contacts', $this->_settings);
    foreach ($defaultContacts as $name => $data) {
      $mode = $data['mode'];
      $defaults["volunteer_project_default_contacts_mode_{$name}"] = $mode;

      if ($mode !== 'acting_contact') {
        $defaults["volunteer_project_default_contacts_{$mode}_{$name}"] = $data['value'];
      }
    }

    // General Settings
    $defaults['volunteer_general_campaign_filter_type'] = CRM_Utils_Array::value('volunteer_general_campaign_filter_type', $this->_settings);
    $defaults['volunteer_general_campaign_filter_list'] = CRM_Utils_Array::value('volunteer_general_campaign_filter_list', $this->_settings);
    $defaults['volunteer_general_project_settings_help_text'] = CRM_Utils_Array::value('volunteer_general_project_settings_help_text', $this->_settings);

    return $defaults;
  }

  function validate() {
    $values = $this->exportValues();

    // This is not true validation; just a warning for a configuration that most
    // users are not likely to want.
    if ($values['volunteer_general_campaign_filter_type'] == "whitelist" &&
        empty($values['volunteer_general_campaign_filter_list'])
    ) {
      CRM_Core_Session::setStatus(ts("Your whitelist of campaign types is empty. As a result, no campaigns will be available for Volunteer Projects.", array('domain' => 'org.civicrm.volunteer')), "Warning", "warning");
    }

    foreach ($this->getProjectRelationshipTypes() as $relTypeData) {
      $name = $relTypeData['name'];
      $selectedMode = CRM_Utils_Array::value("volunteer_project_default_contacts_mode_{$name}", $values);

      // skip this check if user did not select a mode; that field is required
      // and there's no sense displaying two messages for the same error
      if (!$selectedMode) {
        continue;
      }

      $fieldName = "volunteer_project_default_contacts_{$selectedMode}_{$name}";
      // unless 'acting_contact' is the mode, some other value needs to have been selected
      if ($selectedMode !== 'acting_contact' && empty($values[$fieldName])) {
        $this->_errors[$fieldName] = ts("%1 is a required field.", array(
          1 => $relTypeData['label'],
          'domain' => 'org.civicrm.volunteer',
        ));
      }
    }

    return parent::validate();
  }

  function postProcess() {
    $values = $this->exportValues();

    //Compose the profiles before we save tem.
    $profiles = array();

    foreach (CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes() as $audience) {
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

    Civi::settings()->set('volunteer_general_project_settings_help_text', CRM_Utils_Array::value('volunteer_general_project_settings_help_text', $values));

    civicrm_api3('Setting', 'create', array(
      "volunteer_project_default_is_active" => CRM_Utils_Array::value('volunteer_project_default_is_active', $values, 0)
    ));

    civicrm_api3('Setting', 'create', array(
      'volunteer_project_default_contacts' => $this->formatDefaultContacts()
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
    if (!$settingName || !$attr) {
      return FALSE;
    }
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
      $groupName = $this->getGroupName($element);

      $label = $element->getLabel();
      if (!empty($label)) {

        if (!array_key_exists($groupName, $elementGroups)) {
          $elementGroups[$groupName] = array();
        }

        $elementGroups[$groupName][] = $element->getName();
      }
    }

    return $elementGroups;
  }

  /**
   * Helper method for getting the group name for a field/element.
   *
   * @param HTML_QuickForm_element $element
   * @return mixed
   *   String or NULL
   */
  private function getGroupName(HTML_QuickForm_element $element) {
    $groupName = NULL;

    // radios and checkboxes are nested inside HTML_QuickForm_group objects;
    // check *their* elements for the data-fieldgroup attribute
    if (is_a($element, 'HTML_QuickForm_group')) {
      if ($first = CRM_Utils_Array::value(0, $element->_elements)) {
        $groupName = $first->getAttribute("data-fieldgroup");
      }
    }
    else {
      $groupName = $element->getAttribute("data-fieldgroup");
    }

    // otherwise fallback to settings metadata
    if (empty($groupName)) {
      $setting = CRM_Utils_Array::value($element->getName(), $this->_settingsMetadata);
      $groupName = $setting['group_name'];
    }

    return $groupName;
  }

  /**
   * Getter for projectRelationshipTypes.
   *
   * @return array
   */
  public function getProjectRelationshipTypes() {
    if (empty($this->projectRelationshipTypes)) {
      $result = civicrm_api3('OptionValue', 'get', array(
        'is_active' => 1,
        'option_group_id' => "volunteer_project_relationship",
        'options' => array(
          'limit' => 0,
        )
      ));
      $this->projectRelationshipTypes = $result['values'];
    }

    return $this->projectRelationshipTypes;
  }

  /**
   * Returns an array of modes for specifying project relationship defaults.
   *
   * @return array
   */
  public function getProjectRelationshipSettingModes() {
    return array(
      'acting_contact' => ts('Acting Contact', array('domain' => 'org.civicrm.volunteer')),
      'relationship' => ts('Related Contact(s)', array('domain' => 'org.civicrm.volunteer')),
      'contact' => ts('Specific Contact(s)', array('domain' => 'org.civicrm.volunteer')),
    );
  }

  /**
   * Gets relationships that can be made with an Individual on one end or
   * the other.
   *
   * @return array
   */
  private function getValidRelationshipTypes() {
    if (empty($this->validRelationshipTypes)) {
      $commonParams = array(
        'is_active' => 1,
        'options' => array(
          'limit' => 0,
        )
      );
      $apiContactA = civicrm_api3('RelationshipType', 'get', $commonParams + array(
        'contact_type_a' => "Individual",
      ));
      foreach ($apiContactA['values'] as $id => $data) {
        $this->validRelationshipTypes["{$id}_a"] = $data['label_a_b'];
      }

      $apiContactB = civicrm_api3('RelationshipType', 'get', $commonParams + array(
        'contact_type_b' => "Individual",
      ));
      foreach ($apiContactB['values'] as $id => $data) {
        $this->validRelationshipTypes["{$id}_b"] = $data['label_b_a'];
      }
    }
    return $this->validRelationshipTypes;
  }

  /**
   * Creates a composite data structure for the default contact fields.
   *
   * This is the format in which this data is stored in the database.
   *
   * @return array
   */
  private function formatDefaultContacts() {
    $store = array();
    $values = $this->exportValues();

    foreach ($this->getProjectRelationshipTypes() as $relTypeData) {
      $name = $relTypeData['name'];
      $mode = $values["volunteer_project_default_contacts_mode_{$name}"];

      $store[$name] = array(
        'mode' => $mode,
      );

      if ($mode === 'acting_contact') {
        // For interface consistency we supply a 'value' key though it isn't strictly needed.
        $store[$name]['value'] = TRUE;
      }
      else {
        $fieldName = "volunteer_project_default_contacts_{$mode}_{$name}";
        $store[$name]['value'] = $values[$fieldName];
      }
    }

    return $store;
  }

}
