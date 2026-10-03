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
  $params['visibility_id']['api.default'] = CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public');

  // these metadata fields are managed; don't display them in the API explorer
  unset($params['created'], $params['last_updated']);
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
  $publicRead = FALSE;
  if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
    // Resolve every project in scope. `id` and `project_id` may each be a
    // scalar, a comma-separated list, or an operator array, so a privileged
    // read requires update access to all of them; an unenumerable filter
    // leaves the scope unknown and falls back to the public read.
    $projectIds = CRM_Volunteer_Permission::extractRequestedIds($params['project_id'] ?? NULL);
    if ($projectIds === array()) {
      $needIds = CRM_Volunteer_Permission::extractRequestedIds($params['id'] ?? NULL);
      $projectIds = $needIds === NULL
        ? NULL
        : CRM_Volunteer_Permission::projectIdsForRecords('CRM_Volunteer_DAO_Need', $needIds);
    }

    $privileged = !empty($projectIds);
    foreach (($projectIds === NULL ? array() : $projectIds) as $projectId) {
      if (!CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $projectId)) {
        $privileged = FALSE;
        break;
      }
    }

    if (!$privileged) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::VIEW);
      $publicRead = TRUE;
      $params['is_active'] = 1;
      $params['visibility_id'] = CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public');
      if (!CRM_Volunteer_Permission::isInternalBypassActive()) {
        $params = CRM_Volunteer_Permission::stripChainedApiParams($params);
      }
      // Short-circuit only when every project in scope is disabled; the
      // per-row active-project filter below still applies otherwise.
      if (!empty($projectIds)) {
        $activeInScope = FALSE;
        foreach ($projectIds as $projectId) {
          if (CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Project', $projectId, 'is_active')) {
            $activeInScope = TRUE;
            break;
          }
        }
        if (!$activeInScope) {
          return civicrm_api3_create_success(array(), $params, 'VolunteerNeed', 'get');
        }
      }
    }
  }
  if (!$publicRead && !CRM_Volunteer_Permission::isInternalBypassActive()) {
    $params = CRM_Volunteer_Permission::enforceChainedApiPermissions($params);
  }
  $result = _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
  $activeProjectIds = array();
  if ($publicRead && !empty($result['values'])) {
    $projectIds = array_unique(array_map(
      'intval',
      array_column($result['values'], 'project_id')
    ));
    $projectIds = array_filter($projectIds);
    if ($projectIds) {
      // IDs originate from the need records and are normalized to integers.
      $projectDao = CRM_Core_DAO::executeQuery(
        'SELECT id FROM civicrm_volunteer_project WHERE is_active = 1 AND id IN (' . implode(',', $projectIds) . ')'
      );
      while ($projectDao->fetch()) {
        $activeProjectIds[(int) $projectDao->id] = TRUE;
      }
    }
  }
  if (!empty($result['values'])) {
    $result['values'] = CRM_Volunteer_BAO_Need::addDisplayFields($result['values']);

    foreach ($result['values'] as $needKey => $need) {
      if ($publicRead) {
        if (empty($activeProjectIds[(int) $need['project_id']])) {
          unset($result['values'][$needKey]);
          continue;
        }
        $result['values'][$needKey] = array_intersect_key($need, array_flip(array(
          'id',
          'project_id',
          'start_time',
          'end_time',
          'duration',
          'is_flexible',
          'quantity',
          'visibility_id',
          'role_id',
          'is_active',
          'display_time',
          'role_label',
          'role_description',
        )));
      }
    }
    if ($publicRead) {
      $result['count'] = count($result['values']);
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
  $filters = CRM_Volunteer_Api4::stripApi3Envelope($params);
  // APIv3 named these filters in snake_case; API4 parameters are camelCase.
  $setters = array(
    'beneficiary' => 'setBeneficiary',
    'project' => 'setProject',
    'proximity' => 'setProximity',
    'role_id' => 'setRoleId',
    'date_start' => 'setDateStart',
    'date_end' => 'setDateEnd',
  );

  $search = \Civi\Api4\VolunteerNeed::search(
    CRM_Volunteer_Permission::shouldCheckPermissions($params)
  );
  foreach ($setters as $param => $setter) {
    if (isset($filters[$param]) && $filters[$param] !== '') {
      $search->$setter($filters[$param]);
    }
  }

  // APIv3 returned the needs keyed by need ID.
  $result = array();
  foreach ($search->execute() as $need) {
    $result[(int) $need['id']] = $need;
  }

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
  \Civi\Api4\VolunteerNeed::delete(CRM_Volunteer_Permission::shouldCheckPermissions($params))
    ->addWhere('id', '=', $params['id'])
    ->execute();

  return civicrm_api3_create_success(TRUE, $params, 'VolunteerNeed', 'delete');
}
