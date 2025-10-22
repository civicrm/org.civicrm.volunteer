<?php

/**
 * This file is used to collect util API functions not related to any particular
 * CiviCRM entity. Since so much of the interface has moved to the client side,
 * we need server-side code to handle things like managing dependencies.
 *
 * @package CiviVolunteer_APIv3
 * @subpackage API_Volunteer_Project
 */

/**
 * @deprecated api notice
 * @return array
 *   Array of deprecated actions
 */
function _civicrm_api3_volunteer_util_deprecation() {
  return array(
    'getbeneficiaries' => 'VolunteerUtil API "getbeneficiaries" action is '
    . 'deprecated in favor of api.VolunteerProjectContacts.getList. Set the '
    . '"params" parameter to array("relationship_type_id" => "volunteer_beneficiary") '
    . 'to replace calls to api.VolunteerUtil.getbeneficiaries.',
  );
}

/**
 * This function will return the needed pieces to load up the backbone/
 * marionette project backend from within an angular page.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 *   Keyed with "css," "templates," "scripts," and "settings," this array
 *   contains the dependencies of the backbone-based volunteer app.
 *
 */
function civicrm_api3_volunteer_util_loadbackbone($params) {

  $results = array("css" => array(), "templates" => array(), "scripts" => array(), "settings" => array());

  $ccr = CRM_Core_Resources::singleton();
  $config = CRM_Core_Config::singleton();

  $results['css'][] = $ccr->getUrl('org.civicrm.volunteer', 'css/volunteer_app.css');

  $baseDir = CRM_Extension_System::singleton()->getMapper()->keyToBasePath('org.civicrm.volunteer') . '/';
  // This glob pattern will recurse the js directory up to 4 levels deep
  foreach (glob($baseDir . 'js/backbone/{*,*/*,*/*/*,*/*/*/*}.js', GLOB_BRACE) as $file) {
    $fileName = substr($file, strlen($baseDir));
    $results['scripts'][] = $ccr->getUrl('org.civicrm.volunteer', $fileName);
  }

  $results['templates'][] = 'civicrm/volunteer/backbonetemplates';

  $results['settings'] = array(
    'pseudoConstant' => array(
      'volunteer_need_visibility' => array_flip(CRM_Volunteer_BAO_Need::buildOptions('visibility_id', 'validate')),
      'volunteer_role' => CRM_Volunteer_BAO_Need::buildOptions('role_id', 'get'),
      'volunteer_status' => CRM_Activity_BAO_Activity::buildOptions('status_id', 'validate'),
    ),
    // TODO: This API is about satisfying generic depenedencies need to build
    // the backbone-based volunteer UIs inside an Angular app. Previously
    // CRM.volunteer.default_date provided the start time of the event as a
    // default for new needs; project-specific information does not belong in
    // this API so we'll temporarily set this for noon of the next day until
    // we have an alternative mechanism.
    'volunteer' => array(
      //'default_date' => CRM_Utils_Array::value('start_date', $entity),
      'default_date' => date("Y-m-d H:i:s", strtotime('tomorrow noon')),
    ),
    'config' => array(
      'timeInputFormat' => $config->timeInputFormat,
    ),
    'constants' => array(
      'CRM_Core_Action' => array(
        'NONE' => 0,
        'ADD' => 1,
        'UPDATE' => 2,
        'VIEW' => 4,
        'DELETE' => 8,
        'BROWSE' => 16,
        'ENABLE' => 32,
        'DISABLE' => 64,
        'EXPORT' => 128,
        'BASIC' => 256,
        'ADVANCED' => 512,
        'PREVIEW' => 1024,
        'FOLLOWUP' => 2048,
        'MAP' => 4096,
        'PROFILE' => 8192,
        'COPY' => 16384,
        'RENEW' => 32768,
        'DETACH' => 65536,
        'REVERT' => 131072,
        'CLOSE' => 262144,
        'REOPEN' => 524288,
        'MAX_ACTION' => 1048575,
      ),
    ),
  );

  return civicrm_api3_create_success($results, "VolunteerUtil", "loadbackbone", $params);
}

