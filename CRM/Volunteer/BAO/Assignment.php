<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
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
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

class CRM_Volunteer_BAO_Assignment extends CRM_Activity_DAO_Activity {

  public $volunteer_need_id;
  public $time_scheduled;
  public $time_completed;

  /**
   * class constructor
   */
  function __construct() {
    parent::__construct();
  }

  /**
   * Get a list of Assignments matching the params, where each param key is:
   *  1. the key of a field in civicrm_activity (e.g., activity_id, not id),
   *     except for activity_type_id and activity_duration
   *  2. the key of a custom field on the activity
   *     (volunteer_need_id, time_scheduled, time_completed);
   *  3. project_id
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  static function retrieve(array $params) {
    $activity_fields = CRM_Activity_DAO_Activity::fields();
    $custom_fields = self::getCustomFields();
    $foreign_fields = array('project_id');

    // enforce restrictions on parameters
    $allowed_params = array_merge(array_keys($activity_fields), array_keys($custom_fields), $foreign_fields);
    $allowed_params = array_flip($allowed_params);
    unset($allowed_params['activity_type_id']);
    unset($allowed_params['activity_duration']);
    $filtered_params = array_intersect_key($params, $allowed_params);

    $custom_group = self::getCustomGroup();
    $customTableName = $custom_group['table_name'];

    foreach ($custom_fields as $name => $field) {
      $selectClause[] = "{$customTableName}.{$field['column_name']} AS {$name}";
    }
    $customSelect = implode(', ', $selectClause);

    $activityContactTypes = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $assigneeID = CRM_Utils_Array::key('Activity Assignees', $activityContactTypes);

    $volunteerStatus = CRM_Activity_BAO_Activity::buildOptions('status_id', 'validate');
    $available =  CRM_Utils_Array::key('Available', $volunteerStatus);
    $scheduled =  CRM_Utils_Array::key('Scheduled', $volunteerStatus);

    $placeholders = array(
      1 => array($assigneeID, 'Integer'),
      2 => array(self::volunteerActivityTypeId(), 'Integer'),
      3 => array($scheduled, 'Integer'),
      4 => array($available, 'Integer'),
    );

    $i = count($placeholders) + 1;
    $where = array();
    foreach ($filtered_params as $key => $value) {

      if (CRM_Utils_Array::value($key, $activity_fields)) {
        $dataType = CRM_Utils_Type::typeToString($activity_fields[$key]['type']);
        $fieldName = $activity_fields[$key]['name'];
        $tableName = CRM_Activity_DAO_Activity::$_tableName;
      } elseif (CRM_Utils_Array::value($key, $custom_fields)) {
        $dataType = $custom_fields[$key]['data_type'];
        $fieldName = $key;
        $tableName = $customTableName;
      } else { // this would be project_id
        $dataType = 'Int';
        $fieldName = 'id';
        $tableName = CRM_Volunteer_DAO_Project::$_tableName;
      }
      $where[] = "{$tableName}.{$fieldName} = %{$i}";

      $placeholders[$i] = array($value, $dataType);
      $i++;
    }
    $where = implode("\nAND ", $where);

    $query = "
      SELECT civicrm_activity_contact.contact_id,
        civicrm_activity.status_id,
        {$customTableName}.entity_id AS activity_id,
        {$customSelect},
        civicrm_volunteer_need.start_time,
        civicrm_volunteer_need.is_flexible,
        civicrm_volunteer_need.role_id
      FROM civicrm_activity
      LEFT JOIN civicrm_activity_contact
        ON (
          civicrm_activity_contact.activity_id = civicrm_activity.id
          AND civicrm_activity_contact.record_type_id = %1
        )
      LEFT JOIN {$customTableName}
        ON ({$customTableName}.entity_id = civicrm_activity.id)
      LEFT JOIN civicrm_volunteer_need
        ON (civicrm_volunteer_need.id = {$customTableName}.{$custom_fields['volunteer_need_id']['column_name']})
      LEFT JOIN civicrm_volunteer_project
        ON (civicrm_volunteer_project.id = civicrm_volunteer_need.project_id)
      WHERE civicrm_activity.activity_type_id = %2
      AND civicrm_activity.status_id IN (%3, %4 )
      AND {$where}
    ";

    $dao = CRM_Core_DAO::executeQuery($query, $placeholders);
    $rows = array();
    while ($dao->fetch()) {
      $rows[] = clone $dao;
    }
    return $rows;
  }

  /**
   * Get information about CiviVolunteer's custom Activity table
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
      'name' => CRM_Volunteer_Upgrader::customGroupName,
      'return' => array('id', 'table_name'),
      'version' => 3
    );

    $custom_group = civicrm_api('CustomGroup', 'getsingle', $params);

    if (CRM_Utils_Array::value('is_error', $custom_group) == 1) {
      CRM_Core_Error::fatal('CiviVolunteer custom group appears to be missing.');
    }

    unset($custom_group['extends']);
    unset($custom_group['is_active']);
    unset($custom_group['name']);
    return $custom_group;
  }

  /**
   * Get information about CiviVolunteer's custom Activity fields
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
      'version' => 3,
    );

    $fields = civicrm_api('CustomField', 'get', $params);

    if (
      CRM_Utils_Array::value('is_error', $fields) == 1 ||
      CRM_Utils_Array::value('count', $fields) < 1
    ) {
      CRM_Core_Error::fatal('CiviVolunteer custom fields appear to be missing.');
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

  public static function createVolunteerActivity($volunteer) {
    $params = $volunteer['params'];
    $volunteerCustom = $volunteer['custom'];

    $customFields = self::getCustomFields();
    $params['activity_type_id'] = self::volunteerActivityTypeId();

    foreach ($volunteerCustom as $field => $val) {
      $id = $customFields[$field]['id'];
      $params['custom_' . $id] = $val;
    }

    $result = civicrm_api('Activity', 'create', $params);

  }

  /**
   * Fetch activity type id of 'volunteer' type activity
   * @return integer
   */
  public static function volunteerActivityTypeId() {
    $activityTypes = CRM_Activity_BAO_Activity::buildOptions('activity_type_id', 'validate');
    return CRM_Utils_Array::key(CRM_Volunteer_Upgrader::customActivityTypeName, $activityTypes);
  }
}
