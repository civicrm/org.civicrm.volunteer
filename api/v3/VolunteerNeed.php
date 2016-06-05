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
 * File for the CiviCRM APIv3 Volunteer Need functions
 *
 * @package CiviVolunteer_APIv3
 * @subpackage API_Volunteer_Need
 * @copyright CiviCRM LLC (c) 2004-2013
 */


/**
 * Create or update a need
 *
 * @param array $params  Associative array of property
 *                       name/value pairs to insert in new 'need'
 * @example NeedCreate.php Std Create example
 *
 * @return array api result array
 * {@getfields volunteer_need create}
 * @access public
 */
function civicrm_api3_volunteer_need_create($params) {
  return _civicrm_api3_basic_create('CRM_Volunteer_BAO_Need', $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_need_create_spec(&$params) {
  $params['is_flexible']['api.default'] = 0;
  $params['is_active']['api.default'] = 1;
  $params['visibility_id']['api.default'] = CRM_Core_OptionGroup::getValue('visibility', 'public', 'name');
}

/**
 * Returns array of needs  matching a set of one or more group properties
 *
 * @param array $params  Array of one or more valid
 *                       property_name=>value pairs. If $params is set
 *                       as null, all needs will be returned
 *
 * @return array  (referance) Array of matching needs
 * {@getfields need_get}
 * @access public
 */
function civicrm_api3_volunteer_need_get($params) {
  $result = _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
  if (!empty($result['values'])) {
    foreach ($result['values'] as &$need) {
      if (!empty($need['start_time'])) {
        $need['display_time'] = CRM_Volunteer_BAO_Need::getTimes($need['start_time'],
          CRM_Utils_Array::value('duration', $need),
          CRM_Utils_Array::value('end_time', $need));
      }
      else {
        $need['display_time'] = CRM_Volunteer_BAO_Need::getFlexibleDisplayTime();
      }
      if (isset($need['role_id'])) {
        $role = CRM_Core_OptionGroup::getRowValues(
          CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP, $need['role_id'],
          'value'
        );
        $need['role_label'] = $role['label'];
        $need['role_description'] = $role['description'];
      } elseif (CRM_Utils_Array::value('is_flexible', $need)) {
        $need['role_label'] = CRM_Volunteer_BAO_Need::getFlexibleRoleLabel();
        $need['role_description'] = NULL;
      }
    }
  }
  return $result;
}

/**
 * Adjust Metadata for Get action
 *
 * The metadata is used for setting defaults, documentation, validation, aliases, etc.
 *
 * @param array $params
 */
function _civicrm_api3_volunteer_need_get_spec(&$params) {
  // VOL-196: these aliases facilitate API chaining as well as provide backwards
  // compatibility for code referencing the fields' removed uniqueNames
  $params['id']['api.aliases'] = array('volunteer_need_id');
  $params['project_id']['api.aliases'] = array('volunteer_project_id', 'volunteer_need_project_id');
}

function _civicrm_api3_volunteer_need_getsearchresult_spec(&$params) {
  $params['beneficiary'] = array(
    'title' => 'Project Beneficiary',
    'description' => 'Contacts which benefit from a Volunteer Project. (An
      int-like string, a comma-separated list thereof, or an array representing
      one or more contact IDs who benefit from the Needs/Opportunities.)',
    'type' => CRM_Utils_Type::T_INT,
  );
  $params['project'] = array(
    'title' => 'Volunteer Project',
    'description' => 'Volunteer Project ID',
    'type' => CRM_Utils_Type::T_INT,
  );
  $params['proximity'] = array(
    'title' => 'Proximity',
    'description' => 'Array of parameters (lat, lon, radius, unit) by which to
      geographically limit results. See CRM_Volunteer_BAO_Project::retrieve().
      This parameter is used for filtering only; project contacts are not returned.',
    'type' => CRM_Utils_Type::T_STRING,
  );
  $params['role_id'] = array(
    'title' => 'Role',
    'description' => 'The role the volunteer will perform in the project. (An
      int-like string, a comma-separated list thereof, or an array representing
      one or more role IDs.)',
    'type' => CRM_Utils_Type::T_STRING,
  );
  $params['date_start'] = array(
    'title' => 'Start Date',
    'description' => 'Used to filter Needs/Opportunities. Needs/Opportunities before this date won\'t be returned.',
    'type' => CRM_Utils_Type::T_DATE,
  );
  $params['date_end'] = array(
    'title' => 'End Date',
    'description' => 'Used to filter Needs/Opportunities. Needs/Opportunities after this date won\'t be returned.',
    'type' => CRM_Utils_Type::T_DATE,
  );
}

/**
 * Returns the results of a search.
 *
 * This API is used with the volunteer opportunities search UI.
 *
 * @param array $params
 *   See CRM_Volunteer_BAO_NeedSearch::doSearch().
 *
 * @return array
 */
function civicrm_api3_volunteer_need_getsearchresult($params) {
  $result = CRM_Volunteer_BAO_NeedSearch::doSearch($params);
  return civicrm_api3_create_success($result, $params, 'VolunteerNeed', 'getsearchresult');
}

/**
 * delete an existing need
 *
 * This method is used to delete any existing need. id of the group
 * to be deleted is required field in $params array
 *
 * @param array $params  (reference) array containing id of the group
 *                       to be deleted
 *
 * @return array  (referance) returns flag true if successfull, error
 *                message otherwise
 * {@getfields need_delete}
 * @access public
 */
function civicrm_api3_volunteer_need_delete($params) {
  return _civicrm_api3_basic_delete('CRM_Volunteer_BAO_Need', $params);
}

