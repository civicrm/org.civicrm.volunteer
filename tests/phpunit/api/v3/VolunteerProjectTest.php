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
    );

    $this->callAPIAndDocument('VolunteerProject', 'create', $params, __FUNCTION__, __FILE__);
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