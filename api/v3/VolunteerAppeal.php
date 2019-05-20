<?php
use CRM_Volunteer_ExtensionUtil as E;

/**
 * VolunteerAppeal.create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_volunteer_appeal_create_spec(&$spec) {
  // $spec['some_parameter']['api.required'] = 1;
}

/**
 * VolunteerAppeal.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_volunteer_appeal_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * VolunteerAppeal.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_volunteer_appeal_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * VolunteerAppeal.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_volunteer_appeal_get($params) { 
	//$params["check_permissions"] = 0;	
	$result = CRM_Volunteer_BAO_VolunteerAppeal::retrieve($params);
  foreach ($result as $k => $bao) {  	
    $result[$k] = $bao->toArray();
    $result[$k]['entity_attributes'] = $bao->getEntityAttributes();
  }	
  return civicrm_api3_create_success($result, $params, 'VolunteerAppeal', 'get');
}
