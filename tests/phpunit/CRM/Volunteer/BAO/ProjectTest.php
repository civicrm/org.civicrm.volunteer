<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Project BAO - volunteer_project
 *
 * @group headless
 */
class CRM_Volunteer_BAO_ProjectTest extends VolunteerTestAbstract {

  /**
   * Clean table civicrm_volunteer_project
   */
  public function setUp(): void {
    $this->quickCleanup(array('civicrm_volunteer_project', 'civicrm_volunteer_need'));
    parent::setUp();
  }

  public function testProjectCreate(): void {
    $params = array(
      'entity_id' => 1,
      'entity_table' => 'civicrm_event',
      'title' => 'Unit Testing for CiviVolunteer (How Meta)',
    );

    $project = CRM_Volunteer_BAO_Project::create($params);
    $this->assertObjectHasProperty('id', $project);
  }

  /**
   * A site whose administrator saved the settings form without choosing
   * default profiles stores an array of empty audiences. That is "no
   * choice", so new projects must still get the shipped signup profile.
   */
  public function testDefaultProfilesFallBackToSignupWhenEveryAudienceIsBlank(): void {
    $signupProfileId = CRM_Volunteer_BAO_Project::getDefaultSignupProfileId();
    $this->assertNotNull($signupProfileId, 'The installer ships a volunteer_sign_up profile.');
    $original = Civi::settings()->get('volunteer_project_default_profiles');
    try {
      foreach (array(NULL, array(), array('primary' => array(), 'additional' => array(), 'both' => array())) as $stored) {
        Civi::settings()->set('volunteer_project_default_profiles', $stored);
        $profiles = CRM_Volunteer_BAO_Project::composeDefaultSettingsArray()['profiles'];
        $this->assertCount(1, $profiles, 'Stored ' . json_encode($stored) . ' must fall back to one profile.');
        $this->assertSame($signupProfileId, (int) $profiles[0]['uf_group_id']);
        $this->assertSame('primary', $profiles[0]['module_data']['audience']);
      }

      // A real choice is honoured, and the fallback stays out of the way.
      Civi::settings()->set('volunteer_project_default_profiles', array('primary' => array(), 'additional' => array($signupProfileId), 'both' => array()));
      $profiles = CRM_Volunteer_BAO_Project::composeDefaultSettingsArray()['profiles'];
      $this->assertCount(1, $profiles);
      $this->assertSame('additional', $profiles[0]['module_data']['audience']);
    }
    finally {
      Civi::settings()->set('volunteer_project_default_profiles', $original);
    }
  }

  public function testProjectRetrieve(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $projectRetrieved = CRM_Volunteer_BAO_Project::retrieve(array('id' => $project->id));
    $this->assertNotEmpty($projectRetrieved);
  }

