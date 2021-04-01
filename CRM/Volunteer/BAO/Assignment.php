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
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

class CRM_Volunteer_BAO_Assignment extends CRM_Volunteer_BAO_Activity {

  const CUSTOM_ACTIVITY_TYPE = 'Volunteer';
  const CUSTOM_GROUP_NAME = 'CiviVolunteer';
  const ROLE_OPTION_GROUP = 'volunteer_role';

  protected static $customGroup = array();
  protected static $customFields = array();

  public $volunteer_need_id;
  public $time_scheduled;
  public $time_completed;

  /**
   * Get a list of Assignments matching the params, where each param key is:
   *  1. the key of a field in civicrm_activity
   *     except for activity_type_id and activity_duration
   *  2. the key of a custom field on the activity
   *     (volunteer_need_id, time_scheduled, time_completed)
   *  3. the key of a field in civicrm_contact
   *  4. project_id
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  public static function retrieve(array $params) {
    $activity_fields = CRM_Activity_DAO_Activity::fields();
    $contact_fields = CRM_Contact_DAO_Contact::fields();
    $custom_fields = self::getCustomFields();
    $foreign_fields = array(
      'project_id',
      'target_contact_id',
      'assignee_contact_id',
    );

    // This is the "real" id
    $activity_fields['id'] = $activity_fields['activity_id'];
    unset($activity_fields['activity_id']);

    // enforce restrictions on parameters
    $allowed_params = array_flip(array_merge(
      array_keys($activity_fields),
      array_keys($contact_fields),
      array_keys($custom_fields),
      $foreign_fields
    ));
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
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContactTypes);

    $volunteerStatus = CRM_Activity_BAO_Activity::buildOptions('status_id', 'validate');
    $available =  CRM_Utils_Array::key('Available', $volunteerStatus);
    $scheduled =  CRM_Utils_Array::key('Scheduled', $volunteerStatus);

    $placeholders = array(
      1 => array($assigneeID, 'Integer'),
      2 => array(self::getActivityTypeId(), 'Integer'),
      3 => array($scheduled, 'Integer'),
      4 => array($available, 'Integer'),
      5 => array($targetID, 'Integer'),
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
      } elseif($key == 'project_id') {
        $dataType = 'Int';
        $fieldName = 'id';
        $tableName = CRM_Volunteer_DAO_Project::$_tableName;
      } elseif ($key == 'target_contact_id') {
        $dataType = 'Int';
        $fieldName = 'contact_id';
        $tableName = 'tgt'; // this is an alias for civicrm_activity_contact
      } elseif ($key == 'assignee_contact_id') {
        $dataType = 'Int';
        $fieldName = 'contact_id';
        $tableName = 'assignee'; // this is an alias for civicrm_activity_contact
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
        assignee.contact_id AS assignee_contact_id,
        {$customSelect},
        civicrm_volunteer_need.start_time,
        civicrm_volunteer_need.is_flexible,
        civicrm_volunteer_need.role_id,
        assignee_contact.sort_name AS assignee_sort_name,
        assignee_contact.display_name AS assignee_display_name,
        assignee_phone.phone AS assignee_phone,
        assignee_phone.phone_ext AS assignee_phone_ext,
        assignee_email.email AS assignee_email,
        -- begin target contact fields
        tgt.contact_id AS target_contact_id,
        tgt_contact.sort_name AS target_sort_name,
        tgt_contact.display_name AS target_display_name,
        tgt_phone.phone AS target_phone,
        tgt_phone.phone_ext AS target_phone_ext,
        tgt_email.email AS target_email
        -- end target contact fields
      FROM civicrm_activity
      INNER JOIN civicrm_activity_contact assignee
        ON (
          assignee.activity_id = civicrm_activity.id
          AND assignee.record_type_id = %1
        )
      INNER JOIN civicrm_contact assignee_contact
        ON assignee.contact_id = assignee_contact.id
      LEFT JOIN civicrm_email assignee_email
        ON assignee_email.contact_id = assignee_contact.id AND assignee_email.is_primary = 1
      LEFT JOIN civicrm_phone assignee_phone
        ON assignee_phone.contact_id = assignee_contact.id AND assignee_phone.is_primary = 1
      -- begin target contact joins
      LEFT JOIN civicrm_activity_contact tgt
        ON (
          tgt.activity_id = civicrm_activity.id
          AND tgt.record_type_id = %5
        )
      LEFT JOIN civicrm_contact tgt_contact
        ON tgt.contact_id = tgt_contact.id
      LEFT JOIN civicrm_email tgt_email
        ON tgt_email.contact_id = tgt_contact.id AND tgt_email.is_primary = 1
      LEFT JOIN civicrm_phone tgt_phone
        ON tgt_phone.contact_id = tgt_contact.id AND tgt_phone.is_primary = 1
      -- end target contact joins
      INNER JOIN {$customTableName}
        ON ({$customTableName}.entity_id = civicrm_activity.id)
      INNER JOIN civicrm_volunteer_need
        ON (civicrm_volunteer_need.id = {$customTableName}.{$custom_fields['volunteer_need_id']['column_name']})
      INNER JOIN civicrm_volunteer_project
        ON (civicrm_volunteer_project.id = civicrm_volunteer_need.project_id)
      WHERE civicrm_activity.activity_type_id = %2
      AND civicrm_activity.status_id IN (%3, %4 )
      {$whereClause}
    ";

    $dao = CRM_Core_DAO::executeQuery($query, $placeholders);
    $rows = array();
    while ($dao->fetch()) {
      $rows[$dao->id] = $dao->toArray();
    }

    /*
     * For clarity we want the fields associated with each contact prefixed with
     * the contact type (e.g., target_phone). For backwards compatibility,
     * however, we want the fields associated with each assignee contact to be
     * accessible sans prefix. Eventually we should deprecate the non-prefixed
     * field names.
     */
    foreach ($rows as $id => $fields) {
      foreach ($fields as $key => $value) {
        if (substr($key, 0, 9) == 'assignee_') {
          $rows[$id][substr($key, 9)] = $value;
        }
      }
    }

