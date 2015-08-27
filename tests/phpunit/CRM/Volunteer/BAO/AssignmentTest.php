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

}