  public function testProjectRetrievePreservesZeroValuedFilters(): void {
    $active = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array('is_active' => 1));
    $inactive = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array('is_active' => 0));

    $projects = CRM_Volunteer_BAO_Project::retrieve(array('is_active' => 0));

    $this->assertArrayNotHasKey((int) $active->id, $projects);
    $this->assertArrayHasKey((int) $inactive->id, $projects);
  }

  public function testContactFilterNormalizesIdsBeforeBuildingSql(): void {
    $method = new ReflectionMethod(CRM_Volunteer_BAO_Project::class, 'buildContactJoin');
    $method->setAccessible(TRUE);
    $join = $method->invoke(NULL, array(
      'volunteer_owner' => array('4', '5) OR 1=1 --'),
    ));

    $this->assertStringContainsString('vpc.contact_id IN (4,5)', $join);
    $this->assertStringNotContainsString('OR 1=1', $join);
    $this->assertFalse($method->invoke(NULL, array(
      'not_a_relationship' => array('4'),
    )));
  }

  public function testPostalOnlyGeocodableAddressStartsWithPostalCode(): void {
    $country = \Civi\Api4\Country::get(FALSE)
      ->addSelect('id')
      ->addWhere('iso_code', '=', 'US')
      ->execute()
      ->single();
    $state = \Civi\Api4\StateProvince::get(FALSE)
      ->addSelect('id')
      ->addWhere('country_id', '=', $country['id'])
      ->addWhere('abbreviation', '=', 'NM')
      ->execute()
      ->single();

    $method = new ReflectionMethod(CRM_Volunteer_BAO_Project::class, 'buildGeocodableAddress');
    $method->setAccessible(TRUE);
    $baseLocation = array(
      'street_address' => '',
      'city' => '',
      'state_province_id' => (int) $state['id'],
      'postal_code' => '87110',
      'country_id' => (int) $country['id'],
    );

    $this->assertSame(
      '87110, New Mexico, United States',
      $method->invoke(NULL, $baseLocation)
    );
    $this->assertSame(
      '10801 Academy Rd NE, Albuquerque, New Mexico, 87111, United States',
      $method->invoke(NULL, array_merge($baseLocation, array(
        'street_address' => '10801 Academy Rd NE',
        'city' => 'Albuquerque',
        'postal_code' => '87111',
      )))
    );
  }

  /**
   * Test helper method isOff, which should return TRUE passed an "off" value
   */
  public function testProjectIsOff(): void {
    $this->assertTrue(CRM_Volunteer_BAO_Project::isOff(FALSE));
    $this->assertTrue(CRM_Volunteer_BAO_Project::isOff(0));
    $this->assertTrue(CRM_Volunteer_BAO_Project::isOff('0'));
  }

  public function testProjectRetrieveByID(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $projectRetrieved = CRM_Volunteer_BAO_Project::retrieveByID($project->id);

    // note: a strict comparison doesn't work: the first value is an int and the
    // second is a string; not sure where this occurs, but seems worth a look...
    $this->assertTrue($project->id == $projectRetrieved->id, 'CRM_Volunteer_BAO_Project::retrieveByID failed');
  }

  /**
   * Tests magic __get for needs
   */
  public function testProjectGetNeeds(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'),
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $test = $project->needs;
    $this->assertCount(1, $test);
  }

  /**
   * Tests magic __isset for needs
   */
  public function testProjectIssetNeeds(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'),
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertTrue(isset($project->needs));
  }

  /**
   * Tests magic __isset for needs
   */
  public function testProjectEmptyNeeds(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'),
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertFalse(empty($project->needs));
  }

  /**
   * Tests magic __get for needs
   */
  public function testProjectGetRoles(): void {
    $role_id = 2;
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'is_flexible' => 0,
      'project_id' => $project->id,
      'role_id' => $role_id,
      'visibility_id' => CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'),
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $test = $project->roles;
    $this->assertArrayHasKey($role_id, $test);
  }

  /**
   * Tests magic __isset for needs
   */
  public function testProjectIssetRoles(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'is_flexible' => 0,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'),
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertTrue(isset($project->roles));
  }

  /**
   * Tests magic __isset for needs
   */
  public function testProjectEmptyRoles(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'),
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertFalse(empty($project->roles));
  }

  /**
   * Tests magic __get for open needs
   */
  public function testProjectGetOpenNeeds(): void {
    list($project, $need, $role_id) = $this->createProjectWithNeed();
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $test = $project->open_needs;
    $this->assertArrayHasKey($need->id, $test);
    $this->assertArrayHasKey('role_id', $test[$need->id]);
    $this->assertEquals($role_id, $test[$need->id]['role_id']);
  }

  /**
   * Tests magic __isset for open needs
   */
  public function testProjectIssetOpenNeeds(): void {
    list($project, $need, $role_id) = $this->createProjectWithNeed();
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertTrue(isset($project->open_needs));
  }

  /**
   * Tests magic __isset for open needs
   */
  public function testProjectEmptyOpenNeeds(): void {
    list($project, $need, $role_id) = $this->createProjectWithNeed();
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertFalse(empty($project->open_needs));
  }

  /**
   * Helper function to create an associated project and need
   *
   * @return array Contains three elements:
   * <ul>
   *   <li>CRM_Volunteer_BAO_Project</li>
   *   <li>CRM_Volunteer_BAO_Need</li>
   *   <li>int Role ID for the created need</li>
   * </ul>
   */
  private function createProjectWithNeed() {
    $role_id = 2;
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'is_flexible' => 0,
      'project_id' => $project->id,
      'quantity' => 5,
      'role_id' => $role_id,
      'start_time' => date('YmdHis', strtotime('tomorrow')),
      'visibility_id' => CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'),
    ));

    return array($project, $need, $role_id);
  }

  public function testGetContactsByRelationship(): void {
    $contactId = 1;
    $relType = $this->getOptionValue(
      CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
      'volunteer_owner'
    );

    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $projectContact = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact', array(
      'contact_id' => $contactId,
      'project_id' => $project->id,
      'relationship_type_id' => $relType
    ));
    $this->assertObjectHasProperty('id', $projectContact, 'Failed to prepopulate Volunteer Project Contact');

    $contacts = CRM_Volunteer_BAO_Project::getContactsByRelationship($project->id, $relType);
    $this->assertTrue(in_array($contactId, $contacts));
  }

  /**
   * VOL-154: Verifies that, when a project's campaign is updated, the campaign
   * for each associated activity is as well.
   */
  public function testProjectCampaignUpdate(): void {
    $testObjects = $this->_createTestObjects();

    CRM_Volunteer_BAO_Project::create(array(
      'campaign_id' => $testObjects['campaign']->id,
      'id' => $testObjects['project']->id,
    ));

    $updatedActivity = CRM_Volunteer_BAO_Assignment::findById($testObjects['activity']['id']);
    $this->assertEquals($testObjects['campaign']->id, $updatedActivity->campaign_id,
        'Activity campaign was not updated with project campaign');

    // Test unsetting campaign from a project.
    CRM_Volunteer_BAO_Project::create(array(
      'campaign_id' => '',
      'id' => $testObjects['project']->id,
    ));

    $updatedActivity = CRM_Volunteer_BAO_Assignment::findById($testObjects['activity']['id']);
    $this->assertEquals('', $updatedActivity->campaign_id,
        'Activity campaign was not updated with empty project campaign');
  }

  /**
   * A campaign change must reach assignments that are no longer Scheduled.
   *
   * Campaign propagation used to iterate Assignment::retrieve(), whose SQL is
   * restricted to Scheduled and Available -- the statuses that consume a need's
   * capacity. The Hours workflow exists to move assignments out of that set, so
   * every volunteer who had actually turned up kept the old campaign.
   */
  public function testProjectCampaignUpdateReachesHistoricalAssignments(): void {
    $testObjects = $this->_createTestObjects();
    $projectId = (int) $testObjects['project']->id;

    // A second need, so neither entry can be refused for want of capacity.
    $secondNeed = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'project_id' => $projectId,
    ));
    $noShow = $this->createAssignment(array(
      'assignee_contact_id' => 1,
      'source_contact_id' => 1,
      'volunteer_need_id' => $secondNeed->id,
    ));

    // Drive the statuses through the real Hours workflow rather than writing
    // the activities directly, so the fixture matches what users produce.
    $attendedId = (int) $testObjects['activity']['id'];
    $noShowId = (int) $noShow['id'];
    CRM_Volunteer_BAO_Assignment::logHours($projectId, NULL, array(
      array(
        'id' => $attendedId,
        'volunteer_need_id' => $testObjects['need']->id,
        'status_id' => $this->getOptionValue('activity_status', 'Completed'),
        'time_completed_minutes' => 135,
        'details' => 'Stayed to clear up.',
      ),
      array(
        'id' => $noShowId,
        'volunteer_need_id' => $secondNeed->id,
        'status_id' => $this->getOptionValue('activity_status', 'No_show'),
      ),
    ), FALSE);

    $this->assertSame(
      array(),
      array_intersect(
        array($attendedId, $noShowId),
        array_keys(CRM_Volunteer_BAO_Assignment::retrieve(array('project_id' => $projectId)))
      ),
      'Fixture is not exercising the defect: both assignments are still visible to retrieve().'
    );

    CRM_Volunteer_BAO_Project::create(array(
      'campaign_id' => $testObjects['campaign']->id,
      'id' => $projectId,
    ));

    $rows = CRM_Volunteer_BAO_Assignment::retrieveAllStatuses($projectId);
    foreach (array($attendedId, $noShowId) as $activityId) {
      $activity = CRM_Volunteer_BAO_Assignment::findById($activityId);
      $this->assertEquals(
        $testObjects['campaign']->id,
        $activity->campaign_id,
        "Assignment $activityId did not inherit the project's new campaign."
      );
    }

    // Re-saving must not disturb anything the Hours workflow recorded.
    $this->assertEquals('Completed', $rows[$attendedId]['status_name']);
    $this->assertEquals(135, $rows[$attendedId]['time_completed_minutes']);
    $this->assertEquals('Stayed to clear up.', $rows[$attendedId]['details']);
    $this->assertEquals('No_show', $rows[$noShowId]['status_name']);

    // And clearing the campaign must reach them too.
    CRM_Volunteer_BAO_Project::create(array(
      'campaign_id' => '',
      'id' => $projectId,
    ));
    foreach (array($attendedId, $noShowId) as $activityId) {
      $this->assertEquals(
        '',
        CRM_Volunteer_BAO_Assignment::findById($activityId)->campaign_id,
        "Assignment $activityId kept a campaign the project no longer has."
      );
    }
  }

  /**
   * getProjectAssignmentIds() is the status-agnostic list behind propagation.
   */
  public function testGetProjectAssignmentIdsIsStatusAgnostic(): void {
    $testObjects = $this->_createTestObjects();
    $projectId = (int) $testObjects['project']->id;
    $activityId = (int) $testObjects['activity']['id'];

    $this->assertSame(
      array($activityId),
      CRM_Volunteer_BAO_Assignment::getProjectAssignmentIds($projectId)
    );

    CRM_Volunteer_BAO_Assignment::logHours($projectId, NULL, array(
      array(
        'id' => $activityId,
        'volunteer_need_id' => $testObjects['need']->id,
        'status_id' => $this->getOptionValue('activity_status', 'Completed'),
        'time_completed_minutes' => 60,
      ),
    ), FALSE);

    $this->assertSame(
      array($activityId),
      CRM_Volunteer_BAO_Assignment::getProjectAssignmentIds($projectId),
      'A Completed assignment must still be listed.'
    );

    \Civi\Api4\Activity::update(FALSE)
      ->addWhere('id', '=', $activityId)
      ->addValue('is_deleted', TRUE)
      ->execute();
    $this->assertSame(
      array(),
      CRM_Volunteer_BAO_Assignment::getProjectAssignmentIds($projectId),
      'A trashed assignment must not be listed.'
    );
  }

  /**
   * Creates test case data for use in the Unit Tests.
   *
   * return $returnObjects array(
   *   'project' => CRM_Volunteer_BAO_Project,
   *   'need' => CRM_Volunteer_BAO_Need,
   *   'activity' => api.VolunteerAssignment.create,
   *   'campaign' => CRM_Campaign_BAO_Campaign
   * )
   */
  function _createTestObjects() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'project_id' => $project->id,
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $campaign = CRM_Core_DAO::createTestObject('CRM_Campaign_BAO_Campaign');
    $this->assertObjectHasProperty('id', $campaign, 'Failed to prepopulate Campaign');

    // The campaign above was created with CRM_Core_DAO::createTestObject(), so
    // the cached option list backing Activity.campaign_id validation is stale.
    // APIv3 accepted a cache_clear flag for this; API4 has no such parameter,
    // so flush the metadata explicitly.
    $activity = $this->createAssignment(array(
      'assignee_contact_id' => 1,
      'source_contact_id' => 1,
      'volunteer_need_id' => $need->id,
    ));

    return array(
      'project' => $project,
      'need' => $need,
      'activity' => $activity,
      'campaign' => $campaign,
    );
  }

  /**
   * A project with assignments cannot be deleted; volunteer history is
   * preserved by forcing the disable path instead.
   */
  public function testDeleteProjectRefusesAssignedProjects(): void {
    $project = $this->createProject(array('title' => 'Assigned, undeletable'));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'is_flexible' => 0,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    \Civi\Api4\VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $need['id'])
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute();

    try {
      CRM_Volunteer_BAO_Project::deleteProject((int) $project['id'], FALSE);
      $this->fail('An assigned project must not be deletable.');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('cannot be deleted', $e->getMessage());
    }

    $this->assertCount(
      1,
      \Civi\Api4\VolunteerProject::get(FALSE)->addWhere('id', '=', $project['id'])->execute(),
      'The refused delete must leave the project in place.'
    );
  }

  /**
   * Deleting an empty project removes every aggregate-owned row: needs
   * (including the flexible one), project contacts and profile joins.
   */
  public function testDeleteProjectCleansUpOwnedRows(): void {
    $ownerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_owner');
    $project = $this->createProject(array(
      'title' => 'Empty and deletable',
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-18 09:00:00',
      'is_flexible' => 0,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $projectId = (int) $project['id'];
    $needId = (int) $need['id'];
    $this->assertGreaterThan(0, (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId));

    $this->assertTrue(CRM_Volunteer_BAO_Project::deleteProject($projectId, FALSE));

 $this->assertCount(0, \Civi\Api4\VolunteerProject::get(FALSE)->addWhere('id', '=', $projectId)->execute());
    $this->assertCount(0, \Civi\Api4\VolunteerNeed::get(FALSE)->addWhere('project_id', '=', $projectId)->execute(), 'All needs including the flexible one must be deleted.');
    $this->assertSame(
      0,
      (int) CRM_Core_DAO::singleValueQuery(
        'SELECT COUNT(*) FROM civicrm_volunteer_project_contact WHERE project_id = %1',
        array(1 => array($projectId, 'Integer'))
      ),
      'Project-contact rows must be deleted.'
    );
    $this->assertSame(
      0,
      (int) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(*) FROM civicrm_uf_join WHERE entity_table = 'civicrm_volunteer_project' AND entity_id = %1",
        array(1 => array($projectId, 'Integer'))
      ),
      'Profile joins must be deleted.'
    );
  }

  /**
   * Replacing a project's location releases the superseded block only when
   * nothing else references it: editing must never mutate (or destroy) a
   * location shared with another project.
   */
  public function testReplacingALocationReleasesOnlyUnreferencedBlocks(): void {
    $locationValues = array(
      'address' => array('name' => 'Shared site', 'street_address' => '1 Main St', 'city' => 'Springfield'),
      'email' => array('email' => 'shared@example.org'),
    );
    $solo = $this->createProject(array(
      'title' => 'Solo location owner',
      'location' => $locationValues,
    ));
    $shared = $this->createProject(array(
      'title' => 'Shared location owner',
      'location' => $locationValues,
    ));
    $sharedBlockId = (int) $shared['loc_block_id'];

    // Point the second project at the first project's block: the sharing the
    // guard exists to protect.
    \Civi\Api4\VolunteerProject::update(FALSE)
      ->addWhere('id', '=', $shared['id'])
      ->addValue('loc_block_id', $solo['loc_block_id'])
      ->execute();
    $soloBlockId = (int) $solo['loc_block_id'];

    // Replace the shared block: it is still referenced by the other project,
    // so it must survive.
    $this->createProject(array(
      'id' => $solo['id'],
      'title' => 'Solo location owner',
      'location' => $locationValues + array('address' => array('name' => 'Replacement site', 'street_address' => '2 Other St', 'city' => 'Shelbyville')),
    ));
    $this->assertNotNull(
      CRM_Core_DAO::getFieldValue('CRM_Core_DAO_LocBlock', $soloBlockId, 'id'),
      'A block another project references must survive replacement.'
    );
    // Detach the second project (back to its own block). Collection is
    // opportunistic -- it runs on the save that supersedes a block -- so
    // the next replacement supersedes the first replacement's block, which
    // is now unreferenced and must be collected.
    \Civi\Api4\VolunteerProject::update(FALSE)
      ->addWhere('id', '=', $shared['id'])
      ->addValue('loc_block_id', $sharedBlockId)
      ->execute();
    $secondBlockId = (int) CRM_Core_DAO::getFieldValue(
      'CRM_Volunteer_DAO_Project',
      (int) $solo['id'],
      'loc_block_id'
    );
    $this->assertNotSame($soloBlockId, $secondBlockId);
    $this->createProject(array(
      'id' => $solo['id'],
      'title' => 'Solo location owner',
      'location' => $locationValues + array('address' => array('name' => 'Second replacement', 'street_address' => '3 Far Ave', 'city' => 'Springfield')),
    ));
    $this->assertNull(
      CRM_Core_DAO::getFieldValue('CRM_Core_DAO_LocBlock', $secondBlockId, 'id'),
      'A superseded block nothing references must be collected.'
    );
  }

}
