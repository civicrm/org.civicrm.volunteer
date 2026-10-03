<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\UFGroup;

/**
 * Compatibility tests for CiviVolunteer's deprecated APIv3 actions.
 *
 * This is the only file in the suite that may invoke civicrm_api3(). Every
 * public APIv3 action the extension still ships is a thin adapter over an API4
 * action or a shared domain method; these tests pin the legacy request and
 * result shapes those adapters promise -- parameter aliases, ID-keyed `values`
 * arrays, error envelopes and permission behaviour.
 *
 * New coverage belongs in the API4 suites under tests/phpunit/api/v4. The
 * static guard in CRM_Volunteer_Api3RegressionTest fails the build if APIv3
 * appears in production code or in any other test file.
 *
 * @group headless
 */
class api_v3_DeprecatedApi3AdapterTest extends VolunteerTestAbstract {

  private $campaignIds;
  private $contactIds;
  private $defaults;

  public function setUpHeadless() {
    parent::setUpHeadless();
    $this->createContacts();
    $this->createCampaigns();
    $this->setProjectDefaults();
  }

  public function setUp(): void {
    $this->quickCleanup(array(
      'civicrm_volunteer_need',
      'civicrm_volunteer_project',
      'civicrm_volunteer_project_contact',
    ));
    parent::setUp();
    $this->defaults = CRM_Volunteer_BAO_Project::composeDefaultSettingsArray();
  }

  /**
   * Call APIv3 and assert a success envelope.
   *
   * The single sanctioned APIv3 entry point in this suite.
   */
  protected function callAPISuccess(string $entity, string $action, array $params = array()): array {
    $result = civicrm_api3($entity, $action, $params);
    $this->assertSame(0, (int) ($result['is_error'] ?? 0), $result['error_message'] ?? 'API call failed.');
    return $result;
  }

  /**
   * Records written through API4 are readable through the APIv3 adapters.
   *
   * The adapters are the compatibility surface, so the round trip has to work
   * in both directions: this covers API4 write -> APIv3 read for each entity
   * whose APIv3 get action survives.
   */
  public function testApi4WritesAreVisibleThroughDeprecatedApi3Reads(): void {
    $project = \Civi\Api4\VolunteerProject::commit(FALSE)
      ->setValues(array('title' => 'Written through API4', 'is_active' => TRUE))
      ->execute()
      ->single();
    $projectId = (int) $project['id'];

    $api3Project = $this->callAPISuccess('VolunteerProject', 'getsingle', array('id' => $projectId));
    $this->assertSame('Written through API4', $api3Project['title']);

    $need = \Civi\Api4\VolunteerNeed::create(FALSE)
      ->setValues(array(
        'project_id' => $projectId,
        'start_time' => '2027-01-15 09:00:00',
        'duration' => 90,
        'is_flexible' => FALSE,
        'quantity' => 4,
        'visibility_id' => $this->getOptionValue('visibility', 'public'),
        'is_active' => TRUE,
      ))
      ->execute()
      ->single();

    $api3Need = $this->callAPISuccess('VolunteerNeed', 'getsingle', array('id' => $need['id']));
    $this->assertSame($projectId, (int) $api3Need['project_id']);
    $this->assertSame('4', (string) $api3Need['quantity']);

    $contactId = $this->individualCreate();
    $projectContact = \Civi\Api4\VolunteerProjectContact::create(FALSE)
      ->addValue('project_id', $projectId)
      ->addValue('contact_id', $contactId)
      ->addValue('relationship_type_id:name', 'volunteer_manager')
      ->execute()
      ->single();

    $api3ProjectContact = $this->callAPISuccess('VolunteerProjectContact', 'getsingle', array(
      'id' => $projectContact['id'],
    ));
    $this->assertSame($projectId, (int) $api3ProjectContact['project_id']);
    $this->assertSame('volunteer_manager', $api3ProjectContact['relationship_type_name']);

    $assignment = \Civi\Api4\VolunteerAssignment::create(FALSE)
      ->setValues(array(
        'volunteer_need_id' => $need['id'],
        'assignee_contact_id' => $contactId,
        'source_contact_id' => $this->getMockedContactId(),
      ))
      ->execute()
      ->single();

    $api3Assignment = $this->callAPISuccess('VolunteerAssignment', 'get', array(
      'id' => $assignment['id'],
    ));
    $this->assertSame(1, (int) $api3Assignment['count']);
    $this->assertArrayHasKey((int) $assignment['id'], $api3Assignment['values']);
  }

  /**
   * Every APIv3 action the extension ships maps onto an API4 action or a
   * shared domain method.
   *
   * The provider is the call-site conversion matrix: it names each surviving
   * APIv3 action, the API4 action that now owns the implementation, and
   * whether the APIv3 action is a pure adapter. Any action added to api/v3
   * without a row here fails testEveryApi3ActionIsCovered().
   *
   * @return array
   */
  public function api3ToApi4Matrix(): array {
    return array(
      // [apiv3 entity, apiv3 action, api4 entity, api4 action]
      'project create' => array('VolunteerProject', 'create', 'VolunteerProject', 'commit'),
      'project get' => array('VolunteerProject', 'get', 'VolunteerProject', 'search'),
      'project delete' => array('VolunteerProject', 'delete', 'VolunteerProject', 'delete'),
      'project removeprofile' => array('VolunteerProject', 'removeprofile', 'VolunteerProject', 'removeProfile'),
      'project locations' => array('VolunteerProject', 'locations', 'VolunteerProject', 'getLocationOptions'),
      'project getlocblockdata' => array('VolunteerProject', 'getlocblockdata', 'VolunteerProject', 'getLocation'),
      'project savelocblock' => array('VolunteerProject', 'savelocblock', 'VolunteerProject', 'saveLocation'),
      'need create' => array('VolunteerNeed', 'create', 'VolunteerNeed', 'create'),
      'need get' => array('VolunteerNeed', 'get', 'VolunteerNeed', 'get'),
      'need getsearchresult' => array('VolunteerNeed', 'getsearchresult', 'VolunteerNeed', 'search'),
      'need delete' => array('VolunteerNeed', 'delete', 'VolunteerNeed', 'delete'),
      'assignment create' => array('VolunteerAssignment', 'create', 'VolunteerAssignment', 'create'),
      'assignment get' => array('VolunteerAssignment', 'get', 'VolunteerAssignment', 'get'),
      'assignment delete' => array('VolunteerAssignment', 'delete', 'VolunteerAssignment', 'delete'),
      'project contact create' => array('VolunteerProjectContact', 'create', 'VolunteerProjectContact', 'create'),
      'project contact get' => array('VolunteerProjectContact', 'get', 'VolunteerProjectContact', 'get'),
      'project contact delete' => array('VolunteerProjectContact', 'delete', 'VolunteerProjectContact', 'delete'),
      'commendation get' => array('VolunteerCommendation', 'get', 'VolunteerCommendation', 'get'),
      'util getperms' => array('VolunteerUtil', 'getperms', 'VolunteerUtil', 'getPermissions'),
      'util getprofiles' => array('VolunteerUtil', 'getprofiles', 'VolunteerUtil', 'getProfiles'),
      'util getsupportingdata' => array('VolunteerUtil', 'getsupportingdata', 'VolunteerUtil', 'getSupportingData'),
      'util getcountries' => array('VolunteerUtil', 'getcountries', 'VolunteerUtil', 'getCountries'),
      'util getcustomfields' => array('VolunteerUtil', 'getcustomfields', 'VolunteerUtil', 'getCustomFields'),
    );
  }

