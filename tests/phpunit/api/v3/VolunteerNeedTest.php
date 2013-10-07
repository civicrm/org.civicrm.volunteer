<?php

require_once 'VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Need API - volunteer_need
 */
class api_v3_VolunteerNeedTest extends VolunteerTestAbstract {
  static protected $_params;
  static protected $_project_id;

  function setUp() {
    $this->quickCleanup(array('civicrm_volunteer_need', 'civicrm_volunteer_project'));
    parent::setUp();
  }

  /**
   * Test simple create via API
   */
  function testCreateNeed() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $params = array(
      "project_id"    => $project->id,
      "start_time"    => "2013-12-17 16:00:00",
      "duration"      => 240,
      "is_flexible"   => 0,
      "quantity"      => 1,
      "visibility_id" => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
      "role_id"       => 1,
      "is_active"     => 1,
    );

    $this->callAPIAndDocument('VolunteerNeed', 'create', $params, __FUNCTION__, __FILE__);
  }

  /**
   * Test simple delete via API
   */
  function testDeleteNeedbyID() {
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need');
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->callAPIAndDocument('VolunteerNeed', 'delete', array('id' => $need->id), __FUNCTION__, __FILE__);
  }

  /**
   * Test simple get via API
   */
  function testGetNeedbyID() {
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need');
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $this->callAPIAndDocument('VolunteerNeed', 'get', array('id' => $need->id), __FUNCTION__, __FILE__);
  }
}