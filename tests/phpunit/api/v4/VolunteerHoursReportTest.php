<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerAssignment;
use Civi\Api4\VolunteerHoursReport;

/**
 * Live reporting-view and authorization tests.
 *
 * @group headless
 */
class api_v4_VolunteerHoursReportTest extends VolunteerTestAbstract {

  /**
   * @return array{0: int, 1: int}
   *   Project and need IDs.
   */
  private function createShift(string $startTime, int $duration = 60, bool $isFlexible = FALSE): array {
    $project = $this->createProject([
      'title' => 'Hours report project ' . $startTime,
    ]);

    // Creating a project already creates its flexible need (VOL-269), and a
    // second one is rejected outright -- "A volunteer project cannot have more
    // than one flexible need." So for the flexible case, take the one that is
    // already there and give it the attributes this fixture wants.
    if ($isFlexible) {
      $flexibleNeedId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID($project['id']);
      \Civi\Api4\VolunteerNeed::update(FALSE)
        ->addWhere('id', '=', $flexibleNeedId)
        ->addValue('quantity', 5)
        ->addValue('visibility_id', $this->getOptionValue('visibility', 'public'))
        ->addValue('is_active', TRUE)
        ->execute();
      return [(int) $project['id'], $flexibleNeedId];
    }

    $need = $this->createNeed([
      'project_id' => $project['id'],
      'start_time' => $startTime,
      'duration' => $duration,
      'is_flexible' => FALSE,
      'quantity' => 5,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => TRUE,
    ]);
    return [(int) $project['id'], (int) $need['id']];
  }

  public function testReportUsesLiveAssignmentValuesAndPreservesExplicitZero(): void {
    $pastStart = date('Y-m-d H:i:s', strtotime('-3 hours'));
    [$projectId, $needId] = $this->createShift($pastStart);
    $assignment = $this->createAssignment([
      'volunteer_need_id' => $needId,
      'assignee_contact_id' => $this->individualCreate(),
      'time_scheduled_minutes' => 90,
    ]);

    $awaiting = VolunteerHoursReport::get(FALSE)
      ->addWhere('activity_id', '=', $assignment['id'])
      ->execute()
      ->single();
    $this->assertSame($projectId, (int) $awaiting['project_id']);
    $this->assertSame('awaiting', $awaiting['hours_entry_state']);
    $this->assertSame(1, (int) $awaiting['awaiting_hours']);
    $this->assertEqualsWithDelta(1.5, (float) $awaiting['scheduled_hours'], 0.00001);
    $this->assertNull($awaiting['logged_hours']);

    VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $assignment['id'])
      ->addValue('status_id', $this->getOptionValue('activity_status', 'Completed'))
      ->addValue('time_completed_minutes', 0)
      ->execute();