  /**
   * @dataProvider api3ToApi4Matrix
   */
  public function testMatrixActionsAreRegisteredOnBothSides(
    string $api3Entity,
    string $api3Action,
    string $api4Entity,
    string $api4Action
  ): void {
    $api3Actions = $this->callAPISuccess($api3Entity, 'getactions', array());
    $this->assertContains(
      $api3Action,
      $api3Actions['values'],
      "The deprecated $api3Entity.$api3Action action is gone."
    );

    $api4Class = '\\Civi\\Api4\\' . $api4Entity;
    $this->assertTrue(
      method_exists($api4Class, $api4Action),
      "$api4Entity::$api4Action() does not exist, so $api3Entity.$api3Action has no API4 owner."
    );
  }

  /**
   * The matrix covers every APIv3 action the extension still declares.
   */
  public function testEveryApi3ActionIsCovered(): void {
    $covered = array();
    foreach ($this->api3ToApi4Matrix() as $row) {
      $covered[] = strtolower($row[0] . '.' . $row[1]);
    }

    $declared = array();
    foreach (glob(dirname(__DIR__, 4) . '/api/v3/*.php') as $file) {
      $entity = basename($file, '.php');
      $prefix = 'civicrm_api3_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entity)) . '_';
      if (!preg_match_all('/^function\s+(' . preg_quote($prefix, '/') . '\w+)\s*\(/m', file_get_contents($file), $matches)) {
        continue;
      }
      foreach ($matches[1] as $function) {
        $action = substr($function, strlen($prefix));
        // getbeneficiaries is deprecated with no API4 replacement by design.
        if ($action === 'getbeneficiaries') {
          continue;
        }
        $declared[] = strtolower($entity . '.' . $action);
      }
    }

    $this->assertNotEmpty($declared, 'Failed to enumerate the APIv3 actions.');
    $this->assertSame(
      array(),
      array_values(array_diff($declared, $covered)),
      'These APIv3 actions are missing from the conversion matrix.'
    );
  }

  // -------------------------------------------------------------------
  // Migrated from the former api_v3_VolunteerProjectTest class.
  // -------------------------------------------------------------------



