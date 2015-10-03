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
  foreach (glob($baseDir . 'js/{*,*/*,*/*/*,*/*/*/*}.js', GLOB_BRACE) as $file) {
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
        'CLOSE' =>  262144,
        'REOPEN' =>  524288,
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

  foreach(CRM_Volunteer_Permission::getVolunteerPermissions() as $k => $v) {
    $results[] = array(
      'description' => $v[1],
      'label' => $v[0],
      'name' => $k,
      'safe_name' => strtolower(str_replace(array(' ', '-'), '_', $k)),
    );
  }

  return civicrm_api3_create_success($results, "VolunteerUtil", "getperms", $params);
}
