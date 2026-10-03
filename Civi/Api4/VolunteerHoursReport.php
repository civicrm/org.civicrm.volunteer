<?php

namespace Civi\Api4;

use Civi\Api4\Generic\SqlView;
use Civi\Core\Event\GenericHookEvent;
use CRM_Volunteer_ExtensionUtil as E;

/**
 * Read-only reporting rows for volunteer shift assignments.
 *
 * Each row represents one non-deleted volunteer activity assigned to a
 * scheduled (non-flexible) need. Values are read live from the assignment,
 * need, project, and contact records; the view does not copy report data.
 *
 * @searchable secondary
 * @primaryKey activity_id
 * @labelField volunteer_display_name
 */
// SqlView is marked @internal, but its class documentation explicitly provides
// it for extension inheritance and defines this viewSelect/viewFrom contract.
// @phpstan-ignore-next-line
class VolunteerHoursReport extends SqlView {


  /**
   * Only organization-wide volunteer administrators may use this report.
   */
  public static function permissions(): array {
    $reportPermission = [
      'edit all volunteer projects',
      'view all contacts',
    ];

    return [
      'get' => $reportPermission,
      'autocomplete' => $reportPermission,
      'getFields' => $reportPermission,
    ];
  }

  /**
   * Rebuild the database view, but only when it can actually be built.
   *
   * SqlView's own callback drops and recreates the view unconditionally on every
   * API4 entityTypes rebuild. That rebuild happens *during* installation, before
   * this extension's own tables and custom fields exist, so the CREATE VIEW
   * failed and took the whole installation with it:
   *
   *   Table 'civicrm_volunteer_need' doesn't exist
   *   Unknown column 'cv.time_scheduled_in_minutes_3' in 'field list'
   *
   * Dropping a stale view and standing aside is the right behaviour in that
   * state -- the next rebuild, once install() has finished, creates it properly.
   *
   * When storage is available, use one CREATE OR REPLACE statement instead of
   * SqlView's separate DROP and CREATE statements. Multiple AJAX requests can
   * rebuild an empty API4 metadata cache concurrently; the parent sequence lets
   * both requests drop the view before one creates it, making the other fail
   * with MySQL error 1050 ("Table already exists").
   *
   * @internal
   */
  public static function _on_civi_api4_entityTypes(GenericHookEvent $event): void {
    if (empty(self::getViewContext()['is_available'])) {
      $viewName = static::viewName();
      \CRM_Core_DAO::executeQuery("DROP VIEW IF EXISTS `$viewName`");
      return;
    }
    \CRM_Core_DAO::executeQuery(static::buildViewSql());
  }

  /**
   * Build the race-safe statement used to create or refresh the reporting view.
   */
  protected static function buildViewSql(): string {
    $viewName = static::viewName();
    $selects = [];
    foreach (static::viewSelect() as $field) {
      $selects[] = "{$field['select']} AS `{$field['name']}`";
    }
    $select = implode(', ', $selects);
    $from = static::viewFrom();

    return "CREATE OR REPLACE VIEW `$viewName` AS SELECT $select $from";
  }

  protected static function getEntityTitle(bool $plural = FALSE): string {
    return $plural
      ? E::ts('Volunteer Hours Report Rows')
      : E::ts('Volunteer Hours Report Row');
  }

  /**
   * Options for the report-specific audit state.
   *
   * Underscore-prefixed deliberately, and it must stay that way.
   * Civi\Api4\Action\GetActions::getRecords() reflects every *public static*
   * method on an API entity class as a candidate action, excluding only
   * `permissions`, `getInfo`, `getEntityName` and `_`-prefixed names. As
   * `getHoursEntryStateOptions` this was picked up as an action, and
   * Civi\API\Request::create() invoked it and called ->set() on the plain array
   * it returns -- so VolunteerHoursReport.getActions fatalled with "Call to a
   * member function set() on array" for every authorized caller introspecting
   * the entity (cv api4, the API Explorer, SearchKit and afform admin screens).
   * Core's own SqlView helpers are `_`-prefixed for exactly this reason; see
   * _getFieldsFromViewSelect() and _getOptions().
   *
   * @return array<int, array<string, string>>
   */
  public static function _getHoursEntryStateOptions(): array {
    return [
      [
        'id' => 'logged',
        'name' => 'logged',
        'label' => E::ts('Logged'),
      ],
      [
        'id' => 'awaiting',
        'name' => 'awaiting',
        'label' => E::ts('Awaiting hours'),
      ],
      [
        'id' => 'not_due',
        'name' => 'not_due',
        'label' => E::ts('Not due'),
      ],
      [
        'id' => 'not_required',
        'name' => 'not_required',
        'label' => E::ts('Not required'),
      ],
    ];
  }

