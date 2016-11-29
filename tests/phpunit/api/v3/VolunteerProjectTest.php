<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Project API - volunteer_project
 *
 * @group headless
 */
class api_v3_VolunteerProjectTest extends VolunteerTestAbstract {

  private $campaignIds;
  private $contactIds;
  private $defaults;

  public function setUpHeadless() {
    parent::setUpHeadless();
    $this->createContacts();
    $this->createCampaigns();
    $this->setProjectDefaults();
  }

  function setUp() {
    parent::setUp();
    $this->defaults = CRM_Volunteer_BAO_Project::composeDefaultSettingsArray();
  }

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
  function testCreateProject() {
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
   * Tests the project_contacts parameter to the create API, i.e., tests the
   * ability to specify at project creation the contacts related to the project.
   */
  function testCreateProjectWithContacts() {
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
    $bao->relationship_type_id = CRM_Core_OptionGroup::getValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner', 'name');
    $this->assertEquals(count($projectContacts['volunteer_owner']), $bao->find());

    $bao->relationship_type_id = CRM_Core_OptionGroup::getValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_manager', 'name');
    $this->assertEquals(count($projectContacts['volunteer_manager']), $bao->find());
  }

  /**
   * Test simple delete via API
   */
  function testDeleteProjectByID() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array('title' => 'Delete Me'));
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    civicrm_api3('VolunteerProject', 'delete', array('id' => $project->id));

    $projectSearch = new CRM_Volunteer_BAO_Project();
    $params = array('id' => $project->id);
    $projectSearch->copyValues($params);
    $this->assertEquals(0, $project->find());
  }

  /**
   * Test simple get via API
   */
  function testGetProjectByID() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array('title' => 'Get Me'));
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $result = civicrm_api3('VolunteerProject', 'get', array('id' => $project->id));
    $this->assertEquals(1, $result['count']);
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
  function testAdminProjectDefaultsDuringCreate() {
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
  function testAdminProjectDefaultsDuringContactUpdate() {
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
  function testAdminProjectDefaultsDuringProfileUpdate() {
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
  function testAdminProjectDefaultsDuringFieldUpdate() {
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
  function testCoordProjectDefaultsDuringCreate() {
    $this->setCoordPerms();
    $this->testAdminProjectDefaultsDuringCreate();
  }

  /**
   * Action: update project. Expectation: ancillary data is not dropped.
   */
  function testCoordProjectDefaultsDuringUpdate() {
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
  function testCoordProjectPermsDuringCreate() {
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
  function testCoordProjectPermsDuringUpdate() {
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

}
