<?php

require_once 'VolunteerTestAbstract.php';

/**
 * Test class for Volunteer Assignment BAO
 */
class CRM_Volunteer_BAO_AssignmentTest extends VolunteerTestAbstract {

  private function setUpProject() {
    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project');
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    // attach need to project
    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'is_active' => 1,
      'project_id' => $project->id,
      'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');

    $beneficiaryContactId = $this->individualCreate();
    $volunteerContactId = $this->individualCreate();

    return array(
      'beneficiaryContactId' => $beneficiaryContactId,
      'need' => $need,
      'project' => $project,
      'volunteerContactId' => $volunteerContactId,
    );
  }

  /**
   * Tests CRM_Volunteer_BAO_Assignment::createVolunteerActivity() to ensure
   * that the project beneficiary is made the activity target.
   */
  function testActivityTarget() {
    $beneficiaryContactId = $need = $project = $volunteerContactId = NULL;
    extract($this->setUpProject(), EXTR_IF_EXISTS);

    $projectContact = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_ProjectContact', array(
      'contact_id' => $beneficiaryContactId,
      'project_id' => $project->id,
      'relationship_type_id' => CRM_Core_OptionGroup::getValue('volunteer_project_relationship', 'volunteer_beneficiary', 'name'),
    ));
    $this->assertObjectHasAttribute('id', $projectContact, 'Failed to prepopulate VolunteerContact');

    $assignmentId = CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
      'assignee_contact_id' => $volunteerContactId,
      // source is set to avoid errors when Civi can't identify the currently logged in user
      'source_contact_id' => $volunteerContactId,
      'volunteer_need_id' => $need->id,
    ));

    $targetContactId = civicrm_api3('ActivityContact', 'getvalue', array(
      'activity_id' => $assignmentId,
      'record_type_id' => 'Activity Targets',
      'return' => 'contact_id'
    ));

    $this->assertEquals($beneficiaryContactId, $targetContactId);
  }

  /**
   * Tests CRM_Volunteer_BAO_Assignment::createVolunteerActivity() to ensure
   * that the activity subject defaults to the project title.
   */
  function testActivitySubject() {
    $need = $project = $volunteerContactId = NULL;
    extract($this->setUpProject(), EXTR_IF_EXISTS);

    $assignmentId = CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
      'assignee_contact_id' => $volunteerContactId,
      // source is set to avoid errors when Civi can't identify the currently logged in user
      'source_contact_id' => $volunteerContactId,
      'volunteer_need_id' => $need->id,
    ));

    $activitySubject = civicrm_api3('Activity', 'getvalue', array(
      'activity_id' => $assignmentId,
      'return' => 'subject'
    ));

    $this->assertEquals($project->title, $activitySubject);

  }

  /**
   * VOL-154: Verifies that an activity created in a project is tagged with the
   * project's campaign.
   */
  function testCampaignInheritance() {
    // begin setup
    $campaign = CRM_Core_DAO::createTestObject('CRM_Campaign_BAO_Campaign');
    $this->assertObjectHasAttribute('id', $campaign, 'Failed to prepopulate Campaign');

    $project = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project', array(
      'campaign_id' => $campaign->id,
    ));
    $this->assertObjectHasAttribute('id', $project, 'Failed to prepopulate Volunteer Project');

    $need = CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Need', array(
      'project_id' => $project->id,
    ));
    $this->assertObjectHasAttribute('id', $need, 'Failed to prepopulate Volunteer Need');
    // end setup

    $activityId = CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
      'assignee_contact_id' => 1,
      'source_contact_id' => 1,
      'volunteer_need_id' => $need->id,
    ));
    $this->assertNotSame(FALSE, $activityId, 'Failed to create Volunteer Activity');

    $createdActivity = CRM_Volunteer_BAO_Assignment::findById($activityId);
    $this->assertEquals($campaign->id, $createdActivity->campaign_id,
        'Activity did not inherit campaign from volunteer project');
  }
}
