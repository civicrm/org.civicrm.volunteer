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

class CRM_Volunteer_BAO_Need extends CRM_Volunteer_DAO_Need {

  const FLEXIBLE_ROLE_ID = -1;

   /**
   * class constructor
   */
  function __construct() {
      parent::__construct();

  }

  /**
   * create a Volunteer Need
   * takes an associative array and creates a Need object
   *
   * This function is invoked from within the web form layer and also from the api layer
   *
   * @param array   $params      (reference ) an assoc array of name/value pairs
   *
   * @return CRM_Volunteer_BAO_Need object
   * @access public
   * @static
   */
  public static function &create($params) {
    // these metadata fields are managed; don't accept them as params
    unset($params['created'], $params['last_updated']);

    $existing = NULL;
    if (!empty($params['id'])) {
      $existing = new CRM_Volunteer_DAO_Need();
      $existing->id = (int) $params['id'];
      if (!$existing->find(TRUE)) {
        throw new CRM_Core_Exception(ts('The volunteer need does not exist.', array('domain' => 'org.civicrm.volunteer')));
      }
      if (array_key_exists('project_id', $params)
        && (int) $params['project_id'] !== (int) $existing->project_id) {
        throw new CRM_Core_Exception(ts('A volunteer need cannot be moved to a different project.', array('domain' => 'org.civicrm.volunteer')));
      }
      if (array_key_exists('is_flexible', $params)
        && (bool) $params['is_flexible'] !== (bool) $existing->is_flexible) {
        throw new CRM_Core_Exception(ts('The flexible status of an existing volunteer need cannot be changed.', array('domain' => 'org.civicrm.volunteer')));
      }
    }

    $need = new CRM_Volunteer_BAO_Need();
    $need->copyValues($params);
    $projectId = $need->getProjectId();
    $isFlexible = $existing ? (bool) $existing->is_flexible : (bool) $need->is_flexible;

    if ($projectId === FALSE) {
      throw new CRM_Core_Exception(ts('A project ID is required to save a volunteer need.', array('domain' => 'org.civicrm.volunteer')));
    }
    if (!CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Project', $projectId, 'id')) {
      throw new CRM_Core_Exception(ts('The volunteer project does not exist.', array('domain' => 'org.civicrm.volunteer')));
    }

    // creating a Need constitutes updating a Project
    $op = CRM_Core_Action::UPDATE;
    if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
      CRM_Volunteer_Permission::assertProjectPerms($op, $projectId);
    }

    $transaction = CRM_Core_Transaction::create(TRUE);
    try {
      // VOL-269: Do not allow creation of more than one flexible need per
      // project. The project row lock and save must share one transaction or
      // two concurrent requests could both pass the uniqueness check.
      if ($isFlexible) {
        CRM_Core_DAO::executeQuery(
          'SELECT id FROM civicrm_volunteer_project WHERE id = %1 FOR UPDATE',
          array(1 => array($projectId, 'Integer'))
        );
        // Must be a locking read: a plain SELECT would answer from this
        // transaction's read view, which may predate a concurrent commit.
        $existingNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedIDForUpdate($projectId);
        $thisNeedId = property_exists($need, 'id') ? (int) $need->id : NULL;
        if ($existingNeedId && (int) $existingNeedId !== $thisNeedId) {
          throw new CRM_Core_Exception(ts('A volunteer project cannot have more than one flexible need.', array('domain' => 'org.civicrm.volunteer')));
        }
      }

      $need->save();
      $transaction->commit();
    }
    catch (Throwable $e) {
      $transaction->rollback()->commit();
      throw $e;
    }

