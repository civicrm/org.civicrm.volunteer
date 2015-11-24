<?php

require_once 'CRM/Core/Page.php';

class CRM_Volunteer_Page_Listings extends CRM_Core_Page {
  private $todaysDate;
  
  /**
   * Builds the page.
   */
  function run() {

    // Retrieve project id or bail.
    $projectId = filter_input (INPUT_GET, 'project_id', FILTER_VALIDATE_INT);

    if (!$projectId) {
      // Will fail $projectId is empty, invalid, or 0.
      $this->error('Invalid project id.');
    }

    $this->checkPermissions($projectId);
    $this->todaysDate = new DateTime();
    $this->todaysDate->setTime(0, 0, 0); // just the date.
    $this->assign('endDate', $this->todaysDate->format('Y-m-d'));
    $this->setProjectDetails($projectId);
    $this->setVolunteerAssignments($projectId);
    parent::run();
  }

  /**
   *
   * @param string $errorMessage
   * @param bool $contactSysAdmin - a polite note asking the user to contact their sysadmin.
   */
  private function error ($errorMessage, $contactSysAdmin = TRUE) {
    if ($contactSysAdmin) {
      $errorMessage .= ' Please contact your system administrator for assistance.';
    }
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
    $contact_id = $session->get('userID');

    // See if they have the required permission, if so bail.
    if (CRM_Volunteer_Permission::checkProjectPerms(CRM_Volunteer_Permission::VIEW_LISTINGS, $projectId)) {
      return;
    }

    $errorMessage = 'You must either have the  \'edit all volunteer projects\' '
        . 'permission or be the volunteer coordinator for this project.';

    $this->error($errorMessage);
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
      $this->error('VolunteerProject getvalue call failed.');
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
      $this->error('VolunteerProject getvalue call failed.');
    }

    if ($volunteerAssignments['count'] == 0) {
      $this->error('No volunteers have been assigned to this project yet!', FALSE); // TODO include URL where to assign some.
    }

    $needToDetails = array();

    foreach($volunteerAssignments['values'] as $assignmentKey => &$assignment) {
      if (!array_key_exists($assignment['volunteer_need_id'], $needToDetails)){
        
        // TODO: getsingle and getvalue don't calculate display time, so use 'get' call for now.
        $volunteerNeed = civicrm_api3('VolunteerNeed', 'get', array(
          'sequential' => 1,
          'id' => $assignment['volunteer_need_id'],
        ));

        if (count($volunteerNeed['values']) != 1) {
          $this->error('Couldn\'t retrieve only one VolunteerNeed, found ' . count($volunteerNeed['values']) . '. ');          
        }
        
        $needToDetails[$assignment['volunteer_need_id']]['display_time'] = $volunteerNeed['values'][0]['display_time'];
        $needToDetails[$assignment['volunteer_need_id']]['end_time'] = new DateTime($volunteerNeed['values'][0]['end_time']);
        $needToDetails[$assignment['volunteer_need_id']]['role_label'] = $volunteerNeed['values'][0]['role_label'];
      }
      
      // If the end date for this assignment is in the past - unset it and move onto the next one.
      if ($this->todaysDate > $needToDetails[$assignment['volunteer_need_id']]['end_time']) {
        unset($volunteerAssignments['values'][$assignmentKey]);
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
        // If the display times match so will the start times. This makes sorting easier.
        $sortedResults[$assignment['display_time']]['start_time'] = new DateTime($assignment['start_time']);
        $sortedResults[$assignment['display_time']]['values'] = array();
      }

      // Assign to array keyed by display time to effect grouping by assignment time.
      $sortedResults[$assignment['display_time']]['values'][] = array(
        'contact_id' => $assignment['assignee_contact_id'],
        'name' => $assignment['assignee_display_name'],
        'role_label' => $assignment['role_label'],
        'email' => $assignment['assignee_email'],
        'phone' => $assignment['assignee_phone'],
      );
    }

    uasort($sortedResults, function($a, $b) {
      if ($a['start_time'] == $b['start_time']) {
          return 0;
      }
      return ($a['start_time'] > $b['start_time']) ? -1 : 1;
    });

    return $sortedResults;
  }
}
