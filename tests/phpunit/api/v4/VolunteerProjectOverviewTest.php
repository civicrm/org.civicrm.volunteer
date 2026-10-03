<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerAssignment;

/**
 * Tests CRM_Volunteer_BAO_Project::getManageOverview(), the single bundle
 * behind the redesigned Manage Projects list and dashboard.
 *
 * Every test injects $now so the rolling seven- and fourteen-day windows are
 * deterministic rather than dependent on the day the suite runs.
 *
 * @group headless
 */
class api_v4_VolunteerProjectOverviewTest extends VolunteerTestAbstract {

  /**
   * A Tuesday at midday, in the site timezone.
   */
  private function now(): DateTimeImmutable {
    return new DateTimeImmutable('2027-06-15 12:00:00', new DateTimeZone(date_default_timezone_get()));
  }

  /**
   * A wall-clock string offset from the pinned "now".
   */
  private function shifted(string $modify): string {
    return $this->now()->modify($modify)->format('Y-m-d H:i:s');
  }

  private function overview(int $projectId): array {
    return CRM_Volunteer_BAO_Project::getManageOverview(
      array('id' => $projectId),
      FALSE,
      $this->now()
    );
  }

  private function getAnyRoleId(): int {
    $role = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id.name', '=', CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP)
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('weight', 'ASC')
      ->execute()
      ->first();
    $this->assertNotNull($role, 'No volunteer role is configured.');
    return (int) $role['value'];
  }

  private function makeProject(array $values = array()): int {
    $project = $this->createProject($values + array(
      'is_active' => 1,
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
    ));
    return (int) $project['id'];
  }

  private function makeNeed(int $projectId, array $values): int {
    $need = $this->createNeed($values + array(
      'project_id' => $projectId,
      'is_flexible' => FALSE,
      'is_active' => TRUE,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'role_id' => $this->getAnyRoleId(),
    ));
    return (int) $need['id'];
  }

  private function assign(int $needId, string $status = 'Scheduled'): int {
    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->addValue('status_id', $this->getOptionValue('activity_status', $status))
      ->execute()
      ->single();
    return (int) $created['id'];
  }

