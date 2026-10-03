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
    $aid = $params['aid'] ?? NULL;
    if ($aid) {
      $existing = self::retrieve(array('id' => $aid));
      $existing = $existing[$aid] ?? NULL;
      if (!$existing) {
        throw new CRM_Core_Exception(ts('The volunteer commendation does not exist.', array('domain' => 'org.civicrm.volunteer')));
      }
      foreach (array(
        'vid' => 'volunteer_project_id',
        'cid' => 'volunteer_contact_id',
      ) as $paramName => $fieldName) {
        if (!empty($params[$paramName])
          && (int) $params[$paramName] !== (int) $existing[$fieldName]) {
          throw new CRM_Core_Exception(ts('An existing commendation cannot be moved to another project or contact.', array('domain' => 'org.civicrm.volunteer')));
        }
        $params[$paramName] = $existing[$fieldName];
      }
    }

    // check required params
    if (!self::requiredParamsArePresent($params)) {
      throw new CRM_Core_Exception(ts('Not enough data was supplied to create a volunteer commendation.', array('domain' => 'org.civicrm.volunteer')));
    }
    if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $params['vid']);
    }

    $activity_statuses = array_column(
      \Civi::entity('Activity')->getOptions('status_id', $params, FALSE, TRUE) ?? array(),
      'name',
      'id'
    );
    $api_params = array(
      'activity_type_id' => self::getActivityTypeId(),
      'status_id' => CRM_Utils_Array::key('Completed', $activity_statuses),
    );

    if ($aid) {
      $api_params['id'] = $aid;
    }

    $cid = $params['cid'] ?? NULL;
    if ($cid) {
      $api_params['target_contact_id'] = $cid;
    }

    $vid = $params['vid'] ?? NULL;
    if ($vid) {
      $project = CRM_Volunteer_BAO_Project::retrieveByID($vid);
      $api_params['subject'] = ts('Volunteer Commendation for %1', array('1' => $project->title, 'domain' => 'org.civicrm.volunteer'));

      // A commendation is a volunteer activity in the same sense an assignment
      // is, so it belongs to the project's campaign for the same reason.
      // Without this, half of what CiviVolunteer records is invisible to
      // campaign reporting.
      //
      // No isEnabled('CiviCampaign') guard, deliberately: the guards elsewhere
      // in this extension exist because \Civi\Api4\Campaign is a class that
      // does not exist when the component is off, and writing a column value
      // reaches no such class. Guarding here would also have made commendations
      // and assignments disagree, since
      // CRM_Volunteer_BAO_Assignment::setActivityDefaults() sets the same field
      // unconditionally. Empty string rather than NULL mirrors it too.
      $api_params['campaign_id'] = empty($project->campaign_id) ? '' : $project->campaign_id;

      $customFieldSpec = self::getCustomFields();
      $projectField = $customFieldSpec['volunteer_project_id'];
      $api_params[self::CUSTOM_GROUP_NAME . '.' . $projectField['name']] = $vid;
    }

    if (array_key_exists('details', $params)) {
      $api_params['details'] = $params['details'] ?? NULL;
    }

    if (!$aid) {
      // APIv3's Activity.create filled this from `user_contact_id`; API4
      // requires it outright. Fall back to the commended volunteer when there
      // is no session contact.
      $api_params['source_contact_id'] = CRM_Core_Session::getLoggedInContactID() ?: $cid;
    }

    // Project authorization above is more specific than core's broad activity
    // permission and applies to both create and update.
    if (!empty($api_params['id'])) {
      $activityId = (int) $api_params['id'];
      unset($api_params['id']);
      return \Civi\Api4\Activity::update(FALSE)
        ->addWhere('id', '=', $activityId)
        ->setValues($api_params)
        ->execute()
        ->single();
    }
    return \Civi\Api4\Activity::create(FALSE)
      ->setValues($api_params)
      ->execute()
      ->single();
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
      !empty($params['aid']) || ( // activity id
        !empty($params['cid']) && // contact id
        !empty($params['vid']) // volunteer project id
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

    $selectClause = array();
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

      if (!empty($activity_fields[$key])) {
        $dataType = CRM_Utils_Type::typeToString($activity_fields[$key]['type']);
        $fieldName = $activity_fields[$key]['name'];
        $tableName = CRM_Activity_DAO_Activity::getTableName();
      } elseif (!empty($contact_fields[$key])) {
        $dataType = CRM_Utils_Type::typeToString($contact_fields[$key]['type']);
        $fieldName = $contact_fields[$key]['name'];
        $tableName = CRM_Contact_DAO_Contact::getTableName();
      } elseif (!empty($custom_fields[$key])) {
        $dataType = $custom_fields[$key]['data_type'];
        $fieldName = $custom_fields[$key]['column_name'];
        $tableName = $customTableName;
      }
      else {
        continue;
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
