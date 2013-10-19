<?php

require_once 'VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Project BAO - volunteer_project
 */
class CRM_Volunteer_BAO_VolunteerProjectTest extends VolunteerTestAbstract {

  /**
   * Clean table civicrm_volunteer_project
   */
  function setUp() {
    $this->quickCleanup(array('civicrm_volunteer_project'));
    parent::setUp();
  }

  /**
   * tearDown
   *
   */
  function tearDown() {
    parent::tearDown();
  }

  /**
   * [testProjectCreateBAO description]
   * @return [type] [description]
   */
  function testProjectCreateBAO() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project);
  }

  /**
   * [testProjectDisableBAO description]
   * @return [type] [description]
   */
  function testProjectDisableBAO() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $project->disable();
    $this->assertEquals($project->is_active, 0);
  }

  /**
   * [testProjectEnableBAO description]
   * @return [type] [description]
   */
  function testProjectEnableBAO() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $project->enable();
    $this->assertEquals($project->is_active, 1);
  }

  /**
   * [testProjectRetrieveBAO description]
   * @return [type] [description]
   */
  function testProjectRetrieveBAO() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $projectRetriveds = CRM_Volunteer_BAO_Project::retrieve(array('id' => $project->id));
    $this->assertArrayHasKey('1', $projectRetriveds);
  }

  /**
   * [testProjectDataExistBAO description]
   * @return [type] [description]
   */
  function testProjectDataExistBAO() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $params = array('id' => $project->id, 'entity_id' => $project->entity_id, 'entity_table' => $project->entity_table);
    $valueDataExist = CRM_Volunteer_BAO_Project::dataExists($params);
    $this->assertEquals($valueDataExist, TRUE);
  }

  /**
   * [testProjectIsOffBAO description]
   * @return [type] [description]
   */
  function testProjectIsOffBAO() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $isOff = CRM_Volunteer_BAO_Project::isOff($project->is_active);
    $this->assertEquals($isOff, FALSE);
  }

  /**
   * [testProjectCopyValuesBAO description]
   * @return [type] [description]
   * It's not using CRM_Core_DAO::createTestObject because this method create in field entity_table
   * not corrects values(entity_table_)
   */
  function testProjectCopyValuesBAO() {
    $params = array(
        'entity_id' => $this->eventCreate(),
        'entity_table' => 'civicrm_event'
    );

    $this->callAPIAndDocument('VolunteerProject', 'create', $params, __FUNCTION__, __FILE__);
    $project = new CRM_Volunteer_BAO_Project();
    $project->entity_id = $params['entity_id'];
    $project->entity_table = $params['entity_table'];
    $project->find();

    $this->assertObjectHasAttribute('id', $project->copyValues($params));
  }

  /**
   * [testProjectGetTitleBAO description]
   * @return [type] [description]
   */
  function testProjectGetTitleBAO() {
    $params = array(
        'entity_id' => $this->createEvent(),
        'entity_table' => 'civicrm_event'
    );
    $this->callAPIAndDocument('VolunteerProject', 'create', $params, __FUNCTION__, __FILE__);

    $project = new CRM_Volunteer_BAO_Project();
    $project->entity_id = $params['entity_id'];
    $project->entity_table = $params['entity_table'];
    $project->find();

    $this->assertTrue(is_string($project->title));
  }

  /**
   * [createEvent description]
   * @return [type] [description]
   */
  function createEvent() {
    $event = $this->eventCreate();
    $this->assertArrayHasKey('id', $event, 'Failed to creating Event');
    $event = array_shift($event['values']);
    $event_id = $event['id'];
    return $event_id;
  }

}