  protected static function viewSelect(): array {
    $context = self::getViewContext();
    $effectiveEnd = self::effectiveEndExpression();
    if (empty($context['is_available'])) {
      // No custom table to read the minute columns from, and viewFrom() omits
      // the `cv` alias in this state. Keep every column in place so getFields()
      // does not change shape; the view returns no rows regardless.
      $scheduled = $completed = 'NULL';
      $awaiting = 'FALSE';
      $context['scheduled_status_id'] = $context['completed_status_id'] = 0;
    }
    else {
      $scheduled = 'cv.' . self::quoteIdentifier($context['scheduled_column']);
      $completed = 'cv.' . self::quoteIdentifier($context['completed_column']);
      $awaiting = sprintf(
        '(%s IS NULL AND a.status_id IN (%d, %d) AND %s IS NOT NULL AND %s <= CURRENT_TIMESTAMP)',
        $completed,
        $context['scheduled_status_id'],
        $context['completed_status_id'],
        $effectiveEnd,
        $effectiveEnd
      );
    }

    return [
      [
        'select' => 'a.id',
        'name' => 'activity_id',
        'original_field' => 'Activity.id',
        'title' => E::ts('Assignment ID'),
        'primary_key' => TRUE,
      ],
      [
        'select' => 'assignee.contact_id',
        'name' => 'volunteer_contact_id',
        'original_field' => 'Contact.id',
        'title' => E::ts('Volunteer'),
      ],
      [
        'select' => 'contact.display_name',
        'name' => 'volunteer_display_name',
        'original_field' => 'Contact.display_name',
        'title' => E::ts('Volunteer Name'),
      ],
      [
        'select' => 'contact.sort_name',
        'name' => 'volunteer_sort_name',
        'original_field' => 'Contact.sort_name',
        'title' => E::ts('Volunteer Sort Name'),
      ],
      [
        'select' => 'n.project_id',
        'name' => 'project_id',
        'original_field' => 'VolunteerProject.id',
        'title' => E::ts('Volunteer Project'),
      ],
      [
        'select' => 'project.title',
        'name' => 'project_title',
        'original_field' => 'VolunteerProject.title',
        'title' => E::ts('Project Title'),
      ],
      [
        'select' => 'n.id',
        'name' => 'volunteer_need_id',
        'original_field' => 'VolunteerNeed.id',
        'title' => E::ts('Shift ID'),
      ],
      [
        'select' => 'n.start_time',
        'name' => 'shift_start',
        'original_field' => 'VolunteerNeed.start_time',
        'title' => E::ts('Shift Start'),
      ],
      [
        'select' => $effectiveEnd,
        'name' => 'shift_end',
        'data_type' => 'Timestamp',
        'input_type' => 'Date',
        'title' => E::ts('Shift End'),
        'description' => E::ts('Explicit shift end, or the start plus the shift duration.'),
      ],
      [
        'select' => 'n.role_id',
        'name' => 'role_id',
        'original_field' => 'VolunteerNeed.role_id',
        'title' => E::ts('Volunteer Role'),
      ],
      [
        'select' => 'a.status_id',
        'name' => 'status_id',
        'original_field' => 'Activity.status_id',
        'title' => E::ts('Assignment Status'),
      ],
      [
        'select' => 'a.details',
        'name' => 'details',
        'original_field' => 'Activity.details',
        'title' => E::ts('Notes'),
      ],
      [
        'select' => sprintf('COALESCE(%s, 0)', $scheduled),
        'name' => 'scheduled_minutes',
        'data_type' => 'Integer',
        'input_type' => 'Number',
        'title' => E::ts('Scheduled Minutes'),
      ],
      [
        'select' => $completed,
        'name' => 'logged_minutes',
        'data_type' => 'Integer',
        'input_type' => 'Number',
        'title' => E::ts('Logged Minutes'),
      ],
      [
        'select' => sprintf('(COALESCE(%s, 0) / 60.0)', $scheduled),
        'name' => 'scheduled_hours',
        'data_type' => 'Float',
        'input_type' => 'Number',
        'title' => E::ts('Scheduled Hours'),
      ],
      [
        'select' => sprintf('(CASE WHEN %s IS NULL THEN NULL ELSE %s / 60.0 END)', $completed, $completed),
        'name' => 'logged_hours',
        'data_type' => 'Float',
        'input_type' => 'Number',
        'title' => E::ts('Logged Hours'),
      ],
      [
        'select' => sprintf('(COALESCE(%s, 0) / 60.0)', $completed),
        'name' => 'logged_hours_total',
        'data_type' => 'Float',
        'input_type' => 'Number',
        'title' => E::ts('Logged Hours (Total)'),
        'description' => E::ts('Logged hours with missing entries counted as zero, for aggregation.'),
      ],
      [
        'select' => sprintf('(%s IS NOT NULL)', $completed),
        'name' => 'has_logged_hours',
        'data_type' => 'Boolean',
        'input_type' => 'CheckBox',
        'title' => E::ts('Hours Logged'),
      ],
      [
        'select' => sprintf('(a.status_id = %d)', $context['completed_status_id']),
        'name' => 'is_attended',
        'data_type' => 'Boolean',
        'input_type' => 'CheckBox',
        'title' => E::ts('Attended'),
      ],
      [
        'select' => $awaiting,
        'name' => 'awaiting_hours',
        'data_type' => 'Boolean',
        'input_type' => 'CheckBox',
        'title' => E::ts('Awaiting Hours'),
      ],
      [
        'select' => sprintf(
          "(CASE WHEN %s IS NOT NULL THEN 'logged' WHEN %s THEN 'awaiting' WHEN a.status_id IN (%d, %d) THEN 'not_due' ELSE 'not_required' END)",
          $completed,
          $awaiting,
          $context['scheduled_status_id'],
          $context['completed_status_id']
        ),
        'name' => 'hours_entry_state',
        'data_type' => 'String',
        'input_type' => 'Select',
        'title' => E::ts('Hours Entry State'),
        'pseudoconstant' => [
          'callback' => [self::class, '_getHoursEntryStateOptions'],
        ],
      ],
      [
        'select' => '1',
        'name' => 'assignment_count',
        'data_type' => 'Integer',
        'input_type' => 'Number',
        'title' => E::ts('Assignment Count'),
      ],
    ];
  }

