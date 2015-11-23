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

    $this->checkPermissions($projectId);
    $this->setProjectDetails($projectId);
    $this->setVolunteerAssignments($projectId);

    parent::run();
  }

  /**
   * 
   * @param string $errorMessage
   */
  private function error ($errorMessage) {
    $this->assign('errorMessage', $errorMessage);
    parent::run();
  }
  
  /**
   * We only allow viewing in the following cases:
   *   - First case is having the permission 'edit all volunteer projects'.
   *   - Second case is where the user has Volunteer Coordinator relationship to project.
   * Our default position is no admittance. 
   * 
   * @param int $projectId
   */
  private function checkPermissions ($projectId) {
    $session = CRM_Core_Session::singleton();
    $userContactId = $session->get('userID');
    
    // See if they have the required permission.
    if ($this->hasPermission($userContactId, 'edit all volunteer projects')) {
      return true;
    }
    
    // See if they are the volunteer coordinator.
    if ($this->isVolunteerCoordinator($userContactId, $projectId)) {
      return true;
    }
    
    $errorMessage = 'You must either have the  \'edit all volunteer projects\' '
        . 'permission or be the volunteer coordinator for this project. Please '
        . 'contact your system administrator for assistance.';
    
    $this->error($errorMessage);
  }

  /**
   * 
   * @param int $userContactId
   * @param int $permissionName
   * @return boolean
   */
  private function hasPermission($userContactId, $permissionName) {
    // TODO flesh out function.
//    CRM_Volunteer_Permission::checkProjectPerms($op, $projectId = NULL); need to specify a new operation (currently based off crm_core_action) browse/preview/show schedule.
    return true;  
  }

  /**
   * 
   * @param int $userContactId
   * @param int $projectId
   * @return boolean
   */
  private function isVolunteerCoordinator($userContactId, $projectId) {
    // TODO flesh out function.
    return true;
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
      // TODO handle case where it's inactive, see api call.
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
      $this->error('No volunteers have been assigned to this project yet!'); // TODO include URL where to assign some.
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
