<?php

class CRM_Volunteer_BAO_Commendation extends CRM_Volunteer_BAO_Activity {

  const CUSTOM_ACTIVITY_TYPE = 'volunteer_commendation';
  const CUSTOM_GROUP_NAME = 'volunteer_commendation';
  const PROJECT_REF_FIELD_NAME = 'volunteer_project_id';

  protected static $customGroup = array();
  protected static $customFields = array();

  /**
   * create or update a Volunteer Commendation
   *
   * This function is invoked from within the web form layer
   *
   * @param array $params An assoc array of name/value pairs
   *  - aid: activity id of an existing commendation to update
   *  - cid: id of contact to be commended
   *  - vid: id of project for which contact is to be commended
   *  - details: text about the contact's exceptional volunteerism
   * @see self::requiredParamsArePresent for rules re required params
   * @return array Result of api.activity.create
   * @access public
   * @static
   */
  public static function create(array $params) {
    // check required params
    if (!self::requiredParamsArePresent($params)) {
      CRM_Core_Error::fatal('Not enough data to create commendation object.');
    }

    $activity_statuses = CRM_Activity_BAO_Activity::buildOptions('status_id', 'create');
    $api_params = array(
      'activity_type_id' => self::getActivityTypeId(),
      'status_id' => CRM_Utils_Array::key('Completed', $activity_statuses),
    );

    $aid = CRM_Utils_Array::value('aid', $params);
    if ($aid) {
      $api_params['id'] = $aid;
    }

    $cid = CRM_Utils_Array::value('cid', $params);
    if ($cid) {
      $api_params['target_contact_id'] = $cid;
    }

    $vid = CRM_Utils_Array::value('vid', $params);
    if ($vid) {
      $project = CRM_Volunteer_BAO_Project::retrieveByID($vid);
      $api_params['subject'] = ts('Volunteer Commendation for %1', array('1' => $project->title, 'domain' => 'org.civicrm.volunteer'));

      $customFieldSpec = self::getCustomFields();
      $volunteer_project_id_field_name = $customFieldSpec['volunteer_project_id']['custom_n'];
      $api_params[$volunteer_project_id_field_name] = $vid;
    }

    if (array_key_exists('details', $params)) {
      $api_params['details'] = CRM_Utils_Array::value('details', $params);
    }

    return civicrm_api3('Activity', 'create', $api_params);
  }

  /**
   * Check if there is absolute minimum of data to add the object
   *
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return boolean
   * @access public
   */
  private static function requiredParamsArePresent($params) {
    if (
      CRM_Utils_Array::value('aid', $params) || ( // activity id
        CRM_Utils_Array::value('cid', $params) && // contact id
        CRM_Utils_Array::value('vid', $params) // volunteer project id
      )
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get a list of Commendations matching the params, where each param key is:
   *  1. the key of a field in civicrm_activity, except for activity_type_id
   *  2. the key of a custom field on the activity (volunteer_project_id)
   *  3. the key of a field in civicrm_contact
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  public static function retrieve(array $params) {
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
}