/**
 * This function returns the permissions defined by the volunteer extension.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 */
function civicrm_api3_volunteer_util_getperms($params) {
  $results = array();

  foreach (CRM_Volunteer_Permission::getVolunteerPermissions() as $k => $v) {
    $results[] = array(
      'description' => $v[1],
      'label' => $v[0],
      'name' => $k,
      'safe_name' => strtolower(str_replace(array(' ', '-'), '_', $k)),
    );
  }

  return civicrm_api3_create_success($results, "VolunteerUtil", "getperms", $params);
}

function _civicrm_api3_volunteer_util_getsupportingdata_spec(&$params) {
  $params['controller'] = array(
    'title' => 'Controller',
    'description' => 'For which Angular controller is supporting data required?',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  );
}

/**
 * This function returns supporting data for various JavaScript-driven interfaces.
 *
 * The purpose of this API is to provide limited access to general-use APIs to
 * facilitate building user interfaces without having to grant users access to
 * APIs they otherwise shouldn't be able to access.
 *
 * @param array $params
 *   @see _civicrm_api3_volunteer_util_getsupportingdata_spec()
 * @return array
 */
function civicrm_api3_volunteer_util_getsupportingdata($params) {
  $results = array();

  $controller = $params['controller'] ?? NULL;
  if ($controller === 'VolunteerProject') {
    $relTypes = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
      'options' => array('limit' => 0),
    ));
    $results['relationship_types'] = $relTypes['values'];

    $results['phone_types'] = CRM_Core_OptionGroup::values("phone_type", FALSE, FALSE, TRUE);
    $results['volunteer_general_project_settings_help_text'] = civicrm_api3('Setting', 'getvalue', array(
      'name' => "volunteer_general_project_settings_help_text",
    ));

    //Fetch the Defaults from saved settings.
    $defaults = CRM_Volunteer_BAO_Project::composeDefaultSettingsArray();

    //Allow other extensions to modify the defaults
    CRM_Volunteer_Hook::projectDefaultSettings($defaults);

    $results['defaults'] = $defaults;
  }

  if ($controller === 'VolOppsCtrl') {
    $results['roles'] = CRM_Core_OptionGroup::values('volunteer_role', FALSE, FALSE, TRUE);
  }

  $results['use_profile_editor'] = CRM_Volunteer_Permission::check(array("access CiviCRM","profile listings and forms"));

  $results['profile_audience_types'] = CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes();

  if (!$results['use_profile_editor']) {
    $profiles = civicrm_api3('UFGroup', 'get', array("return" => "title", "sequential" => 1, 'options' => array('limit' => 0)));
    $results['profile_list'] = $profiles['values'];
  }


  return civicrm_api3_create_success($results, "VolunteerUtil", "getsupportingdata", $params);
}

/**
 * This method returns a list of beneficiaries
 *
 * @deprecated since version 2.3
 *   api.VolunteerProjectContacts.getList serves the same purpose and is both
 *   more efficient more versatile.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 */
function civicrm_api3_volunteer_util_getbeneficiaries($params) {
  $beneficiaries = civicrm_api3('VolunteerProjectContact', 'get', array(
    'options' => array('limit' => 0),
    'relationship_type_id' => 'volunteer_beneficiary',
    'return' => 'contact_id',
  ));

  if (!$beneficiaries['count']) {
    return array();
  }

  $contactIds = array();
  foreach ($beneficiaries['values'] as $b) {
    array_push($contactIds, $b['contact_id']);
  }
  $contactIds = array_unique($contactIds);

  return civicrm_api3('Contact', 'get', array(
    'id' => array('IN' => $contactIds),
    'options' => array('limit' => 0),
    'return' => 'display_name',
  ));
}

/**
 * This function returns the enabled countries in CiviCRM.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 */
