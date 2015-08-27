<?php

require_once 'VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Project Contact API
 */
class api_v3_VolunteerProjectContactTest extends VolunteerTestAbstract {

  function setUp() {
    $this->quickCleanup(array('civicrm_volunteer_project', 'civicrm_volunteer_project_contact'));
    parent::setUp();
  }

  /**
   * Test simple create via API
   */
  function testCreateProjectContact() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $params = array(
      'project_id' => $project->id,
      'contact_id' => 1,
      'relationship_type_id' => CRM_Core_OptionGroup::getValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner', 'name'),
    );

    $this->callAPIAndDocument('VolunteerProjectContact', 'create', $params, __FUNCTION__, __FILE__);
  }

  /**
   * Test create via API using relationship type name instead of ID
   */
  function testCreateProjectContactWithRelTypeName() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $params = array(
      'project_id' => $project->id,
      'contact_id' => 1,
      'relationship_type_id' => 'volunteer_owner',
    );

    $this->callAPIAndDocument('VolunteerProjectContact', 'create', $params, __FUNCTION__, __FILE__);
  }

  /**
   * Test simple delete via API
   */
  function testDeleteProjectContactById() {
    $dao = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact');
    $this->assertObjectHasAttribute('id', $dao, 'Failed to prepopulate Volunteer Project Contact');

    $this->callAPIAndDocument('VolunteerProjectContact', 'delete', array('id' => $dao->id), __FUNCTION__, __FILE__);
  }

  /**
   * Test simple get via API
   */
  function testGetProjectContactById() {
    $relTypeId = CRM_Core_OptionGroup::getValue(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, 'volunteer_owner', 'name');
    $relTypeLabel = CRM_Core_OptionGroup::getLabel(CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP, $relTypeId);

    $dao = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact', array(
      'relationship_type_id' => $relTypeId,
    ));
    $this->assertObjectHasAttribute('id', $dao, 'Failed to prepopulate Volunteer Project Contact');

    $api = $this->callAPIAndDocument('VolunteerProjectContact', 'get', array('id' => $dao->id), __FUNCTION__, __FILE__);

    // make sure the label and machine name are returned
    $vpc = $api['values'][1];
    $this->assertEquals('volunteer_owner', $vpc['relationship_type_name']);
    $this->assertEquals($relTypeLabel, $vpc['relationship_type_label']);
  }

}