  protected static function viewFrom(): string {
    $context = self::getViewContext();
    if (empty($context['is_available'])) {
      // Same aliases, no `cv`, and an impossible WHERE. Every column in
      // viewSelect() still resolves, and MySQL discards the query outright.
      return 'FROM civicrm_activity a
       INNER JOIN civicrm_activity_contact assignee
         ON assignee.activity_id = a.id
       INNER JOIN civicrm_contact contact
         ON contact.id = assignee.contact_id
       INNER JOIN civicrm_volunteer_need n
         ON n.is_flexible = 0
       INNER JOIN civicrm_volunteer_project project
         ON project.id = n.project_id
       WHERE 1 = 0';
    }

    $customTable = self::quoteIdentifier($context['custom_table']);
    $needColumn = self::quoteIdentifier($context['need_column']);

    return sprintf(
      'FROM civicrm_activity a
       INNER JOIN civicrm_activity_contact assignee
         ON assignee.activity_id = a.id AND assignee.record_type_id = %d
       INNER JOIN civicrm_contact contact
         ON contact.id = assignee.contact_id
       INNER JOIN %s cv
         ON cv.entity_id = a.id
       INNER JOIN civicrm_volunteer_need n
         ON n.id = cv.%s AND n.is_flexible = 0
       INNER JOIN civicrm_volunteer_project project
         ON project.id = n.project_id
       WHERE a.activity_type_id = %d
         AND a.is_deleted = 0
         AND contact.is_deleted = 0',
      $context['assignee_record_type_id'],
      $customTable,
      $needColumn,
      $context['activity_type_id']
    );
  }

