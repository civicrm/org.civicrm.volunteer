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
    $this->assertEquals($oldCount + 1, $newCount);
  }

  /**
   * [testProjectCreate description]
   * @return [type] [description]
   */
  function testProjectCreate() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');
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

