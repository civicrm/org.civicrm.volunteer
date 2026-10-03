<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerNeed;

/**
 * Tests the VolunteerNeed.search API4 action and the shared display
 * enrichment applied to every need read.
 *
 * @group headless
 */
class api_v4_VolunteerNeedSearchTest extends VolunteerTestAbstract {

  /**
   * @return array
   *   [projectId, datedNeedId, flexibleNeedId, beneficiaryId]
   */
  private function createSearchableProject(): array {
    $beneficiaryId = $this->individualCreate(array(
      'first_name' => 'Bea',
      'last_name' => 'Search',
    ));
    $project = $this->createProject(array(
      'title' => 'Search fixture project',
      'is_active' => 1,
      'project_contacts' => array(
        'volunteer_owner' => array($this->getMockedContactId()),
        'volunteer_beneficiary' => array($beneficiaryId),
      ),
    ));
    $projectId = (int) $project['id'];
    $need = $this->createNeed(array(
      'project_id' => $projectId,
      'start_time' => date('Y-m-d H:i:s', strtotime('+21 days')),
      'duration' => 90,
      'is_flexible' => FALSE,
      'quantity' => 3,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => TRUE,
      'role_id' => $this->getAnyRoleId(),
    ));

    return array(
      $projectId,
      (int) $need['id'],
      (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId),
      $beneficiaryId,
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

  public function testSearchByProjectReturnsOpenNeeds(): void {
    [$projectId, $needId] = $this->createSearchableProject();

    $rows = VolunteerNeed::search(FALSE)
      ->setProject($projectId)
      ->execute();

    $ids = array_map('intval', $rows->column('id'));
    $this->assertContains($needId, $ids);
  }

  public function testSearchByBeneficiaryAndRole(): void {
    [$projectId, $needId, , $beneficiaryId] = $this->createSearchableProject();
    $roleId = (int) VolunteerNeed::get(FALSE)
      ->addSelect('role_id')
      ->addWhere('id', '=', $needId)
      ->execute()
      ->single()['role_id'];

    $byBeneficiary = VolunteerNeed::search(FALSE)
      ->setBeneficiary($beneficiaryId)
      ->execute()
      ->column('id');
    $this->assertContains($needId, array_map('intval', $byBeneficiary));

    $byRole = VolunteerNeed::search(FALSE)
      ->setProject($projectId)
      ->setRoleId($roleId)
      ->execute()
      ->column('id');
    $this->assertContains($needId, array_map('intval', $byRole));

    // Multi-select and bookmarked role_id[] filters arrive at API4 as arrays.
    // This also exercises the action's reflected parameter metadata, which
    // must advertise the API4-supported `array` type rather than `int[]`.
    $byRoleArray = VolunteerNeed::search(FALSE)
      ->setProject($projectId)
      ->setRoleId(array($roleId))
      ->execute()
      ->column('id');
    $this->assertContains($needId, array_map('intval', $byRoleArray));

    $byBeneficiaryArray = VolunteerNeed::search(FALSE)
      ->setBeneficiary(array($beneficiaryId))
      ->execute()
      ->column('id');
    $this->assertContains($needId, array_map('intval', $byBeneficiaryArray));
  }

  /**
   * The end-date filter covers the whole of the chosen day.
   */
  public function testSearchDateRangeIsInclusive(): void {
    $project = $this->createProject(array('title' => 'Date range project', 'is_active' => 1));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2027-03-15 18:30:00',
      'duration' => 60,
      'is_flexible' => FALSE,
      'quantity' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => TRUE,
    ));

    $found = VolunteerNeed::search(FALSE)
      ->setProject($project['id'])
      ->setDateStart('2027-03-15')
      ->setDateEnd('2027-03-15')
      ->execute()
      ->column('id');
    $this->assertContains((int) $need['id'], array_map('intval', $found));
  }

  /**
   * Every need read carries the shared display fields.
   */
  public function testDisplayEnrichmentIsAppliedToReadsAndSearches(): void {
    [$projectId, $needId, $flexibleNeedId] = $this->createSearchableProject();

    $dated = VolunteerNeed::get(FALSE)
      ->addSelect('*')
      ->addWhere('id', '=', $needId)
      ->execute()
      ->single();
    $this->assertNotEmpty($dated['display_time']);
    $this->assertNotEmpty($dated['role_label']);
    $this->assertArrayHasKey('role_description', $dated);

    $flexible = VolunteerNeed::get(FALSE)
      ->addSelect('*')
      ->addWhere('id', '=', $flexibleNeedId)
      ->execute()
      ->single();
    $this->assertSame(CRM_Volunteer_BAO_Need::getFlexibleDisplayTime(), $flexible['display_time']);
    $this->assertSame(CRM_Volunteer_BAO_Need::getFlexibleRoleLabel(), $flexible['role_label']);

    // The same enrichment reaches the project aggregate's needs.
    $project = CRM_Volunteer_BAO_Project::retrieveByID($projectId);
    $needs = $project->needs;
    $this->assertArrayHasKey($needId, $needs);
    $this->assertSame($dated['display_time'], $needs[$needId]['display_time']);
    $this->assertSame($dated['role_label'], $needs[$needId]['role_label']);

    // And the search result, which comes from the search service.
    $searched = VolunteerNeed::search(FALSE)
      ->setProject($projectId)
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();
    $this->assertArrayHasKey($needId, $searched);
    $this->assertNotEmpty($searched[$needId]['display_time']);
    $this->assertNotEmpty($searched[$needId]['role_label']);
  }

