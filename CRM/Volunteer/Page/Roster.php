<?php

class CRM_Volunteer_Page_Roster extends CRM_Core_Page {
  /**
   * @var array<int, array<string, mixed>>
   *   Array of volunteer assignments as retrieved from api.VolunteerAssignment.get
   */
  private $assignments = array();

  /**
   * Contact IDs the current user is allowed to view.
   *
   * @var array<int, bool>
   */
  private $viewableContactIds = array();

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
   *
   * @return void
   */
  public function run() {
    $this->projectId = CRM_Utils_Request::retrieve('project_id', 'Positive', NULL, TRUE);
    $this->project = CRM_Volunteer_BAO_Project::retrieveByID($this->projectId);
    CRM_Utils_System::setTitle(ts('Volunteer Roster for %1', array(
      1 => $this->project->title,
     'domain' => 'org.civicrm.volunteer'
    )));

    $this->todaysDate = new DateTime();
    $this->todaysDate->setTime(0, 0, 0);

    $this->fetchAssignments();
    $sortedAssignments = $this->getAssignmentsGroupedByTime();
    $this->assign('projectTitle', $this->project->title);
    $this->assign('assignmentCount', count($this->assignments));
    $this->assign('shiftCount', count($sortedAssignments));
    $this->assign('sortedResults', $sortedAssignments);

    $this->assign('endDate', $this->todaysDate->format('Y-m-d'));

    $resources = CRM_Core_Resources::singleton();
    $resources
      ->addScriptFile('org.civicrm.volunteer', 'js/roster.js', 0, 'html-header')
      ->addScriptFile('org.civicrm.volunteer', 'js/roster.js', 0, 'ajax-snippet')
      ->addStyleFile('org.civicrm.volunteer', 'css/volunteer-tokens.css', 0, 'html-header')
      ->addStyleFile('org.civicrm.volunteer', 'css/volunteer-tokens.css', 0, 'ajax-snippet')
      ->addStyleFile('org.civicrm.volunteer', 'css/roster.css', 0, 'html-header')
      ->addStyleFile('org.civicrm.volunteer', 'css/roster.css', 0, 'ajax-snippet');

    parent::run();
  }

  /**
   * Retrieves the volunteer assignments for this project's roster.
   *
   * @return void
   */
  private function fetchAssignments(){
    try {
      $volunteerAssignments = \Civi\Api4\VolunteerAssignment::get()
        ->addWhere('project_id', '=', $this->projectId)
        ->execute()
        ->getArrayCopy();
    }
    catch (Exception $e){
      throw new CRM_Core_Exception(ts('Unable to retrieve assignments for the volunteer project.', array('domain' => 'org.civicrm.volunteer')), 0, array(), $e);
    }

    /** @var array<int, array<string, mixed>> $needs */
    $needs = $this->project->__get('needs');
    foreach ($volunteerAssignments as $assignmentKey => &$assignment) {
      if ($this->isAssignmentInThePast($assignment)) {
        unset($volunteerAssignments[$assignmentKey]);
        continue;
      }

      $needId = $assignment['volunteer_need_id'];
      $assignment['display_time'] = $needs[$needId]['display_time'];
      $assignment['role_label'] = $needs[$needId]['role_label'];
    }
    unset($assignment);

    $this->assignments = $volunteerAssignments;

    $contactIds = array_values(array_unique(array_filter(array_map(
      'intval',
      array_column($this->assignments, 'assignee_contact_id')
    ))));
    if ($contactIds) {
      $allowedContactIds = CRM_Contact_BAO_Contact_Permission::allowList(
        $contactIds,
        CRM_Core_Permission::VIEW
      );
      $this->viewableContactIds = array_fill_keys($allowedContactIds, TRUE);
    }
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
   * @param array<string, mixed> $assignment
   *
   * @return bool
   */
  private function isAssignmentInThePast(array $assignment){
    // If we don't have the crucial data then we assume that it's not in the future.
    if (empty($assignment['start_time'])) {
      return TRUE;
    }

    $startTime = new DateTime($assignment['start_time']);
    if (!empty($assignment['end_time'])) {
      $endTime = new DateTime($assignment['end_time']);
    } elseif (!empty($assignment['duration'])) {
      $endTime = date_add($startTime, new DateInterval('PT' . $assignment['duration'] . 'M'));
    } else {
      // In case there is no end time and no duration, we use the start date as
      // our default end date.
      $endTime = $startTime;
    }

    return $this->todaysDate > $endTime;
  }

  /**
   * Sorts the volunteer assignments grouping them into timeslots.
   *
   * @return array<string, array<string, mixed>>
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
        'phone_ext' => $assignment['assignee_phone_ext'] ?? '',
        'can_view_contact' => !empty($this->viewableContactIds[(int) $assignment['assignee_contact_id']]),
      );
    }

    foreach ($sortedResults as &$group) {
      usort($group['values'], static function(array $a, array $b) {
        $nameComparison = strnatcasecmp((string) $a['name'], (string) $b['name']);
        return $nameComparison ?: strnatcasecmp((string) $a['role_label'], (string) $b['role_label']);
      });
      $group['assignment_count'] = count($group['values']);
    }
    unset($group);

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
