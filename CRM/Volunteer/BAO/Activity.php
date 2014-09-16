<?php

abstract class CRM_Volunteer_BAO_Activity extends CRM_Activity_DAO_Activity {

  /**
   * Get information about the custom Activity table
   *
   * Using the API is preferable to CRM_Core_DAO::getFieldValue as the latter
   * allows specification of only one criteria by which to filter, and the unique
   * index for the table in question is on the "extends" and "name" fields; i.e.,
   * it is possible to have two custom groups with the same name so long as they
   * extend different entities.
   *
   * @return array Keyed with id (custom group/table id) and table_name
   */
  public static function getCustomGroup() {
    $params = array(
      'extends' => 'Activity',
      'is_active' => 1,
      'name' => static::CUSTOM_GROUP_NAME,
      'return' => array('id', 'table_name'),
    );

    $custom_group = civicrm_api3('CustomGroup', 'getsingle', $params);

    unset($custom_group['extends']);
    unset($custom_group['is_active']);
    unset($custom_group['name']);
    return $custom_group;
  }

  /**
   * Get information about the custom Activity fields
   *
   * @return array Multi-dimensional, keyed by lowercased custom field
   * name (i.e., civicrm_custom_group.name). Subarray keyed with id (i.e.,
   * civicrm_custom_group.id), column_name, and data_type.
   */
  public static function getCustomFields () {
    $result = array();

    $custom_group = static::getCustomGroup();

    $params = array(
      'custom_group_id' => $custom_group['id'],
      'is_active' => 1,
      'return' => array('id', 'column_name', 'name', 'data_type'),
    );

    $fields = civicrm_api3('CustomField', 'get', $params);

    if (CRM_Utils_Array::value('count', $fields) < 1) {
      CRM_Core_Error::fatal('CiviVolunteer-defined custom fields appear to be missing (custom field group' . static::CUSTOM_GROUP_NAME . ').');
    }

    foreach ($fields['values'] as $field) {
      $result[strtolower($field['name'])] = array(
        'id' => $field['id'],
        'column_name' => $field['column_name'],
        'data_type' => $field['data_type'],
      );
    }

    return $result;
  }

  /**
   * Fetch activity type id of custom Activity
   * @return integer
   */
  public static function getActivityTypeId() {
    return CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', static::CUSTOM_ACTIVITY_TYPE);
  }
}