    $logged = VolunteerHoursReport::get(FALSE)
      ->addWhere('activity_id', '=', $assignment['id'])
      ->execute()
      ->single();
    $this->assertSame('logged', $logged['hours_entry_state']);
    $this->assertSame(1, (int) $logged['has_logged_hours']);
    $this->assertEqualsWithDelta(0.0, (float) $logged['logged_hours'], 0.00001);
  }

  public function testFutureCancelledAndFlexibleAssignmentsAreClassifiedOrExcluded(): void {
    [, $futureNeedId] = $this->createShift(date('Y-m-d H:i:s', strtotime('+1 day')));
    $future = $this->createAssignment([
      'volunteer_need_id' => $futureNeedId,
      'assignee_contact_id' => $this->individualCreate(),
    ]);
    $this->assertSame(
      'not_due',
      VolunteerHoursReport::get(FALSE)
        ->addSelect('hours_entry_state')
        ->addWhere('activity_id', '=', $future['id'])
        ->execute()
        ->single()['hours_entry_state']
    );

    [, $cancelledNeedId] = $this->createShift(date('Y-m-d H:i:s', strtotime('-1 day')));
    $cancelled = $this->createAssignment([
      'volunteer_need_id' => $cancelledNeedId,
      'assignee_contact_id' => $this->individualCreate(),
      'status_id' => $this->getOptionValue('activity_status', 'Cancelled'),
    ]);
    $this->assertSame(
      'not_required',
      VolunteerHoursReport::get(FALSE)
        ->addSelect('hours_entry_state')
        ->addWhere('activity_id', '=', $cancelled['id'])
        ->execute()
        ->single()['hours_entry_state']
    );

    [, $flexibleNeedId] = $this->createShift(date('Y-m-d H:i:s', strtotime('-1 day')), 60, TRUE);
    $flexible = $this->createAssignment([
      'volunteer_need_id' => $flexibleNeedId,
      'assignee_contact_id' => $this->individualCreate(),
    ]);
    $this->assertCount(
      0,
      VolunteerHoursReport::get(FALSE)
        ->addWhere('activity_id', '=', $flexible['id'])
        ->execute()
    );
  }

  public function testReportRequiresBothOrganizationWidePermissions(): void {
    $this->assertSame(
      ['edit all volunteer projects', 'view all contacts'],
      VolunteerHoursReport::permissions()['get']
    );

    CRM_Core_Config::singleton()->userPermissionClass->permissions = [
      'access CiviCRM',
      'edit all volunteer projects',
    ];
    try {
      VolunteerHoursReport::get(TRUE)->setLimit(1)->execute();
      $this->fail('The report was readable without view all contacts.');
    }
    catch (Throwable $e) {
      $this->addToAssertionCount(1);
    }

    CRM_Core_Config::singleton()->userPermissionClass->permissions = [
      'access CiviCRM',
      'edit all volunteer projects',
      'view all contacts',
    ];
    VolunteerHoursReport::get(TRUE)->setLimit(1)->execute();
    $this->addToAssertionCount(1);
  }

  public function testHoursEntryStateOptionsAreAvailableToSearchKit(): void {
    // setLoadOptions(TRUE) yields a flat [id => label] map; the richer row shape
    // this asserts on has to be requested explicitly.
    $field = VolunteerHoursReport::getFields(FALSE)
      ->setLoadOptions(['id', 'name', 'label'])
      ->addWhere('name', '=', 'hours_entry_state')
      ->execute()
      ->single();
    $this->assertSame(
      ['logged', 'awaiting', 'not_due', 'not_required'],
      array_column($field['options'], 'name')
    );
  }

  public function testVolunteerProjectEntityReferenceSearchesByTitle(): void {
    $project = $this->createProject(['title' => 'Searchable hours report project']);
    $matches = \Civi\Api4\VolunteerProject::autocomplete(FALSE)
      ->setInput('Searchable hours report')
      ->execute();
    $this->assertContains((int) $project['id'], array_map('intval', $matches->column('id')));
    $this->assertContains('Searchable hours report project', $matches->column('label'));
  }

  /**
   * autocomplete shares the report's AND-ed permission pair.
   */
  public function testAutocompleteRequiresBothOrganizationWidePermissions(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = [
      'access CiviCRM',
      'edit all volunteer projects',
    ];
    try {
      VolunteerHoursReport::autocomplete(TRUE)->setInput('')->execute();
      $this->fail('autocomplete(TRUE) served a caller without view all contacts.');
    }
    catch (CRM_Core_Exception $e) {
      $this->addToAssertionCount(1);
    }

    CRM_Core_Config::singleton()->userPermissionClass->permissions = [
      'access CiviCRM',
      'edit all volunteer projects',
      'view all contacts',
    ];
    VolunteerHoursReport::autocomplete(TRUE)->setInput('')->execute();
    $this->addToAssertionCount(1);
  }

  /**
   * When the custom data storage is unavailable, the view shapes keep every
   * column (so getFields does not change shape) and expose no data.
   *
   * Uses only DML (deactivating the custom group), so it stays inside the
   * per-test transaction.
   */
  public function testDegradedViewKeepsItsColumnShape(): void {
    $viewSelect = new ReflectionMethod(VolunteerHoursReport::class, 'viewSelect');
    $viewSelect->setAccessible(TRUE);
    $viewFrom = new ReflectionMethod(VolunteerHoursReport::class, 'viewFrom');
    $viewFrom->setAccessible(TRUE);

    $availableColumns = array_column($viewSelect->invoke(NULL), 'name');
    $this->assertContains('hours_entry_state', $availableColumns);

    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_custom_group SET is_active = 0 WHERE name = 'CiviVolunteer' AND extends = 'Activity'"
    );
    try {
      $degradedColumns = array_column($viewSelect->invoke(NULL), 'name');
      $this->assertSame($availableColumns, $degradedColumns, 'getFields must not change shape when the view is degraded.');

      $degradedByColumn = [];
      foreach ($viewSelect->invoke(NULL) as $column) {
        $degradedByColumn[$column['name']] = $column['select'];
      }
      $this->assertSame('NULL', $degradedByColumn['logged_minutes']);
      $this->assertSame('FALSE', $degradedByColumn['awaiting_hours']);

      $this->assertStringContainsString(
        'WHERE 1 = 0',
        $viewFrom->invoke(NULL),
        'The degraded view must answer every query with no rows.'
      );
    }
    finally {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_custom_group SET is_active = 1 WHERE name = 'CiviVolunteer' AND extends = 'Activity'"
      );
    }
  }

  /**
   * View refreshes must be a single idempotent statement so concurrent API4
   * metadata-cache rebuilds cannot interleave DROP and CREATE operations.
   */
  public function testEntityTypesRebuildUsesCreateOrReplace(): void {
    $buildViewSql = new ReflectionMethod(VolunteerHoursReport::class, 'buildViewSql');
    $buildViewSql->setAccessible(TRUE);

    $this->assertStringStartsWith(
      'CREATE OR REPLACE VIEW `civicrm_view_volunteer_hours_report` AS SELECT ',
      $buildViewSql->invoke(NULL)
    );
  }

  /**
   * The entityTypes rebuild must survive missing custom data storage: it
   * drops a stale view and stands aside instead of fatalling, and the next
   * rebuild once the storage is back creates the view properly.
   *
   * This is the path that aborted every fresh installation before the
   * drop-and-stand-aside behaviour existed ("Table 'civicrm_volunteer_need'
   * doesn't exist" during install). Deliberately the last test in this
   * class: DROP/CREATE VIEW commit the test transaction, so anything this
   * test created would leak into the disposable database.
   *
   * @see VolunteerHoursReport::_on_civi_api4_entityTypes()
   */
  public function testEntityTypesRebuildSurvivesMissingCustomDataStorage(): void {
    $viewName = 'civicrm_view_volunteer_hours_report';
    $this->assertTrue(
      CRM_Core_DAO::checkTableExists($viewName),
      'The view should exist after installation.'
    );

    $event = new \Civi\Core\Event\GenericHookEvent(['entities' => []]);
    VolunteerHoursReport::_on_civi_api4_entityTypes($event);
    $this->assertTrue(
      CRM_Core_DAO::checkTableExists($viewName),
      'An existing view must be replaced successfully.'
    );

    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_custom_group SET is_active = 0 WHERE name = 'CiviVolunteer' AND extends = 'Activity'"
    );
    try {
      VolunteerHoursReport::_on_civi_api4_entityTypes($event);
      $this->assertFalse(
        CRM_Core_DAO::checkTableExists($viewName),
        'A stale view must be dropped, not left behind, when storage is missing.'
      );
    }
    finally {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_custom_group SET is_active = 1 WHERE name = 'CiviVolunteer' AND extends = 'Activity'"
      );
      VolunteerHoursReport::_on_civi_api4_entityTypes($event);
    }

    $this->assertTrue(
      CRM_Core_DAO::checkTableExists($viewName),
      'The next rebuild must create the view once the storage is back.'
    );
    VolunteerHoursReport::get(FALSE)->setLimit(1)->execute();
    $this->addToAssertionCount(1);
  }
}
