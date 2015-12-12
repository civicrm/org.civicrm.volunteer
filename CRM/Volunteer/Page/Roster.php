<?php

class CRM_Volunteer_Page_Roster extends CRM_Core_Page {
  /**
   * @var array
   *   Array of volunteer assignments as retrieved from api.VolunteerAssignment.get
   */
  private $assignments = array();

  /**
   * @var Int
   */
  private $projectId;

  /**
   * @var DateTime
   */
  private $todaysDate;

  /**
   * @var CRM_Volunteer_BAO_Project
   */
  private $project;

  /**
   * Builds the page.
   */
  public function run() {
    $this->projectId = CRM_Utils_Request::retrieve('project_id', 'Positive', CRM_Core_DAO::$_nullObject, TRUE);
    $this->project = CRM_Volunteer_BAO_Project::retrieveByID($this->projectId);
    CRM_Utils_System::setTitle(ts('Volunteer Roster for %1', array(
      1 => $this->project->title,
     'domain' => 'org.civicrm.volunteer'
    )));

    $this->fetchAssignments();
    $sortedAssignments = $this->getAssignmentsGroupedByTime();
    $this->assign('sortedResults', $sortedAssignments);
    if (!count($sortedAssignments)) {
     CRM_Core_Session::setStatus(ts('No volunteers have been assigned to this project yet!', array(
         'domain' => 'org.civicrm.volunteer')), '', 'no-popup');
    }

    $this->todaysDate = new DateTime();
    $this->todaysDate->setTime(0, 0, 0); // just the date.
    $this->assign('endDate', $this->todaysDate->format('Y-m-d'));

    CRM_Core_Resources::singleton()->addScriptFile('org.civicrm.volunteer', 'js/roster.js', 0, 'html-header');

    parent::run();
  }

  /**
   * Retrieves the volunteer assignments for this project's roster.
   */
  private function fetchAssignments(){
    try {
      $volunteerAssignments = civicrm_api3('VolunteerAssignment', 'get', array(
        'sequential' => 1,
        'project_id' => $this->projectId,
        'count' => 0,
      ));
    }
    catch (Exception $e){
      CRM_Core_Error::fatal('Unable to retrieve assignments for Volunteer Project.');
    }

    foreach($volunteerAssignments['values'] as $assignmentKey => &$assignment) {
      if ($this->isAssignmentInThePast($assignment)) {
        unset($volunteerAssignments['values'][$assignmentKey]);
        continue;
      }

      $needId = $assignment['volunteer_need_id'];
      $assignment['display_time'] = $this->project->needs[$needId]['display_time'];
      $assignment['role_label'] = $this->project->needs[$needId]['role_label'];
    }

    $this->assignments = $volunteerAssignments['values'];
  }

  /**
   * Determine if a given assignment is in the past.
   *
   * There are two flavors of Volunteer Assignment End Date:
   *
   * Fixed date: Start time and duration are set. Activity is expected to start at start time and last duration minutes.
   * Fuzzy date: Start time, end time, and duration are set. Activity needs to be completed between start time and end
   *   time and take duration minutes. Example: I need 5 hours of filing completed between December 1 and December 31.
   * Just start date: If we just have the start date then we'll compare that to today.
   *
   * @param array $assignment
   */
  private function isAssignmentInThePast(array $assignment){
    // If we don't have the crucial data then we assume that it's not in the future.
    if (empty($assignment['start_time'])) {
      return TRUE;
    }

    // In case there is no end time and no duration, we use the start date as
    // our default end date.
    $endTime = new DateTime($assignment['start_time']);
    if (!empty($assignment['end_time'])) {
      $endTime = new DateTime($assignment['end_time']);
    } elseif (!empty($assignment['duration'])) {
      $endTime = date_add($assignment['start_time'], new DateInterval('PT' . $assignment['duration'] . 'M'));
    }

    return $this->todaysDate > $endTime;
  }

  /**
   * Sorts the volunteer assignments grouping them into timeslots.
   *
   * @return array
   */
  private function getAssignmentsGroupedByTime() {
    $sortedResults = array();

    foreach($this->assignments as $assignment){
      $displayTime = $assignment['display_time'];
      if (!array_key_exists($displayTime, $sortedResults)){
        $sortedResults[$displayTime] = array();
        // If the display times match so will the start times. This makes sorting easier.
        $sortedResults[$displayTime]['start_time'] = new DateTime($assignment['start_time']);
        $sortedResults[$displayTime]['values'] = array();
      }

      $sortedResults[$displayTime]['values'][] = array(
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
