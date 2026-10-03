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
  $checkPermissions = CRM_Volunteer_Permission::shouldCheckPermissions($params);
  $values = CRM_Volunteer_Api4::stripApi3Envelope($params);

  // APIv3 used one action for insert and update; API4 separates them.
  if (empty($values['id'])) {
    $record = \Civi\Api4\VolunteerAssignment::create($checkPermissions)
      ->setValues($values)
      ->execute()
      ->single();
  }
  else {
    $id = $values['id'];
    unset($values['id']);
    $record = \Civi\Api4\VolunteerAssignment::update($checkPermissions)
      ->addWhere('id', '=', $id)
      ->setValues($values)
      ->execute()
      ->single();
  }

  return civicrm_api3_create_success(array((int) $record['id'] => $record), $params, 'VolunteerAssignment', 'create');
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_assignment_create_spec(&$params) {
  $params['assignee_contact_id']['api.aliases'] = array('contact_id');
  // Required create fields and defaults are handled by the aggregate service,
  // allowing callers to update an existing assignment with only its ID and
  // changed fields.
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
  if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
    // Each of these may be a scalar, a comma-separated list, or an operator
    // array, so resolve every project in scope and require access to all of
    // them. An unenumerable filter leaves the scope unknown and is refused.
    $projectIds = CRM_Volunteer_Permission::extractRequestedIds($params['project_id'] ?? NULL);
    if ($projectIds === array()) {
      $needIds = CRM_Volunteer_Permission::extractRequestedIds($params['volunteer_need_id'] ?? NULL);
      $projectIds = $needIds === NULL
        ? NULL
        : CRM_Volunteer_Permission::projectIdsForRecords('CRM_Volunteer_DAO_Need', $needIds);
    }
    if ($projectIds === array() && !empty($params['id'])) {
      $assignmentIds = CRM_Volunteer_Permission::extractRequestedIds($params['id']);
      if ($assignmentIds === NULL) {
        $projectIds = NULL;
      }
      else {
        $projectIds = array();
        foreach ($assignmentIds as $assignmentId) {
          $assignment = CRM_Volunteer_BAO_Assignment::retrieve(array('id' => $assignmentId));
          $first = reset($assignment);
          if ($first) {
            $projectIds[] = (int) $first['project_id'];
          }
        }
        $projectIds = array_values(array_unique(array_filter($projectIds)));
      }
    }

    if (empty($projectIds)) {
      throw new API_Exception(ts('You do not have permission to view assignments for this volunteer project.', array('domain' => 'org.civicrm.volunteer')), 403);
    }
    foreach ($projectIds as $projectId) {
      if (!CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $projectId)
        && !CRM_Volunteer_Permission::checkProjectPerms(CRM_Volunteer_Permission::VIEW_ROSTER, $projectId)) {
        throw new API_Exception(ts('You do not have permission to view assignments for this volunteer project.', array('domain' => 'org.civicrm.volunteer')), 403);
      }
    }
  }
  if (!CRM_Volunteer_Permission::isInternalBypassActive()) {
    $params = CRM_Volunteer_Permission::enforceChainedApiPermissions($params);
  }
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
  $assignment = CRM_Volunteer_BAO_Assignment::retrieve(array('id' => $params['id']));
  $assignment = reset($assignment);
  if (!$assignment) {
    throw new API_Exception(ts('The volunteer assignment does not exist.', array('domain' => 'org.civicrm.volunteer')));
  }
  if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
    CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $assignment['project_id']);
  }
  $params['check_permissions'] = FALSE;
  return _civicrm_api3_basic_delete('CRM_Activity_BAO_Activity', $params);
}
