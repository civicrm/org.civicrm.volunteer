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
 * @deprecated Use the VolunteerProject.commit API4 action.
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
  $project = \Civi\Api4\VolunteerProject::commit(CRM_Volunteer_Permission::shouldCheckPermissions($params))
    ->setValues(CRM_Volunteer_Api4::stripApi3Envelope($params))
    ->execute()
    ->single();

  return civicrm_api3_create_success($project, $params, 'VolunteerProject', 'create');
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_project_create_spec(&$params) {
  // Title is required for new records by the aggregate service. It is not
  // marked unconditionally required here because APIv3 uses create for partial
  // updates as well.
  $params['project_contacts'] = array(
    'title' => 'Project Contacts',
    'description' => 'Create or replace the project contact associations with
      this project. Array of [volunteer relationship type] => [contact IDs].
      See CRM_Volunteer_BAO_Project::create().',
    'type' => CRM_Utils_Type::T_STRING,
  );
  $params['profiles'] = array(
    'title' => 'Profiles',
    'description' => 'Create or replace the profile associations with
      this project. Array of arrays, where each child array is a set of
      parameters that could be passed to api.UFJoin.create. See
      CRM_Volunteer_BAO_Project::create().',
    'type' => CRM_Utils_Type::T_STRING,
  );
  $params['location'] = array(
    'title' => 'Project Location',
    'description' => 'Optional nested LocBlock data saved atomically with the project.',
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
  $checkPermissions = CRM_Volunteer_Permission::shouldCheckPermissions($params);
  $context = $params['context'] ?? NULL;

  // APIv3 chains are not translated: API4 expresses related data as joins, so a
  // caller must request it explicitly. Untrusted requests had their chains
  // stripped or force-checked before; dropping them is the safe equivalent.
  $filters = CRM_Volunteer_Api4::stripApi3Envelope($params);
  unset($filters['context']);

  $result = \Civi\Api4\VolunteerProject::search($checkPermissions)
    ->setContext($context)
    ->setFilters($filters)
    ->execute()
    ->indexBy('id')
    ->getArrayCopy();

  return civicrm_api3_create_success($result, $params, 'VolunteerProject', 'get');
}

function _civicrm_api3_volunteer_project_get_spec(&$params) {
  $params['id']['api.aliases'] = array('project_id');
  $params['context'] = array(
    'title' => 'Action Context',
    'description' => 'String representing the context in which permissions
    are evaluated. E.g You may have the right to view projects, but not edit them. This is for filtering only',
    'type' => CRM_Utils_Type::T_STRING,
  );
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
  \Civi\Api4\VolunteerProject::delete(CRM_Volunteer_Permission::shouldCheckPermissions($params))
    ->addWhere('id', '=', $params['id'])
    ->execute();

  return civicrm_api3_create_success(TRUE, $params, 'VolunteerProject', 'delete');
}

function _civicrm_api3_volunteer_project_delete_spec(&$params) {
  $params['id']['api.required'] = 1;
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
  if (empty($params['id']) || empty($params['project_id'])) {
    throw new API_Exception(ts('Both profile assignment ID and project ID are required.', array('domain' => 'org.civicrm.volunteer')));
  }

  // This action has always enforced permissions regardless of the request's
  // check_permissions flag, because it removes a project's public signup form.
  \Civi\Api4\VolunteerProject::removeProfile()
    ->setId($params['id'])
    ->setProjectId($params['project_id'])
    ->execute();

  return civicrm_api3_create_success(TRUE, $params, 'VolunteerProject', 'removeprofile');
}

function _civicrm_api3_volunteer_project_removeprofile_spec(&$params) {
  $params['id']['api.required'] = 1;
  $params['project_id'] = array(
    'title' => 'Volunteer Project ID',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  );
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
  // This action has always enforced project permissions regardless of the
  // request's check_permissions flag.
  $rows = \Civi\Api4\VolunteerProject::getLocationOptions()
    ->setProjectId($params['project_id'] ?? NULL)
    ->execute();

  // APIv3 returned an id => title map rather than rows.
  $locations = array();
  foreach ($rows as $row) {
    $locations[$row['id']] = $row['title'];
  }

  return civicrm_api3_create_success($locations, $params, 'VolunteerProject', 'locations');
}

/**
 * This method provides all data for a selected LocBlock
 *
 * @param $params
 * @return array
 *
 */
function civicrm_api3_volunteer_project_getlocblockdata($params) {
  // Prevent chaining problems: for instance, if this API is chained to
  // api.volunteer_project.get, and the returned project has no loc_block_id,
  // we should return 0 loc_blocks instead of 25 (the API default limit).
  if (empty($params['id'])) {
    return civicrm_api3_create_success(array(), $params, 'VolunteerProject', 'getlocblockdata');
  }

  // This action has always enforced project permissions regardless of the
  // request's check_permissions flag.
  $rows = \Civi\Api4\VolunteerProject::getLocation()
    ->setId($params['id'])
    ->setProjectId($params['project_id'] ?? NULL)
    ->execute();

  // APIv3's LocBlock.get keyed its values by loc block ID.
  $locBlocks = array();
  foreach ($rows as $row) {
    $locBlocks[$row['id']] = $row;
  }

  return civicrm_api3_create_success($locBlocks, $params, 'VolunteerProject', 'getlocblockdata');
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
  // This action has always enforced project permissions regardless of the
  // request's check_permissions flag.
  $saved = \Civi\Api4\VolunteerProject::saveLocation()
    ->setProjectId($params['project_id'] ?? NULL)
    ->setValues(CRM_Volunteer_Api4::stripApi3Envelope($params))
    ->execute()
    ->single();

  return civicrm_api3_create_success(array('id' => $saved['id']), $params, 'VolunteerProject', 'savelocblock');
}
