<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerAssignment;

/**
 * CRUD and authorization tests for the VolunteerAssignment API4 entity.
 *
 * Assignments are Activity records decorated with CiviVolunteer custom fields,
 * so this entity is a BasicEntity over the shared assignment service rather
 * than a DAO entity.
 *
 * @group headless
 */
class api_v4_VolunteerAssignmentTest extends VolunteerTestAbstract {

  /**
   * @return array
   *   [projectId, needId]
   */
  private function createAssignableNeed(): array {
    $project = $this->createProject(array(
      'title' => 'API4 assignment project',
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2027-02-11 09:00:00',
      'duration' => 120,
      'is_flexible' => FALSE,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => TRUE,
    ));
    return array((int) $project['id'], (int) $need['id']);
  }

  public function testTrustedCrudRoundTrip(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $volunteerId = $this->individualCreate();

    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $volunteerId)
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();

    $assignmentId = (int) $created['id'];
    $this->assertGreaterThan(0, $assignmentId);
    $this->assertSame($needId, (int) $created['volunteer_need_id']);
    $this->assertSame($projectId, (int) $created['project_id']);
    $this->assertSame($volunteerId, (int) $created['assignee_contact_id']);

    $read = VolunteerAssignment::get(FALSE)
      ->addWhere('id', '=', $assignmentId)
      ->execute()
      ->single();
    $this->assertSame($assignmentId, (int) $read['id']);
    // The service joins the assignee's contact record for roster display.
    $this->assertNotEmpty($read['assignee_sort_name']);
    $this->assertNotEmpty($read['assignee_display_name']);

