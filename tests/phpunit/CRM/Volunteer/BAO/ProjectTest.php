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
    $this->quickCleanup(array('civicrm_volunteer_project', 'civicrm_volunteer_need'));
    parent::setUp();
  }

  function testProjectCreate() {
    $params = array(
      'entity_id' => 1,
      'entity_table' => 'civicrm_event',
      'title' => 'Unit Testing for CiviVolunteer (How Meta)',
    );

    $project = CRM_Volunteer_BAO_Project::create($params);
    $this->assertObjectHasAttribute('id', $project);
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

  function testProjectRetrieveByID() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $projectRetrieved = CRM_Volunteer_BAO_Project::retrieveByID($project->id);

    // note: a strict comparison doesn't work: the first value is an int and the
    // second is a string; not sure where this occurs, but seems worth a look...
    $this->assertTrue($project->id == $projectRetrieved->id, 'CRM_Volunteer_BAO_Project::retrieveByID failed');
  }

  /**
   * Tests magic __get for needs
   */
  function testProjectGetNeeds() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $test = $project->needs;
    $this->assertCount(1, $test);
  }

  /**
   * Tests magic __isset for needs
   */
  function testProjectIssetNeeds() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertTrue(isset($project->needs));
  }

  /**
   * Tests magic __isset for needs
   */
  function testProjectEmptyNeeds() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertFalse(empty($project->needs));
  }

  /**
   * Tests magic __get for needs
   */
  function testProjectGetRoles() {
    $role_id = 2;
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'is_flexible' => 0,
      'project_id' => $project->id,
      'role_id' => $role_id,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $test = $project->roles;
    $this->assertArrayKeyExists($role_id, $test);
  }

  /**
   * Tests magic __isset for needs
   */
  function testProjectIssetRoles() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'is_flexible' => 0,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertTrue(isset($project->roles));
  }

  /**
   * Tests magic __isset for needs
   */
  function testProjectEmptyRoles() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertFalse(empty($project->roles));
  }

  /**
   * Tests magic __get for open needs
   */
  function testProjectGetOpenNeeds() {
    list($project, $need, $role_id) = $this->createProjectWithNeed();
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $test = $project->open_needs;
    $this->assertArrayKeyExists($need->id, $test);
    $this->assertArrayKeyExists('role_id', $test[$need->id]);
    $this->assertEquals($role_id, $test[$need->id]['role_id']);
  }

  /**
   * Tests magic __isset for open needs
   */
  function testProjectIssetOpenNeeds() {
    list($project, $need, $role_id) = $this->createProjectWithNeed();
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertTrue(isset($project->open_needs));
  }

  /**
   * Tests magic __isset for open needs
   */
  function testProjectEmptyOpenNeeds() {
    list($project, $need, $role_id) = $this->createProjectWithNeed();
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->assertFalse(empty($project->open_needs));
  }

  /**
   * Helper function to create an associated project and need
   *
   * @return array Contains three elements:
   * <ul>
   *   <li>CRM_Volunteer_BAO_Project</li>
   *   <li>CRM_Volunteer_BAO_Need</li>
   *   <li>int Role ID for the created need</li>
   * </ul>
   */
  private function createProjectWithNeed() {
    $role_id = 2;
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'is_flexible' => 0,
      'project_id' => $project->id,
      'quantity' => 5,
      'role_id' => $role_id,
      'start_time' => date('YmdHis', strtotime('tomorrow')),
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    ));

    return array($project, $need, $role_id);
  }

  function testGetContactsByRelationship() {
    $contactId = 1;
    $relType = CRM_Core_OptionGroup::getValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
            'volunteer_owner', 'name');

    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $projectContact = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact', array(
      'contact_id' => $contactId,
      'project_id' => $project->id,
      'relationship_type_id' => $relType
    ));
    $this->assertObjectHasAttribute('id', $projectContact, 'Failed to prepopulate Volunteer Project Contact');

    $contacts = CRM_Volunteer_BAO_Project::getContactsByRelationship($project->id, $relType);
    $this->assertTrue(in_array($contactId, $contacts));
  }

  /**
   * VOL-154: Verifies that, when a project's campaign is updated, the campaign
   * for each associated activity is as well.
   */
  function testProjectCampaignUpdate() {
    $testObjects = $this->_createTestObjects();

    CRM_Volunteer_BAO_Project::create(array(
      'campaign_id' => $testObjects['campaign']->id,
      'id' => $testObjects['project']->id,
    ));

    $updatedActivity = CRM_Volunteer_BAO_Assignment::findById($testObjects['activity']['id']);
    $this->assertEquals($testObjects['campaign']->id, $updatedActivity->campaign_id,
        'Activity campaign was not updated with project campaign');

    // Test unsetting campaign from a project.
    CRM_Volunteer_BAO_Project::create(array(
      'campaign_id' => '',
      'id' => $testObjects['project']->id,
    ));

    $updatedActivity = CRM_Volunteer_BAO_Assignment::findById($testObjects['activity']['id']);
    $this->assertEquals('', $updatedActivity->campaign_id,
        'Activity campaign was not updated with empty project campaign');
  }

  /**
   * Creates test case data for use in the Unit Tests.
   *
   * return $returnObjects array(
   *   'project' => CRM_Volunteer_BAO_Project,
   *   'need' => CRM_Volunteer_BAO_Need,
   *   'activity' => api.VolunteerAssignment.create,
   *   'campaign' => CRM_Campaign_BAO_Campaign
   * )
   */
  function _createTestObjects() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'project_id' => $project->id,
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $campaign = CRM_Core_DAO::createTestObject('CRM_Campaign_BAO_Campaign');
    $this->assertObjectHasAttribute('id', $campaign, 'Failed to prepopulate Campaign');

    $activity = $this->callAPISuccess('VolunteerAssignment', 'create', array(
      'assignee_contact_id' => 1,
      // Passing the following parameter causes the pseudoconstants list to be
      // updated. While generally not necessary, this is needed in a testing
      // scenario because:
      //   1. Campaigns are fundamentally stored as option group values, which
      //      are cached rather than looked up directly.
      //   2. This test creates a new campaign using CRM_Core_DAO::createTestObject()
      //      instead of a standard function which would rebuild the caches for us.
      //   3. api.Activity.create checks to make sure that the campaign ID passed
      //      to it is valid, and throws an exception if it isn't.
      'cache_clear' => 1,
      'source_contact_id' => 1,
      'volunteer_need_id' => $need->id,
    ));

    return array(
      'project' => $project,
      'need' => $need,
      'activity' => $activity,
      'campaign' => $campaign,
    );
  }

}
