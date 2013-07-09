<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
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
  return; // FIXME
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_assignment_create_spec(&$params) {
}

/**
 * Returns array of assignments matching a set of one or more group properties
 *
 * @param array $params  (referance) Array of one or more valid
 *                       property_name=>value pairs. If $params is set
 *                       as null, all assignments will be returned
 *
 * @return array  (referance) Array of matching assignments
 * {@getfields assignment_get}
 * @access public
 */
function civicrm_api3_volunteer_assignment_get($params) {
  return; // FIXME
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