    return $rows;
  }

  /**
   * Set default values for the Activity about to be created/updated.
   *
   * Called from self::createVolunteerActivity(), which checks for the existence
   * of necessary params; thus, no such checks are performed here.
   *
   * @param array $params
   *   @see self::createVolunteerActivity()
   * @return array
   *   Default parameters to use for api.activity.create
   */

  private static function setActivityDefaults(array $params) {
    $defaults = array();
    $op = empty($params['id']) ? CRM_Core_Action::ADD : CRM_Core_Action::UPDATE;

    $need = civicrm_api3('volunteer_need', 'getsingle', array(
      'id' => $params['volunteer_need_id'],
    ));
    $project = CRM_Volunteer_BAO_Project::retrieveByID($need['project_id']);
   
    //add Volunteer project description to activity details for context
    $defaults['details'] = CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Project', $project->id, 'description');
   
    $defaults['campaign_id'] = $project ? $project->campaign_id : '';
    // Force NULL campaign ids to be empty strings, since the API ignores NULL values.
    if (empty($defaults['campaign_id'])) {
      $defaults['campaign_id'] = '';
    }
    if (empty($params['volunteer_role_id'])) {
      $defaults['volunteer_role_id'] = CRM_Utils_Array::value('role_id', $need, 'null');
    }
    if ($op === CRM_Core_Action::ADD) {
      $defaults['time_scheduled_minutes'] = CRM_Utils_Array::value('duration', $need);
      $defaults['target_contact_id'] = CRM_Volunteer_BAO_Project::getContactsByRelationship($project->id, 'volunteer_beneficiary');

      // If the related entity doesn't provide a good default, use tomorrow.
      if (empty($params['activity_date_time'])) {
        $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
        $defaults['activity_date_time'] = CRM_Utils_Array::value('start_time', $project->getEntityAttributes(), $tomorrow);
      }

      if (empty($params['subject'])) {
        $defaults['subject'] = $project->title;
      }
    }

    return $defaults;
  }

  /**
   * Creates a volunteer activity.
   *
   * Wrapper around activity create API. Volunteer field names are translated
   * to the custom_n format expected by the API.
   *
   * @param array $params
   *   An assoc array of name/value pairs. Either id or volunteer_need_id
   *   is required in the params array.
   * @return mixed
   *   Boolean FALSE on failure; activity_id on success.
   */
  public static function createVolunteerActivity(array $params) {
    if (empty($params['id']) && empty($params['volunteer_need_id'])) {
      CRM_Core_Error::fatal('Mandatory key missing from params array: id or volunteer_need_id');
    }

    // These values are always derived from the associated Project; @see self::setActivityDefaults()
    unset($params['campaign_id'], $params['target_contact_id']);
    // Prevent activity type from being changed externally.
    $params['activity_type_id'] = self::getActivityTypeId();

    if (empty($params['volunteer_need_id'])) {
      $params['volunteer_need_id'] = civicrm_api3('VolunteerAssignment', 'getvalue', array(
        'id' => $params['id'],
        'return' => "volunteer_need_id",
      ));
    }

    $defaults = self::setActivityDefaults($params);
    $params = array_merge($defaults, $params);

    // Might as well sync these, but seems redundant
    if (!isset($params['duration']) && isset($params['time_completed_minutes'])) {
      $params['duration'] = $params['time_completed_minutes'];
    }

    // Format custom fields to update the api correctly.
    foreach(self::getCustomFields() as $fieldName => $field) {
      if (isset($params[$fieldName])) {
        $params['custom_' . $field['id']] = $params[$fieldName];
        unset($params[$fieldName]);
      }
    }

    $activity = civicrm_api3('Activity', 'create', $params);
    return empty($activity['id']) ? FALSE : $activity['id'];
  }

}
