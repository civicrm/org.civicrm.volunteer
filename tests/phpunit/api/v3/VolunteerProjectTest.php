<?php

require_once 'VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Project API - volunteer_project
 */
class api_v3_VolunteerProjectTest extends VolunteerTestAbstract {

  /**
   * Clean table civicrm_volunteer_project
   */
  function setUp() {
    $this->quickCleanup(array('civicrm_volunteer_project'));
    parent::setUp();
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

    $this->callAPIAndDocument('VolunteerProject', 'create', $params, __FUNCTION__, __FILE__);
  }

  /**
   * Tests the project_contacts parameter to the create API, i.e., tests the
   * ability to specify at project creation the contacts related to the project.
   */
  function testCreateProjectWithContacts() {
    $contactId1 = $this->individualCreate();
    $contactId2 = $this->individualCreate();
    $contactId3 = $this->individualCreate();

    $projectContacts = array(
      'volunteer_owner' => array($contactId1),
      'volunteer_manager' => array($contactId2, $contactId3),
    );

    $params = array(
      'entity_id' => 1,
      'entity_table' => 'civicrm_event',
      'is_active' => 1,
      'project_contacts' => $projectContacts,
      'title' => 'Unit Testing for CiviVolunteer (How Meta)',
    );

    $this->callAPIAndDocument('VolunteerProject', 'create', $params, __FUNCTION__, __FILE__);

    $bao = new CRM_Volunteer_BAO_ProjectContact();
    $bao->project_id = 1;
    $bao->relationship_type_id = CRM_Core_OptionGroup::getValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner', 'name');
    $this->assertEquals(count($projectContacts['volunteer_owner']), $bao->find());

    $bao->relationship_type_id = CRM_Core_OptionGroup::getValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_manager', 'name');
    $this->assertEquals(count($projectContacts['volunteer_manager']), $bao->find());
  }

  /**
   * Test simple delete via API
   */
  function testDeleteProjectByID() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $this->callAPIAndDocument('VolunteerProject', 'delete', array('id' => $project->id), __FUNCTION__, __FILE__);
  }

  /**
   * Test simple get via API
   */
  function testGetProjectByID() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $this->callAPIAndDocument('VolunteerProject', 'get', array('id' => $project->id), __FUNCTION__, __FILE__);
  }
}