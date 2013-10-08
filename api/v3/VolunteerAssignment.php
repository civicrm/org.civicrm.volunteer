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
 * File for the CiviCRM APIv3 Volunteer Assignment functions
 *
 * @package CiviVolunteer_APIv3
 * @subpackage API_Volunteer_Assignment
 * @copyright CiviCRM LLC (c) 2004-2013
 */


/**
 * Create or update a volunteer assignment
 *
 * @param array $params  Associative array of property
 *                       name/value pairs to insert in new 'assignment'
 * @example AssignmentCreate.php Std Create example
 *
 * @return array api result array
 * {@getfields volunteer_assignment create}
 * @access public
 */
function civicrm_api3_volunteer_assignment_create($params) {
  $result = CRM_Volunteer_BAO_Assignment::createVolunteerActivity($params);
  if ($result) {
    return civicrm_api3('volunteer_assignment', 'get', array('id' => $result));
  }
  return civicrm_api3_create_error('unable to create activity');
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_assignment_create_spec(&$params) {
  $params['volunteer_need_id']['api.required'] = 1;
  $params['assignee_contact_id']['api.required'] = 1;
  $params['assignee_contact_id']['api.aliases'] = array('contact_id');
  $volunteerStatus = CRM_Activity_BAO_Activity::buildOptions('status_id', 'validate');
  $params['status_id']['api.default'] = array_search('Scheduled', $volunteerStatus);
}

/**
 * Returns array of assignments matching a set of one or more group properties
 *
 * @param array $params  Associative array of property name/value pairs
 *                       describing the assignments to be retrieved.
 * @example
 * @return array ID-indexed array of matching assignments
 * {@getfields assignment_get}
 * @access public
 */
function civicrm_api3_volunteer_assignment_get($params) {
  $result = CRM_Volunteer_BAO_Assignment::retrieve($params);
  return civicrm_api3_create_success($result, $params, 'Activity', 'get');
}

/**
 * Adjust Metadata for Get action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_assignment_get_spec(&$params) {
  $params['id']['api.aliases'] = array('activity_id');
}

/**
 * Delete any existing assignment activity.
 * Activity id is required
 *
 * @param array $params  (reference) array containing id of the group
 *                       to be deleted
 *
 * @return array  (referance) returns flag true if successfull, error
 *                message otherwise
 * {@getfields assignment_delete}
 * @access public
 */
function civicrm_api3_volunteer_assignment_delete($params) {
  return _civicrm_api3_basic_delete('CRM_Activity_BAO_Activity', $params);
}

