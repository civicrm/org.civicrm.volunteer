<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
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
 * File for the CiviCRM APIv3 Volunteer Project Contact functions
 *
 * @package CiviVolunteer_APIv3
 * @subpackage API_Volunteer_Project_Contact
 * @copyright CiviCRM LLC (c) 2004-2015
 */


/**
 * Create or update a project contact
 *
 * @param array $params  Associative array of properties
 *                       name/value pairs to insert in new 'project contact'
 * @example
 *
 * @return array api result array
 * {@getfields volunteer_project_contact_create}
 * @access public
 */
function civicrm_api3_volunteer_project_contact_create($params) {
  // Validation and project-level authorization live in
  // CRM_Volunteer_BAO_ProjectContact::create(), which _civicrm_api3_basic_create()
  // dispatches to. Keeping them there means API4's generic save/replace and any
  // direct BAO caller are guarded by the same code rather than a copy of it.
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_project_contact_create_spec(&$params) {
  // The action supports partial updates by ID. Required create fields are
  // validated in civicrm_api3_volunteer_project_contact_create().
}

/**
 * Adjust Metadata for Get action
 *
 * The metadata is used for setting defaults, documentation, validation, aliases, etc.
 *
 * @param array $params
 */
function _civicrm_api3_volunteer_project_contact_get_spec(&$params) {
  // this alias facilitates chaining from api.volunteer_project.get
  $params['project_id']['api.aliases'] = array('volunteer_project_id');
}

/**
 * Returns array of project contacts matching a set of one or more properties
 *
 * @param array $params  Array of one or more valid
 *                       property_name=>value pairs.
 *
 * @return array  Array of matching project contacts
 * {@getfields volunteer_project_contact_get}
 * @access public
 */
function civicrm_api3_volunteer_project_contact_get($params) {
  $publicRead = FALSE;
  if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
    // `id` and `project_id` may each be a scalar, a comma-separated list, or an
    // operator array, so resolve the full set of projects in scope rather than
    // authorizing against whichever one happened to be first.
    $projectIds = CRM_Volunteer_Permission::extractRequestedIds($params['project_id'] ?? NULL);
    if ($projectIds === array()) {
      $rowIds = CRM_Volunteer_Permission::extractRequestedIds($params['id'] ?? NULL);
      $projectIds = $rowIds === NULL
        ? NULL
        : CRM_Volunteer_Permission::projectIdsForRecords('CRM_Volunteer_DAO_ProjectContact', $rowIds);
    }
    if (empty($projectIds)) {
      throw new API_Exception(ts('A project ID is required to retrieve volunteer project contacts.', array('domain' => 'org.civicrm.volunteer')));
    }

    $privileged = TRUE;
    foreach ($projectIds as $projectId) {
      if (!CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $projectId)) {
        $privileged = FALSE;
        break;
      }
    }
    if (!$privileged) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::VIEW);
      $publicRead = TRUE;
      // Pseudoconstant translation happens on civi.api.prepare, before this
      // function runs, so a name here would reach SQL as a string compared
      // against an int column and match nothing. Use the stored value.
      $params['relationship_type_id'] = CRM_Core_PseudoConstant::getKey(
        'CRM_Volunteer_BAO_ProjectContact',
        'relationship_type_id',
        'volunteer_beneficiary'
      );
      $activeInScope = FALSE;
      foreach ($projectIds as $projectId) {
        if (CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Project', $projectId, 'is_active')) {
          $activeInScope = TRUE;
          break;
        }
      }
      if (!$activeInScope) {
        return civicrm_api3_create_success(array(), $params, 'VolunteerProjectContact', 'get');
      }
    }
  }
  if ($publicRead && !CRM_Volunteer_Permission::isInternalBypassActive()) {
    $params = CRM_Volunteer_Permission::stripChainedApiParams($params);
  }
  elseif (!CRM_Volunteer_Permission::isInternalBypassActive()) {
    $params = CRM_Volunteer_Permission::enforceChainedApiPermissions($params);
  }
  $result = _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
  if (!empty($result['values'])) {
    $relationshipOptions = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'name', 'label')
      ->addWhere('option_group_id.name', '=', CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP)
      ->execute();
    $relationshipOptionsByValue = array();
    $relationshipOptionsByName = array();
    foreach ($relationshipOptions as $optionValue) {
      $relationshipOptionsByValue[(int) $optionValue['value']] = $optionValue;
      $relationshipOptionsByName[$optionValue['name']] = $optionValue;
    }
    foreach ($result['values'] as &$projectContact) {
      //In some contexts we are passing 'return' => 'contact_id' in with $params
      //In this case, there is no relationship_type_id returned as part of the results set above
      //Following that, when you pass a null value into getsingle, it finds 3 results and errors out
      //This solution was created to fall back on relationship_type_id if present in
      //$params, and if not, skip loading the relationship type label.
      $rType = false;
      $rType = (array_key_exists("relationship_type_id", $params) ) ? $params['relationship_type_id'] : $rType;
      $rType = (array_key_exists("relationship_type_id", $projectContact) ) ? $projectContact['relationship_type_id'] : $rType;

      if ($rType) {
        $optionValue = is_numeric($rType)
          ? ($relationshipOptionsByValue[(int) $rType] ?? NULL)
          : ($relationshipOptionsByName[$rType] ?? NULL);
        if ($optionValue) {
          $projectContact['relationship_type_label'] = $optionValue['label'];
          $projectContact['relationship_type_name'] = $optionValue['name'];
        }
      }
    }
    unset($projectContact);
  }
  return $result;

}

/**
 * Delete an existing project contact
 *
 * This method is used to delete the relationship(s) between a contact and a
 * project.
 *
 * @param array $params  array containing id of the project
 *                       to be deleted
 *
 * @return array  returns flag true if successfull, error
 *                message otherwise
 * {@getfields volunteer_project_delete}
 * @access public
 */
function civicrm_api3_volunteer_project_contact_delete($params) {
  $projectId = CRM_Core_DAO::getFieldValue("CRM_Volunteer_DAO_ProjectContact", $params['id'], "project_id");
  if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
    CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $projectId);
    if (!CRM_Volunteer_Permission::check('edit volunteer project relationships')) {
      throw new API_Exception(ts('You do not have permission to modify contacts for this project.', array('domain' => 'org.civicrm.volunteer')), 403);
    }
  }
  $result = _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
  CRM_Volunteer_Permission::flushProjectContactCache($projectId);
  return $result;
}

/**
 * Adjust Metadata for Delete action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_volunteer_project_contact_delete_spec(&$params) {
  $params['id']['api.required'] = 1;
}

/**
 * Set the default getList behavior to return a list of contact IDs labeled by
 * contact sort names.
 *
 * @param array $request
 *   The parameters passed to the sub-API call (i.e., the parameters to the get
 *   call underlying the getList call). These are passed to getList in
 *   $params['params'].
 * @return array
 *   Despite the fact that $request represents a subset of the parameters passed
 *   to getList, the return of this function is merged with the getList params
 *   in their entirety.
 */
function _civicrm_api3_volunteer_project_contact_getlist_defaults(&$request) {
  return array(
    'id_field' => 'contact_id',
    'label_field' => 'contact_id.sort_name',
    'search_field' => 'contact_id.sort_name',
  );
}
