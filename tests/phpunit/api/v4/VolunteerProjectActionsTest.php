<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerProject;

/**
 * Tests the custom API4 actions that replaced VolunteerProject's APIv3-only
 * actions: commit, search, getWorkflowContext, getLocationOptions, getLocation,
 * saveLocation and removeProfile, plus the getManageData() domain read behind
 * getManageOverview.
 *
 * @group headless
 */
class api_v4_VolunteerProjectActionsTest extends VolunteerTestAbstract {

  /**
   * commit() is the aggregate write: contacts, profiles and location in one go.
   */
  public function testCommitWritesTheWholeAggregate(): void {
    $beneficiaryId = $this->individualCreate();
    $profileId = $this->createProfile('API4 commit profile');

    $project = VolunteerProject::commit(FALSE)
      ->setValues(array(
        'title' => 'Committed project',
        'is_active' => TRUE,
        'project_contacts' => array(
          'volunteer_owner' => array($this->getMockedContactId()),
          'volunteer_beneficiary' => array($beneficiaryId),
        ),
        'profiles' => array(
          array('uf_group_id' => $profileId, 'module_data' => array('audience' => 'primary')),
        ),
      ))
      ->execute()
      ->single();

    $projectId = (int) $project['id'];
    $this->assertSame('Committed project', $project['title']);
    // VOL-269: a flexible need is created alongside a new project.
    $this->assertNotNull(CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId));
    $this->assertSame(
      array($beneficiaryId),
      CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, 'volunteer_beneficiary')
    );

    $joins = \Civi\Api4\UFJoin::get(FALSE)
      ->addSelect('uf_group_id')
      ->addWhere('entity_table', '=', 'civicrm_volunteer_project')
      ->addWhere('entity_id', '=', $projectId)
      ->addWhere('module', '=', 'CiviVolunteer')
      ->execute()
      ->column('uf_group_id');
    $this->assertSame(array($profileId), array_map('intval', $joins));
  }

  /**
   * search() exposes the aggregate filters that the DAO-backed get cannot.
   */
  public function testSearchAppliesRelationshipFilters(): void {
    $ownerId = $this->individualCreate();
    $mine = $this->createProject(array(
      'title' => 'Searchable by owner',
      'project_contacts' => array('volunteer_owner' => array($ownerId)),
    ));
    $this->createProject(array(
      'title' => 'Owned by somebody else',
      'project_contacts' => array('volunteer_owner' => array($this->individualCreate())),
    ));

    $found = VolunteerProject::search(FALSE)
      ->setContext('edit')
      ->setFilters(array('project_contacts' => array('volunteer_owner' => array($ownerId))))
      ->execute()
      ->column('id');
    $this->assertSame(array((int) $mine['id']), array_map('intval', $found));
  }

  /**
   * A public search is limited to active projects and safe fields, and its
   * profile metadata keeps APIv3's JSON-string shape.
   */
  public function testPublicSearchTrimsFieldsAndKeepsModuleDataAsJson(): void {
    $profileId = $this->createProfile('API4 public search profile');
    $active = $this->createProject(array(
      'title' => 'Publicly searchable',
      'description' => '<p>Safe</p><script>bad()</script>',
      'is_active' => 1,
      'profiles' => array(
        array('uf_group_id' => $profileId, 'module_data' => array('audience' => 'both')),
      ),
    ));

    $row = VolunteerProject::search(FALSE)
      ->setFilters(array('id' => $active['id']))
      ->execute()
      ->single();

    $this->assertSame(
      array('id', 'title', 'description', 'is_active', 'loc_block_id', 'campaign_id', 'profiles'),
      array_keys($row)
    );
    $this->assertStringNotContainsString('<script', $row['description']);
    $this->assertCount(1, $row['profiles']);
    $profile = reset($row['profiles']);
    $this->assertSame($profileId, (int) $profile['uf_group_id']);
    // module_data keeps APIv3's JSON-string shape, not API4's deserialized
    // array, because every consumer of this read parses a string.
    $this->assertIsString($profile['module_data']);
    $this->assertSame(
      array('audience' => 'both'),
      json_decode($profile['module_data'], TRUE),
      'Stored module_data was ' . var_export(\Civi\Api4\UFJoin::get(FALSE)
        ->addSelect('module_data')
        ->addWhere('entity_id', '=', $active['id'])
        ->execute()
        ->column('module_data'), TRUE)
    );
  }

  /**
   * CRM_Volunteer_BAO_Project::getManageData() adds the beneficiary and
   * location payloads that getManageOverview builds on top of the search
   * result. It is a domain read with no API4 action of its own.
   */
  public function testGetManageDataReturnsEverythingTheListingRenders(): void {
    $beneficiaryId = $this->individualCreate();
    $project = $this->createProject(array(
      'title' => 'Manage listing project',
      'project_contacts' => array(
        'volunteer_owner' => array($this->getMockedContactId()),
        'volunteer_beneficiary' => array($beneficiaryId),
      ),
      'location' => $this->locationValues(),
    ));

    $rows = CRM_Volunteer_BAO_Project::getManageData(
      array('id' => $project['id'], 'context' => 'edit'),
      FALSE
    );
    $this->assertCount(1, $rows);
    $row = reset($rows);

    foreach (array('id', 'title', 'is_active', 'profiles', 'beneficiaries', 'location', 'entity_attributes') as $key) {
      $this->assertArrayHasKey($key, $row, "getManageData omitted $key.");
    }
    $this->assertSame(array($beneficiaryId), array_map('intval', $row['beneficiaries']));
    $this->assertSame('Volunteer HQ', $row['location']['address']['name']);
    $this->assertSame('Springfield', $row['location']['address']['city']);
  }

  /**
   * getWorkflowContext() bundles the reads every workflow step needs into one
   * response, each piece in the shape its standalone action returns.
   */
  public function testGetWorkflowContextBundlesTheWorkflowReads(): void {
    $beneficiaryId = $this->individualCreate(array('first_name' => 'Friends', 'last_name' => 'Of The Park'));
    $project = $this->createProject(array(
      'title' => 'Workflow context project',
      'project_contacts' => array(
        'volunteer_owner' => array($this->getMockedContactId()),
        'volunteer_beneficiary' => array($beneficiaryId),
      ),
    ));
    $projectId = (int) $project['id'];
    $need = $this->createNeed(array(
      'project_id' => $projectId,
      'start_time' => '2027-03-01 09:00:00',
      'duration' => 90,
      'is_flexible' => FALSE,
      'quantity' => 3,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => TRUE,
    ));
    \Civi\Api4\VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $need['id'])
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute();

    $context = VolunteerProject::getWorkflowContext(FALSE)
      ->setProjectId($projectId)
      ->execute()
      ->single();

    foreach (array('project', 'needs', 'assignments', 'capacity', 'supporting', 'beneficiary_names') as $key) {
      $this->assertArrayHasKey($key, $context, "getWorkflowContext omitted $key.");
    }
    $this->assertSame($projectId, (int) $context['project']['id']);
    $this->assertSame('Workflow context project', $context['project']['title']);

    $needIds = array_map('intval', array_column($context['needs'], 'id'));
    $this->assertContains((int) $need['id'], $needIds);
    $this->assertArrayHasKey('display_time', $context['needs'][0], 'Needs carry the display fields the steps render.');
    $this->assertArrayHasKey('role_label', $context['needs'][0]);

    $this->assertCount(1, $context['assignments']);
    $this->assertSame((int) $need['id'], (int) $context['assignments'][0]['volunteer_need_id']);
    $this->assertArrayHasKey('assignee_display_name', $context['assignments'][0]);

    $this->assertSame(1, (int) $context['capacity']['filled']);
    $this->assertSame(3, (int) $context['capacity']['total']);
    $this->assertSame(1, (int) $context['capacity']['by_need'][$need['id']]);

    $this->assertArrayHasKey('roles', $context['supporting']['workflow']);
    $this->assertArrayHasKey('shift_filter_presets', $context['supporting']['workflow']);
    $this->assertArrayHasKey('relationship_types', $context['supporting']['project']);
    $this->assertArrayHasKey('defaults', $context['supporting']['project']);

    $this->assertSame(array('Friends Of The Park'), $context['beneficiary_names']);
  }

  /**
   * A project that does not exist is reported as such rather than as an
   * empty bundle, which is what the workflow shows the user.
   */
  public function testGetWorkflowContextRefusesAMissingProject(): void {
    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('The volunteer project does not exist.');
    VolunteerProject::getWorkflowContext(FALSE)->setProjectId(999999999)->execute();
  }

  /**
   * The location actions round-trip a location block.
   */
  public function testLocationActionsRoundTrip(): void {
    $project = $this->createProject(array(
      'title' => 'Location round trip',
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
      'location' => $this->locationValues(),
    ));
    $projectId = (int) $project['id'];
    $locBlockId = (int) $project['loc_block_id'];
    $this->assertGreaterThan(0, $locBlockId);

    $options = VolunteerProject::getLocationOptions(FALSE)
      ->setProjectId($projectId)
      ->execute();
    $this->assertContains($locBlockId, array_map('intval', $options->column('id')));
    $this->assertStringContainsString('Volunteer HQ', $options->first()['title']);

    $loaded = VolunteerProject::getLocation(FALSE)
      ->setId($locBlockId)
      ->setProjectId($projectId)
      ->execute()
      ->single();
    $this->assertSame($locBlockId, (int) $loaded['id']);
    $this->assertSame('Volunteer HQ', $loaded['address']['name']);
    $this->assertSame('hq@example.org', $loaded['email']['email']);
    $this->assertSame('555-0100', $loaded['phone']['phone']);

    $saved = VolunteerProject::saveLocation(FALSE)
      ->setProjectId($projectId)
      ->setValues($this->locationValues(array('name' => 'Second site', 'city' => 'Shelbyville')))
      ->execute()
      ->single();
    $this->assertGreaterThan(0, (int) $saved['id']);
    $this->assertNotSame($locBlockId, (int) $saved['id']);
  }

  /**
   * A location that belongs to neither the project nor the site default is
   * refused, so a caller cannot read arbitrary loc blocks.
   */
  public function testGetLocationRejectsForeignLocationBlocks(): void {
    $mine = $this->createProject(array(
      'title' => 'Owns a location',
      'location' => $this->locationValues(),
    ));
    $theirs = $this->createProject(array(
      'title' => 'Owns another location',
      'location' => $this->locationValues(array('name' => 'Not yours')),
    ));

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('does not belong');
    VolunteerProject::getLocation(FALSE)
      ->setId($theirs['loc_block_id'])
      ->setProjectId($mine['id'])
      ->execute();
  }

  /**
   * removeProfile() detaches a profile, and refuses a cross-project join.
   */
  public function testRemoveProfileDetachesOnlyItsOwnProjectsJoin(): void {
    $profileId = $this->createProfile('API4 removable profile');
    $mine = $this->createProject(array(
      'title' => 'Profile owner',
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
      'profiles' => array(array('uf_group_id' => $profileId)),
    ));
    $theirs = $this->createProject(array(
      'title' => 'Other profile owner',
      'profiles' => array(array('uf_group_id' => $profileId)),
    ));

    $joinIds = array();
    foreach (array($mine, $theirs) as $project) {
      $joinIds[(int) $project['id']] = (int) \Civi\Api4\UFJoin::get(FALSE)
        ->addSelect('id')
        ->addWhere('entity_table', '=', 'civicrm_volunteer_project')
        ->addWhere('entity_id', '=', $project['id'])
        ->addWhere('module', '=', 'CiviVolunteer')
        ->execute()
        ->single()['id'];
    }

    try {
      VolunteerProject::removeProfile(FALSE)
        ->setId($joinIds[(int) $theirs['id']])
        ->setProjectId($mine['id'])
        ->execute();
      $this->fail('A profile join was removed through an unrelated project.');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('does not belong', $e->getMessage());
    }

    VolunteerProject::removeProfile(FALSE)
      ->setId($joinIds[(int) $mine['id']])
      ->setProjectId($mine['id'])
      ->execute();

    $this->assertCount(
      0,
      \Civi\Api4\UFJoin::get(FALSE)->addWhere('id', '=', $joinIds[(int) $mine['id']])->execute()
    );
    $this->assertCount(
      1,
      \Civi\Api4\UFJoin::get(FALSE)->addWhere('id', '=', $joinIds[(int) $theirs['id']])->execute()
    );
  }

  /**
   * The custom actions enforce project permissions when asked to.
   */
  public function testCustomActionsRefuseUnprivilegedCallers(): void {
    $project = $this->createProject(array('title' => 'Guarded custom actions'));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'register to volunteer',
    );

    foreach (array('getManageOverview', 'getWorkflowContext', 'getLocationOptions', 'commit') as $action) {
      try {
        $call = VolunteerProject::$action(TRUE);
        if ($action === 'commit') {
          $call->setValues(array('id' => $project['id'], 'title' => 'Renamed'));
        }
        if ($action === 'getManageOverview') {
          $call->setFilters(array('id' => $project['id']));
        }
        if ($action === 'getLocationOptions' || $action === 'getWorkflowContext') {
          $call->setProjectId($project['id']);
        }
        $call->execute();
        $this->fail("VolunteerProject.$action ran without project rights.");
      }
      catch (Throwable $e) {
        $this->addToAssertionCount(1);
      }
    }
  }

  /**
   * The management screen loads for a project editor, not just an administrator.
   *
   * getManageOverview is a custom action, so it needs its own entry in
   * VolunteerProject::permissions(). Without one, APIv4 falls back to the
   * 'default' permission — 'administer CiviCRM' — and the redesigned Manage
   * Projects page is rejected for exactly the users it is built for.
   */
  public function testManageOverviewRunsForProjectEditors(): void {
    $project = $this->createProject(array('title' => 'Editor visible project'));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'edit own volunteer projects',
    );

    $overview = VolunteerProject::getManageOverview(TRUE)
      ->setFilters(array('id' => $project['id']))
      ->execute()
      ->single();

    $this->assertArrayHasKey('summary', $overview);
    $this->assertArrayHasKey('projects', $overview);
    $this->assertContains(
      (int) $project['id'],
      array_map('intval', array_column($overview['projects'], 'id'))
    );
  }


  /**
   * Nested location values for a project write.
   *
   * @param array $address
   * @return array
   */
  private function locationValues(array $address = array()): array {
    return array(
      'address' => $address + array(
        'name' => 'Volunteer HQ',
        'street_address' => '1 Main St',
        'city' => 'Springfield',
      ),
      'email' => array('email' => 'hq@example.org'),
      'phone' => array('phone' => '555-0100'),
    );
  }

  /**
   * @param string $title
   * @return int
   */
  private function createProfile(string $title): int {
    $group = \Civi\Api4\UFGroup::create(FALSE)
      ->addValue('title', $title)
      ->addValue('is_active', TRUE)
      ->execute()
      ->single();
    return (int) $group['id'];
  }

}
