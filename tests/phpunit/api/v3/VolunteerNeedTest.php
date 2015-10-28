<?php

require_once 'VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Need API - volunteer_need
 */
class api_v3_VolunteerNeedTest extends VolunteerTestAbstract {

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

  function testGetSearchResult() {

    $defaultNeedParams = array(
      'start_time' => date("Y-m-d H:i:s", strtotime("tomorrow noon")),
      'end_time' => date("Y-m-d H:i:s", strtotime("+1 month noon")),
      'is_flexible' => 0,
      'quantity' => 1,
      'visibility_id' => 1,
      'role_id' => 1,
    );

    // Set up Project 1
    $project1 = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Project 1',
      'is_active' => 1,
    ));
    $openNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'start_time' => date("Y-m-d H:i:s", strtotime("+1 week noon")),
    ) + $defaultNeedParams);
    $singleDateNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'end_time' => NULL,
    ) + $defaultNeedParams);
    $disabledNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'is_active' => 0,
    ) + $defaultNeedParams);
    $invisibleNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
      'visibility_id' => 0,
    ) + $defaultNeedParams);
    $needStartsInPastProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'start_time' => date("Y-m-d H:i:s", strtotime("yesterday noon")),
      'end_time' => date("Y-m-d H:i:s", strtotime("tomorrow midnight -1 second")),
      'project_id' => $project1['id'],
    ) + $defaultNeedParams);
    $needEndsInPastProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'start_time' => date("Y-m-d H:i:s", strtotime("yesterday noon")),
      'end_time' => date("Y-m-d H:i:s", strtotime("yesterday 13:00")),
      'project_id' => $project1['id'],
    ) + $defaultNeedParams);
    $needStartsInPastNoEndDateProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'start_time' => date("Y-m-d H:i:s", strtotime("yesterday noon")),
      'project_id' => $project1['id'],
      'visibility_id' => 0,
    ) + $defaultNeedParams);
    $filledNeedProject1 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project1['id'],
    ) + $defaultNeedParams);
    $this->callAPISuccess('VolunteerAssignment', 'create', array(
      'assignee_contact_id' => 1,
      'source_contact_id' => 1,
      'volunteer_need_id' => $filledNeedProject1['id'],
    ));

    // Set up Project 2
    $project2 = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Project 2',
      'is_active' => 1,
    ));
    $openNeedProject2 = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $project2['id'],
      'start_time' => date("Y-m-d H:i:s", strtotime("+1 week noon")),
      'role_id' => 2,
    ) + $defaultNeedParams);

    // Set up Disabled Project
    $disabledProject = $this->callAPISuccess('VolunteerProject', 'create', array(
      'title' => 'Disabled Project',
      'is_active' => 0,
    ));
    $openNeedDisabledProject = $this->callAPISuccess('VolunteerNeed', 'create', array(
      'project_id' => $disabledProject['id'],
    ) + $defaultNeedParams);

    // Check for visibility/enabled/filled errors
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'sequential' => 0,
    ));
    $this->assertArrayNotHasKey($filledNeedProject1['id'], $api['values'],
      'Error: Filled need is present in search results.');
    $this->assertArrayNotHasKey($openNeedDisabledProject['id'], $api['values'],
      'Error: Need from disabled project is present in search results.');
    $this->assertArrayNotHasKey($disabledNeedProject1['id'], $api['values'],
      'Error: Disabled need is present in search results.');
    $this->assertArrayNotHasKey($invisibleNeedProject1['id'], $api['values'],
      'Error: Invisible need is present in search results.');

    // Check that needs that start in the past are returned only if their end date is in the future.
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("today")),
      'date_end' => date("Y-m-d H:i:s", strtotime("today")),
      'sequential' => 0,
    ));
    $this->assertArrayHasKey($needStartsInPastProject1['id'], $api['values'],
      'Error: Failed to retrieve need with start date in the past but end date in the future.');
    $this->assertArrayNotHasKey($needStartsInPastNoEndDateProject1['id'], $api['values'],
      'Error: Past need (with no end-date) is present in search results.');
    $this->assertArrayNotHasKey($needEndsInPastProject1['id'], $api['values'],
      'Error: Past need (with end-date) is present in search results.');

    // Check search by role
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'role_id' => 2,
      'sequential' => 0,
    ));
    $this->assertArrayHasKey($openNeedProject2['id'], $api['values'],
      'Error: Search by role failed.');
    $this->assertCount(1, $api['values'], 'Error: Search by role returned too many results.');

    // Check search window with start date only; need starts after window opens
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("tomorrow")),
    ));
    // Expected: $openNeedProject1, $singleDateNeedProject1, $openNeedProject2
    $this->assertCount(3, $api['values']);

    // Check search window with start date only; need starts before window, but continues into window
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("+3 weeks")),
    ));
    // Expected: $openNeedProject1, $openNeedProject2
    $this->assertCount(2, $api['values']);

    // Check search window with end date only
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_end' => date("Y-m-d H:i:s", strtotime("+5 weeks")),
    ));
    // Expected: $openNeedProject1, $singleDateNeedProject1, $openNeedProject2
    $this->assertCount(4, $api['values']);

    // Check search window with both ends specified for needs with only a start date
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("tomorrow")),
      'date_end' => date("Y-m-d H:i:s", strtotime("+3 days")),
    ));
    // Expected: $singleDateNeedProject1
    $this->assertCount(1, $api['values']);

    // Check search window with both ends specified for needs with start date in window
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("+6 days")),
      'date_end' => date("Y-m-d H:i:s", strtotime("+8 days")),
    ));
    // Expected: $openNeedProject1, $openNeedProject2
    $this->assertCount(2, $api['values']);

    // Check search window with both ends specified for needs with end date in window
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("+3 weeks")),
      'date_end' => date("Y-m-d H:i:s", strtotime("+5 weeks")),
    ));
    // Expected: $openNeedProject1, $openNeedProject2
    $this->assertCount(2, $api['values']);

    // Check search window with both ends specified for needs with dates on either end of the window
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'date_start' => date("Y-m-d H:i:s", strtotime("+2 weeks")),
      'date_end' => date("Y-m-d H:i:s", strtotime("+3 weeks")),
    ));
    // Expected: $openNeedProject1, $openNeedProject2
    $this->assertCount(2, $api['values']);

    // Check search by project ID
    $api = $this->callAPISuccess('VolunteerNeed', 'getsearchresult', array(
      'project' => $project2['id'],
      'sequential' => 0,
    ));
    $this->assertCount(1, $api['values']);
    $this->assertArrayHasKey($openNeedProject2['id'], $api['values'],
      'Error: Search by project ID failed.');
  }

}
