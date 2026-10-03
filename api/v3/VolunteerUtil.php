<?php

/**
 * This file is used to collect util API functions not related to any particular
 * entity. The implementations live in Civi\Api4\VolunteerUtil and
 * CRM_Volunteer_BAO_VolunteerUtil; these APIv3 wrappers exist only for
 * backward compatibility and are deprecated.
 */

/**
 * @deprecated api notice
 * @return array
 *   Array of deprecated actions
 */
function _civicrm_api3_volunteer_util_deprecation() {
  return array(
    'getperms' => 'The "getperms" action is deprecated. Use VolunteerUtil.getPermissions API4 action instead.',
    'getprofiles' => 'The "getprofiles" action is deprecated. Use VolunteerUtil.getProfiles API4 action instead.',
    'getsupportingdata' => 'The "getsupportingdata" action is deprecated. Use VolunteerUtil.getSupportingData API4 action instead.',
    'getbeneficiaries' => 'The "getbeneficiaries" action is deprecated. Use the VolunteerProjectContact and Contact API4 entities instead.',
    'getcountries' => 'The "getcountries" action is deprecated. Use VolunteerUtil.getCountries API4 action instead.',
    'getcustomfields' => 'The "getcustomfields" action is deprecated. Use VolunteerUtil.getCustomFields API4 action instead.',
  );
}

/**
 * This function returns the permissions defined by the volunteer extension.
 *
 * @deprecated Use the VolunteerUtil.getPermissions API4 action.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 */
function civicrm_api3_volunteer_util_getperms($params) {
  $results = \Civi\Api4\VolunteerUtil::getPermissions(CRM_Volunteer_Permission::shouldCheckPermissions($params))
    ->execute()
    ->getArrayCopy();

  return civicrm_api3_create_success($results, $params, 'VolunteerUtil', 'getperms');
}

function _civicrm_api3_volunteer_util_getprofiles_spec(&$params) {
  $params['profile_ids'] = array(
    'title' => 'Selected profile IDs',
    'description' => 'Comma-separated profile IDs which must be returned even when inactive or missing.',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  );
}

/**
 * Return the limited profile metadata required by the project editor.
 *
 * @deprecated Use the VolunteerUtil.getProfiles API4 action.
 *
 * @param array $params
 * @return array
 * @throws API_Exception
 */
