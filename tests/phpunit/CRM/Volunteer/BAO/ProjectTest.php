<?php

require_once 'VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Project BAO - volunteer_project
 */
class CRM_Volunteer_BAO_ProjectTest extends VolunteerTestAbstract {

  /**
   * Clean table civicrm_volunteer_project
   */
  function setUp() {
    $this->quickCleanup(array('civicrm_volunteer_project'));
    parent::setUp();
  }

  function testProjectCreate() {
    $params = array(
      'entity_id' => 1,
      'entity_table' => 'civicrm_event',
    );

    $project = CRM_Volunteer_BAO_Project::create($params);
    $this->assertObjectHasAttribute('id', $project);
  }

  function testProjectDisable() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array('is_active' => 1));
    $this->assertEquals(1, $project->is_active, 'Failed to prepopulate active Volunteer Project');
    $project->disable();
    $this->assertEquals(0, $project->is_active, 'Failed to disable Volunteer Project');
  }

  function testProjectEnable() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array('is_active' => 0));
    $this->assertEquals(0, $project->is_active, 'Failed to prepopulate inactive Volunteer Project');
    $project->enable();
    $this->assertEquals(1, $project->is_active, 'Failed to enable Volunteer Project');
  }

  function testProjectRetrieve() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $projectRetrieved = CRM_Volunteer_BAO_Project::retrieve(array('id' => $project->id));
    $this->assertNotEmpty($projectRetrieved);
  }

  /**
   * Test helper method isOff, which should return TRUE passed an "off" value
   */
  function testProjectIsOff() {
    $this->assertTrue(CRM_Volunteer_BAO_Project::isOff(FALSE));
    $this->assertTrue(CRM_Volunteer_BAO_Project::isOff(0));
    $this->assertTrue(CRM_Volunteer_BAO_Project::isOff('0'));
  }

  /**
   * A project should inherit the title of its associated entity; effectively we
   * are testing our magic __get() method and its delegate _get_title();
   */
  function testGetEventProjectTitle() {
    $title = 'CiviVolunteer Unit Testing Sprint';
    $entity_table = 'civicrm_event';

    // create Event with specified title
    $event = CRM_Core_DAO::createTestObject('CRM_Event_BAO_Event', array('title' => $title));
    $this->assertEquals($title, $event->title, 'Failed to prepopulate named Event');

    // create Project associated with our Event
    $params = array(
      'entity_id' => $event->id,
      'entity_table' => $entity_table,
    );
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', $params);

    // test project title
    $this->assertEquals($title, $project->title, 'Project title does not match associated Event title');
  }
}