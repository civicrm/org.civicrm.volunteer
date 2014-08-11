<?php

/**
 * TODO: This is a quick and dirty solution adapted from
 * CRM/Volunteer/BAO/Assignment.php. There's a lot of overlap in these classes.
 * We should probably create a parent class for both to extend, and we should
 * probably move some of the constants in the Upgrader class to the
 * corresponding Activity classes.
 */
class CRM_Volunteer_BAO_Commendation extends CRM_Activity_DAO_Activity {
  /**
   * Get a list of Commendations matching the params, where each param key is:
   *  1. the key of a field in civicrm_activity, except for activity_type_id
   *  2. the key of a custom field on the activity (volunteer_project_id)
   *  3. the key of a field in civicrm_contact
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  static function retrieve(array $params) {
    $activity_fields = CRM_Activity_DAO_Activity::fields();
    $contact_fields = CRM_Contact_DAO_Contact::fields();
    $custom_fields = self::getCustomFields();

    // This is the "real" id
    $activity_fields['id'] = $activity_fields['activity_id'];
    unset($activity_fields['activity_id']);

    // enforce restrictions on parameters
    $allowed_params = array_flip(array_merge(
      array_keys($activity_fields),
      array_keys($contact_fields),
      array_keys($custom_fields)
    ));
    unset($allowed_params['activity_type_id']);
    $filtered_params = array_intersect_key($params, $allowed_params);

    $custom_group = self::getCustomGroup();
    $customTableName = $custom_group['table_name'];

    foreach ($custom_fields as $name => $field) {
      $selectClause[] = "{$customTableName}.{$field['column_name']} AS {$name}";
    }
    $customSelect = implode(', ', $selectClause);

    $activityContactTypes = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContactTypes);

    $placeholders = array(
      1 => array($targetID, 'Integer'),
      2 => array(self::getActivityTypeId(), 'Integer'),
    );

    $i = count($placeholders) + 1;
    $where = array();
    $whereClause = NULL;
    foreach ($filtered_params as $key => $value) {

      if (CRM_Utils_Array::value($key, $activity_fields)) {
        $dataType = CRM_Utils_Type::typeToString($activity_fields[$key]['type']);
        $fieldName = $activity_fields[$key]['name'];
        $tableName = CRM_Activity_DAO_Activity::$_tableName;
      } elseif (CRM_Utils_Array::value($key, $contact_fields)) {
        $dataType = CRM_Utils_Type::typeToString($contact_fields[$key]['type']);
        $fieldName = $contact_fields[$key]['name'];
        $tableName = CRM_Contact_DAO_Contact::$_tableName;
      } elseif (CRM_Utils_Array::value($key, $custom_fields)) {
        $dataType = $custom_fields[$key]['data_type'];
        $fieldName = $custom_fields[$key]['column_name'];
        $tableName = $customTableName;
      }
      $where[] = "{$tableName}.{$fieldName} = %{$i}";

      $placeholders[$i] = array($value, $dataType);
      $i++;
    }

    if (count($where)) {
      $whereClause = 'AND ' . implode("\nAND ", $where);
    }

    $query = "
      SELECT
        civicrm_activity.*,
        {$customSelect},
        activityContact.contact_id AS volunteer_contact_id,
        volunteer_contact.sort_name AS volunteer_sort_name,
        volunteer_contact.display_name AS volunteer_display_name
      FROM civicrm_activity
      INNER JOIN civicrm_activity_contact activityContact
        ON (
          activityContact.activity_id = civicrm_activity.id
          AND activityContact.record_type_id = %1
        )
      INNER JOIN civicrm_contact volunteer_contact
        ON activityContact.contact_id = volunteer_contact.id
      INNER JOIN {$customTableName}
        ON ({$customTableName}.entity_id = civicrm_activity.id)
      WHERE civicrm_activity.activity_type_id = %2
      {$whereClause}
    ";

    $dao = CRM_Core_DAO::executeQuery($query, $placeholders);
    $rows = array();
    while ($dao->fetch()) {
      $rows[$dao->id] = $dao->toArray();
    }

    return $rows;
  }

  /**
   * Get information about CiviVolunteer's custom Activity table for Commendations
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
      'name' => CRM_Volunteer_Upgrader::commendationCustomGroupName,
      'return' => array('id', 'table_name'),
    );

    $custom_group = civicrm_api3('CustomGroup', 'getsingle', $params);

    if (CRM_Utils_Array::value('is_error', $custom_group) == 1) {
      CRM_Core_Error::fatal("CiviVolunteer's Commendation custom group appears to be missing.");
    }

    unset($custom_group['extends']);
    unset($custom_group['is_active']);
    unset($custom_group['name']);
    return $custom_group;
  }

  /**
   * Get information about CiviVolunteer's custom Commendation fields
   *
   * @return array Multi-dimensional, keyed by lowercased custom field
   * name (i.e., civicrm_custom_group.name). Subarray keyed with id (i.e.,
   * civicrm_custom_group.id), column_name, and data_type.
   */
  public static function getCustomFields () {
    $result = array();

    $custom_group = self::getCustomGroup();

    $params = array(
      'custom_group_id' => $custom_group['id'],
      'is_active' => 1,
      'return' => array('id', 'column_name', 'name', 'data_type'),
    );

    $fields = civicrm_api3('CustomField', 'get', $params);

    if (
      CRM_Utils_Array::value('count', $fields) < 1
    ) {
      CRM_Core_Error::fatal("CiviVolunteer's Commendation custom fields appear to be missing.");
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
   * Fetch activity type id of 'commendation' type activity
   * @return integer
   */
  public static function getActivityTypeId() {
    return CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', CRM_Volunteer_Upgrader::commendationActivityTypeName);
  }
}