function civicrm_api3_volunteer_util_getprofiles($params) {
  $selectedIds = preg_split('/\s*,\s*/', (string) ($params['profile_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

  $results = \Civi\Api4\VolunteerUtil::getProfiles(CRM_Volunteer_Permission::shouldCheckPermissions($params))
    ->setProfileIds($selectedIds ?: [])
    ->execute()
    ->first();

  return civicrm_api3_create_success($results, $params, 'VolunteerUtil', 'getprofiles');
}

function _civicrm_api3_volunteer_util_getsupportingdata_spec(&$params) {
  $params['controller'] = array(
    'title' => 'Controller',
    'description' => 'For which Angular controller is supporting data required?',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  );
}

/**
 * This function returns supporting data for various JavaScript-driven interfaces.
 *
 * @deprecated Use the VolunteerUtil.getSupportingData API4 action.
 *
 * @param array $params
 *   @see _civicrm_api3_volunteer_util_getsupportingdata_spec()
 * @return array
 */
function civicrm_api3_volunteer_util_getsupportingdata($params) {
  $results = \Civi\Api4\VolunteerUtil::getSupportingData(CRM_Volunteer_Permission::shouldCheckPermissions($params))
    ->setController((string) ($params['controller'] ?? ''))
    ->execute()
    ->first();

  return civicrm_api3_create_success($results, $params, 'VolunteerUtil', 'getsupportingdata');
}

/**
 * This method returns a list of beneficiaries
 *
 * @deprecated since version 2.3
 *   api.VolunteerProjectContacts.getList serves the same purpose and is both
 *   more efficient more versatile.
 *
 * No API4 replacement is planned for this action: consumers should read
 * VolunteerProjectContact (relationship volunteer_beneficiary) and resolve
 * display names through Contact. It is retained here, API4-based, for
 * backward compatibility only.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 */
function civicrm_api3_volunteer_util_getbeneficiaries($params) {
  if (!CRM_Volunteer_Permission::check('edit all volunteer projects')
    && !CRM_Volunteer_Permission::check('edit own volunteer projects')) {
    throw new API_Exception(ts('You do not have permission to manage volunteer projects.', array('domain' => 'org.civicrm.volunteer')), 403);
  }

  $beneficiaryType = CRM_Core_PseudoConstant::getKey(
    'CRM_Volunteer_BAO_ProjectContact',
    'relationship_type_id',
    'volunteer_beneficiary'
  );
  $sql = 'SELECT DISTINCT beneficiary.contact_id
    FROM civicrm_volunteer_project_contact beneficiary';
  $queryParams = array(1 => array((int) $beneficiaryType, 'Integer'));
  if (!CRM_Volunteer_Permission::check('edit all volunteer projects')) {
    $ownerType = CRM_Core_PseudoConstant::getKey(
      'CRM_Volunteer_BAO_ProjectContact',
      'relationship_type_id',
      'volunteer_owner'
    );
    $sql .= ' INNER JOIN civicrm_volunteer_project_contact owner
      ON owner.project_id = beneficiary.project_id
      AND owner.relationship_type_id = %2
      AND owner.contact_id = %3';
    $queryParams[2] = array((int) $ownerType, 'Integer');
    $queryParams[3] = array((int) CRM_Core_Session::getLoggedInContactID(), 'Integer');
  }
  $sql .= ' WHERE beneficiary.relationship_type_id = %1';

  $contactIds = array();
  $dao = CRM_Core_DAO::executeQuery($sql, $queryParams);
  while ($dao->fetch()) {
    $contactIds[] = (int) $dao->contact_id;
  }
  if (!$contactIds) {
    return civicrm_api3_create_success(array(), $params, 'VolunteerUtil', 'getbeneficiaries');
  }

  $contacts = \Civi\Api4\Contact::get(FALSE)
    ->addSelect('id', 'display_name')
    ->addWhere('id', 'IN', $contactIds)
    ->addOrderBy('sort_name', 'ASC')
    ->execute();
  $values = array();
  foreach ($contacts as $contact) {
    $values[(int) $contact['id']] = array(
      'contact_id' => (int) $contact['id'],
      'display_name' => $contact['display_name'],
    );
  }

  return civicrm_api3_create_success($values, $params, 'VolunteerUtil', 'getbeneficiaries');
}

/**
 * This function returns the enabled countries in CiviCRM.
 *
 * @deprecated Use the VolunteerUtil.getCountries API4 action.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 */
function civicrm_api3_volunteer_util_getcountries($params) {
  $results = array();
  $countries = \Civi\Api4\VolunteerUtil::getCountries(CRM_Volunteer_Permission::shouldCheckPermissions($params))
    ->execute();
  // APIv3 returned countries keyed by ID.
  foreach ($countries as $country) {
    $results[(int) $country['id']] = $country;
  }

  return civicrm_api3_create_success($results, $params, 'VolunteerUtil', 'getcountries');
}

/**
 * This function returns the active, searchable custom fields in the
 * Volunteer_Information custom field group.
 *
 * @deprecated Use the VolunteerUtil.getCustomFields API4 action.
 *
 * @param array $params
 *   Not presently used.
 * @return array
 */
function civicrm_api3_volunteer_util_getcustomfields($params) {
  $results = \Civi\Api4\VolunteerUtil::getCustomFields(CRM_Volunteer_Permission::shouldCheckPermissions($params))
    ->execute()
    ->getArrayCopy();

  return civicrm_api3_create_success($results, $params, 'VolunteerUtil', 'getcustomfields');
}
