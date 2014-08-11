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
 * File for the CiviCRM APIv3 Volunteer Commendation functions
 *
 * @package CiviVolunteer_APIv3
 * @subpackage API_Volunteer_Commendation
 * @copyright CiviCRM LLC (c) 2004-2013
 */


/**
 * Returns array of commendations
 *
 * @param array $params  Associative array of property name/value pairs
 *                       describing the commendations to be retrieved.
 * @example
 * @return array ID-indexed array of matching commendations
 * {@getfields assignment_get}
 * @access public
 */
function civicrm_api3_volunteer_commendation_get($params) {
  $result = CRM_Volunteer_BAO_Commendation::retrieve($params);
  return civicrm_api3_create_success($result, $params, 'Activity', 'get');
}

/**
 * Adjust Metadata for Get action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_commendation_get_spec(&$params) {
  $params['id']['api.aliases'] = array('activity_id');
}