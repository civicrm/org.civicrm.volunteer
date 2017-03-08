<?php

class CRM_Volunteer_APIWrapper_CustomField implements API_Wrapper {

  /**
   * Interpret and alter the API request as needed.
   *
   * @param array $apiRequest
   */
  public function fromApiInput($apiRequest) {
    // nothing to do here
    return $apiRequest;
  }

  /**
   * Modify the API result before returning it.
   *
   * If the request's action is whitelisted and 'custom' is specified as a
   * return, add all custom field values to the result.
   *
   * @param array $apiRequest
   * @param array $result
   */
  public function toApiOutput($apiRequest, $result) {
    // For now let's whitelist the actions we wish to support.
    $whitelist = array(
      'get',
      // 'getsingle', // supported by 'get' since 'getsingle' is a wrapper
    );

    $requestedReturn = (array) CRM_Utils_Array::value('return', $apiRequest['params']);

    if (in_array($apiRequest['action'], $whitelist) && in_array('custom', $requestedReturn)) {
      foreach ($result['values'] as &$item) {
        $customData = CRM_Core_BAO_CustomValueTable::getEntityValues($item['id'], $apiRequest['entity']);
        foreach ($customData as $customFieldId => $customFieldValue) {
          $key = 'custom_' . $customFieldId;
          $item[$key] = $customFieldValue;
        }
      }
    }

    return $result;
  }

}