  private function createContacts() {
    $api = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'first_name' => '1',
      'last_name' => 'Owner',
    ));
    $this->contactIds['owner1'] = $api['id'];

    $api = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'first_name' => '1',
      'last_name' => 'Manager',
    ));
    $this->contactIds['manager1'] = $api['id'];

    $api = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'first_name' => '2',
      'last_name' => 'Manager',
    ));
    $this->contactIds['manager2'] = $api['id'];
  }

  private function createCampaigns() {
    $api = civicrm_api3('Campaign', 'create', array(
      'title' => 'first',
    ));
    $this->campaignIds['first'] = $api['id'];

    $api = civicrm_api3('Campaign', 'create', array(
      'title' => 'second',
    ));
    $this->campaignIds['second'] = $api['id'];
  }

  private function setProjectDefaults() {
    civicrm_api3('Setting', 'create', array(
      'volunteer_project_default_campaign' => $this->campaignIds['first'],
    ));
  }

  /**
   * Test simple create via API
   */
  public function testCreateProject(): void {
    $params = array(
      'entity_id' => 1,
      'entity_table' => 'civicrm_event',
      'is_active' => 1,
      'title' => 'Unit Testing for CiviVolunteer (How Meta)',
    );

    $api = civicrm_api3('VolunteerProject', 'create', $params);
    $this->assertTrue(is_numeric($api['id']));
    $this->assertTrue($api['id'] > 0);

    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);
    $this->assertEquals(1, $project->find());
  }

  /**
   * Test creating a standalone project without a related entity.
   */
  public function testCreateStandaloneProject(): void {
    $api = civicrm_api3('VolunteerProject', 'create', array(
      'title' => 'Standalone Volunteer Project',
    ));

    $project = CRM_Volunteer_BAO_Project::retrieveByID($api['id']);
    $this->assertNull($project->entity_table);
    $this->assertNull($project->entity_id);
    $this->assertNotNull(
      CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $api['id'])
    );
  }

  /**
   * Tests the project_contacts parameter to the create API, i.e., tests the
   * ability to specify at project creation the contacts related to the project.
   */
  public function testCreateProjectWithContacts(): void {
    $projectContacts = array(
      'volunteer_owner' => array($this->contactIds['owner1']),
      'volunteer_manager' => array($this->contactIds['manager1'], $this->contactIds['manager2']),
    );

    $params = array(
      'project_contacts' => $projectContacts,
      'title' => 'Unit Testing for CiviVolunteer (How Meta)',
    );

    $project = civicrm_api3('VolunteerProject', 'create', $params);

    $bao = new CRM_Volunteer_BAO_ProjectContact();
    $bao->project_id = $project['id'];
    $bao->relationship_type_id = $this->getOptionValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner');
    $this->assertEquals(count($projectContacts['volunteer_owner']), $bao->find());

    $bao->relationship_type_id = $this->getOptionValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_manager');
    $this->assertEquals(count($projectContacts['volunteer_manager']), $bao->find());
  }

  /**
   * Test simple delete via API
   */
  public function testDeleteProjectByID(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array('title' => 'Delete Me'));
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    civicrm_api3('VolunteerProject', 'delete', array('id' => $project->id));

    $projectSearch = new CRM_Volunteer_BAO_Project();
    $params = array('id' => $project->id);
    $projectSearch->copyValues($params);
    $this->assertEquals(0, $project->find());
  }

  /**
   * Test simple get via API
   */
  public function testGetProjectByID(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array('title' => 'Get Me'));
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $result = civicrm_api3('VolunteerProject', 'get', array('id' => $project->id));
    $this->assertEquals(1, $result['count']);
  }

  public function testPartialUpdateDoesNotRequireTitle(): void {
    $created = civicrm_api3('VolunteerProject', 'create', array(
      'title' => 'Title survives a partial update',
      'is_active' => 1,
    ));

    civicrm_api3('VolunteerProject', 'create', array(
      'id' => $created['id'],
      'is_active' => 0,
    ));

    $project = CRM_Volunteer_BAO_Project::retrieveByID((int) $created['id']);
    $this->assertSame('Title survives a partial update', $project->title);
    $this->assertSame('0', (string) $project->is_active);
  }

  public function testRequestCannotDisableProjectAuthorization(): void {
    $created = civicrm_api3('VolunteerProject', 'create', array(
      'title' => 'Owned by a different contact',
      'project_contacts' => array(
        'volunteer_owner' => array($this->contactIds['owner1']),
      ),
    ));
    $this->setCoordPerms();

    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerProject', 'create', array(
      'check_permissions' => FALSE,
      'id' => $created['id'],
      'title' => 'Unauthorized title',
    ));
  }

  public function testMalformedProfileMetadataRollsBackProjectUpdate(): void {
    $created = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Title before failed aggregate update',
    ));
    $profile = \Civi\Api4\UFGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('is_active', '=', TRUE)
      ->setLimit(1)
      ->execute()
      ->first();
    $this->assertNotNull($profile);

    try {
      civicrm_api3('VolunteerProject', 'create', array(
        'id' => $created['id'],
        'title' => 'This title must roll back',
        'profiles' => array(array(
          'uf_group_id' => $profile['id'],
          'module_data' => '{invalid-json',
        )),
      ));
      $this->fail('Malformed profile metadata should reject the project aggregate update.');
    }
    catch (CiviCRM_API3_Exception $e) {
      $this->assertStringContainsString('not valid JSON', $e->getMessage());
    }

    $project = CRM_Volunteer_BAO_Project::retrieveByID((int) $created['id']);
    $this->assertSame('Title before failed aggregate update', $project->title);
  }

  public function testApi4DomainActionsAreRegistered(): void {
    $actions = \Civi\Api4\VolunteerProject::getActions(FALSE)
      ->execute()
      ->column('name');

    $this->assertContains('get', $actions);
    $this->assertContains('create', $actions);
    $this->assertContains('update', $actions);
    $this->assertContains('delete', $actions);
  }

  public function testRemoveProfileRejectsCrossProjectJoin(): void {
    $profile = \Civi\Api4\UFGroup::create(FALSE)
      ->addValue('name', uniqid('cross_project_profile_', FALSE))
      ->addValue('title', 'Cross-project profile')
      ->addValue('is_active', TRUE)
      ->execute()
      ->single();
    $projectA = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Profile owner project',
      'profiles' => array(array(
        'uf_group_id' => $profile['id'],
        'module_data' => array('audience' => 'primary'),
        'weight' => 1,
      )),
    ));
    $projectB = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Unrelated profile project',
    ));
    $join = civicrm_api3('UFJoin', 'getsingle', array(
      'entity_table' => 'civicrm_volunteer_project',
      'entity_id' => $projectA['id'],
      'module' => 'CiviVolunteer',
      'uf_group_id' => $profile['id'],
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'edit all volunteer projects',
      'edit volunteer registration profiles',
    );
    try {
      civicrm_api3('VolunteerProject', 'removeprofile', array(
        'id' => $join['id'],
        'project_id' => $projectB['id'],
      ));
      $this->fail('A project must not delete another project\'s profile assignment.');
    }
    catch (CiviCRM_API3_Exception $e) {
      $this->assertStringContainsString('does not belong', $e->getMessage());
    }

    $this->assertSame(
      (int) $join['id'],
      (int) civicrm_api3('UFJoin', 'getvalue', array(
        'id' => $join['id'],
        'return' => 'id',
      ))
    );
  }

  private function compareProjectEntityFields($expected, $actual) {
    foreach (array('is_active', 'campaign_id', 'loc_block_id') as $field) {
      $this->assertEquals($expected[$field], $actual[$field]);
    }
  }

  private function compareProfilesToDefaults($projectId) {
    $projectProfiles = civicrm_api3('UFJoin', 'get', array(
      'entity_id' => $projectId,
      'entity_table' => 'civicrm_volunteer_project',
    ));

    $defaultProfileIds = array();
    foreach ($this->defaults['profiles'] as $profile) {
      $defaultProfileIds[] = $profile['uf_group_id'];
    }
    $defaultProfileIds = array_unique($defaultProfileIds);
    sort($defaultProfileIds);

    $createdProfileIds = array();
    foreach ($projectProfiles['values'] as $p) {
      $createdProfileIds[] = $p['uf_group_id'];
    }
    $createdProfileIds = array_unique($createdProfileIds);
    sort($createdProfileIds);

    $this->assertEquals($defaultProfileIds, $createdProfileIds);
  }

  private function compareContactsToDefaults($projectId) {
    $api = civicrm_api3('VolunteerProjectContact', 'get', array(
      'project_id' => $projectId,
    ));

    // Format the API result in the same manner as the defaults
    $contacts = array();
    foreach ($api['values'] as $value) {
      $cid = (int) $value['contact_id'];
      $relationshipTypeId = (int) $value['relationship_type_id'];

      if (!array_key_exists($relationshipTypeId, $contacts)) {
        $contacts[$relationshipTypeId] = array();
      }

      if (!in_array($cid, $contacts[$relationshipTypeId])) {
        $contacts[$relationshipTypeId][] = $cid;
      }
    }

    // remove empty arrays to facilitate comparison
    $defaults = array();
    foreach ($this->defaults['relationships'] as $relTypeId => $arr) {
      if (!empty($arr)) {
        $defaults[$relTypeId] = $arr;
      }
    }

    $this->assertEquals($defaults, $contacts);
  }

  /**
   * Action: create project with just a title. Expectation: defaults applied to
   * nonspecified fields.
   */
  public function testAdminProjectDefaultsDuringCreate(): void {
    $api = civicrm_api3('VolunteerProject', 'create', array(
      'check_permissions' => 1,
      'title' => 'Unit Testing for CiviVolunteer (How Meta)',
    ));

    $bao = new CRM_Volunteer_BAO_Project();
    $projectArr = $bao->retrieveByID($api['id'])->toArray();
    $this->compareProjectEntityFields($this->defaults, $projectArr);

    $this->compareContactsToDefaults($api['id']);
    $this->compareProfilesToDefaults($api['id']);
  }

  /**
   * Action: update a project, specifying only project contacts. Expectation:
   * previously specified fields will not be overridden with defaults.
   */
  public function testAdminProjectDefaultsDuringContactUpdate(): void {
    // note that these params are different from the defaults
    $createParams = array(
      'campaign_id' => $this->campaignIds['second'],
      'is_active' => 0,
      'loc_block_id' => 1,
      'title' => "Tedious, isn't it?",
    );
    $create = civicrm_api3('VolunteerProject', 'create', $createParams);

    civicrm_api3('VolunteerProject', 'create', array(
      'id' => $create['id'],
      'project_contacts' => array(
        'volunteer_owner' => array($this->contactIds['owner1']),
        'volunteer_manager' => array($this->contactIds['manager1']),
        'volunteer_beneficiary' => array($this->contactIds['manager2']),
      ),
    ));

    $bao = new CRM_Volunteer_BAO_Project();
    $projectArr = $bao->retrieveByID($create['id'])->toArray();
    $this->compareProjectEntityFields($createParams, $projectArr);
  }

  /**
   * Action: update a project, specifying only profile joins. Expectation:
   * previously specified fields will not be overridden with defaults.
   */
  public function testAdminProjectDefaultsDuringProfileUpdate(): void {
    // note that these params are different from the defaults
    $createParams = array(
      'campaign_id' => $this->campaignIds['second'],
      'is_active' => 0,
      'loc_block_id' => 1,
      'title' => "Tedious, isn't it?",
    );
    $create = civicrm_api3('VolunteerProject', 'create', $createParams);

    civicrm_api3('VolunteerProject', 'create', array(
      'id' => $create['id'],
      'profiles' => array(
        array(
          'module_data' => array(
            'audience' => 'both',
          ),
          'uf_group_id' => 3,
          'weight' => 1,
        ),
      ),
    ));

    $bao = new CRM_Volunteer_BAO_Project();
    $projectArr = $bao->retrieveByID($create['id'])->toArray();
    $this->compareProjectEntityFields($createParams, $projectArr);
  }

  /**
   * Action: update a project, specifying only own-entity fields (i.e., fields
   * that are represented in civicrm_volunteer_project). Expectation: supplied
   * values will not be overridden by defaults.
   */
  public function testAdminProjectDefaultsDuringFieldUpdate(): void {
    $create = civicrm_api3('VolunteerProject', 'create', array(
      'title' => 'Sigue y sigue',
    ));

    // note that these params are different from the defaults
    $updateParams = array(
      'id' => $create['id'],
      'campaign_id' => $this->campaignIds['second'],
      'is_active' => 0,
      'loc_block_id' => 1,
    );
    civicrm_api3('VolunteerProject', 'create', $updateParams);

    $bao = new CRM_Volunteer_BAO_Project();
    $projectArr = $bao->retrieveByID($create['id'])->toArray();
    $this->compareProjectEntityFields($updateParams, $projectArr);
  }

  /**
   * Action: create project with just a title. Expectation: defaults applied to
   * nonspecified fields.
   */
  public function testCoordProjectDefaultsDuringCreate(): void {
    $this->setCoordPerms();
    $this->testAdminProjectDefaultsDuringCreate();
  }

  /**
   * Action: update project. Expectation: ancillary data is not dropped.
   */
  public function testCoordProjectDefaultsDuringUpdate(): void {
    $this->setCoordPerms();

    $create = civicrm_api3('VolunteerProject', 'create', array(
      'check_permissions' => 1,
      'title' => 'Project Title',
    ));

    civicrm_api3('VolunteerProject', 'create', array(
      'check_permissions' => 1,
      'id' => $create['id'],
      // change an arbitrary field
      'is_active' => 0,
    ));

    $this->compareContactsToDefaults($create['id']);
    $this->compareProfilesToDefaults($create['id']);
  }

  /**
   * Action: create project, specifying ancillary data. Expectation: supplied
   * ancillary data is ignored, defaults used.
   */
  public function testCoordProjectPermsDuringCreate(): void {
    $this->setCoordPerms();

    $create = civicrm_api3('VolunteerProject', 'create', array(
      'check_permissions' => 1,
      'title' => 'Jolines',
      'profiles' => array(
        array(
          'module_data' => array(
            'audience' => 'both',
          ),
          'uf_group_id' => 3,
          'weight' => 1,
        ),
      ),
      'project_contacts' => array(
        'volunteer_owner' => array($this->contactIds['owner1']),
        'volunteer_manager' => array($this->contactIds['manager1']),
        'volunteer_beneficiary' => array($this->contactIds['manager2']),
      ),
    ));

    $this->compareContactsToDefaults($create['id']);
    $this->compareProfilesToDefaults($create['id']);
  }

  /**
   * Action: update project, specifying ancillary data. Expectation: supplied
   * ancillary data is ignored and previously stored ancillary data remain
   * unchanged.
   */
  public function testCoordProjectPermsDuringUpdate(): void {
    $this->setCoordPerms();

    $create = civicrm_api3('VolunteerProject', 'create', array(
      'check_permissions' => 1,
      'title' => '¡Ya Basta!',
    ));

    $ucpdate = civicrm_api3('VolunteerProject', 'create', array(
      'check_permissions' => 1,
      'id' => $create['id'],
      'profiles' => array(
        array(
          'module_data' => array(
            'audience' => 'both',
          ),
          'uf_group_id' => 3,
          'weight' => 1,
        ),
      ),
      'project_contacts' => array(
        'volunteer_owner' => array($this->contactIds['owner1']),
        'volunteer_manager' => array($this->contactIds['manager1']),
        'volunteer_beneficiary' => array($this->contactIds['manager2']),
      ),
    ));

    $this->compareContactsToDefaults($create['id']);
    $this->compareProfilesToDefaults($create['id']);
  }

  /**
   * Notably missing from the list of a coordinator's (and perhaps we could have
   * come up with a better name) permissions are:
   *   - edit volunteer project relationships
   *   - edit volunteer registration profiles
   */
  function setCoordPerms() {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'delete own volunteer projects',
      'edit own volunteer projects',
    );
  }

  /**
   * An "edit own" caller must not be able to widen its own ownership filter.
   *
   * Clauses inside `project_contacts` are OR-ed together, so when the mandatory
   * owner constraint was merged into that same group a caller could add any
   * other relationship filter and pull back edit-context records -- including
   * entity_attributes and the full profile set -- for projects it does not own.
   */
  public function testEditOwnCannotEscalateViaExtraRelationshipFilter(): void {
    $ownContactId = $this->getMockedContactId();
    $strangerId = $this->individualCreate();
    $beneficiaryId = $this->individualCreate();

    $mine = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Owned by the caller',
      'project_contacts' => array('volunteer_owner' => array($ownContactId)),
    ));
    $theirs = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Owned by somebody else',
      'project_contacts' => array(
        'volunteer_owner' => array($strangerId),
        'volunteer_beneficiary' => array($beneficiaryId),
      ),
    ));

    $this->setCoordPerms();

    // The escalation attempt: a beneficiary filter naming a contact on the
    // project we do NOT own.
    $result = civicrm_api3('VolunteerProject', 'get', array(
      'check_permissions' => 1,
      'context' => 'edit',
      'project_contacts' => array('volunteer_beneficiary' => array($beneficiaryId)),
    ));

    $returnedIds = array_map('intval', array_keys($result['values']));
    $this->assertNotContains(
      (int) $theirs['id'],
      $returnedIds,
      'A project owned by another contact leaked through an extra relationship filter.'
    );
    // The owner constraint is AND-ed, and this caller owns no project matching
    // that beneficiary, so nothing should come back at all.
    $this->assertSame(array(), $returnedIds);

    // Sanity check: the caller can still read its own project.
    $own = civicrm_api3('VolunteerProject', 'get', array(
      'check_permissions' => 1,
      'context' => 'edit',
    ));
    $this->assertSame(array((int) $mine['id']), array_map('intval', array_keys($own['values'])));
  }

  /**
   * A caller cannot supply the internal mandatory-ownership parameter itself.
   */
  public function testRequiredContactsParamIsNotCallerSettable(): void {
    $ownContactId = $this->getMockedContactId();
    $strangerId = $this->individualCreate();

    $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Owned by the caller',
      'project_contacts' => array('volunteer_owner' => array($ownContactId)),
    ));
    $theirs = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Owned by somebody else',
      'project_contacts' => array('volunteer_owner' => array($strangerId)),
    ));

    $this->setCoordPerms();

    $result = civicrm_api3('VolunteerProject', 'get', array(
      'check_permissions' => 1,
      'context' => 'edit',
      CRM_Volunteer_BAO_Project::REQUIRED_CONTACTS_PARAM => array(
        'volunteer_owner' => array($strangerId),
      ),
    ));

    $returnedIds = array_map('intval', array_keys($result['values']));
    $this->assertNotContains((int) $theirs['id'], $returnedIds);
  }

  /**
   * A project with only completed assignments must not be deletable.
   *
   * The guard counted VolunteerAssignment.getcount, which sees only Scheduled
   * and Available. A project whose assignments had all been completed therefore
   * deleted cleanly, and the hard delete of its needs orphaned the
   * volunteer_need_id on every completed activity -- removing that volunteer
   * history from every roster and report, which is the precise opposite of what
   * the guard's message promises.
   */
  public function testProjectWithCompletedAssignmentsCannotBeDeleted(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Has completed history',
      'is_active' => 1,
    ));
    $need = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project['id'],
      'start_time' => '2020-01-02 09:00:00',
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 5,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $volunteerId = $this->individualCreate();

    $completedStatus = $this->getOptionValue('activity_status', 'Completed');
    CRM_Volunteer_Permission::withInternalBypass(function() use ($need, $volunteerId, $completedStatus) {
      CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
        'check_permissions' => FALSE,
        'volunteer_need_id' => $need['id'],
        'assignee_contact_id' => $volunteerId,
        'status_id' => $completedStatus,
        'activity_date_time' => '2020-01-02 09:00:00',
      ));
    });

    $this->assertSame(
      1,
      CRM_Volunteer_BAO_Assignment::getProjectAssignmentCount((int) $project['id']),
      'The completed assignment was not counted.'
    );

    $threw = FALSE;
    try {
      civicrm_api3('VolunteerProject', 'delete', array(
        'check_permissions' => FALSE,
        'id' => $project['id'],
      ));
    }
    catch (Throwable $e) {
      $threw = TRUE;
      $this->assertStringContainsString('cannot be deleted', $e->getMessage());
    }
    $this->assertTrue($threw, 'A project with completed volunteer history was deleted.');

    // The need -- and therefore the activity's link to it -- must survive.
    $this->assertNotEmpty(civicrm_api3('VolunteerNeed', 'get', array(
      'check_permissions' => FALSE,
      'id' => $need['id'],
    ))['values']);
  }

  /**
   * Project creation survives a renamed or deleted volunteer_sign_up profile.
   *
   * The default came from an UFGroup.getvalue() evaluated inside the settings
   * metadata file and again on every project create. getvalue() throws when no
   * match exists, so renaming the shipped profile turned both a settings-cache
   * rebuild and every project create into a fatal.
   */
  public function testProjectCreateSurvivesMissingSignupProfile(): void {
    $profileId = CRM_Volunteer_BAO_Project::getDefaultSignupProfileId();
    $this->assertNotNull($profileId, 'The volunteer_sign_up profile should exist after installation.');

    // Simulate an administrator renaming it. Restored in the finally: if this
    // test's transaction does not roll back -- an aborted run, or anything that
    // issues DDL -- the shipped profile stays renamed and every later run fails
    // this same assertion, for good.
    try {
      civicrm_api3('UFGroup', 'create', array(
        'check_permissions' => FALSE,
        'id' => $profileId,
        'name' => 'renamed_by_an_administrator',
      ));
      $reset = array('volunteer_project_default_profiles' => NULL);
      CRM_Core_BAO_Setting::setItems($reset);

      $this->assertNull(
        CRM_Volunteer_BAO_Project::getDefaultSignupProfileId(),
        'The resolver should report absence rather than throwing.'
      );

      $project = $this->callAPISuccess('VolunteerProject', 'create', array(
        'check_permissions' => FALSE,
        'title' => 'Created without a signup profile',
      ));
      $this->assertNotEmpty($project['id']);
    }
    finally {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_uf_group SET name = 'volunteer_sign_up' WHERE id = %1",
        array(1 => array($profileId, 'Integer'))
      );
    }
  }

  /**
   * An audience stored as NULL does not trigger a foreach warning.
   *
   * CRM_Volunteer_Form_Settings stores NULL for any audience the administrator
   * left blank, and composeDefaultSettingsArray() iterated it directly.
   */
  public function testProjectCreateToleratesNullAudienceDefaults(): void {
    $nullAudiences = array(
      'volunteer_project_default_profiles' => array(
        'primary' => NULL,
        'additional' => NULL,
      ),
    );
    CRM_Core_BAO_Setting::setItems($nullAudiences);

    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'check_permissions' => FALSE,
      'title' => 'Null audience defaults',
    ));
    $this->assertNotEmpty($project['id']);
  }

  /**
   * Editing a project's location must not leak the previous location block.
   *
   * saveLocationBlock() deliberately builds a fresh block every save so an edit
   * cannot mutate a location shared with another entity, but the superseded
   * block was never removed -- and the project's FK is ON DELETE SET NULL, so
   * nothing ever collected it. Every location edit leaked one loc_block plus
   * its addresses, emails and phones.
   */
  public function testEditingLocationDoesNotLeakLocBlocks(): void {
    $countBlocks = function() {
      return (int) CRM_Core_DAO::singleValueQuery('SELECT COUNT(*) FROM civicrm_loc_block');
    };
    $countAddresses = function() {
      return (int) CRM_Core_DAO::singleValueQuery('SELECT COUNT(*) FROM civicrm_address');
    };

    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'check_permissions' => FALSE,
      'title' => 'Location churn',
      'location' => array(
        'address' => array('street_address' => '1 First Street', 'city' => 'Firstville'),
      ),
    ));
    $firstBlockId = (int) CRM_Core_DAO::getFieldValue(
      'CRM_Volunteer_DAO_Project',
      $project['id'],
      'loc_block_id'
    );
    $this->assertGreaterThan(0, $firstBlockId, 'The project should have a location block.');

    $blocksAfterCreate = $countBlocks();
    $addressesAfterCreate = $countAddresses();

    // Three successive location edits.
    for ($i = 2; $i <= 4; $i++) {
      $this->callAPISuccess('VolunteerProject', 'create', array(
        'check_permissions' => FALSE,
        'id' => $project['id'],
        'location' => array(
          'address' => array('street_address' => "$i Second Street", 'city' => 'Editville'),
        ),
      ));
    }

    $this->assertSame(
      $blocksAfterCreate,
      $countBlocks(),
      'Superseded location blocks accumulated.'
    );
    $this->assertSame(
      $addressesAfterCreate,
      $countAddresses(),
      'Superseded addresses accumulated.'
    );
    $this->assertSame(
      0,
      (int) CRM_Core_DAO::singleValueQuery(
        'SELECT COUNT(*) FROM civicrm_loc_block WHERE id = %1',
        array(1 => array($firstBlockId, 'Integer'))
      ),
      'The original location block should have been collected.'
    );
  }

  /**
   * Location types are resolved, not hard-coded to ids 1 and 2.
   */
  public function testLocationTypesAreResolvedNotHardCoded(): void {
    $primary = CRM_Volunteer_BAO_Project::getPrimaryLocationTypeId();
    $secondary = CRM_Volunteer_BAO_Project::getSecondaryLocationTypeId($primary);

    $this->assertGreaterThan(0, $primary);
    $this->assertGreaterThan(0, $secondary);
    $this->assertNotSame($primary, $secondary, 'Secondary location data would collide with primary.');
    $this->assertSame(
      1,
      (int) CRM_Core_DAO::singleValueQuery(
        'SELECT COUNT(*) FROM civicrm_location_type WHERE id = %1 AND is_default = 1',
        array(1 => array($primary, 'Integer'))
      ),
      'The primary type should be the installation default.'
    );
  }

  // -------------------------------------------------------------------
  // Migrated from the former api_v3_VolunteerNeedTest class.
  // -------------------------------------------------------------------


  /**
   * Test simple create via API
   */
  public function testCreateNeed(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $params = array(
      "project_id"    => $project->id,
      "start_time"    => "2013-12-17 16:00:00",
      "duration"      => 240,
      "is_flexible"   => 0,
      "quantity"      => 1,
      "visibility_id" => $this->getOptionValue('visibility', 'public'),
      "role_id"       => 1,
      "is_active"     => 1,
    );

    $this->callAPISuccess('VolunteerNeed', 'create', $params);
  }

  /**
   * Test simple delete via API
   */
  public function testDeleteNeedbyID(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Need deletion test',
    ));
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'project_id' => $project['id'],
      'is_flexible' => 0,
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->callAPISuccess('VolunteerNeed', 'delete', array('id' => $need->id));
  }

  public function testDeleteNeedReassignsActivitiesToFlexibleNeed(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Need assignment preservation test',
    ));
    $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $project['id']);
    $datedNeed = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('+1 week noon')),
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'role_id' => 1,
      'is_active' => 1,
    ));
    $contactId = $this->individualCreate();
    $assignment = $this->callAPISuccess('VolunteerAssignment', 'create', array(
      'assignee_contact_id' => $contactId,
      'source_contact_id' => $contactId,
      'volunteer_need_id' => $datedNeed['id'],
    ));

    $this->callAPISuccess('VolunteerNeed', 'delete', array('id' => $datedNeed['id']));

    $moved = $this->callAPISuccess('VolunteerAssignment', 'getsingle', array(
      'id' => $assignment['id'],
    ));
    $this->assertSame((int) $flexibleNeedId, (int) $moved['volunteer_need_id']);
  }

  public function testDeleteNeedPreservesEveryAssignmentStatusAndHistory(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Historical assignment preservation',
    ));
    $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $project['id']);
    $datedNeed = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-27 09:00:00',
      'duration' => 75,
      'is_flexible' => 0,
      'quantity' => 10,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'role_id' => 1,
      'is_active' => 1,
    ));

    $activityIds = array();
    foreach (array('Scheduled', 'Completed', 'No_show') as $offset => $statusName) {
      $activityIds[] = CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
        'volunteer_need_id' => $datedNeed['id'],
        'assignee_contact_id' => $this->individualCreate(),
        'source_contact_id' => $this->getMockedContactId(),
        'status_id' => $this->getOptionValue('activity_status', $statusName),
        'activity_date_time' => sprintf('2026-12-27 %02d:00:00', 9 + $offset),
        'duration' => 75 + $offset,
        'time_completed_minutes' => $statusName === 'Completed' ? 72 : 0,
      ));
    }
    $deletedActivityId = CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
      'volunteer_need_id' => $datedNeed['id'],
      'assignee_contact_id' => $this->individualCreate(),
      'source_contact_id' => $this->getMockedContactId(),
      'status_id' => $this->getOptionValue('activity_status', 'Scheduled'),
    ));
    civicrm_api3('Activity', 'create', array(
      'check_permissions' => FALSE,
      'id' => $deletedActivityId,
      'is_deleted' => 1,
    ));
    $activityIds[] = $deletedActivityId;

    $customGroup = CRM_Volunteer_BAO_Assignment::getCustomGroup();
    $customFields = CRM_Volunteer_BAO_Assignment::getCustomFields();
    $needColumn = $customFields['volunteer_need_id']['column_name'];
    $idList = implode(',', array_map('intval', $activityIds));
    $readRows = function() use ($customGroup, $needColumn, $idList) {
      $rows = array();
      $dao = CRM_Core_DAO::executeQuery(sprintf(
        'SELECT a.id, a.status_id, a.activity_date_time, a.duration, a.is_deleted, cv.`%s` AS volunteer_need_id
           FROM civicrm_activity a
           INNER JOIN `%s` cv ON cv.entity_id = a.id
          WHERE a.id IN (%s)
          ORDER BY a.id',
        $needColumn,
        $customGroup['table_name'],
        $idList
      ));
      while ($dao->fetch()) {
        $rows[(int) $dao->id] = array(
          'status_id' => (int) $dao->status_id,
          'activity_date_time' => $dao->activity_date_time,
          'duration' => $dao->duration === NULL ? NULL : (int) $dao->duration,
          'is_deleted' => (int) $dao->is_deleted,
          'volunteer_need_id' => (int) $dao->volunteer_need_id,
        );
      }
      return $rows;
    };

    $before = $readRows();
    $this->callAPISuccess('VolunteerNeed', 'delete', array('id' => $datedNeed['id']));
    $after = $readRows();

    $this->assertSame(array_keys($before), array_keys($after));
    foreach ($before as $activityId => $original) {
      $this->assertSame((int) $flexibleNeedId, $after[$activityId]['volunteer_need_id']);
      unset($original['volunteer_need_id'], $after[$activityId]['volunteer_need_id']);
      $this->assertSame($original, $after[$activityId], "Activity $activityId history changed during need deletion.");
    }
  }

  /**
   * Test simple get via API
   */
  public function testGetNeedbyID(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array(
      'is_active' => 1,
    ));
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'project_id' => $project->id,
      'is_active' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $this->assertObjectHasProperty('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->callAPISuccess('VolunteerNeed', 'get', array('id' => $need->id));
  }

  /**
   * Updating the existing flexible need is valid and must not be mistaken for
   * an attempt to create a second one.
   */
  public function testUpdateExistingFlexibleNeed(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Flexible need update test',
    ));
    $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $project['id']);

    $updated = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'id' => $flexibleNeedId,
      'project_id' => $project['id'],
      'is_flexible' => 1,
      'quantity' => 7,
    ));

    $this->assertSame((int) $flexibleNeedId, (int) $updated['id']);
    $this->assertSame('7', (string) $updated['values'][$updated['id']]['quantity']);
  }

  /**
   * A project may never acquire a second flexible need.
   */
  public function testCreateDuplicateFlexibleNeedFails(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Duplicate flexible need test',
    ));

    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerNeed', 'create', array(
      'project_id' => $project['id'],
      'is_flexible' => 1,
      'quantity' => 1,
    ));
  }

  public function testInactiveFlexibleNeedStillEnforcesUniqueness(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Inactive flexible need uniqueness test',
    ));
    $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $project['id']);
    $this->callAPISuccess('VolunteerNeed', 'create', array(
      'id' => $flexibleNeedId,
      'is_active' => 0,
    ));

    $this->assertSame((int) $flexibleNeedId, CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $project['id']));
    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerNeed', 'create', array(
      'project_id' => $project['id'],
      'is_flexible' => 1,
    ));
  }

  public function testExistingNeedCannotChangeOwningProject(): void {
    $project1 = $this->callAPISuccess('VolunteerProject', 'create', array('title' => 'First project'));
    $project2 = $this->callAPISuccess('VolunteerProject', 'create', array('title' => 'Second project'));
    $need = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'is_flexible' => 0,
    ));

    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerNeed', 'create', array(
      'id' => $need['id'],
      'project_id' => $project2['id'],
    ));
  }

  public function testExistingNeedCannotChangeFlexibleStatus(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array('title' => 'Flexible status project'));
    $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $project['id']);

    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerNeed', 'create', array(
      'id' => $flexibleNeedId,
      'is_flexible' => 0,
    ));
  }

  public function testRequestCannotBypassNeedAuthorization(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Need authorization test',
    ));
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'register to volunteer',
    );

    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerNeed', 'create', array(
      'check_permissions' => FALSE,
      'project_id' => $project['id'],
      'is_flexible' => 0,
    ));
  }

  /**
   * The inclusive end-date boundary must be the calendar day's 23:59:59.
   */
  public function testSearchEndDateIncludesEntireDay(): void {
    $date = '2030-05-17';
    $search = new CRM_Volunteer_BAO_NeedSearch(array('date_end' => $date));
    $property = new ReflectionProperty($search, 'searchParams');
    $property->setAccessible(TRUE);
    $searchParams = $property->getValue($search);

    $expected = (new DateTimeImmutable($date))->setTime(23, 59, 59)->getTimestamp();
    $this->assertSame($expected, $searchParams['need']['date_end']);
  }

  public function testGetSearchResult(): void {

    $publicVisibilityId = $this->getOptionValue('visibility', 'public');
    $adminVisibilityId = $this->getOptionValue('visibility', 'admin');

    $defaultNeedParams = array(
      'start_time' => date("Y-m-d H:i:s", strtotime("tomorrow noon")),
      'end_time' => date("Y-m-d H:i:s", strtotime("+1 month noon")),
      'is_flexible' => 0,
      'quantity' => 1,
      'visibility_id' => $publicVisibilityId,
      'role_id' => 1,
    );

    // Set up Project 1
    $project1 = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Project 1',
      'is_active' => 1,
    ));
    $openNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'start_time' => date("Y-m-d H:i:s", strtotime("+1 week noon")),
    ) + $defaultNeedParams);
    $singleDateNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'end_time' => NULL,
    ) + $defaultNeedParams);
    $disabledNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'is_active' => 0,
    ) + $defaultNeedParams);
    $invisibleNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'visibility_id' => $adminVisibilityId,
    ) + $defaultNeedParams);
    $needStartsInPastProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'start_time' => date("Y-m-d H:i:s", strtotime("yesterday noon")),
      'end_time' => date("Y-m-d H:i:s", strtotime("tomorrow midnight -1 second")),
      'project_id' => $project1['id'],
    ) + $defaultNeedParams);
    $needEndsInPastProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'start_time' => date("Y-m-d H:i:s", strtotime("yesterday noon")),
      'end_time' => date("Y-m-d H:i:s", strtotime("yesterday 13:00")),
      'project_id' => $project1['id'],
    ) + $defaultNeedParams);
    $needStartsInPastNoEndDateProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'start_time' => date("Y-m-d H:i:s", strtotime("yesterday noon")),
      'project_id' => $project1['id'],
      'visibility_id' => $adminVisibilityId,
    ) + $defaultNeedParams);
    $filledNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
    ) + $defaultNeedParams);
    $this->callAPISuccess('VolunteerAssignment', 'create', array(
      'assignee_contact_id' => 1,
      'source_contact_id' => 1,
      'volunteer_need_id' => $filledNeedProject1['id'],
    ));

    // Set up Project 2
    $project2 = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Project 2',
      'is_active' => 1,
    ));
    $openNeedProject2 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project2['id'],
      'start_time' => date("Y-m-d H:i:s", strtotime("+1 week noon")),
      'role_id' => 2,
    ) + $defaultNeedParams);

    // Set up Disabled Project
    $disabledProject = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Disabled Project',
      'is_active' => 0,
    ));
    $openNeedDisabledProject = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $disabledProject['id'],
    ) + $defaultNeedParams);

    // Check for visibility/enabled/filled errors
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'sequential' => 0,
    ));
    $this->assertArrayNotHasKey($filledNeedProject1['id'], $api['values'],
      'Error: Filled need is present in search results.');
    $this->assertArrayNotHasKey($openNeedDisabledProject['id'], $api['values'],
      'Error: Need from disabled project is present in search results.');
    $this->assertArrayNotHasKey($disabledNeedProject1['id'], $api['values'],
      'Error: Disabled need is present in search results.');
    $this->assertArrayNotHasKey($invisibleNeedProject1['id'], $api['values'],
      'Error: Invisible need is present in search results.');

    // Check that needs that start in the past are returned only if their end date is in the future.
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("today")),
      'date_end' => date("Y-m-d H:i:s", strtotime("today")),
      'sequential' => 0,
    ));
    $this->assertArrayHasKey($needStartsInPastProject1['id'], $api['values'],
      'Error: Failed to retrieve need with start date in the past but end date in the future.');
    $this->assertArrayNotHasKey($needStartsInPastNoEndDateProject1['id'], $api['values'],
      'Error: Past need (with no end-date) is present in search results.');
    $this->assertArrayNotHasKey($needEndsInPastProject1['id'], $api['values'],
      'Error: Past need (with end-date) is present in search results.');

    // Check search by role
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'role_id' => 2,
      'sequential' => 0,
    ));
    $this->assertArrayHasKey($openNeedProject2['id'], $api['values'],
      'Error: Search by role failed.');
    $this->assertCount(1, $api['values'], 'Error: Search by role returned too many results.');

    // Check search window with start date only; need starts after window opens
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("tomorrow")),
    ));
    // Expected: $openNeedProject1, $singleDateNeedProject1, $openNeedProject2
    $this->assertCount(3, $api['values']);

    // Check search window with start date only; need starts before window, but continues into window
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("+3 weeks")),
    ));
    // Expected: $openNeedProject1, $openNeedProject2
    $this->assertCount(2, $api['values']);

    // Check search window with end date only
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_end' => date("Y-m-d H:i:s", strtotime("+5 weeks")),
    ));
    // Expected: $openNeedProject1, $singleDateNeedProject1, $openNeedProject2
    $this->assertCount(4, $api['values']);

    // Check search window with both ends specified for needs with only a start date
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("tomorrow")),
      'date_end' => date("Y-m-d H:i:s", strtotime("+3 days")),
    ));
    // Expected: $singleDateNeedProject1
    $this->assertCount(1, $api['values']);

    // Check search window with both ends specified for needs with start date in window
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("+6 days")),
      'date_end' => date("Y-m-d H:i:s", strtotime("+8 days")),
    ));
    // Expected: $openNeedProject1, $openNeedProject2
    $this->assertCount(2, $api['values']);

    // Check search window with both ends specified for needs with end date in window
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("+3 weeks")),
      'date_end' => date("Y-m-d H:i:s", strtotime("+5 weeks")),
    ));
    // Expected: $openNeedProject1, $openNeedProject2
    $this->assertCount(2, $api['values']);

    // Check search window with both ends specified for needs with dates on either end of the window
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("+2 weeks")),
      'date_end' => date("Y-m-d H:i:s", strtotime("+3 weeks")),
    ));
    // Expected: $openNeedProject1, $openNeedProject2
    $this->assertCount(2, $api['values']);

    // Check search by project ID
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'project' => $project2['id'],
      'sequential' => 0,
    ));
    $this->assertCount(1, $api['values']);
    $this->assertArrayHasKey($openNeedProject2['id'], $api['values'],
      'Error: Search by project ID failed.');
  }

  /**
   * Regression: an array-shaped `id` filter must not fatal.
   *
   * The public signup form reads its needs with
   * VolunteerNeed.get id={IN:[...]}. The permission preamble used to hand that
   * array straight to CRM_Core_DAO::getFieldValue(), which does
   * trim(strtolower($searchValue)) and therefore raised a TypeError on PHP 8 --
   * fataling the signup page before it could render.
   */
  public function testGetAcceptsArrayShapedIdFilter(): void {
    $needIds = array();
    foreach (array('Array filter A', 'Array filter B') as $title) {
      $project = $this->callAPISuccess('VolunteerProject', 'create', array(
        'title' => $title,
        'is_active' => 1,
      ));
      $need = $this->callAPISuccess('VolunteerNeed', 'create', array(
        'project_id' => $project['id'],
        'start_time' => '2026-12-17 16:00:00',
        'duration' => 60,
        'is_flexible' => 0,
        'quantity' => 3,
        'visibility_id' => $this->getOptionValue('visibility', 'public'),
        'is_active' => 1,
      ));
      $needIds[] = (int) $need['id'];
    }

    $result = $this->callAPISuccess('VolunteerNeed', 'get', array(
      'id' => array('IN' => $needIds),
    ));

    $returned = array_map('intval', array_keys($result['values']));
    sort($returned);
    $expected = $needIds;
    sort($expected);
    $this->assertSame($expected, $returned);
  }

  /**
   * A comma-separated id list resolves the same way.
   */
  public function testGetAcceptsCommaSeparatedIdFilter(): void {
    $project = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Comma filter',
      'is_active' => 1,
    ));
    $need = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'is_flexible' => 0,
      'quantity' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));

    $result = $this->callAPISuccess('VolunteerNeed', 'get', array(
      'id' => (string) $need['id'],
    ));
    $this->assertArrayHasKey((int) $need['id'], $result['values']);
  }

  // -------------------------------------------------------------------
  // Migrated from the former api_v3_VolunteerProjectContactTest class.
  // -------------------------------------------------------------------


  /**
   * Test simple create via API
   */
  public function testCreateProjectContact(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $params = array(
      'project_id' => $project->id,
      'contact_id' => 1,
      'relationship_type_id' => $this->getOptionValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner'),
    );

    $this->callAPISuccess('VolunteerProjectContact', 'create', $params);
  }

  /**
   * Test create via API using relationship type name instead of ID
   */
  public function testCreateProjectContactWithRelTypeName(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasProperty('id', $project, 'Failed to prepopulate Volunteer Project');

    $params = array(
      'project_id' => $project->id,
      'contact_id' => 1,
      'relationship_type_id' => 'volunteer_owner',
    );

    $this->callAPISuccess('VolunteerProjectContact', 'create', $params);
  }

  public function testDuplicateProjectContactIsRejected(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $params = array(
      'project_id' => $project->id,
      'contact_id' => $this->getMockedContactId(),
      'relationship_type_id' => $this->getOptionValue(
        CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
        'volunteer_owner'
      ),
    );
    $this->callAPISuccess('VolunteerProjectContact', 'create', $params);

    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerProjectContact', 'create', $params);
  }

  public function testRequestCannotBypassProjectContactAuthorization(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'edit all volunteer projects',
    );

    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerProjectContact', 'create', array(
      'check_permissions' => FALSE,
      'project_id' => $project->id,
      'contact_id' => $this->getMockedContactId(),
      'relationship_type_id' => $this->getOptionValue(
        CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
        'volunteer_owner'
      ),
    ));
  }

  /**
   * Test simple delete via API
   */
  public function testDeleteProjectContactById(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $dao = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact', array(
      'project_id' => $project->id,
      'contact_id' => $this->getMockedContactId(),
      'relationship_type_id' => $this->getOptionValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner'),
    ));
    $this->assertObjectHasProperty('id', $dao, 'Failed to prepopulate Volunteer Project Contact');

    $this->callAPISuccess('VolunteerProjectContact', 'delete', array('id' => $dao->id));
  }

  /**
   * Test simple get via API
   */
  public function testGetProjectContactById(): void {
    $relTypeId = $this->getOptionValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner');
    $relType = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('label')
      ->addWhere('option_group_id.name', '=', CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP)
      ->addWhere('value', '=', $relTypeId)
      ->execute()
      ->single();
    $relTypeLabel = $relType['label'];

    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $dao = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact', array(
      'project_id' => $project->id,
      'contact_id' => $this->getMockedContactId(),
      'relationship_type_id' => $relTypeId,
    ));
    $this->assertObjectHasProperty('id', $dao, 'Failed to prepopulate Volunteer Project Contact');

    $api = $this->callAPISuccess('VolunteerProjectContact', 'get', array('id' => $dao->id));

    // make sure the label and machine name are returned
    $vpc = $api['values'][$dao->id];
    $this->assertEquals('volunteer_owner', $vpc['relationship_type_name']);
    $this->assertEquals($relTypeLabel, $vpc['relationship_type_label']);
  }

  public function testPartialUpdateById(): void {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $managerTypeId = $this->getOptionValue(
      CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
      'volunteer_manager'
    );
    $dao = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact', array(
      'project_id' => $project->id,
      'contact_id' => $this->getMockedContactId(),
      'relationship_type_id' => $this->getOptionValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner'),
    ));

    $updated = $this->callAPISuccess('VolunteerProjectContact', 'create', array(
      'id' => $dao->id,
      'relationship_type_id' => $managerTypeId,
    ));

    $this->assertSame((int) $project->id, (int) $updated['values'][$dao->id]['project_id']);
    $this->assertSame((int) $managerTypeId, (int) $updated['values'][$dao->id]['relationship_type_id']);
  }

  // -------------------------------------------------------------------
  // Migrated from the former api_v3_VolunteerUtilTest class.
  // -------------------------------------------------------------------


  public function testGetProfilesReturnsActiveAndSelectedExceptionalProfiles(): void {
    $active = $this->createProfile('A CiviVolunteer active profile', TRUE);
    $inactive = $this->createProfile('Z CiviVolunteer inactive profile', FALSE);
    $missingId = 999999999;

    $field = civicrm_api3('UFField', 'create', array(
      'uf_group_id' => $active['id'],
      'field_name' => 'first_name',
      'field_type' => 'Individual',
      'label' => 'Volunteer first name',
      'visibility' => 'Public Pages and Listings',
      'is_required' => 1,
      'is_active' => 1,
      'weight' => 7,
    ));

    $result = civicrm_api3('VolunteerUtil', 'getprofiles', array(
      'profile_ids' => $inactive['id'] . ',' . $missingId,
    ));
    $profiles = $this->indexProfiles($result['values']['profiles']);

    $this->assertArrayHasKey((int) $active['id'], $profiles);
    $this->assertArrayHasKey((int) $inactive['id'], $profiles);
    $this->assertArrayHasKey($missingId, $profiles);
    $this->assertTrue($profiles[(int) $active['id']]['is_active']);
    $this->assertFalse($profiles[(int) $active['id']]['is_missing']);
    $this->assertSame((int) $field['id'], $profiles[(int) $active['id']]['fields'][0]['id']);
    $this->assertSame('Volunteer first name', $profiles[(int) $active['id']]['fields'][0]['label']);
    $this->assertTrue($profiles[(int) $active['id']]['fields'][0]['is_required']);
    $previewQuery = array();
    parse_str((string) parse_url($profiles[(int) $active['id']]['preview_url'], PHP_URL_QUERY), $previewQuery);
    $this->assertSame((string) $active['id'], $previewQuery['gid']);
    $this->assertArrayNotHasKey('id', $previewQuery);
    $this->assertFalse($profiles[(int) $inactive['id']]['is_active']);
    $this->assertStringContainsString('Inactive', $profiles[(int) $inactive['id']]['display_title']);
    $this->assertTrue($profiles[$missingId]['is_missing']);
    $this->assertSame(array(), $profiles[$missingId]['fields']);
  }

  public function testGetProfilesOmitsUnselectedInactiveProfiles(): void {
    $inactive = $this->createProfile('Unselected inactive volunteer profile', FALSE);

    $result = civicrm_api3('VolunteerUtil', 'getprofiles', array());
    $profiles = $this->indexProfiles($result['values']['profiles']);

    $this->assertArrayNotHasKey((int) $inactive['id'], $profiles);
  }

  public function testGetProfilesSeparatesSelectionAndManagementPermissions(): void {
    $ownedProfile = $this->createProfile('Permission-separated volunteer profile', TRUE);
    $permissionClass = CRM_Core_Config::singleton()->userPermissionClass;
    $permissionClass->permissions = array('edit volunteer registration profiles');

    $result = civicrm_api3('VolunteerUtil', 'getprofiles', array());
    $this->assertFalse($result['values']['can_manage']);
    $this->assertNull($result['values']['create_url']);
    $profiles = $this->indexProfiles($result['values']['profiles']);
    $this->assertArrayHasKey((int) $ownedProfile['id'], $profiles);
    $this->assertNull($profiles[(int) $ownedProfile['id']]['edit_url']);
    $this->assertNull($profiles[(int) $ownedProfile['id']]['preview_url']);
  }

  public function testGetProfilesRejectsUsersWithoutSelectionPermission(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');

    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('VolunteerUtil', 'getprofiles', array());
  }

  /**
   * civicrm_uf_group.name is unique, and a test whose transaction never rolled
   * back -- an aborted run, or one where something issued DDL -- leaves its
   * profile behind. A name derived only from the title then fails every later
   * run with "DB Error: already exists", so make it unique per call.
   */
  private function createProfile(string $title, bool $isActive): array {
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $title));
    return UFGroup::create(FALSE)
      ->addValue('name', uniqid($base . '_', FALSE))
      ->addValue('title', $title)
      ->addValue('is_active', $isActive)
      ->execute()
      ->first();
  }

  private function indexProfiles(array $profiles): array {
    $indexed = array();
    foreach ($profiles as $profile) {
      $indexed[(int) $profile['id']] = $profile;
    }
    return $indexed;
  }

}
