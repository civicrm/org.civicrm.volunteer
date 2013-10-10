<?php

require_once 'CiviTest/CiviUnitTestCase.php';
/**
 * PHPUnit for API and BAO from CIVIVolunteer Project
 */


class api_v3_VolunteerProjectTest extends CiviUnitTestCase {
  
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
   * 
   * [testCreateDeleteVolunteerProject description]
   * @return [type] [description]
   */
  function testCreateDeleteVolunteerProject() {
    
    $event_id = $this->createEvent();
    $params = array(
      'entity_id' => $event_id, 
      'entity_table' => 'civicrm_event'
      );    

    $project = $this->callAPIAndDocument('VolunteerProject', 'create', $params, __FUNCTION__, __FILE__);
    $this->assertArrayHasKey('id', $project, 'Failed to prepopulate Volunteer Project');

    //Is necessary a sequence to control the order the execution and don't lost the id from project
    $this->getVolunteerProject($project['id']);    
    $this->deleteVolunteerProject($project['id']);
  }

  /**
   * [getVolunteerProject description]
   * @param  [type] $id [description]
   * @return [type]     [description]
   */
  function getVolunteerProject($id) {
    $params = array('id' => $id);
    $this->callAPIAndDocument('VolunteerProject', 'get', $params, __FUNCTION__, __FILE__);
  }
  
  /**
   * [deleteVolunteerProject description]
   * @param  [type] $id [description]
   * @return [type]     [description]
   */
  function deleteVolunteerProject($id) {
    $params = array('id' => $id);  
    $this->callAPIAndDocument('VolunteerProject', 'delete', $params, __FUNCTION__, __FILE__);    
  }
  
  /**
   * [testVolunteerProjectDeleteError description]
   * @return [type] [description]
   */
  function testVolunteerProjectDeleteError() {
    $params = array('id' => 999);
    $this->callAPIFailure('VolunteerProject', 'delete', $params, __FUNCTION__, __FILE__);
  }

  /**
   * [testVolunteerProjectAdd description]
   * @return [type] [description]
   */
  function testVolunteerProjectAdd() {
    $oldCount = CRM_Core_DAO::singleValueQuery('select count(*) from civicrm_volunteer_project');
    $event_id = $this->createEvent();
    $params = array(
      'entity_id' => $event_id, 
      'entity_table' => 'civicrm_event',
    );

    $volunteerProject = $this->callAPISuccess('VolunteerProject', 'create', $params);
    $this->assertTrue(is_numeric($volunteerProject['id']), "In line " . __LINE__);
    $this->assertTrue($volunteerProject['id'] > 0, "In line " . __LINE__);
    $newCount = CRM_Core_DAO::singleValueQuery('select count(*) from civicrm_volunteer_project');
    $this->assertEquals($oldCount+1, $newCount);

  }

  
  /**
   * [testProjectCreate description]
   * @return [type] [description]
   */
  function testProjectCreate() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');    
  }
  
  
  //Testing BAO
  //
  
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

    $this->assertObjectHasAttribute('id',$project->copyValues($params));
    
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