  /**
   * The role description is purified, so stored markup cannot inject script.
   */
  public function testRoleDescriptionIsPurified(): void {
    $optionGroupId = (int) \Civi\Api4\OptionGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP)
      ->execute()
      ->single()['id'];
    // Both name and value are unique within the option group, and a row left
    // behind by a run whose transaction never rolled back made this fail with
    // "volunteer_role: Value already exists in the database: 9987" from then
    // on. Neither value matters to what this test asserts, so derive both.
    $nextValue = 1 + (int) CRM_Core_DAO::singleValueQuery(
      'SELECT COALESCE(MAX(CAST(value AS SIGNED)), 0)
         FROM civicrm_option_value WHERE option_group_id = %1',
      array(1 => array($optionGroupId, 'Integer'))
    );
    $role = \Civi\Api4\OptionValue::create(FALSE)
      ->addValue('option_group_id', $optionGroupId)
      ->addValue('label', 'Scripted role')
      ->addValue('name', uniqid('scripted_role_', FALSE))
      ->addValue('value', $nextValue)
      ->addValue('description', '<p>Safe</p><script>bad()</script>')
      ->addValue('is_active', TRUE)
      ->execute()
      ->single();
    // The role option list is memoised per request; a role added mid-request
    // is only visible once that memo is dropped.
    unset(Civi::$statics['CRM_Volunteer_BAO_Need']);

