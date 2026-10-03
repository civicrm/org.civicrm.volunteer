<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerAssignment;

/**
 * Tests CRM_Volunteer_BAO_Need::deleteNeed(), the trigger-sensitive
 * reparenting path behind the APIv3 and APIv4 need-delete actions.
 *
 * A dated need's assignments are not deleted: they are reparented to the
 * project's flexible need with status, minutes and audit data intact --
 * including statuses VolunteerAssignment.get never returns.
 *
 * @group headless
 */
class CRM_Volunteer_BAO_NeedTest extends VolunteerTestAbstract {

  /**
   * @return array{0: int, 1: int}
   *   Project and dated-need IDs, with two assignments on the dated need.
   */
  private function buildNeedWithHistory(): array {
    $project = $this->createProject(array(
      'title' => 'Need deletion project',
      'is_active' => 1,
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'duration' => 120,
      'is_flexible' => 0,
      'quantity' => 4,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));

    $scheduled = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $need['id'])
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();
    $this->scheduledAssignmentId = (int) $scheduled['id'];

    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');
    $completed = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $need['id'])
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();
    VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $completed['id'])
      ->addValue('status_id', $completedStatusId)
      ->addValue('time_completed_minutes', 75)
      ->execute();
    $this->completedAssignmentId = (int) $completed['id'];

    return array((int) $project['id'], (int) $need['id']);
  }

  /**
   * @var int
   */
  private int $scheduledAssignmentId = 0;

  /**
   * @var int
   */
  private int $completedAssignmentId = 0;

  /**
   * Deleting a dated need reparents its assignments -- including historical
   * statuses -- to the project's flexible need, preserving minutes.
   */
  public function testDeleteNeedReparentsAssignmentsToTheFlexibleNeed(): void {
    list($projectId, $needId) = $this->buildNeedWithHistory();
    $flexibleNeedId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId);

    $this->assertTrue(CRM_Volunteer_BAO_Need::deleteNeed($needId, FALSE));

    $this->assertCount(
      0,
      \Civi\Api4\VolunteerNeed::get(FALSE)->addWhere('id', '=', $needId)->execute(),
      'The dated need must be gone.'
    );

    // Historical statuses are invisible to VolunteerAssignment.get; the
    // hour-entries surface carries them.
    $entries = VolunteerAssignment::getHourEntries(FALSE)
      ->setProjectId($projectId)
      ->execute()
      ->single();
    $rowsById = array();
    foreach ($entries['rows'] as $row) {
      $rowsById[(int) $row['id']] = $row;
    }

    $this->assertArrayHasKey($this->completedAssignmentId, $rowsById, 'The Completed assignment must survive the shift deletion.');
    $completedRow = $rowsById[$this->completedAssignmentId];
    $this->assertSame($flexibleNeedId, (int) $completedRow['volunteer_need_id'], 'History must be reparented to the flexible need.');
    $this->assertSame(75, (int) $completedRow['time_completed_minutes'], 'Recorded minutes must survive the reparenting.');
    $this->assertSame(
      $this->getOptionValue('activity_status', 'Completed'),
      (int) $completedRow['status_id'],
      'Status must survive the reparenting.'
    );

    $this->assertSame(
      $flexibleNeedId,
      (int) $rowsById[$this->scheduledAssignmentId]['volunteer_need_id'],
      'The Scheduled assignment must be reparented too.'
    );
  }

  /**
   * The flexible need is a property of the project and cannot be deleted.
   */
  public function testDeleteNeedRefusesTheFlexibleNeedItself(): void {
    $project = $this->createProject(array('title' => 'Flexible need guard'));
    $flexibleNeedId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $project['id']);
    $this->assertGreaterThan(0, $flexibleNeedId);

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('cannot be deleted');
    CRM_Volunteer_BAO_Need::deleteNeed($flexibleNeedId, FALSE);
  }

  /**
   * The permission-aware entry point enforces project-update rights.
   */
  public function testDeleteNeedRequiresProjectEditPermission(): void {
    list($projectId, $needId) = $this->buildNeedWithHistory();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');

    try {
      CRM_Volunteer_BAO_Need::deleteNeed($needId, TRUE);
      $this->fail('deleteNeed() served a caller without project rights.');
    }
    catch (CRM_Core_Exception $e) {
      $this->addToAssertionCount(1);
    }

    $this->assertCount(
      1,
      \Civi\Api4\VolunteerNeed::get(FALSE)->addWhere('id', '=', $needId)->execute(),
      'The refused delete must not have removed the need.'
    );
  }

}