  private static function effectiveEndExpression(): string {
    return '(CASE
      WHEN n.end_time IS NOT NULL THEN n.end_time
      WHEN n.start_time IS NOT NULL AND n.duration > 0
        THEN DATE_ADD(n.start_time, INTERVAL n.duration MINUTE)
      ELSE NULL
    END)';
  }

  /**
   * Resolve installation-specific custom column names and option IDs.
   *
   * @return array<string, int|string>
   */
  private static function getViewContext(): array {
    // Deliberately not cached in a static. install() creates the custom group
    // part-way through a request in which the entityTypes cache is rebuilt more
    // than once, so a cached context served column names for a custom group
    // that no longer had that ID -- "Unknown column
    // 'cv.time_scheduled_in_minutes_3'". Two cheap statements per rebuild is
    // the right trade.


    // This method runs while the API4 entity cache is being constructed. Use
    // direct SQL instead of API calls here; an API call would request the same
    // unfinished entity cache and recurse.
    $custom = \CRM_Core_DAO::executeQuery(
      "SELECT cg.table_name,
              MAX(CASE WHEN cf.name = 'Volunteer_Need_Id' THEN cf.column_name END) AS need_column,
              MAX(CASE WHEN cf.name = 'Time_Scheduled_Minutes' THEN cf.column_name END) AS scheduled_column,
              MAX(CASE WHEN cf.name = 'Time_Completed_Minutes' THEN cf.column_name END) AS completed_column
         FROM civicrm_custom_group cg
         LEFT JOIN civicrm_custom_field cf
           ON cf.custom_group_id = cg.id AND cf.is_active = 1
        WHERE cg.name = 'CiviVolunteer'
          AND cg.extends = 'Activity'
          AND cg.is_active = 1
        GROUP BY cg.id, cg.table_name"
    );
    $custom->fetch();

    $required = [
      'custom_table' => $custom->table_name ?? NULL,
      'need_column' => $custom->need_column ?? NULL,
      'scheduled_column' => $custom->scheduled_column ?? NULL,
      'completed_column' => $custom->completed_column ?? NULL,
      'assignee_record_type_id' => self::getOptionValue('activity_contacts', 'Activity Assignees'),
      'activity_type_id' => self::getOptionValue('activity_type', 'Volunteer'),
      'scheduled_status_id' => self::getOptionValue('activity_status', 'Scheduled'),
      'completed_status_id' => self::getOptionValue('activity_status', 'Completed'),
    ];

    $missing = [];
    foreach ($required as $name => $value) {
      if ($value === NULL || $value === FALSE || $value === '') {
        $missing[] = $name;
      }
    }

    // The custom group can exist before this extension's own tables do -- the
    // civimix-schema mixin creates them at a different point in install() --
    // and viewFrom() joins both of them.
    foreach (array('civicrm_volunteer_need', 'civicrm_volunteer_project') as $table) {
      if (!\CRM_Core_DAO::checkTableExists($table)) {
        $missing[] = $table;
      }
    }

    // And civicrm_custom_field can name a column that the custom table does not
    // physically have yet: the metadata rows are imported before the ALTER TABLE
    // that adds the columns, and an entityTypes rebuild in between produced
    // "Unknown column 'cv.time_scheduled_in_minutes_3' in 'field list'".
    // Trusting the metadata alone is not enough; ask the table.
    if (!$missing) {
      $columns = array();
      $columnDao = \CRM_Core_DAO::executeQuery(
        'SELECT COLUMN_NAME AS column_name
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %1',
        array(1 => array($required['custom_table'], 'String'))
      );
      while ($columnDao->fetch()) {
        $columns[$columnDao->column_name] = TRUE;
      }
      foreach (array('need_column', 'scheduled_column', 'completed_column') as $name) {
        if (!isset($columns[$required[$name]])) {
          $missing[] = $required['custom_table'] . '.' . $required[$name];
        }
      }
    }

    // Throwing when something is missing is not an option. This runs while API4
    // builds its entity metadata, which happens *before* install() has created
    // the CiviVolunteer custom group or the volunteer tables -- so the throw
    // aborted every fresh installation, and with it every PHPUnit run, with
    // "Unable to build the volunteer hours report: missing custom_table". The
    // same applies to any entity-cache rebuild against a database where the
    // custom data is absent. Report the view as unavailable instead;
    // _on_civi_api4_entityTypes() then leaves it uncreated until the next
    // rebuild can build it properly.
    $required['is_available'] = !$missing;
    if ($missing) {
      \Civi::log()->debug(sprintf(
        'The volunteer hours report is not available yet; missing %s.',
        implode(', ', $missing)
      ));
    }

    return $required;
  }

  private static function getOptionValue(string $groupName, string $optionName): ?int {
    $value = \CRM_Core_DAO::singleValueQuery(
      'SELECT ov.value
         FROM civicrm_option_value ov
         INNER JOIN civicrm_option_group og ON og.id = ov.option_group_id
        WHERE og.name = %1 AND ov.name = %2
        LIMIT 1',
      [
        1 => [$groupName, 'String'],
        2 => [$optionName, 'String'],
      ]
    );
    return $value === NULL ? NULL : (int) $value;
  }

  private static function quoteIdentifier(string $identifier): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
      throw new \CRM_Core_Exception(E::ts('Invalid database identifier in the volunteer hours report.'));
    }
    return '`' . $identifier . '`';
  }

}
