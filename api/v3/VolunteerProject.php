<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 * File for the CiviCRM APIv3 Volunteer Project functions
 *
 * @package CiviVolunteer_APIv3
 * @subpackage API_Volunteer_Project
 * @copyright CiviCRM LLC (c) 2004-2013
 */


/**
 * Create or update a project
 *
 * @param array $params  Associative array of property
 *                       name/value pairs to insert in new 'project'
 * @example
 *
 * @return array api result array
 * {@getfields volunteer_project_create}
 * @access public
 */
function civicrm_api3_volunteer_project_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_project_create_spec(&$params) {
  $params['title']['api.required'] = 1;
  $params['is_active']['api.default'] = 1;
  $params['project_contacts'] = array(
    'title' => 'Project Contacts',
    'description' => 'Array of [volunteer relationship type] => [contact IDs].
      See CRM_Volunteer_BAO_Project::create().',
    'type' => CRM_Utils_Type::T_STRING,
  );
}

/**
 * Returns array of projects matching a set of one or more project properties
 *
 * @param array $params  Array of one or more valid
 *                       property_name=>value pairs. If $params is set
 *                       as null, all projects will be returned
 *
 * @return array  Array of matching projects
 * {@getfields volunteer_project_get}
 * @access public
 */
function civicrm_api3_volunteer_project_get($params) {
  $result = CRM_Volunteer_BAO_Project::retrieve($params);
  foreach ($result as $k => $dao) {
    $result[$k] = $dao->toArray();
  }
  return civicrm_api3_create_success($result, $params, 'VolunteerProject', 'get');
}

function _civicrm_api3_volunteer_project_get_spec(&$params) {
  $params['id']['api.aliases'] = array('project_id');
  $params['project_contacts'] = array(
    'title' => 'Project Contacts',
    'description' => 'Array of [volunteer relationship type] => [contact IDs].
      See CRM_Volunteer_BAO_Project::retrieve(). This parameter is used for
      filtering only; project contacts are not returned.',
    'type' => CRM_Utils_Type::T_STRING,
  );
  $params['proximity'] = array(
    'title' => 'Proximity',
    'description' => 'Array of parameters (lat, lon, radius, unit) by which to
      geographically limit results. See CRM_Volunteer_BAO_Project::retrieve().
      This parameter is used for filtering only; project contacts are not returned.',
    'type' => CRM_Utils_Type::T_STRING,
  );
}
/**
 * delete an existing project
 *
 * This method is used to delete any existing project. id of the project
 * to be deleted is required field in $params array
 *
 * @param array $params  array containing id of the project
 *                       to be deleted
 *
 * @return array  returns flag true if successfull, error
 *                message otherwise
 * {@getfields volunteer_project_delete}
 * @access public
 */
function civicrm_api3_volunteer_project_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}


/**
 * remove a UFJoin record
 *
 *
 *
 * @param array $params  array containing id of the profile
 *                       to be removed
 *
 * @return array  returns flag true if successfull, error
 *                message otherwise
 */
function civicrm_api3_volunteer_project_removeprofile($params) {
  return _civicrm_api3_basic_delete('CRM_Core_BAO_UFJoin', $params);
}

/**
 * Returns an key/value array of location blocks with proper names
 * Instead of the null values returned when using a crmEntityref
 * connected to the locBlock entity
 *
 * @param $params
 * @return array
 *
 */
function civicrm_api3_volunteer_project_locations($params) {

  $locations = array();

  $query = "
SELECT CONCAT_WS(' :: ' , ca.name, ca.street_address, ca.city, sp.name, ca.supplemental_address_1, ca.supplemental_address_2) title, lb.id
FROM   civicrm_loc_block lb
INNER JOIN civicrm_address ca   ON lb.address_id = ca.id
LEFT  JOIN civicrm_state_province sp ON ca.state_province_id = sp.id
ORDER BY sp.name, ca.city, ca.street_address ASC
";

  $dao = CRM_Core_DAO::executeQuery($query);
  while ($dao->fetch()) {
    $locations[$dao->id] = $dao->title;
  }

  return civicrm_api3_create_success($locations, $params, 'VolunteerProject', 'locations');
}


/**
 * Saves/creates an entire location block with a single call instead of
 * requiring a handful of calls/promises/resolutions from angular
 *
 * @param $params
 * @return array
 *
 */
function civicrm_api3_volunteer_project_savelocblock($params) {

  $locBlockData = array();

  //Address 1
  if(array_key_exists("address", $params) && is_array($params['address'])) {
    $locBlockData['address'][1] = $params['address'];
    if(!array_key_exists("location_type_id", $locBlockData['address'][1])) {
      $locBlockData['address'][1]['location_type_id'] = 1;
    }
  }
  //Address 2
  if(array_key_exists("address_2", $params) && is_array($params['address_2'])) {
    $locBlockData['address'][2] = $params['address_2'];
    if(!array_key_exists("location_type_id", $locBlockData['address'][2])) {
      $locBlockData['address'][2]['location_type_id'] = 2;
    }
  }

  //Email 1
  if(array_key_exists("email", $params) && is_array($params['email'])) {
    $locBlockData['email'][1] = $params['email'];
    if(!array_key_exists("location_type_id", $locBlockData['email'][1])) {
      $locBlockData['email'][1]['location_type_id'] = 1;
    }
  }

  //Email 2
  if(array_key_exists("email_2", $params) && is_array($params['email_2'])) {
    $locBlockData['email'][2] = $params['email_2'];
    if(!array_key_exists("location_type_id", $locBlockData['email'][2])) {
      $locBlockData['email'][2]['location_type_id'] = 2;
    }
  }

  //Phone 1
  if(array_key_exists("phone", $params) && is_array($params['phone'])) {
    $locBlockData['phone'][1] = $params['phone'];
    if(!array_key_exists("location_type_id", $locBlockData['phone'][1])) {
      $locBlockData['phone'][1]['location_type_id'] = 1;
    }
  }

  //Phone 2
  if(array_key_exists("phone_2", $params) && is_array($params['phone_2'])) {
    $locBlockData['phone'][2] = $params['phone_2'];
    if(!array_key_exists("location_type_id", $locBlockData['phone'][2])) {
      $locBlockData['phone'][2]['location_type_id'] = 2;
    }
  }

  if(array_key_exists("id", $params) && is_array($params['id']) && !$params['id'] == 0) {
    $locBlockData['id'] = $params['id'];
  }

  $locBlockData['entity_table'] = 'civicrm_volunteer_project';
  $locBlockData['entity_id'] = $params['entity_id'];
  $location = CRM_Core_BAO_Location::create($locBlockData, TRUE, 'VolunteerProject');

  return civicrm_api3_create_success($location['id'], "VolunteerProject", "SaveLocBlock", $params);

}