    $updated = VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $assignmentId)
      ->addValue('time_completed_minutes', 45)
      ->execute()
      ->single();
    $this->assertSame($assignmentId, (int) $updated['id']);
    $this->assertSame(45, (int) $updated['time_completed_minutes']);

    VolunteerAssignment::delete(FALSE)
      ->addWhere('id', '=', $assignmentId)
      ->execute();
    $this->assertCount(
      0,
      VolunteerAssignment::get(FALSE)->addWhere('id', '=', $assignmentId)->execute()
    );
  }

  /**
   * `contact_id` remains a writable alias for `assignee_contact_id`.
   */
  public function testLegacyContactIdAliasIsAccepted(): void {
    [, $needId] = $this->createAssignableNeed();
    $volunteerId = $this->individualCreate();

    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('contact_id', $volunteerId)
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();

    $this->assertSame($volunteerId, (int) $created['assignee_contact_id']);
  }

  /**
   * getFields() must not advertise the alias as read-only, because create and
   * update both accept it.
   */
  public function testContactIdIsAdvertisedAsWritable(): void {
    $fields = VolunteerAssignment::getFields(FALSE)->execute()->indexBy('name');
    $this->assertArrayHasKey('contact_id', (array) $fields);
    $this->assertFalse((bool) $fields['contact_id']['readonly']);
    // Fields the service derives from the need or the project stay read-only.
    $this->assertTrue((bool) $fields['project_id']['readonly']);
    $this->assertTrue((bool) $fields['is_flexible']['readonly']);
    // The joined contact columns keep their activity-role prefix.
    $this->assertArrayHasKey('assignee_display_name', (array) $fields);
    $this->assertArrayNotHasKey('display_name', (array) $fields);
  }

  /**
   * A read has to name a project scope; an unscoped read is refused.
   */
  public function testCheckedReadRequiresAProjectScope(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'edit all volunteer projects',
    );

    $this->expectException(CRM_Core_Exception::class);
    VolunteerAssignment::get(TRUE)->execute();
  }

  public function testProjectOwnerCanReadAndWriteAssignments(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $volunteerId = $this->individualCreate();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'view all contacts',
      'edit contacts',
      'create volunteer projects',
      'edit own volunteer projects',
    );

    $created = VolunteerAssignment::create(TRUE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $volunteerId)
      ->execute()
      ->single();

    $read = VolunteerAssignment::get(TRUE)
      ->addWhere('project_id', '=', $projectId)
      ->execute()
      ->column('id');
    $this->assertSame(array((int) $created['id']), array_map('intval', $read));
  }

  /**
   * A permission-checked delete authorizes each row against its owning
   * project, so the batch lookup must resolve project_id alongside the
   * primary key. The inherited batch select returns only the id, which used
   * to fail authorization for every caller -- project editors included.
   */
  public function testProjectEditorCanDeleteAssignments(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $volunteerId = $this->individualCreate();

    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $volunteerId)
      ->execute()
      ->single();
    $assignmentId = (int) $created['id'];

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'view all contacts',
      'edit all volunteer projects',
    );

    VolunteerAssignment::delete(TRUE)
      ->addWhere('id', '=', $assignmentId)
      ->execute();

    $this->assertCount(
      0,
      VolunteerAssignment::get(FALSE)->addWhere('id', '=', $assignmentId)->execute()
    );
  }

  public function testStrangerCannotReadOrWriteAssignments(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $volunteerId = $this->individualCreate();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'register to volunteer',
    );

    try {
      VolunteerAssignment::get(TRUE)->addWhere('project_id', '=', $projectId)->execute();
      $this->fail('A volunteer without project rights read the roster.');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('permission', $e->getMessage());
    }

    try {
      VolunteerAssignment::create(TRUE)
        ->addValue('volunteer_need_id', $needId)
        ->addValue('assignee_contact_id', $volunteerId)
        ->execute();
      $this->fail('A volunteer without project rights created an assignment.');
    }
    catch (Throwable $e) {
      $this->addToAssertionCount(1);
    }
  }

  /**
   * Reads are scoped to the statuses that occupy a place, as APIv3's were.
   *
   * CRM_Volunteer_BAO_Assignment::retrieve() is a roster query: it returns
   * only Scheduled and Available assignments and never trashed ones. Moving an
   * assignment to Completed therefore removes it from this entity's reads even
   * though the activity survives -- which is what preserves volunteer history.
   */
  public function testReadsCoverOnlyPlaceOccupyingStatuses(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();

    $this->assertCount(
      1,
      VolunteerAssignment::get(FALSE)->addWhere('project_id', '=', $projectId)->execute()
    );

    VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $created['id'])
      ->addValue('status_id', $this->getOptionValue('activity_status', 'Completed'))
      ->execute();

    $this->assertCount(
      0,
      VolunteerAssignment::get(FALSE)->addWhere('project_id', '=', $projectId)->execute()
    );
    // The underlying activity is still there.
    $this->assertCount(
      1,
      \Civi\Api4\Activity::get(FALSE)->addWhere('id', '=', $created['id'])->execute()
    );
  }

  /**
   * The generic save and replace actions are refused: they would bypass the
   * assignment service's own capacity and authorization checks.
   */
  public function testGenericWritesAreDenied(): void {
    $permissions = VolunteerAssignment::permissions();
    $this->assertSame(CRM_Core_Permission::ALWAYS_DENY_PERMISSION, $permissions['save']);
    $this->assertSame(CRM_Core_Permission::ALWAYS_DENY_PERMISSION, $permissions['replace']);
  }

  /**
   * Capacity is enforced on the API4 path, not only in the signup form.
   */
  public function testCapacityIsEnforced(): void {
    [, $needId] = $this->createAssignableNeed();

    foreach (array(1, 2) as $ignored) {
      VolunteerAssignment::create(FALSE)
        ->addValue('volunteer_need_id', $needId)
        ->addValue('assignee_contact_id', $this->individualCreate())
        ->addValue('source_contact_id', $this->getMockedContactId())
        ->execute();
    }

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('no remaining places');
    VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute();
  }

  /**
   * Reporting actions retain rows after attendance changes their status.
   */
  public function testRosterAndHoursIncludeHistoricalStatuses(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');
    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();

    VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $created['id'])
      ->addValue('status_id', $completedStatusId)
      ->execute();

    $roster = VolunteerAssignment::getRoster(FALSE)
      ->setProjectId($projectId)
      ->setIncludePast(TRUE)
      ->execute()
      ->single();
    $this->assertSame('Attended', $roster['rows'][0]['status_label']);

    $hours = VolunteerAssignment::getHourEntries(FALSE)
      ->setProjectId($projectId)
      ->setVolunteerNeedId($needId)
      ->execute()
      ->single();
    $this->assertSame((int) $created['id'], (int) $hours['rows'][0]['id']);
    $this->assertSame($completedStatusId, (int) $hours['rows'][0]['status_id']);
  }

  /**
   * A walk-in may exceed capacity through logHours, while normal assignment
   * creation remains capacity constrained.
   */
  public function testLogHoursCanRecordWalkInOverCapacity(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    \Civi\Api4\VolunteerNeed::update(FALSE)
      ->addWhere('id', '=', $needId)
      ->addValue('quantity', 1)
      ->execute();

    VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute();

    $walkInId = $this->individualCreate();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');
    $result = VolunteerAssignment::logHours(FALSE)
      ->setProjectId($projectId)
      ->setVolunteerNeedId($needId)
      ->setEntries(array(array(
        'assignee_contact_id' => $walkInId,
        'volunteer_need_id' => $needId,
        'status_id' => $completedStatusId,
        'time_completed_minutes' => 95,
      )))
      ->execute()
      ->single();

    $walkIn = array_values(array_filter($result['rows'], static function(array $row) use ($walkInId) {
      return (int) $row['assignee_contact_id'] === $walkInId;
    }));
    $this->assertCount(1, $walkIn);
    $this->assertSame(95, (int) $walkIn[0]['time_completed_minutes']);

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('no remaining places');
    VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute();
  }

  /**
   * A walk-in is dated to the shift it is logged against. Without an explicit
   * activity_date_time, setActivityDefaults() falls back to the project start
   * time or to tomorrow, which would date a walk-in worse than an assigned
   * volunteer.
   */
  public function testWalkInIsDatedToItsShift(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');

    $result = VolunteerAssignment::logHours(FALSE)
      ->setProjectId($projectId)
      ->setVolunteerNeedId($needId)
      ->setEntries(array(array(
        'assignee_contact_id' => $this->individualCreate(),
        'volunteer_need_id' => $needId,
        'status_id' => $completedStatusId,
        'time_completed_minutes' => 60,
      )))
      ->execute()
      ->single();

    $this->assertCount(1, $result['rows']);
    $this->assertSame('2027-02-11 09:00:00', $result['rows'][0]['activity_date_time']);
  }

  /**
   * The capacity bypass exists so an event that already happened can be
   * recorded. Scheduled and Available consume a need's capacity, so logHours
   * must not become a general over-assignment channel for them.
   */
  public function testLogHoursCannotExceedCapacityForScheduledStatus(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    \Civi\Api4\VolunteerNeed::update(FALSE)
      ->addWhere('id', '=', $needId)
      ->addValue('quantity', 1)
      ->execute();

    VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute();

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('no remaining places');
    VolunteerAssignment::logHours(FALSE)
      ->setProjectId($projectId)
      ->setVolunteerNeedId($needId)
      ->setEntries(array(array(
        'assignee_contact_id' => $this->individualCreate(),
        'volunteer_need_id' => $needId,
        'status_id' => $this->getOptionValue('activity_status', 'Scheduled'),
      )))
      ->execute();
  }

  /**
   * The hour-log dropdown must not offer a disabled activity status, but a row
   * already holding one keeps it so re-saving does not rewrite its status.
   */
  public function testDisabledStatusesAreNotOfferedButRemainSelectable(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $statuses = VolunteerAssignment::getHourEntries(FALSE)
      ->setProjectId($projectId)
      ->setVolunteerNeedId($needId)
      ->execute()
      ->single()['statuses'];
    $offeredNames = array_column($statuses, 'name');

    $disabled = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('name')
      ->addWhere('option_group_id.name', '=', 'activity_status')
      ->addWhere('is_active', '=', FALSE)
      ->execute();
    foreach ($disabled as $option) {
      $this->assertNotContains($option['name'], $offeredNames);
    }
    $this->assertContains('Completed', $offeredNames);
  }

  /**
   * getCapacity counts every status, unlike get(), so a project whose
   * volunteers have been marked Attended still reports its spots as filled.
   */
  public function testGetCapacityCountsEveryStatus(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();

    $before = VolunteerAssignment::getCapacity(FALSE)
      ->setProjectId($projectId)
      ->execute()
      ->single();
    $this->assertSame(2, (int) $before['total']);
    $this->assertSame(1, (int) $before['filled']);

    VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $created['id'])
      ->addValue('status_id', $this->getOptionValue('activity_status', 'Completed'))
      ->execute();

    $after = VolunteerAssignment::getCapacity(FALSE)
      ->setProjectId($projectId)
      ->execute()
      ->single();
    $this->assertSame(2, (int) $after['total']);
    $this->assertSame(1, (int) $after['filled'], 'An Attended volunteer still occupies their spot.');
    $this->assertSame(1, (int) $after['by_need'][$needId]);

    // The generic get() deliberately reports only place-occupying statuses.
    $this->assertCount(0, VolunteerAssignment::get(FALSE)
      ->addWhere('project_id', '=', $projectId)
      ->execute());
  }

  /**
   * A recorded hours value must be erasable. createVolunteerActivity() used
   * isset() when forwarding custom fields, so an explicit NULL was dropped and
   * the old number stuck no matter what the user did.
   */
  public function testLoggedHoursCanBeCleared(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');
    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();

    $entry = static function($minutes) use ($needId, $created, $completedStatusId) {
      return array(array(
        'id' => (int) $created['id'],
        'volunteer_need_id' => $needId,
        'status_id' => $completedStatusId,
        'time_completed_minutes' => $minutes,
      ));
    };

    $saved = VolunteerAssignment::logHours(FALSE)
      ->setProjectId($projectId)->setVolunteerNeedId($needId)
      ->setEntries($entry(151))->execute()->single();
    $this->assertSame(151, (int) $saved['rows'][0]['time_completed_minutes']);

    $cleared = VolunteerAssignment::logHours(FALSE)
      ->setProjectId($projectId)->setVolunteerNeedId($needId)
      ->setEntries($entry(NULL))->execute()->single();
    $this->assertNull($cleared['rows'][0]['time_completed_minutes'], 'An explicit NULL must erase the recorded hours.');
  }


  /**
   * logHours enforces project-update rights when invoked through the API
   * action, not only when the BAO is called directly.
   */
  public function testLogHoursRefusesStrangersAtTheActionBoundary(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');
    $volunteerId = $this->individualCreate();
    $created = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $volunteerId)
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');

    try {
      VolunteerAssignment::logHours(TRUE)
        ->setProjectId($projectId)
        ->setVolunteerNeedId($needId)
        ->setEntries(array(array(
          'id' => (int) $created['id'],
          'volunteer_need_id' => $needId,
          'status_id' => $completedStatusId,
          'time_completed_minutes' => 45,
        )))
        ->execute();
      $this->fail('logHours(TRUE) recorded hours for a caller with no project rights.');
    }
    catch (CRM_Core_Exception $e) {
      $this->addToAssertionCount(1);
    }
    $minutes = VolunteerAssignment::get(FALSE)
      ->addSelect('time_completed_minutes')
      ->addWhere('id', '=', (int) $created['id'])
      ->execute()
      ->single()['time_completed_minutes'];
    $this->assertNotSame(45, (int) $minutes, 'The refused call must not have recorded hours.');
  }

  /**
   * The project owner's logHours call goes through with checks enabled.
   */
  public function testProjectOwnerCanLogHoursThroughTheAction(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );

    $result = VolunteerAssignment::logHours(TRUE)
      ->setProjectId($projectId)
      ->setVolunteerNeedId($needId)
      ->setEntries(array(array(
        'assignee_contact_id' => $this->individualCreate(),
        'volunteer_need_id' => $needId,
        'status_id' => $completedStatusId,
        'time_completed_minutes' => 95,
      )))
      ->execute()
      ->single();

    $this->assertCount(1, $result['rows']);
    $this->assertSame(95, (int) $result['rows'][0]['time_completed_minutes']);
  }

  /**
   * getRoster refuses a stranger with checks enabled; the owner reads it.
   */
  public function testGetRosterRespectsProjectPermsAtTheActionBoundary(): void {
    [$projectId, $needId] = $this->createAssignableNeed();
    VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $needId)
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');
    try {
      VolunteerAssignment::getRoster(TRUE)->setProjectId($projectId)->execute();
      $this->fail('getRoster(TRUE) served a stranger.');
    }
    catch (CRM_Core_Exception $e) {
      $this->addToAssertionCount(1);
    }

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );
    $roster = VolunteerAssignment::getRoster(TRUE)->setProjectId($projectId)->execute()->single();
    $this->assertNotEmpty($roster);
  }

  /**
   * getCapacity and getHourEntries enforce project scope with checks on.
   */
  public function testGetCapacityAndHourEntriesEnforceProjectScope(): void {
    [$projectId, $needId] = $this->createAssignableNeed();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');
    foreach (array('getCapacity', 'getHourEntries') as $action) {
      try {
        if ($action === 'getCapacity') {
          VolunteerAssignment::getCapacity(TRUE)->setProjectId($projectId)->execute();
        }
        else {
          VolunteerAssignment::getHourEntries(TRUE)->setProjectId($projectId)->execute();
        }
        $this->fail("VolunteerAssignment::$action(TRUE) served a stranger.");
      }
      catch (CRM_Core_Exception $e) {
        $this->addToAssertionCount(1);
      }
    }

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );
    VolunteerAssignment::getCapacity(TRUE)->setProjectId($projectId)->execute();
    VolunteerAssignment::getHourEntries(TRUE)->setProjectId($projectId)->execute();
    $this->addToAssertionCount(2);
  }
}
