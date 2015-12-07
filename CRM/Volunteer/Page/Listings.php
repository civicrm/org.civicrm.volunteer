<?php

require_once 'CRM/Core/Page.php';

class CRM_Volunteer_Page_Listings extends CRM_Core_Page {
  private $projectId;
  private $todaysDate;
  private $projectDetails;

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

    $this->projectId = $projectId;

    $this->checkPermissions();
    $this->todaysDate = new DateTime();
    $this->todaysDate->setTime(0, 0, 0); // just the date.
    $this->assign('endDate', $this->todaysDate->format('Y-m-d'));

    $this->projectDetails = CRM_Volunteer_BAO_Project::retrieveByID($projectId);
    $this->assign('projectTitle', $this->projectDetails->title);
    $this->assignTplVolunteerAssignments();
    parent::run();
  }

  /**
   * Stores an error for the templates and runs the class without any further processing.
   * This is basically a bail-out method.
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
   */
  private function checkPermissions () {

    $session = CRM_Core_Session::singleton();
    $contact_id = $session->get('userID');

    // See if they have the required permission, if so bail.
    if (CRM_Volunteer_Permission::checkProjectPerms(CRM_Volunteer_Permission::VIEW_LISTINGS, $this->projectId)) {
      return;
    }

    $errorMessage = 'You do not have the required permissions to view this page.';

    $this->error($errorMessage);
  }

  /**
   * Initialises the volunteer data for the template.
   */
  private function assignTplVolunteerAssignments(){
    // Retrieve data and group it according to assignment time.
    try {
      $volunteerAssignments = civicrm_api3('VolunteerAssignment', 'get', array(
        'sequential' => 1,
        'project_id' => $this->projectId,
        'count' => 0, // will not limit to first 25.
      ));
    }
    catch (Exception $e){
      $this->error('Unable to retrieve assignments for Volunteer Project.');
      return;
    }

    if ($volunteerAssignments['count'] == 0) {
      $this->error('No volunteers have been assigned to this project yet!', FALSE); // TODO include URL where to assign some.
    }

    $volunteerNeedsCache = array();

    foreach($volunteerAssignments['values'] as $assignmentKey => &$assignment) {
      if (!array_key_exists($assignment['volunteer_need_id'], $volunteerNeedsCache)){

        // TODO: getsingle and getvalue don't calculate display time, so use 'get' call for now.
        $volunteerNeed = civicrm_api3('VolunteerNeed', 'get', array(
          'sequential' => 1,
          'id' => $assignment['volunteer_need_id'],
        ));

        if (count($volunteerNeed['values']) != 1) {
          $this->error('Couldn\'t retrieve only one VolunteerNeed, found ' . count($volunteerNeed['values']) . '. ');
        }

        $volunteerNeedsCache[$assignment['volunteer_need_id']]['display_time'] = $volunteerNeed['values'][0]['display_time'];
        $volunteerNeedsCache[$assignment['volunteer_need_id']]['end_time'] = new DateTime($volunteerNeed['values'][0]['end_time']);
        $volunteerNeedsCache[$assignment['volunteer_need_id']]['role_label'] = $volunteerNeed['values'][0]['role_label'];
      }

      // If this assignment is in the past - unset it and move onto the next one.
      if ($this->isAssignmentInThePast($assignment)) {
        unset($volunteerAssignments['values'][$assignmentKey]);
        continue;
      }

      $assignment['display_time'] =  $volunteerNeedsCache[$assignment['volunteer_need_id']]['display_time'];
      $assignment['role_label'] =  $volunteerNeedsCache[$assignment['volunteer_need_id']]['role_label'];
    }
    $this->assign('sortedResults', $this->sortVolunteerAssignments($volunteerAssignments['values']));
  }

  /**
   * Determine if a given assignment is in the past.
   * There are two flavors of Volunteer Assignment End Date:
   *
   * Fixed date: Start time and duration are set. Activity is expected to start at start time and last duration minutes.
   * Fuzzy date: Start time, end time, and duration are set. Activity needs to be completed between start time and end
   *   time and take duration minutes. Example: I need 5 hours of filing completed between December 1 and December 31.
   * Just start date: If we just have the start date then we'll compare that to today.
   *
   * @param array $assignment
   */
  private function isAssignmentInThePast($assignment){
    // If we don't have the crucial data then we assume that it's not in the future.
    if (empty($assignment['start_time'])) {
      return FALSE;
    }

    // Measure against tomorrow, easier than setting time to 00:00:00 in each case.
    $tomorrow = date_add($this->todaysDate, new DateInterval('P1D'));

    // If no end date, add the duration to the start time for the end time.
    if (empty($assignment['end_time']) && empty($assignment['duration'])) {
      // With no end time or duration, we just work from the start date.
      return $tomorrow > new DateTime($assignment['start_time']);
    }
    elseif (empty($assignment['end_time'])) {
      $endTime = date_add($assignment['start_time'], new DateInterval('PT' . $assignment['duration'] . 'M'));
    }
    else {
      $endTime = new DateInterval($assignment['end_time']);
    }
    return $this->todaysDate > $endTime;
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
      // Assignments further in the future at the bottom.
      return ($a['start_time'] < $b['start_time']) ? -1 : 1;
    });

    return $sortedResults;
  }
}