    return $need;
  }

  /**
   * Returns the Need's Project ID.
   *
   * @return mixed
   *   On success, int project ID. On failure, boolean FALSE.
   */
  public function getProjectId() {
    // If the project ID was passed into the create method, or if the object is
    // already fully loaded, we already have the project ID and can return it...
    if (isset($this->project_id) && CRM_Utils_Type::validate($this->project_id, 'Positive', FALSE)) {
      return (int) $this->project_id;
    }

    // ... otherwise we have to look it up from the database
    if (isset($this->id) && CRM_Utils_Type::validate($this->id, 'Positive', FALSE)) {
      $dbNeed = $this->findById($this->id);
      return $dbNeed->project_id;
    }

    return FALSE;
  }

  /**
   * Gets role label to be used for Flexible Needs.
   *
   * Implemented as a function in case we need to use logic later (e.g., if we
   * allow users to set this on a per-project basis).
   *
   * @return string
   */
  static function getFlexibleRoleLabel() {
    return ts("Any", array('domain' => 'org.civicrm.volunteer'));
  }

  /**
   * Gets display time to be used for Flexible Needs.
   *
   * Implemented as a function in case we need to use logic later (e.g., if we
   * allow users to set this on a per-project basis).
   *
   * @return string
   */
  static function getFlexibleDisplayTime() {
    return ts("Any", array('domain' => 'org.civicrm.volunteer'));
  }

  /**
   * Memoisation key for the volunteer role option list.
   *
   * Shape: [(int) value => ['label' => string, 'description' => string]].
   *
   * Kept in Civi::$statics rather than a class static so that a cache flush --
   * or a long-running process which adds a role -- invalidates it. A plain
   * class static outlives Civi::reset() and served a stale list.
   */
  const DISPLAY_ROLE_OPTIONS_CACHE = 'displayRoleOptions';

  /**
   * Add the derived display fields (display_time, role_label,
   * role_description) shared by the API4/API3 need reads and the project
   * BAO's need lists.
   *
   * @param array $needs
   *   Need rows; keyed or indexed. Modified copies are returned.
   * @return array
   */
  public static function addDisplayFields(array $needs) {
    if ($needs === array()) {
      return $needs;
    }
    $roleOptions = self::getDisplayRoleOptions();
    foreach ($needs as &$need) {
      if (!empty($need['start_time'])) {
        $need['display_time'] = self::getTimes(
          $need['start_time'],
          $need['duration'] ?? NULL,
          $need['end_time'] ?? NULL
        );
      }
      else {
        $need['display_time'] = self::getFlexibleDisplayTime();
      }
      if (isset($need['role_id'])) {
        $role = $roleOptions[(int) $need['role_id']] ?? array();
        $need['role_label'] = $role['label'] ?? (string) $need['role_id'];
        $need['role_description'] = CRM_Utils_String::purifyHTML($role['description'] ?? '');
      }
      elseif (!empty($need['is_flexible'])) {
        $need['role_label'] = self::getFlexibleRoleLabel();
        $need['role_description'] = NULL;
      }
    }
    unset($need);
    return $needs;
  }

  /**
   * @return array
   */
  private static function getDisplayRoleOptions() {
    if (!isset(Civi::$statics[__CLASS__][self::DISPLAY_ROLE_OPTIONS_CACHE])) {
      $options = array();
      $roleOptions = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('value', 'label', 'description')
        ->addWhere('option_group_id.name', '=', CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP)
        ->execute();
      foreach ($roleOptions as $roleOption) {
        $options[(int) $roleOption['value']] = $roleOption;
      }
      Civi::$statics[__CLASS__][self::DISPLAY_ROLE_OPTIONS_CACHE] = $options;
    }
    return Civi::$statics[__CLASS__][self::DISPLAY_ROLE_OPTIONS_CACHE];
  }

  /**
   * Returns a string representing the times of a shift. Times will be formatted
   * according to the user's defined time display settings. If no duration/end
   * date is given, only the formatted start time will be returned.
   *
   * @param string $start
   *   Should be a parseable time string
   * @param mixed $duration
   *   An int or a string, in minutes, or NULL for none
   * @param mixed $end
   *   Should be a parseable time string, or NULL for none
   * @return mixed
   *   Returns a string on success, boolean FALSE if $start is not
   *   a parseable time.
   */
  static function getTimes($start, $duration = NULL, $end = NULL) {
    if ($start === NULL || $start === '' || !strtotime((string) $start)) {
      return FALSE;
    }

    $config = CRM_Core_Config::singleton();
    $timeFormat = $config->dateformatDatetime;
    $result = CRM_Utils_Date::customFormat($start, $timeFormat);

    if ($end !== NULL && $end !== '' && strtotime((string) $end)) {
      $result .= ' - ' . CRM_Utils_Date::customFormat($end, $timeFormat);
    } elseif (CRM_Utils_Type::validate($duration, 'Positive', FALSE)) {
      $date = new DateTime($start);
      $startDay = $date->format('Y-m-d');
      $date->add(new DateInterval("PT{$duration}M"));
      $end = $date->format('Y-m-d H:i:s');
      // If days are the same, only show time
      if ($date->format('Y-m-d') == $startDay) {
        $timeFormat = $config->dateformatTime;
      }
      $result .= ' - ' . CRM_Utils_Date::customFormat($end, $timeFormat);
    }

    return $result;
  }

  /**
   * Delete a need, reassign its activities to the project's default flexible need
   * @param $id
   * @return bool
   */
  static function del($id) {
    $transaction = CRM_Core_Transaction::create(TRUE);
    try {
      $id = (int) $id;
      // Assignment creation locks this same row before writing the activity.
      // Taking the lock first prevents a new assignment from appearing after
      // the reparenting query but before the need is deleted.
      $need = CRM_Core_DAO::executeQuery(
        'SELECT id, project_id, is_flexible
           FROM civicrm_volunteer_need
          WHERE id = %1
          FOR UPDATE',
        array(1 => array($id, 'Integer'))
      );
      if (!$need->fetch()) {
        $transaction->rollback()->commit();
        return FALSE;
      }

      if (!empty($need->is_flexible)) {
        throw new CRM_Core_Exception(ts('The flexible need is required and cannot be deleted.', array('domain' => 'org.civicrm.volunteer')));
      }

      // Reassign activities from a dated need to the required fallback before
      // deleting the dated need.
      $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $need->project_id);
      if (!$flexibleNeedId) {
        throw new CRM_Core_Exception(ts('The project has no flexible need to receive existing assignments.', array('domain' => 'org.civicrm.volunteer')));
      }

      // Reparent the custom-field reference directly. VolunteerAssignment.get
      // intentionally returns only capacity-consuming statuses, so using it
      // here omitted Completed and No-show history. Changing only the need ID
      // preserves status, scheduled/completed minutes, dates, and audit data.
      // Include soft-deleted activities too so restoring one later cannot
      // resurrect a reference to a deleted need.
      $customGroup = CRM_Volunteer_BAO_Assignment::getCustomGroup();
      $customFields = CRM_Volunteer_BAO_Assignment::getCustomFields();
      $customTable = $customGroup['table_name'];
      $needColumn = $customFields['volunteer_need_id']['column_name'];
      // Resolve the rows first, then update them without a join.
      //
      // `UPDATE custom_table cv INNER JOIN civicrm_activity a ...` is rejected
      // outright by MySQL on any normal CiviCRM install: core maintains an
      // AFTER UPDATE trigger on every Activity custom-value table which sets
      // civicrm_activity.modified_date, and a trigger may not write to a table
      // the invoking statement is already reading --
      //   ERROR 1442: Can't update table 'civicrm_activity' in stored
      //   function/trigger because it is already used by statement which
      //   invoked this stored function/trigger.
      // So deleting any dated shift that had volunteers assigned to it failed.
      // A SELECT may join freely; only the UPDATE has to stay off
      // civicrm_activity.
      $reassignIds = array();
      $reassignDao = CRM_Core_DAO::executeQuery(
        sprintf(
          'SELECT cv.entity_id
             FROM `%s` cv
             INNER JOIN civicrm_activity a ON a.id = cv.entity_id
            WHERE cv.`%s` = %%1
              AND a.activity_type_id = %%2',
          $customTable,
          $needColumn
        ),
        array(
          1 => array($id, 'Integer'),
          2 => array(CRM_Volunteer_BAO_Assignment::getActivityTypeId(), 'Integer'),
        )
      );
      while ($reassignDao->fetch()) {
        $reassignIds[] = (int) $reassignDao->entity_id;
      }

      if ($reassignIds) {
        CRM_Core_DAO::executeQuery(
          sprintf(
            'UPDATE `%s` SET `%s` = %%1 WHERE entity_id IN (%s)',
            $customTable,
            $needColumn,
            implode(',', $reassignIds)
          ),
          array(1 => array((int) $flexibleNeedId, 'Integer'))
        );
      }

      $dao = new CRM_Volunteer_DAO_Need();
      $dao->id = $id;
      if ($dao->find()) {
        while ($dao->fetch()) {
          $dao->delete();
        }
      }
      else {
        $transaction->rollback()->commit();
        return FALSE;
      }
      $transaction->commit();
      return TRUE;
    }
    catch (Throwable $e) {
      $transaction->rollback()->commit();
      throw $e;
    }
  }

  /**
   * Permission-aware entry point used by API3 and API4 delete actions.
   */
  public static function deleteNeed($id, $checkPermissions = TRUE) {
    $id = (int) $id;
    $need = \Civi\Api4\VolunteerNeed::get(FALSE)
      ->addSelect('id', 'project_id', 'is_flexible')
      ->addWhere('id', '=', $id)
      ->execute()
      ->single();
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $need['project_id']);
    }
    if (!empty($need['is_flexible'])) {
      throw new CRM_Core_Exception(ts('The flexible need is required and cannot be deleted.', array('domain' => 'org.civicrm.volunteer')));
    }
    return self::del($id);
  }

  /**
   * @param int $need_id
   * @return int The number of assignments on the given need
   */
  public static function getAssignmentCount($need_id) {
    CRM_Utils_Type::validate($need_id, 'Integer');
    return \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addSelect('row_count')
      ->addWhere('volunteer_need_id', '=', $need_id)
      ->execute()
      ->countMatched();
  }

  /**
   * Count a need's capacity-consuming assignments under a row lock.
   *
   * getAssignmentCount() reads through the APIv3 query builder, i.e. a plain
   * SELECT answered from the transaction's read view. A capacity decision has
   * to see rows committed by a writer we just queued behind, so it needs a
   * locking read -- which always reads the latest committed version.
   *
   * Mirrors CRM_Volunteer_BAO_Assignment::retrieve() exactly: only Scheduled
   * and Available, nondeleted activities occupy a place.
   *
   * @param int $need_id
   *
   * @return int
   */
  public static function getAssignmentCountForUpdate($need_id) {
    $need_id = (int) $need_id;
    if ($need_id < 1) {
      return 0;
    }
    $customGroup = CRM_Volunteer_BAO_Assignment::getCustomGroup();
    $customFields = CRM_Volunteer_BAO_Assignment::getCustomFields();
    $needColumn = $customFields['volunteer_need_id']['column_name'];

    $statuses = array_column(
      \Civi::entity('Activity')->getOptions('status_id', array(), TRUE) ?? array(),
      'name',
      'id'
    );
    $scheduled = CRM_Utils_Array::key('Scheduled', $statuses);
    $available = CRM_Utils_Array::key('Available', $statuses);

    return (int) CRM_Core_DAO::singleValueQuery(
      sprintf(
        'SELECT COUNT(*)
           FROM `%s` cv
           INNER JOIN civicrm_activity a ON a.id = cv.entity_id
          WHERE cv.`%s` = %%1
            AND a.activity_type_id = %%2
            AND a.status_id IN (%%3, %%4)
            AND a.is_deleted = 0
          FOR UPDATE',
        $customGroup['table_name'],
        $needColumn
      ),
      array(
        1 => array($need_id, 'Integer'),
        2 => array(CRM_Volunteer_BAO_Assignment::getActivityTypeId(), 'Integer'),
        3 => array($scheduled, 'Integer'),
        4 => array($available, 'Integer'),
      )
    );
  }
}