function civicrm_api3_volunteer_util_getcountries($params) {
  $settings = civicrm_api3('Setting', 'get', array(
    "return" => array("countryLimit", "defaultContactCountry"),
    "sequential" => 1,
  ));


  $countryParams = array(
    "options" => array("limit" => 0),
  );

  if (!empty($settings['values'][0]['countryLimit'])) {
    $countryParams["id"] = array("IN" => $settings['values'][0]['countryLimit']);
  }

  $countries = civicrm_api3('Country', 'get', $countryParams);

  $results = $countries['values'];
  foreach ($results as $k => $country) {
    // since we are wrapping CiviCRM's API, and it provides even boolean data
    // as quoted strings, we'll do the same
    $is_default = "0";
    if (isset($settings['values'][0]['defaultContactCountry']) && $country['id'] === $settings['values'][0]['defaultContactCountry'] ) {
      $is_default = "1";
    }
     $results[$k]['is_default'] = $is_default;
  }

  return civicrm_api3_create_success($results, "VolunteerUtil", "getcountries", $params);
}

/**
 * This function returns the active, searchable custom fields in the
 * Volunteer_Information custom field group.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 */
function civicrm_api3_volunteer_util_getcustomfields($params) {
  $allowedCustomFieldTypes = array('Autocomplete-Select',
    'CheckBox', 'Multi-Select', 'Radio', 'Select', 'Text');

  $customGroupAPI = civicrm_api3('CustomGroup', 'getsingle', array(
    'extends' => 'Individual',
    'name' => 'Volunteer_Information',
    'api.customField.get' => array(
      'html_type' => array('IN' => $allowedCustomFieldTypes),
      'is_active' => 1,
      'is_searchable' => 1
    ),
    'options' => array('limit' => 0),
  ));
  $customFields = $customGroupAPI['api.customField.get']['values'];

  $optionListIDs = _volunteer_util_getOptionGroupIds($customFields);
  $optionValueAPI = civicrm_api3('OptionValue', 'get', array(
    'is_active' => 1,
    'opt_group_id' => array('IN' => $optionListIDs),
    'options' => array(
      'limit' => 0,
      'sort' => 'weight',
    )
  ));

  $optionData = _volunteer_util_groupBy($optionValueAPI['values'], 'option_group_id');
  foreach($customFields as &$field) {
    $optionGroupId = $field['option_group_id'] ?? NULL;
    if ($optionGroupId) {
      $field['options'] = $optionData[$optionGroupId];

      // Boolean fields don't use option groups, so we supply one
    } elseif ($field['data_type'] === 'Boolean' && $field['html_type'] === 'Radio') {
      $field['options'] = array(
        array (
          'is_active' => 1,
          'is_default' => 1,
          'label' => ts("Yes", array('domain' => 'org.civicrm.volunteer')),
          'value' => 1,
          'weight' => 1,
        ),
        array (
          'is_active' => 1,
          'is_default' => 0,
          'label' => ts("No", array('domain' => 'org.civicrm.volunteer')),
          'value' => 0,
          'weight' => 2,
        ),
      );
    }
  }

  return civicrm_api3_create_success($customFields, "VolunteerUtil", "getcustomfields", $params);
}

/**
 * @param array $customFields
 *   api.customField.get.values
 * @return array
 */
function _volunteer_util_getOptionGroupIds(array $customFields) {
  $optionListIDs = array();
  foreach ($customFields as $field) {
    if (!empty($field['option_group_id'])) {
      $optionListIDs[] = $field['option_group_id'];
    }
  }
  return array_unique($optionListIDs);
}

/**
 * Splits an array into sets based on the $property.
 *
 * Inspired by underscorejs's _.groupBy function.
 *
 * @param array $collection
 * @param string $property
 * @return array
 */
function _volunteer_util_groupBy(array $collection, $property) {
  $result = array();
  foreach ($collection as $item) {
    $key = $item[$property] ?? NULL;
    if (!array_key_exists($key, $result)) {
      $result[$key] = array();
    }
    $result[$key][] = $item;
  }

  return $result;
}
