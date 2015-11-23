<?php

require_once 'CRM/Core/Page.php';

class CRM_Volunteer_Page_Listings extends CRM_Core_Page {
  function run() {

    // Retrieve project id or bail.
    $projectId = filter_input (INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
    
    if (!$projectId) {
      // Will fail $projectId is empty, invalid, or 0.
      // TODO invalid project id.
    }
    
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
    
    $this->assign('sortedResults', $this->sortVolunteerAssignments($volunteerAssignments['values']));
    parent::run();
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
      $volunteerNeed = civicrm_api3('VolunteerNeed', 'get', array(
        'sequential' => 1,
        'id' => $assignment['volunteer_need_id'],
      ));
 
      $displayTime = $volunteerNeed['values'][0]['display_time']; // getsingle and getvalue don't calculate display time.

      if (!array_key_exists($displayTime, $sortedResults)){
        $sortedResults[$displayTime] = array();
      }

      // Assign to array keyed by display time to effect grouping by assignment time.
      $sortedResults[$displayTime][] = array(
        'name' => $assignment['target_display_name'],
        'role' => $assignment['role_label'],
        'email' => $assignment['target_email'],
        'phone' => $assignment['target_phone'], 
      );
    }

    $sortedResults = print_r($sortedResults, TRUE);

    return $sortedResults;
  }
}
