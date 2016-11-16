<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Project API - volunteer_project
 *
 * @group headless
 */
class api_v3_VolunteerProjectTest extends VolunteerTestAbstract {

  private $contactIds;

  public function setUp() {
    parent::setUp();
    $this->createContacts();
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

}