    $project = $this->createProject(array('title' => 'Purified role project', 'is_active' => 1));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2027-04-01 10:00:00',
      'duration' => 60,
      'is_flexible' => FALSE,
      'quantity' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => TRUE,
      'role_id' => $role['value'],
    ));

    $read = VolunteerNeed::get(FALSE)
      ->addSelect('*')
      ->addWhere('id', '=', $need['id'])
      ->execute()
      ->single();
    $this->assertSame('Scripted role', $read['role_label']);
    $this->assertStringNotContainsString('<script', (string) $read['role_description']);
  }

  /**
   * Build one project holding four needs that exercise every timeFilter branch.
   *
   * Dates are pinned rather than relative so the weekday and 5:00 PM boundary
   * assertions cannot drift with the day the suite happens to run.
   *
   * @return array
   *   [projectId, [label => needId]]
   */
  private function createScheduleFixture(): array {
    $project = $this->createProject(array('title' => 'Schedule filter project', 'is_active' => 1));
    $projectId = (int) $project['id'];
    $public = $this->getOptionValue('visibility', 'public');

    $make = function(array $values) use ($projectId, $public) {
      $need = $this->createNeed($values + array(
        'project_id' => $projectId,
        'is_flexible' => FALSE,
        'quantity' => 5,
        'visibility_id' => $public,
        'is_active' => TRUE,
      ));
      return (int) $need['id'];
    };

    $needs = array(
      // Friday 2027-03-12, 16:59 — weekday, before the evening boundary.
      'weekday_afternoon' => $make(array('start_time' => '2027-03-12 16:59:00', 'duration' => 60)),
      // Friday 2027-03-12, 17:00 — the inclusive evening boundary.
      'weekday_evening' => $make(array('start_time' => '2027-03-12 17:00:00', 'duration' => 60)),
      // Saturday 2027-03-13, 09:00 — weekend, not an evening.
      'weekend_morning' => $make(array('start_time' => '2027-03-13 09:00:00', 'duration' => 60)),
      // Sunday 2027-03-14, 20:00 — weekend and evening.
      'weekend_evening' => $make(array('start_time' => '2027-03-14 20:00:00', 'duration' => 60)),
      // A window need: start and end, no duration.
      'window' => $make(array(
        'start_time' => '2027-03-15 09:00:00',
        'end_time' => '2027-03-17 17:00:00',
      )),
      // An ongoing need: neither end_time nor duration.
      'ongoing' => $make(array('start_time' => '2027-03-16 09:00:00')),
    );

    return array($projectId, $needs);
  }

  private function searchIds(int $projectId, string $timeFilter): array {
    return array_map('intval', VolunteerNeed::search(FALSE)
      ->setProject($projectId)
      ->setTimeFilter($timeFilter)
      ->execute()
      ->column('id'));
  }

  /**
   * timeFilter=all changes nothing.
   */
  public function testTimeFilterAllReturnsEveryOpenNeed(): void {
    [$projectId, $needs] = $this->createScheduleFixture();
    $found = $this->searchIds($projectId, 'all');
    foreach ($needs as $label => $needId) {
      $this->assertContains($needId, $found, "timeFilter=all dropped the $label need.");
    }
  }

  /**
   * Weekends are Saturday and Sunday, by ISO weekday.
   */
  public function testTimeFilterWeekends(): void {
    [$projectId, $needs] = $this->createScheduleFixture();
    $found = $this->searchIds($projectId, 'weekends');

    $this->assertContains($needs['weekend_morning'], $found, 'Saturday must match.');
    $this->assertContains($needs['weekend_evening'], $found, 'Sunday must match.');
    $this->assertNotContains($needs['weekday_afternoon'], $found, 'Friday must not match.');
    $this->assertNotContains($needs['weekday_evening'], $found, 'Friday must not match.');
  }

  /**
   * The evening boundary is 5:00 PM inclusive.
   */
  public function testTimeFilterEveningsBoundaryIsInclusive(): void {
    [$projectId, $needs] = $this->createScheduleFixture();
    $found = $this->searchIds($projectId, 'evenings');

    $this->assertContains($needs['weekday_evening'], $found, '17:00 is an evening.');
    $this->assertContains($needs['weekend_evening'], $found, '20:00 is an evening.');
    $this->assertNotContains($needs['weekday_afternoon'], $found, '16:59 is not an evening.');
    $this->assertNotContains($needs['weekend_morning'], $found, '09:00 is not an evening.');
  }

  /**
   * "No fixed time" means window or ongoing, never a fixed start+duration.
   */
  public function testTimeFilterNoFixedTimeClassification(): void {
    [$projectId, $needs] = $this->createScheduleFixture();
    $found = $this->searchIds($projectId, 'no_fixed_time');

    $this->assertContains($needs['window'], $found, 'A window need has no fixed time.');
    $this->assertContains($needs['ongoing'], $found, 'An ongoing need has no fixed time.');
    foreach (array('weekday_afternoon', 'weekday_evening', 'weekend_morning', 'weekend_evening') as $label) {
      $this->assertNotContains($needs[$label], $found, "The $label need has a fixed time.");
    }
  }

  /**
   * General availability is offered whichever quick filter is active.
   */
  public function testTimeFilterKeepsGeneralAvailability(): void {
    [$projectId] = $this->createScheduleFixture();
    $flexibleId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId);

    // A project's flexible need is created 'admin' visible, so it is absent
    // from public search until someone publishes it. Publish it here: the
    // point of this test is that the quick time filters never hide general
    // availability, not that it is offered by default.
    \Civi\Api4\VolunteerNeed::update(FALSE)
      ->addWhere('id', '=', $flexibleId)
      ->addValue('visibility_id', $this->getOptionValue('visibility', 'public'))
      ->execute();

    foreach (array('all', 'weekends', 'evenings', 'no_fixed_time') as $filter) {
      $this->assertContains(
        $flexibleId,
        $this->searchIds($projectId, $filter),
        "timeFilter=$filter hid the project's general availability."
      );
    }
  }

  /**
   * timeFilter composes with the other search criteria rather than replacing them.
   */
  public function testTimeFilterCombinesWithDateRange(): void {
    [$projectId, $needs] = $this->createScheduleFixture();

    $found = array_map('intval', VolunteerNeed::search(FALSE)
      ->setProject($projectId)
      ->setTimeFilter('weekends')
      ->setDateStart('2027-03-14')
      ->setDateEnd('2027-03-14')
      ->execute()
      ->column('id'));

    $this->assertContains($needs['weekend_evening'], $found, 'Sunday is in range and is a weekend.');
    $this->assertNotContains($needs['weekend_morning'], $found, 'Saturday is outside the date range.');
  }

  /**
   * An unrecognised timeFilter falls back to "all" rather than filtering everything out.
   */
  public function testUnknownTimeFilterFallsBackToAll(): void {
    [$projectId, $needs] = $this->createScheduleFixture();
    $found = $this->searchIds($projectId, 'no-such-filter');
    $this->assertContains($needs['weekday_afternoon'], $found);
    $this->assertContains($needs['weekend_morning'], $found);
  }

  /**
   * search() requires the public volunteer permission when checking.
   */
  public function testSearchRequiresRegistrationPermission(): void {
    $project = $this->createProject(array('title' => 'Guarded search', 'is_active' => 1));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');
    $this->expectException(Exception::class);
    VolunteerNeed::search(TRUE)->setProject($project['id'])->execute();
  }

}
