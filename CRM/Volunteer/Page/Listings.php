<?php

require_once 'CRM/Core/Page.php';

class CRM_Volunteer_Page_Listings extends CRM_Core_Page {
  /**
   * Builds the page.
   */
  function run() {

    // Retrieve project id or bail.
    $projectId = filter_input (INPUT_GET, 'project_id', FILTER_VALIDATE_INT);

    if (!$projectId) {
      // Will fail $projectId is empty, invalid, or 0.
      // TODO invalid project id.
    }

    $this->setProjectDetails($projectId);
    $this->setVolunteerAssignments($projectId);

    parent::run();
  }

  /**
   * Initialises the project data for the template.
   * @param int $projectId
   */
  private function setProjectDetails ($projectId) {
    try {
      $projectDetails = civicrm_api3('VolunteerProject', 'getvalue', array(
        'sequential' => 1,
        'id' => $projectId,
        'is_active' => 1,
        'return' => 'title',
      ));
    }
    catch (Exception $e){
      // TODO handle case where it's inactive, see above.
    }
    $this->assign('projectTitle', $projectDetails);
  }

  /**
   * Initialises the volunteer data for the template.
   *
   * @param int $projectId
   */
  private function setVolunteerAssignments($projectId){
    // Retrieve data and group it according to assignment time.
    try {
      $volunteerAssignments = civicrm_api3('VolunteerAssignment', 'get', array(
        'sequential' => 1,
        'project_id' => $projectId,
        'count' => 0, // will not limit to first 25.
      ));
    }
    catch (Exception $e){
      // TODO handle error retrieving assignments.

    }

    if ($volunteerAssignments['count'] == 0) {
      $this->assign('errorMessage', 'No volunteers have been assigned to this project yet!'); // TODO include URL where to assign some.
    }

    $needToDetails = array();

    foreach($volunteerAssignments['values'] as &$assignment){
      if (!array_key_exists($assignment['volunteer_need_id'], $needToDetails)){
        $volunteerNeed = civicrm_api3('VolunteerNeed', 'get', array(
          'sequential' => 1,
          'id' => $assignment['volunteer_need_id'],
        ));

        // TODO handle if need has been deleted?
        $needToDetails[$assignment['volunteer_need_id']]['display_time'] = $volunteerNeed['values'][0]['display_time']; // getsingle and getvalue don't calculate display time.
        $needToDetails[$assignment['volunteer_need_id']]['role_label'] = $volunteerNeed['values'][0]['role_label'];
      }
      $assignment['display_time'] =  $needToDetails[$assignment['volunteer_need_id']]['display_time'];
      $assignment['role_label'] =  $needToDetails[$assignment['volunteer_need_id']]['role_label'];
    }

    $this->assign('sortedResults', $this->sortVolunteerAssignments($volunteerAssignments['values']));
  }

  /**
   * Sorts the volunteer assignments grouping them into timeslots.
   *
   * @param array $volunteerAssignments - the values part from the output of the get api call.
   * @return sortedResults
   */
  private function sortVolunteerAssignments($volunteerAssignments) {
    $sortedResults = array();

    foreach($volunteerAssignments as $assignment){
      if (!array_key_exists($assignment['display_time'], $sortedResults)){
        $sortedResults[$assignment['display_time']] = array();
      }

      // Assign to array keyed by display time to effect grouping by assignment time.
      $sortedResults[$assignment['display_time']][] = array(
        'contact_id' => $assignment['assignee_contact_id'],
        'name' => $assignment['assignee_display_name'],
        'role_label' => $assignment['role_label'],
        'email' => $assignment['assignee_email'],
        'phone' => $assignment['assignee_phone'],
      );
    }

    return $sortedResults;
  }
}