  private function setMinutes(int $assignmentId, $minutes): void {
    VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $assignmentId)
      ->addValue('time_completed_minutes', $minutes)
      ->execute();
  }

  /**
   * Scheduled and Available occupy a spot; the summary aggregates them.
   */
  public function testSummaryCountsStaffingForUpcomingShifts(): void {
    $projectId = $this->makeProject(array('title' => 'Overview staffing'));
    $needId = $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+1 day'),
      'duration' => 60,
      'quantity' => 4,
    ));
    $this->assign($needId, 'Scheduled');
    $this->assign($needId, 'Available');

    $overview = $this->overview($projectId);
    $summary = $overview['summary'];

    $this->assertSame(1, $summary['active_projects']);
    $this->assertSame(2, $summary['filled_spots_14_days']);
    $this->assertSame(4, $summary['total_spots_14_days']);
    $this->assertSame(1, $summary['projects_short'], 'Two spots remain open.');

    $project = $overview['projects'][0];
    $this->assertSame(2, $project['staffing']['filled']);
    $this->assertSame(4, $project['staffing']['total']);
    $this->assertSame(2, $project['staffing']['open']);
    $this->assertTrue((bool) $project['needs_volunteers']);
  }

  /**
   * A Completed assignment is history, not occupancy.
   */
  public function testCompletedAssignmentsDoNotCountAsFilled(): void {
    $projectId = $this->makeProject(array('title' => 'Overview completed'));
    $needId = $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+1 day'),
      'duration' => 60,
      'quantity' => 2,
    ));
    $assignmentId = $this->assign($needId, 'Scheduled');
    VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $assignmentId)
      ->addValue('status_id', $this->getOptionValue('activity_status', 'Completed'))
      ->execute();

    $project = $this->overview($projectId)['projects'][0];
    $this->assertSame(0, $project['staffing']['filled']);
    $this->assertSame(2, $project['staffing']['open']);
  }

  /**
   * The fourteen-day window is half-open: [now, now + 14 days).
   */
  public function testFourteenDayWindowIsHalfOpen(): void {
    $projectId = $this->makeProject(array('title' => 'Overview fortnight'));
    $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+13 days'),
      'duration' => 60,
      'quantity' => 3,
    ));
    $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+14 days'),
      'duration' => 60,
      'quantity' => 7,
    ));

    $summary = $this->overview($projectId)['summary'];
    $this->assertSame(
      3,
      $summary['total_spots_14_days'],
      'A shift starting exactly 14 days out is outside the window.'
    );
  }

  /**
   * "This week" is the rolling seven-day window, capped at five shifts.
   */
  public function testThisWeekCoversSevenDaysAndCapsAtFive(): void {
    $projectId = $this->makeProject(array('title' => 'Overview this week'));
    foreach (array(1, 2, 3, 4, 5, 6) as $day) {
      $this->makeNeed($projectId, array(
        'start_time' => $this->shifted("+$day days"),
        'duration' => 60,
        'quantity' => 2,
      ));
    }
    $outsideId = $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+8 days'),
      'duration' => 60,
      'quantity' => 2,
    ));

    $thisWeek = $this->overview($projectId)['this_week'];
    $this->assertCount(5, $thisWeek, 'This week shows at most five shifts.');
    $this->assertNotContains(
      $outsideId,
      array_map('intval', array_column($thisWeek, 'need_id')),
      'A shift eight days out is beyond the seven-day window.'
    );
    // Ordered by start time.
    $starts = array_column($thisWeek, 'start_time');
    $sorted = $starts;
    sort($sorted);
    $this->assertSame($sorted, $starts);
  }

  /**
   * A recorded zero is a logged value; only NULL means nobody logged hours.
   */
  public function testZeroMinutesCountsAsLoggedButNullDoesNot(): void {
    $projectId = $this->makeProject(array('title' => 'Overview hours'));

    $awaitingNeedId = $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('-3 hours'),
      'duration' => 60,
      'quantity' => 2,
    ));
    $this->assign($awaitingNeedId, 'Scheduled');

    $loggedNeedId = $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('-4 hours'),
      'duration' => 60,
      'quantity' => 2,
    ));
    $this->setMinutes($this->assign($loggedNeedId, 'Scheduled'), 0);

    $overview = $this->overview($projectId);
    $this->assertSame(
      1,
      $overview['summary']['shifts_awaiting_hours'],
      'Only the shift with a NULL minute value is awaiting hours.'
    );

    $hourItems = array_values(array_filter($overview['attention'], static function(array $item) {
      return $item['action_type'] === 'hours';
    }));
    $this->assertCount(1, $hourItems);
    $this->assertSame($awaitingNeedId, (int) $hourItems[0]['need_id']);
    $this->assertSame(1, $hourItems[0]['missing_hours']);
  }

  /**
   * The attention queue keeps the three items whose action date is nearest now.
   */
  public function testAttentionQueueIsCappedAtThreeAndSortedByProximity(): void {
    $projectId = $this->makeProject(array('title' => 'Overview attention'));
    $needIds = array();
    foreach (array(1, 2, 3, 4) as $day) {
      $needIds[$day] = $this->makeNeed($projectId, array(
        'start_time' => $this->shifted("+$day days"),
        'duration' => 60,
        'quantity' => 2,
      ));
    }

    $attention = $this->overview($projectId)['attention'];
    $this->assertCount(3, $attention);
    $this->assertSame(
      array($needIds[1], $needIds[2], $needIds[3]),
      array_map('intval', array_column($attention, 'need_id')),
      'The three soonest understaffed shifts come first, in order.'
    );
    foreach ($attention as $item) {
      $this->assertSame('staffing', $item['action_type']);
      $this->assertSame(2, $item['open']);
    }
  }

  /**
   * Overdue work sorts ahead of upcoming work at the same distance from now.
   */
  public function testOverdueWorkWinsTies(): void {
    $projectId = $this->makeProject(array('title' => 'Overview ties'));

    // Ends exactly two hours before now, with hours unlogged.
    $overdueId = $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('-3 hours'),
      'duration' => 60,
      'quantity' => 2,
    ));
    $this->assign($overdueId, 'Scheduled');

    // Starts exactly two hours after now, with spots open.
    $upcomingId = $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+2 hours'),
      'duration' => 60,
      'quantity' => 2,
    ));

    $attention = $this->overview($projectId)['attention'];
    $this->assertSame(
      array($overdueId, $upcomingId),
      array_map('intval', array_column($attention, 'need_id'))
    );
  }

  /**
   * Beneficiary names are returned keyed by contact ID.
   *
   * The names used to come back as a positionally-packed list alongside the
   * 'beneficiaries' ID list, and the two were zipped by index. Any beneficiary
   * whose name did not survive that packing -- array_filter() drops a blank
   * display_name as readily as a missing one -- collapsed the list and shifted
   * every later name onto the wrong contact.
   */
  public function testBeneficiaryNamesArePairedByContactId(): void {
    $blankId = $this->individualCreate(array('first_name' => 'Blank', 'last_name' => 'Name'));
    $keptId = $this->individualCreate(array('first_name' => 'Still', 'last_name' => 'Here'));

    // Blank the display name behind CiviCRM's back; it rebuilds one on save.
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_contact SET display_name = '' WHERE id = %1",
      array(1 => array($blankId, 'Integer'))
    );

    $projectId = $this->makeProject(array(
      'title' => 'Overview beneficiaries',
      'project_contacts' => array(
        'volunteer_owner' => array($this->getMockedContactId()),
        // The blank-named contact sorts first, so a positional zip would hand
        // its slot to the next name along.
        'volunteer_beneficiary' => array($blankId, $keptId),
      ),
    ));

    $project = $this->overview($projectId)['projects'][0];

    $this->assertArrayHasKey('beneficiary_options', $project);
    $this->assertSame(
      'Still Here',
      $project['beneficiary_options'][$keptId] ?? NULL,
      'The named beneficiary must keep its own name, not inherit a neighbour\'s.'
    );
    $this->assertSame(
      '',
      $project['beneficiary_options'][$blankId] ?? NULL,
      'A blank name is still that contact\'s name, not a reason to re-pack.'
    );
    foreach (array_keys($project['beneficiary_options']) as $contactId) {
      $this->assertContains(
        (int) $contactId,
        array_map('intval', $project['beneficiaries']),
        'Every option key must be one of the project\'s beneficiary IDs.'
      );
    }
  }

  /**
   * List-row enrichment: next shift, upcoming roles, and the campaign label.
   */
  public function testProjectRowsCarryListDisplayFields(): void {
    $projectId = $this->makeProject(array('title' => 'Overview enrichment'));
    $soonId = $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+2 days'),
      'duration' => 60,
      'quantity' => 2,
    ));
    $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+5 days'),
      'duration' => 60,
      'quantity' => 2,
    ));

    $project = $this->overview($projectId)['projects'][0];
    $this->assertSame($soonId, (int) $project['next_shift']['need_id'], 'Next shift is the soonest future one.');
    $this->assertNotEmpty($project['upcoming_roles']);
    $this->assertSame(
      array_values(array_unique($project['upcoming_roles'])),
      $project['upcoming_roles'],
      'Upcoming roles are deduplicated.'
    );
    $this->assertArrayHasKey('campaign_label', $project);
  }

  /**
   * Up Next is one row per project, capped at four.
   */
  public function testUpNextIsOneRowPerProjectCappedAtFour(): void {
    $projectId = $this->makeProject(array('title' => 'Overview up next'));
    $this->makeNeed($projectId, array('start_time' => $this->shifted('+1 day'), 'duration' => 60, 'quantity' => 2));
    $this->makeNeed($projectId, array('start_time' => $this->shifted('+2 days'), 'duration' => 60, 'quantity' => 2));

    $upNext = $this->overview($projectId)['up_next'];
    $this->assertCount(1, $upNext, 'Up Next lists projects, not shifts.');
    $this->assertSame($projectId, (int) $upNext[0]['project_id']);
    $this->assertLessThanOrEqual(4, count($upNext));
  }

  /**
   * An inactive project is excluded from every derived section.
   */
  public function testInactiveProjectsAreExcludedFromDerivedSections(): void {
    $projectId = $this->makeProject(array('title' => 'Overview archived', 'is_active' => 0));
    $this->makeNeed($projectId, array(
      'start_time' => $this->shifted('+1 day'),
      'duration' => 60,
      'quantity' => 5,
    ));

    $overview = $this->overview($projectId);
    $this->assertSame(0, $overview['summary']['active_projects']);
    $this->assertSame(0, $overview['summary']['total_spots_14_days']);
    $this->assertSame(0, $overview['summary']['projects_short']);
    $this->assertSame(array(), $overview['attention']);
    $this->assertSame(array(), $overview['up_next']);
    $this->assertSame(array(), $overview['this_week']);
    // The row itself still appears, so the Archived filter can show it.
    $this->assertCount(1, $overview['projects']);
  }

  /**
   * The overview skips the per-project associated-entity lookup.
   *
   * getEntityAttributes() issues one Civi\Api4\Event::get() per event-linked
   * project. The list and dashboard render no associated-entity column, so
   * that N+1 was pure cost on every load and every post-bulk refresh.
   */
  public function testOverviewSkipsAssociatedEntityLookup(): void {
    $projectId = $this->makeProject(array('title' => 'Overview entity skip'));

    $project = $this->overview($projectId)['projects'][0];
    $this->assertArrayNotHasKey('entity_attributes', $project);

    // getManageData still carries it, so nothing else loses the field.
    $managed = CRM_Volunteer_BAO_Project::getManageData(array('id' => $projectId), FALSE);
    $this->assertArrayHasKey('entity_attributes', reset($managed));
  }

  /**
   * An empty result set returns a fully zeroed bundle, not a warning.
   */
  public function testEmptyDatasetReturnsZeroedSummary(): void {
    $overview = CRM_Volunteer_BAO_Project::getManageOverview(
      array('id' => 999999999),
      FALSE,
      $this->now()
    );

    $this->assertSame(0, $overview['summary']['active_projects']);
    $this->assertSame(0, $overview['summary']['filled_spots_14_days']);
    $this->assertSame(0, $overview['summary']['total_spots_14_days']);
    $this->assertSame(0, $overview['summary']['projects_short']);
    $this->assertSame(0, $overview['summary']['shifts_awaiting_hours']);
    $this->assertSame(array(), $overview['projects']);
    $this->assertSame(array(), $overview['attention']);
    $this->assertSame(array(), $overview['up_next']);
    $this->assertSame(array(), $overview['this_week']);
  }

  /**
   * The bundle reports the timezone its windows were calculated in.
   */
  public function testOverviewReportsItsCalculationTimezone(): void {
    $overview = CRM_Volunteer_BAO_Project::getManageOverview(
      array('id' => 999999999),
      FALSE,
      $this->now()
    );
    $this->assertSame(date_default_timezone_get(), $overview['timezone']);
    $this->assertSame('2027-06-15 12:00:00', $overview['generated_at']);
  }

}
