<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Assignment BAO
 *
 * @group headless
 */
class CRM_Volunteer_BAO_AssignmentTest extends VolunteerTestAbstract {

  private function setUpProject() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'),
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $beneficiaryContactId = $this->individualCreate();
    $volunteerContactId = $this->individualCreate();

    return array(
      'beneficiaryContactId' => $beneficiaryContactId,
      'need' => $need,
      'project' => $project,
      'volunteerContactId' => $volunteerContactId,
    );
  }

  /**
   * Tests CRM_Volunteer_BAO_Assignment::createVolunteerActivity() to ensure
   * that the project beneficiary is made the activity target.
   */
  public function testActivityTarget(): void {
    $beneficiaryContactId = $need = $project = $volunteerContactId = NULL;
    extract($this->setUpProject(), EXTR_IF_EXISTS);

    $projectContact = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact', array(
      'contact_id' => $beneficiaryContactId,
      'project_id' => $project->id,
      'relationship_type_id' => $this->getOptionValue('volunteer_project_relationship', 'volunteer_beneficiary'),
    ));
    $this->assertObjectHasProperty('id', $projectContact, 'Failed to prepopulate VolunteerContact');

    $assignmentId = CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
      'assignee_contact_id' => $volunteerContactId,
      // source is set to avoid errors when Civi can't identify the currently logged in user
      'source_contact_id' => $volunteerContactId,
      'volunteer_need_id' => $need->id,
    ));

    $targetContactId = \Civi\Api4\ActivityContact::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('activity_id', '=', $assignmentId)
      ->addWhere('record_type_id:name', '=', 'Activity Targets')
      ->execute()
      ->single()['contact_id'];

    $this->assertEquals($beneficiaryContactId, $targetContactId);
  }

  /**
   * Tests CRM_Volunteer_BAO_Assignment::createVolunteerActivity() to ensure
   * that the activity subject defaults to the project title.
   */
  public function testActivitySubject(): void {
    $need = $project = $volunteerContactId = NULL;
    extract($this->setUpProject(), EXTR_IF_EXISTS);

    $assignmentId = CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
      'assignee_contact_id' => $volunteerContactId,
      // source is set to avoid errors when Civi can't identify the currently logged in user
      'source_contact_id' => $volunteerContactId,
      'volunteer_need_id' => $need->id,
    ));

    $activitySubject = \Civi\Api4\Activity::get(FALSE)
      ->addSelect('subject')
      ->addWhere('id', '=', $assignmentId)
      ->execute()
      ->single()['subject'];

    $this->assertEquals($project->title, $activitySubject);

  }

  /**
   * VOL-154: Verifies that an activity created in a project is tagged with the
   * project's campaign.
   */
  public function testCampaignInheritance(): void {
    // begin setup
    $campaign = CRM_Core_DAO::createTestObject('CRM_Campaign_BAO_Campaign');
    $this->assertObjectHasProperty('id', $campaign, 'Failed to prepopulate Campaign');

    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array(
      'campaign_id' => $campaign->id,
    ));
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'project_id' => $project->id,
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');
    // end setup

    $activityId = CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
      'assignee_contact_id' => 1,
      'source_contact_id' => 1,
      'volunteer_need_id' => $need->id,
    ));
    $this->assertNotSame(FALSE, $activityId, 'Failed to create Volunteer Activity');

    $createdActivity = CRM_Volunteer_BAO_Assignment::findById($activityId);
    $this->assertEquals($campaign->id, $createdActivity->campaign_id,
        'Activity did not inherit campaign from volunteer project');
  }

  /**
   * Trusted signup-style writes must be able to load project defaults.
   *
   * createVolunteerActivity() accepts check_permissions=FALSE only inside the
   * internal bypass scope. Its nested project read must preserve that explicit
   * flag; otherwise a public signup passes the outer authorization boundary but
   * fails while deriving the activity subject and campaign.
   */
  public function testTrustedAssignmentCreateCanReadProjectDefaults(): void {
    $data = $this->setUpProject();
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array();

    $assignmentId = CRM_Volunteer_Permission::withInternalBypass(function() use ($data) {
      return CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
        'check_permissions' => FALSE,
        'assignee_contact_id' => $data['volunteerContactId'],
        'source_contact_id' => $data['volunteerContactId'],
        'volunteer_need_id' => $data['need']->id,
      ));
    });

    $activity = \Civi\Api4\Activity::get(FALSE)
      ->addSelect('subject')
      ->addWhere('id', '=', $assignmentId)
      ->execute()
      ->single();
    $this->assertSame($data['project']->title, $activity['subject']);
  }

  public function testAssignmentCapacityIsEnforcedServerSide(): void {
    $data = $this->setUpProject();
    CRM_Volunteer_BAO_Need::create(array(
      'id' => $data['need']->id,
      'quantity' => 1,
    ));

    CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
      'assignee_contact_id' => $data['volunteerContactId'],
      'source_contact_id' => $data['volunteerContactId'],
      'volunteer_need_id' => $data['need']->id,
    ));

    $secondVolunteerId = $this->individualCreate();
    try {
      CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
        'assignee_contact_id' => $secondVolunteerId,
        'source_contact_id' => $secondVolunteerId,
        'volunteer_need_id' => $data['need']->id,
      ));
      $this->fail('A second assignment should not exceed the need capacity.');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('no remaining places', $e->getMessage());
    }

    $this->assertSame(1, CRM_Volunteer_BAO_Need::getAssignmentCount($data['need']->id));
  }

  /**
   * The locking capacity count must agree with the non-locking one.
   *
   * getAssignmentCountForUpdate() hand-writes the SQL that
   * CRM_Volunteer_BAO_Assignment::retrieve() expresses through the APIv3 query
   * builder, so the two can drift. Pin the equivalence across a mix of statuses:
   * only Scheduled and Available occupy a place, and soft-deleted activities
   * never do.
   */
  public function testLockingAssignmentCountMatchesPlainCount(): void {
    $project = $this->createProject(array('title' => 'Capacity count equivalence'));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-11-05 09:00:00',
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 10,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $needId = (int) $need['id'];

    $assertAgree = function(string $label) use ($needId) {
      $plain = (int) CRM_Volunteer_BAO_Need::getAssignmentCount($needId);
      $locking = CRM_Volunteer_BAO_Need::getAssignmentCountForUpdate($needId);
      $this->assertSame($plain, $locking, "Counts disagree $label.");
      return $locking;
    };

    $this->assertSame(0, $assertAgree('with no assignments'));

    $activityIds = array();
    foreach (array('Scheduled', 'Available', 'Completed', 'No_show') as $status) {
      $activityIds[$status] = CRM_Volunteer_Permission::withInternalBypass(function() use ($needId, $status) {
        return CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
          'check_permissions' => FALSE,
          'volunteer_need_id' => $needId,
          'assignee_contact_id' => $this->individualCreate(),
          'status_id' => $this->getOptionValue('activity_status', $status),
        ));
      });
    }

    // Only Scheduled and Available occupy a place.
    $this->assertSame(2, $assertAgree('across mixed statuses'));

    // Trashing an assignment frees its place. Both query paths must exclude it.
    \Civi\Api4\Activity::update(FALSE)
      ->addWhere('id', '=', $activityIds['Scheduled'])
      ->addValue('is_deleted', TRUE)
      ->execute();
    $this->assertSame(1, $assertAgree('after a soft delete'));
  }

  public function testAssignmentGetAcceptsEnumerableIdFilterShapes(): void {
    $project = $this->createProject(array('title' => 'Assignment filter shapes'));
    $needIds = array();
    $assignmentIds = array();
    foreach (array(1, 2) as $day) {
      $need = $this->createNeed(array(
        'project_id' => $project['id'],
        'start_time' => "2026-12-2{$day} 09:00:00",
        'is_flexible' => 0,
        'quantity' => 5,
        'visibility_id' => $this->getOptionValue('visibility', 'public'),
      ));
      $needIds[] = (int) $need['id'];
      $assignmentIds[] = (int) CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
        'volunteer_need_id' => $need['id'],
        'assignee_contact_id' => $this->individualCreate(),
        'source_contact_id' => $this->getMockedContactId(),
      ));
    }

    $byOperator = \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addWhere('id', 'IN', $assignmentIds)
      ->execute()
      ->column('id');
    $this->assertEqualsCanonicalizing($assignmentIds, array_map('intval', $byOperator));

    // A need-scoped IN filter reaches the same rows through the custom field.
    $byNeed = \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addWhere('volunteer_need_id', 'IN', $needIds)
      ->execute()
      ->column('id');
    $this->assertEqualsCanonicalizing($assignmentIds, array_map('intval', $byNeed));

    // And a scalar filter narrows to the one assignment on that need.
    $bySingleNeed = \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addWhere('volunteer_need_id', '=', $needIds[0])
      ->execute()
      ->column('id');
    $this->assertSame(array($assignmentIds[0]), array_map('intval', $bySingleNeed));
  }
  /**
   * The permission-gated reads share the VIEW_ROSTER/UPDATE boundary the
   * API4 actions forward to.
   */
  public function testRosterAndCapacityReadsRequireProjectAccess(): void {
    $project = $this->createProject(array(
      'title' => 'Gated read project',
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
    ));
    $projectId = (int) $project['id'];

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');
    foreach (array('roster', 'capacity') as $surface) {
      try {
        if ($surface === 'roster') {
          CRM_Volunteer_BAO_Assignment::getRosterData($projectId, FALSE, TRUE);
        }
        else {
          CRM_Volunteer_BAO_Assignment::getCapacitySummary($projectId, TRUE);
        }
        $this->fail("The $surface read served a stranger.");
      }
      catch (CRM_Core_Exception $e) {
        $this->addToAssertionCount(1);
      }
    }

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );
    CRM_Volunteer_BAO_Assignment::getRosterData($projectId, FALSE, TRUE);
    CRM_Volunteer_BAO_Assignment::getCapacitySummary($projectId, TRUE);
    $this->addToAssertionCount(2);
  }

  /**
   * Roster rows carry the shift information the roster screen renders, and
   * the includePast flag governs historical rows.
   */
  public function testRosterDataCarriesShiftRowsAndHonorsIncludePast(): void {
    $project = $this->createProject(array('title' => 'Roster read project'));
    $pastNeed = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('-2 days')),
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 3,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $futureNeed = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('+1 day')),
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 3,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));

    $current = \Civi\Api4\VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $futureNeed['id'])
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();
    $past = \Civi\Api4\VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $pastNeed['id'])
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');
    \Civi\Api4\VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $past['id'])
      ->addValue('status_id', $completedStatusId)
      ->execute();

    $roster = CRM_Volunteer_BAO_Assignment::getRosterData((int) $project['id'], FALSE, FALSE);
    $this->assertSame('Roster read project', $roster['project_title']);
    $this->assertSame(1, $roster['shift_count'], 'Only the future shift is current.');
    $rosterIds = array_map('intval', array_column($roster['rows'], 'id'));
    $this->assertContains((int) $current['id'], $rosterIds);
    $this->assertNotContains((int) $past['id'], $rosterIds, 'An assignment on a past shift is hidden unless requested.');
    $this->assertSame(1, $roster['assignment_count']);

    $withPast = CRM_Volunteer_BAO_Assignment::getRosterData((int) $project['id'], TRUE, FALSE);
    $withPastIds = array_map('intval', array_column($withPast['rows'], 'id'));
    $this->assertContains((int) $past['id'], $withPastIds, 'includePast must surface historical rows.');
    $this->assertSame(2, $withPast["assignment_count"]);
  }

  /**
   * Capacity counting matches the workflow rules: only active, dated needs
   * with a finite quantity count, and every non-deleted assignment --
   * whatever its status -- occupies a place.
   */
  public function testCapacitySummaryMatchesTheWorkflowCountingRules(): void {
    $project = $this->createProject(array('title' => 'Capacity read project'));
    $countedNeed = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('+1 day')),
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('+2 days')),
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 5,
      'is_active' => 0,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));

    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');
    $attended = \Civi\Api4\VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $countedNeed['id'])
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();
    \Civi\Api4\VolunteerAssignment::update(FALSE)
      ->addWhere('id', '=', $attended['id'])
      ->addValue('status_id', $completedStatusId)
      ->execute();

    $summary = CRM_Volunteer_BAO_Assignment::getCapacitySummary((int) $project['id'], FALSE);
    $this->assertSame(2, $summary['total'], 'Only the active, finite need counts toward total capacity.');
    $this->assertSame(1, $summary['filled'], 'An attended volunteer still occupies their place.');
    $this->assertSame(array((int) $countedNeed['id'] => 1), $summary['by_need']);

    $viaApi = \Civi\Api4\VolunteerAssignment::getCapacity(FALSE)
      ->setProjectId((int) $project['id'])
      ->execute()
      ->single();
    $this->assertSame($summary, $viaApi, 'The API4 action must forward the BAO summary unchanged.');
  }


}
