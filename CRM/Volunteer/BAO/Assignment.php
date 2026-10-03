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
   * Count every volunteer assignment belonging to a project, in any status.
   *
   * retrieve() deliberately restricts to Scheduled and Available because those
   * are the statuses that consume a need's capacity. Callers deciding whether
   * destroying a project would discard volunteer history need the opposite:
   * Completed and No-show assignments are exactly the records worth protecting.
   *
   * @param int $projectId
   *
   * @return int
   */
  public static function getProjectAssignmentCount($projectId) {
    $projectId = (int) $projectId;
    if ($projectId < 1) {
      return 0;
    }
    $customGroup = CRM_Volunteer_BAO_Assignment::getCustomGroup();
    $customFields = CRM_Volunteer_BAO_Assignment::getCustomFields();
    $needColumn = $customFields['volunteer_need_id']['column_name'];

    return (int) CRM_Core_DAO::singleValueQuery(
      sprintf(
        'SELECT COUNT(*)
           FROM `%s` cv
           INNER JOIN civicrm_activity a ON a.id = cv.entity_id
           INNER JOIN civicrm_volunteer_need n ON n.id = cv.`%s`
          WHERE n.project_id = %%1
            AND a.activity_type_id = %%2
            AND a.is_deleted = 0',
        $customGroup['table_name'],
        $needColumn
      ),
      array(
        1 => array($projectId, 'Integer'),
        2 => array(CRM_Volunteer_BAO_Assignment::getActivityTypeId(), 'Integer'),
      )
    );
  }

  /**
   * Activity IDs of every non-deleted volunteer assignment for a project.
   *
   * Deliberately status-agnostic, for the same reason getProjectAssignmentCount()
   * is: callers that re-derive project-owned activity values must reach
   * Completed, No-show and Cancelled rows too. retrieve() cannot serve them --
   * it is restricted to the capacity-consuming statuses -- and
   * retrieveAllStatuses() decorates every row with contact-permission and
   * display data that an ID list has no use for.
   *
   * @param int $projectId
   * @return int[]
   */
  public static function getProjectAssignmentIds($projectId) {
    $projectId = (int) $projectId;
    if ($projectId < 1) {
      return array();
    }
    $customGroup = self::getCustomGroup();
    $customFields = self::getCustomFields();
    $needColumn = $customFields['volunteer_need_id']['column_name'];

    $dao = CRM_Core_DAO::executeQuery(
      sprintf(
        'SELECT a.id
           FROM `%s` cv
           INNER JOIN civicrm_activity a ON a.id = cv.entity_id
           INNER JOIN civicrm_volunteer_need n ON n.id = cv.`%s`
          WHERE n.project_id = %%1
            AND a.activity_type_id = %%2
            AND a.is_deleted = 0
          ORDER BY a.id',
        $customGroup['table_name'],
        $needColumn
      ),
      array(
        1 => array($projectId, 'Integer'),
        2 => array(self::getActivityTypeId(), 'Integer'),
      )
    );
    $ids = array();
    while ($dao->fetch()) {
      $ids[] = (int) $dao->id;
    }

    return $ids;
  }

  /**
   * Retrieve every non-deleted volunteer assignment for a project.
   *
   * Unlike retrieve(), this deliberately includes Completed, No-show and any
   * other activity status. It backs reporting and attendance correction, not
   * capacity decisions.
   *
   * @param int $projectId
   * @param int|null $needId
   *
   * @return array<int, array<string, mixed>>
   */
  public static function retrieveAllStatuses($projectId, $needId = NULL) {
    $projectId = (int) $projectId;
    $needId = $needId === NULL ? NULL : (int) $needId;
    if ($projectId < 1) {
      return array();
    }

    $customGroup = self::getCustomGroup();
    $customFields = self::getCustomFields();
    $customTable = $customGroup['table_name'];
    $needColumn = $customFields['volunteer_need_id']['column_name'];
    $scheduledColumn = $customFields['time_scheduled_minutes']['column_name'];
    $completedColumn = $customFields['time_completed_minutes']['column_name'];
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $assigneeTypeId = CRM_Utils_Array::key('Activity Assignees', $activityContacts);

    $whereNeed = '';
    $params = array(
      1 => array($assigneeTypeId, 'Integer'),
      2 => array(self::getActivityTypeId(), 'Integer'),
      3 => array($projectId, 'Integer'),
    );
    if ($needId !== NULL) {
      $whereNeed = ' AND n.id = %4';
      $params[4] = array($needId, 'Integer');
    }

    $query = sprintf(
      'SELECT
         a.id,
         a.status_id,
         a.details,
         a.activity_date_time,
         a.duration AS activity_duration,
         assignee.contact_id AS assignee_contact_id,
         contact.sort_name AS assignee_sort_name,
         contact.display_name AS assignee_display_name,
         email.email AS assignee_email,
         phone.phone AS assignee_phone,
         phone.phone_ext AS assignee_phone_ext,
         cv.`%s` AS volunteer_need_id,
         cv.`%s` AS time_scheduled_minutes,
         cv.`%s` AS time_completed_minutes,
         n.start_time,
         n.end_time,
         n.duration,
         n.is_flexible,
         n.role_id,
         n.quantity,
         n.is_active,
         n.visibility_id,
         n.project_id
       FROM civicrm_activity a
       INNER JOIN civicrm_activity_contact assignee
         ON assignee.activity_id = a.id AND assignee.record_type_id = %%1
       INNER JOIN civicrm_contact contact ON contact.id = assignee.contact_id
       LEFT JOIN civicrm_email email
         ON email.contact_id = contact.id AND email.is_primary = 1
       LEFT JOIN civicrm_phone phone
         ON phone.contact_id = contact.id AND phone.is_primary = 1
       INNER JOIN `%s` cv ON cv.entity_id = a.id
       INNER JOIN civicrm_volunteer_need n ON n.id = cv.`%s`
       WHERE a.activity_type_id = %%2
         AND a.is_deleted = 0
         AND n.project_id = %%3%s
       ORDER BY n.start_time, contact.sort_name, a.id',
      $needColumn,
      $scheduledColumn,
      $completedColumn,
      $customTable,
      $needColumn,
      $whereNeed
    );

    $dao = CRM_Core_DAO::executeQuery($query, $params);
    $rows = array();
    while ($dao->fetch()) {
      $rows[(int) $dao->id] = $dao->toArray();
    }

    $rows = CRM_Volunteer_BAO_Need::addDisplayFields($rows);
    $statusOptions = self::getStatusOptions();
    $viewableContactIds = array();
    $contactIds = array_values(array_unique(array_map(
      'intval',
      array_column($rows, 'assignee_contact_id')
    )));
    if ($contactIds) {
      $viewableContactIds = array_fill_keys(
        CRM_Contact_BAO_Contact_Permission::allowList($contactIds, CRM_Core_Permission::VIEW),
        TRUE
      );
    }

    foreach ($rows as &$row) {
      $status = $statusOptions[(int) $row['status_id']] ?? array();
      $row['status_name'] = $status['name'] ?? (string) $row['status_id'];
      $row['status_label'] = $status['label'] ?? $row['status_name'];
      if ($row['status_name'] === 'Completed') {
        $row['status_label'] = ts('Attended', array('domain' => 'org.civicrm.volunteer'));
      }
      $row['can_view_contact'] = !empty($viewableContactIds[(int) $row['assignee_contact_id']]);
      $row['is_past'] = self::assignmentIsPast($row);
      // DAO::toArray() renders a SQL NULL as '', which would make an unset
      // duration display as "0 hrs" instead of "-" and makes "no hours
      // recorded" indistinguishable from zero hours recorded.
      foreach (array('time_scheduled_minutes', 'time_completed_minutes') as $minuteField) {
        $row[$minuteField] = ($row[$minuteField] === NULL || $row[$minuteField] === '')
          ? NULL
          : (int) $row[$minuteField];
      }
    }
    unset($row);

    return $rows;
  }

  /**
   * Filled and total shift capacity for a project, across every status.
   *
   * The workflow header counts a spot as filled for as long as somebody holds
   * it, so an Attended volunteer still occupies their shift. Countable needs
   * match the client-side rules in volWorkflow.summarize(): non-flexible,
   * active, and carrying a finite quantity.
   *
   * @param int $projectId
   * @param bool $checkPermissions
   *
   * @return array<string, mixed>
   */
  public static function getCapacitySummary($projectId, $checkPermissions = TRUE) {
    $projectId = (int) $projectId;
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Volunteer_Permission::VIEW_ROSTER, $projectId);
    }

    $needs = CRM_Core_DAO::executeQuery(
      'SELECT id, quantity FROM civicrm_volunteer_need
        WHERE project_id = %1 AND is_flexible = 0 AND is_active = 1
          AND quantity IS NOT NULL AND quantity > 0',
      array(1 => array($projectId, 'Integer'))
    );
    $total = 0;
    $byNeed = array();
    while ($needs->fetch()) {
      $byNeed[(int) $needs->id] = 0;
      $total += (int) $needs->quantity;
    }

    $filled = 0;
    foreach (self::retrieveAllStatuses($projectId) as $row) {
      $needId = (int) ($row['volunteer_need_id'] ?? 0);
      if (!array_key_exists($needId, $byNeed)) {
        continue;
      }
      $byNeed[$needId]++;
      $filled++;
    }

    return array(
      'project_id' => $projectId,
      'filled' => $filled,
      'total' => $total,
      'by_need' => $byNeed,
    );
  }

  /**
   * Workflow roster data, including historical statuses when requested.
   *
   * @return array<string, mixed>
   */
  public static function getRosterData($projectId, $includePast = FALSE, $checkPermissions = TRUE) {
    $projectId = (int) $projectId;
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Volunteer_Permission::VIEW_ROSTER, $projectId);
    }
    $project = CRM_Volunteer_BAO_Project::retrieveByID($projectId, array('check_permissions' => $checkPermissions));

    $rows = array_values(array_filter(
      self::retrieveAllStatuses($projectId),
      static function(array $row) use ($includePast) {
        if (!empty($row['is_flexible'])) {
          return FALSE;
        }
        return $includePast || empty($row['is_past']);
      }
    ));

    return array(
      'project_id' => $projectId,
      'project_title' => $project->title,
      'include_past' => (bool) $includePast,
      'assignment_count' => count($rows),
      'shift_count' => count(array_unique(array_column($rows, 'volunteer_need_id'))),
      'rows' => $rows,
    );
  }

  /**
   * Workflow hour-entry data for one need or the complete project.
   *
   * @return array<string, mixed>
   */
  public static function getHourEntriesData($projectId, $needId = NULL, $checkPermissions = TRUE) {
    $projectId = (int) $projectId;
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $projectId);
    }
    self::assertNeedBelongsToProject($projectId, $needId);

    $needs = \Civi\Api4\VolunteerNeed::get(FALSE)
      ->addWhere('project_id', '=', $projectId)
      ->addOrderBy('start_time', 'ASC')
      ->execute()
      ->getArrayCopy();
    $needs = CRM_Volunteer_BAO_Need::addDisplayFields($needs);
    $rows = array_values(self::retrieveAllStatuses($projectId, $needId));

    return array(
      'project_id' => $projectId,
      'volunteer_need_id' => $needId,
      'flexible_need_id' => CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId),
      'needs' => array_values($needs),
      'statuses' => self::getSelectableStatusOptions($rows),
      'completed_status_id' => self::getStatusId('Completed'),
      'no_show_status_id' => self::getStatusId('No_show'),
      'rows' => $rows,
    );
  }

  /**
   * Save one hour-entry batch atomically.
   *
   * @param int $projectId
   * @param int|null $needId
   * @param array $entries
   * @param bool $checkPermissions
   *
   * @return array<string, mixed>
   */
  public static function logHours($projectId, $needId, array $entries, $checkPermissions = TRUE) {
    $projectId = (int) $projectId;
    $needId = $needId === NULL ? NULL : (int) $needId;
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $projectId);
    }
    self::assertNeedBelongsToProject($projectId, $needId);

    $existing = self::retrieveAllStatuses($projectId);
    $validStatuses = self::getStatusOptions();
    $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId);
    // One query for the whole batch; assertNeedBelongsToProject() would
    // otherwise issue a getFieldValue per entry.
    $projectNeeds = self::getProjectNeedStartTimes($projectId);
    // Scheduled and Available are the statuses that consume a need's capacity.
    // Only a status outside that set may exceed it, because such a row records
    // something that already happened.
    $capacityStatusIds = array_values(array_filter(array(
      self::getStatusId('Scheduled'),
      self::getStatusId('Available'),
    )));
    $savedIds = array();
    $transaction = CRM_Core_Transaction::create(TRUE);
    try {
      CRM_Volunteer_Permission::withInternalBypass(function() use (
        $entries,
        $existing,
        $validStatuses,
        $needId,
        $flexibleNeedId,
        $projectNeeds,
        $capacityStatusIds,
        &$savedIds
      ) {
        foreach ($entries as $index => $entry) {
          if (!is_array($entry)) {
            throw new CRM_Core_Exception(ts('Hour entry %1 is invalid.', array(1 => $index + 1, 'domain' => 'org.civicrm.volunteer')));
          }
          $assignmentId = empty($entry['id']) ? NULL : (int) $entry['id'];
          $rowNeedId = empty($entry['volunteer_need_id'])
            ? ($needId ?: $flexibleNeedId)
            : (int) $entry['volunteer_need_id'];
          if (!array_key_exists($rowNeedId, $projectNeeds)) {
            throw new CRM_Core_Exception(ts('The selected volunteer shift does not belong to this project.', array('domain' => 'org.civicrm.volunteer')));
          }

          $statusId = isset($entry['status_id']) ? (int) $entry['status_id'] : 0;
          if (!$statusId || !isset($validStatuses[$statusId])) {
            throw new CRM_Core_Exception(ts('Select a valid attendance status for every hour entry.', array('domain' => 'org.civicrm.volunteer')));
          }
          $minutes = $entry['time_completed_minutes'] ?? NULL;
          if ($minutes === '' || $minutes === NULL) {
            $minutes = NULL;
          }
          elseif (filter_var($minutes, FILTER_VALIDATE_INT) === FALSE || (int) $minutes < 0) {
            throw new CRM_Core_Exception(ts('Hours worked must resolve to a non-negative number of minutes.', array('domain' => 'org.civicrm.volunteer')));
          }
          else {
            $minutes = (int) $minutes;
          }

          $params = array(
            'check_permissions' => FALSE,
            'volunteer_need_id' => $rowNeedId,
            'status_id' => $statusId,
            'time_completed_minutes' => $minutes,
            'details' => isset($entry['details']) ? trim((string) $entry['details']) : '',
          );
          if ($assignmentId) {
            if (empty($existing[$assignmentId])) {
              throw new CRM_Core_Exception(ts('An hour entry does not belong to this volunteer project.', array('domain' => 'org.civicrm.volunteer')));
            }
            $params['id'] = $assignmentId;
          }
          else {
            $contactId = isset($entry['assignee_contact_id'])
              ? (int) $entry['assignee_contact_id']
              : (int) ($entry['contact_id'] ?? 0);
            if ($contactId < 1) {
              throw new CRM_Core_Exception(ts('Select a volunteer for every new hour entry.', array('domain' => 'org.civicrm.volunteer')));
            }
            $params['assignee_contact_id'] = $contactId;
            // Without this, setActivityDefaults() falls back to the project's
            // start time or to tomorrow, so a walk-in would be dated worse than
            // an assigned volunteer.
            if (!empty($projectNeeds[$rowNeedId])) {
              $params['activity_date_time'] = $projectNeeds[$rowNeedId];
            }
          }
          $savedIds[] = self::createVolunteerActivity(
            $params,
            !in_array($statusId, $capacityStatusIds, TRUE)
          );
        }
      });
      $transaction->commit();
    }
    catch (Throwable $e) {
      $transaction->rollback()->commit();
      throw $e;
    }

    $result = self::getHourEntriesData($projectId, $needId, FALSE);
    $result['saved_ids'] = array_values(array_map('intval', $savedIds));
    return $result;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private static function getStatusOptions() {
    $options = \Civi::entity('Activity')->getOptions('status_id', array(), TRUE) ?? array();
    $results = array();
    foreach ($options as $option) {
      $id = (int) $option['id'];
      $results[$id] = array(
        'id' => $id,
        'name' => $option['name'],
        'label' => $option['name'] === 'Completed'
          ? ts('Attended', array('domain' => 'org.civicrm.volunteer'))
          : $option['label'],
      );
    }
    return $results;
  }

  /**
   * Statuses offerable in the hour-log dropdown.
   *
   * getStatusOptions() intentionally includes disabled activity statuses so a
   * historical row can still resolve a label, but a disabled status must not be
   * offered as a new choice. It stays selectable only while a loaded row still
   * holds it, so re-saving that batch does not silently rewrite its status.
   *
   * @param array<int, array<string, mixed>> $rows
   *
   * @return array<int, array<string, mixed>>
   */
  private static function getSelectableStatusOptions(array $rows) {
    $enabled = \Civi::entity('Activity')->getOptions('status_id', array(), FALSE) ?? array();
    $allowed = array_map('intval', array_column($enabled, 'id'));
    foreach ($rows as $row) {
      if (!empty($row['status_id'])) {
        $allowed[] = (int) $row['status_id'];
      }
    }
    $allowed = array_unique($allowed);

    $results = array();
    foreach (self::getStatusOptions() as $id => $option) {
      if (in_array((int) $id, $allowed, TRUE)) {
        $results[] = $option;
      }
    }
    return $results;
  }

  private static function getStatusId($name) {
    foreach (self::getStatusOptions() as $id => $option) {
      if ($option['name'] === $name) {
        return (int) $id;
      }
    }
    return NULL;
  }

  /**
   * Every need id belonging to a project, mapped to its start time.
   *
   * @param int $projectId
   *
   * @return array<int, string|null>
   */
  private static function getProjectNeedStartTimes($projectId) {
    $results = array();
    $needs = CRM_Core_DAO::executeQuery(
      'SELECT id, start_time FROM civicrm_volunteer_need WHERE project_id = %1',
      array(1 => array((int) $projectId, 'Integer'))
    );
    while ($needs->fetch()) {
      $results[(int) $needs->id] = $needs->start_time ?: NULL;
    }
    return $results;
  }

  private static function assertNeedBelongsToProject($projectId, $needId) {
    if ($needId === NULL) {
      return;
    }
    $actualProjectId = (int) CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Need', (int) $needId, 'project_id');
    if (!$actualProjectId || $actualProjectId !== (int) $projectId) {
      throw new CRM_Core_Exception(ts('The selected volunteer shift does not belong to this project.', array('domain' => 'org.civicrm.volunteer')));
    }
  }

  private static function assignmentIsPast(array $row) {
    if (empty($row['start_time'])) {
      return FALSE;
    }
    try {
      $end = new DateTime($row['start_time']);
      if (!empty($row['end_time'])) {
        $end = new DateTime($row['end_time']);
      }
      elseif (!empty($row['duration'])) {
        $end->add(new DateInterval('PT' . (int) $row['duration'] . 'M'));
      }
      return new DateTime('today') > $end;
    }
    catch (Throwable $e) {
      return FALSE;
    }
  }

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

    $selectClause = array();
    foreach ($custom_fields as $name => $field) {
      $selectClause[] = "{$customTableName}.{$field['column_name']} AS {$name}";
    }
    $customSelect = implode(', ', $selectClause);

    $activityContactTypes = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $assigneeID = CRM_Utils_Array::key('Activity Assignees', $activityContactTypes);
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContactTypes);

    $volunteerStatus = array_column(
      \Civi::entity('Activity')->getOptions('status_id', array(), TRUE) ?? array(),
      'name',
      'id'
    );
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
      } elseif($key == 'project_id') {
        $dataType = 'Int';
        $fieldName = 'id';
        $tableName = CRM_Volunteer_DAO_Project::getTableName();
      } elseif ($key == 'target_contact_id') {
        $dataType = 'Int';
        $fieldName = 'contact_id';
        $tableName = 'tgt'; // this is an alias for civicrm_activity_contact
      } elseif ($key == 'assignee_contact_id') {
        $dataType = 'Int';
        $fieldName = 'contact_id';
        $tableName = 'assignee'; // this is an alias for civicrm_activity_contact
      }
      else {
        // Defensive guard if generated metadata gains a key which has not yet
        // been mapped to one of the joined tables above.
        continue;
      }
      $qualifiedField = "{$tableName}.{$fieldName}";
      if (in_array($key, array('id', 'project_id', 'volunteer_need_id'), TRUE)) {
        $ids = CRM_Volunteer_Permission::extractRequestedIds($value);
        if ($ids === NULL) {
          throw new CRM_Core_Exception(ts('Unsupported ID filter for volunteer assignments.', array('domain' => 'org.civicrm.volunteer')));
        }
        if (!$ids) {
          $where[] = '1 = 0';
          continue;
        }
        $idPlaceholders = array();
        foreach ($ids as $filterId) {
          $idPlaceholders[] = "%{$i}";
          $placeholders[$i] = array($filterId, $dataType);
          $i++;
        }
        $where[] = count($idPlaceholders) === 1
          ? $qualifiedField . ' = ' . reset($idPlaceholders)
          : $qualifiedField . ' IN (' . implode(', ', $idPlaceholders) . ')';
      }
      else {
        $where[] = "{$qualifiedField} = %{$i}";
        $placeholders[$i] = array($value, $dataType);
        $i++;
      }
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
        civicrm_volunteer_project.id AS project_id,
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
      AND civicrm_activity.is_deleted = 0
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

    $need = \Civi\Api4\VolunteerNeed::get(FALSE)
      ->addWhere('id', '=', $params['volunteer_need_id'])
      ->execute()
      ->single();
    $projectReadParams = array();
    if (array_key_exists('check_permissions', $params)) {
      // createVolunteerActivity() already authorized this write. Preserve its
      // explicit trusted-read flag so public signup and API4 aggregate callers
      // can load project-derived defaults inside withInternalBypass(). Outside
      // that scope, shouldCheckPermissions() still refuses a caller-supplied
      // FALSE and the project read remains guarded.
      $projectReadParams['check_permissions'] = $params['check_permissions'];
    }
    $project = CRM_Volunteer_BAO_Project::retrieveByID($need['project_id'], $projectReadParams);

    //copy over duration to Volunteer activity
    $defaults['duration'] = $need['duration'] ?? 'null';

    $defaults['campaign_id'] = $project ? $project->campaign_id : '';
    // Force NULL campaign ids to be empty strings, since the API ignores NULL values.
    if (empty($defaults['campaign_id'])) {
      $defaults['campaign_id'] = '';
    }
    if (empty($params['volunteer_role_id'])) {
      $defaults['volunteer_role_id'] = $need['role_id'] ?? 'null';
    }
    if ($op === CRM_Core_Action::ADD) {
      $defaults['time_scheduled_minutes'] = $need['duration'] ?? NULL;
      // APIv3 filled this from `user_contact_id`; API4 requires it outright.
      // Anonymous signups have no session contact, so credit the volunteer.
      $assignee = $params['assignee_contact_id'] ?? NULL;
      $defaults['source_contact_id'] = CRM_Core_Session::getLoggedInContactID()
        ?: (int) (is_array($assignee) ? reset($assignee) : $assignee);
      $defaults['target_contact_id'] = CRM_Volunteer_BAO_Project::getContactsByRelationship($project->id, 'volunteer_beneficiary');

      // If the related entity doesn't provide a good default, use tomorrow.
      if (empty($params['activity_date_time'])) {
        $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
        $defaults['activity_date_time'] = $project->getEntityAttributes()['start_time'] ?? $tomorrow;
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
  public static function createVolunteerActivity(array $params, $allowOverCapacity = FALSE) {
    if (empty($params['id']) && empty($params['volunteer_need_id'])) {
      throw new CRM_Core_Exception(ts('A volunteer need ID is required to save an assignment.', array('domain' => 'org.civicrm.volunteer')));
    }
    if (empty($params['id']) && empty($params['assignee_contact_id'])) {
      throw new CRM_Core_Exception(ts('An assignee contact ID is required to create an assignment.', array('domain' => 'org.civicrm.volunteer')));
    }

    // These values are always derived from the associated Project; @see self::setActivityDefaults()
    unset($params['campaign_id'], $params['target_contact_id']);
    // Prevent activity type from being changed externally.
    $params['activity_type_id'] = self::getActivityTypeId();

    $existingNeedId = NULL;
    if (!empty($params['id'])) {
      // VolunteerAssignment.get intentionally exposes only Scheduled and
      // Available rows. Updates from the Hours workflow must also find
      // Completed, No-show, Cancelled, and any other historical status.
      $customGroup = self::getCustomGroup();
      $customFields = self::getCustomFields();
      $existingNeedId = CRM_Core_DAO::singleValueQuery(
        sprintf(
          'SELECT `%s` FROM `%s` WHERE entity_id = %%1',
          $customFields['volunteer_need_id']['column_name'],
          $customGroup['table_name']
        ),
        array(1 => array((int) $params['id'], 'Integer'))
      );
      if (!$existingNeedId) {
        throw new CRM_Core_Exception(ts('The volunteer assignment does not exist.', array('domain' => 'org.civicrm.volunteer')));
      }
      if (empty($params['volunteer_need_id'])) {
        $params['volunteer_need_id'] = $existingNeedId;
      }
    }

    $needId = (int) $params['volunteer_need_id'];
    if (empty($params['id']) && empty($params['status_id'])) {
      $statusOptions = \Civi::entity('Activity')->getOptions('status_id', array(), TRUE) ?? array();
      $statusByName = array_column($statusOptions, 'id', 'name');
      $params['status_id'] = $statusByName['Scheduled'] ?? NULL;
    }
    $projectId = (int) CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Need', $needId, 'project_id');
    if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $projectId);
      if ($existingNeedId && (int) $existingNeedId !== $needId) {
        $existingProjectId = (int) CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Need', (int) $existingNeedId, 'project_id');
        CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $existingProjectId);
      }
    }

    $transaction = CRM_Core_Transaction::create(TRUE);
    try {
      $need = CRM_Core_DAO::executeQuery(
        'SELECT id, quantity FROM civicrm_volunteer_need WHERE id = %1 FOR UPDATE',
        array(1 => array($needId, 'Integer'))
      );
      if (!$need->fetch()) {
        throw new CRM_Core_Exception(ts('The selected volunteer need no longer exists.', array('domain' => 'org.civicrm.volunteer')));
      }
      // Locking read: the plain count would answer from this transaction's read
      // view, which may predate the commit of a writer we just queued behind.
      if (!$allowOverCapacity && (!$existingNeedId || (int) $existingNeedId !== $needId) && $need->quantity !== NULL
        && CRM_Volunteer_BAO_Need::getAssignmentCountForUpdate($needId) >= (int) $need->quantity) {
        throw new CRM_Core_Exception(ts('The selected volunteer opportunity has no remaining places.', array('domain' => 'org.civicrm.volunteer')));
      }

      $defaults = self::setActivityDefaults($params);
      $params = array_merge($defaults, $params);

    // Might as well sync these, but seems redundant
      if (!isset($params['duration']) && isset($params['time_completed_minutes'])) {
        $params['duration'] = $params['time_completed_minutes'];
      }

    // Format custom fields using API4's CustomGroup.CustomField notation.
    // array_key_exists rather than isset, so an explicit NULL is forwarded and
    // clears the field; isset() silently dropped it, which made a recorded
    // hours value impossible to erase.
      foreach(self::getCustomFields() as $fieldName => $field) {
        if (array_key_exists($fieldName, $params)) {
          $params[self::CUSTOM_GROUP_NAME . '.' . $field['name']] = $params[$fieldName];
          unset($params[$fieldName]);
        }
      }

      // CiviVolunteer has completed project-level authorization above.
      unset($params['check_permissions']);
      if (!empty($params['id'])) {
        $activityId = (int) $params['id'];
        unset($params['id']);
        $activity = \Civi\Api4\Activity::update(FALSE)
          ->addWhere('id', '=', $activityId)
          ->setValues($params)
          ->execute()
          ->single();
      }
      else {
        $activity = \Civi\Api4\Activity::create(FALSE)
          ->setValues($params)
          ->execute()
          ->single();
      }
      $transaction->commit();
      return empty($activity['id']) ? FALSE : (int) $activity['id'];
    }
    catch (Throwable $e) {
      $transaction->rollback()->commit();
      throw $e;
    }
  }

}
