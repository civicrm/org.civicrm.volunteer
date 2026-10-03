<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerAssignment;

/**
 * Tests CRM_Volunteer_Form_Log, the batch hour-logging screen.
 *
 * The BAO logHours() path is covered by the api/v4 suite; this class pins the
 * form's own surfaces: the preProcess permission gate, formRule validation,
 * default-value assembly, and postProcess's aggregate write (updates plus
 * walk-in creation on the flexible need, with whole-batch rollback).
 *
 * @group headless
 */
class CRM_Volunteer_Form_LogTest extends VolunteerTestAbstract {

  /**
   * @var array<int, int>
   */
  private array $fixture = array();

  /**
   * The contact assigned by the fixture, for submitting form rows.
   *
   * @var int
   */
  private int $volunteerContactId = 0;

  /**
   * Build a project with one scheduled need, one assigned volunteer and a
   * second free slot, through the real API.
   *
   * @return array{0: int, 1: int, 2: int}
   *   Project ID, need ID, assignment ID.
   */
  private function buildProjectWithAssignment(): array {
    $event = $this->createEvent(array(
      'title' => 'Log form event',
      'start_date' => '2027-02-11 08:00:00',
      'end_date' => '2027-02-11 17:00:00',
    ));
    $project = $this->createProject(array(
      'title' => 'Log form project',
      'entity_table' => 'civicrm_event',
      'entity_id' => $event['id'],
      'is_active' => 1,
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2027-02-11 09:00:00',
      'duration' => 120,
      'is_flexible' => 0,
      'quantity' => 5,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $this->volunteerContactId = $this->individualCreate();
    $assignment = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $need['id'])
      ->addValue('assignee_contact_id', $this->volunteerContactId)
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();



    $this->fixture = array(
      (int) $project['id'],
      (int) $need['id'],
      (int) $assignment['id'],
    );
    return $this->fixture;
  }

  /**
   * A caller without project-update rights is refused during preProcess,
   * before any data is loaded.
   */
  public function testPreProcessRefusesStrangers(): void {
    list($projectId, , ) = $this->buildProjectWithAssignment();
    $_REQUEST['vid'] = $_GET['vid'] = $projectId;

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');

    $form = $this->newForm();
    $this->expectException(CRM_Core_Exception::class);
    $form->preProcess();
  }

  /**
   * The project owner gets through preProcess and loads the assignment rows.
   */
  public function testPreProcessLoadsAssignmentRows(): void {
    list($projectId, , $assignmentId) = $this->buildProjectWithAssignment();
    $_REQUEST['vid'] = $_GET['vid'] = $projectId;

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );

    $form = $this->newForm();
    $form->preProcess();

    $property = new ReflectionProperty(CRM_Volunteer_Form_Log::class, '_volunteerData');
    $property->setAccessible(TRUE);
    $rows = $property->getValue($form);
    $this->assertCount(1, $rows);
    $row = reset($rows);
    $this->assertSame($assignmentId, (int) $row['id']);
  }

  /**
   * Rows without a contact are not rows; the remaining ones must carry a
   * numeric actual duration.
   */
  public function testFormRuleValidation(): void {
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');
    $validRow = array(
      'contact_id' => 2,
      'volunteer_status' => $completedStatusId,
      'actual_duration' => '95',
    );

    // A row with no contact is ignored entirely, even if invalid.
    $this->assertTrue(CRM_Volunteer_Form_Log::formRule(array(
      'field' => array(1 => array('contact_id' => NULL, 'actual_duration' => 'junk')),
    ), array(), NULL));

    $missingDuration = $validRow;
    $missingDuration['actual_duration'] = '';
    $errors = CRM_Volunteer_Form_Log::formRule(array('field' => array(1 => $missingDuration)), array(), NULL);
    $this->assertArrayHasKey('field[1][actual_duration]', $errors);

    $nonNumericDuration = $validRow;
    $nonNumericDuration['actual_duration'] = 'one hour';
    $errors = CRM_Volunteer_Form_Log::formRule(array('field' => array(1 => $nonNumericDuration)), array(), NULL);
    $this->assertArrayHasKey('field[1][actual_duration]', $errors);

    $negativeDuration = $validRow;
    $negativeDuration['actual_duration'] = '-5';
    $errors = CRM_Volunteer_Form_Log::formRule(array('field' => array(1 => $negativeDuration)), array(), NULL);
    $this->assertArrayHasKey('field[1][actual_duration]', $errors);

    $missingStatus = $validRow;
    unset($missingStatus['volunteer_status']);
    $errors = CRM_Volunteer_Form_Log::formRule(array('field' => array(1 => $missingStatus)), array(), NULL);
    $this->assertArrayHasKey('field[1][volunteer_status]', $errors);

    $invalidStatus = $validRow;
    $invalidStatus['volunteer_status'] = PHP_INT_MAX;
    $errors = CRM_Volunteer_Form_Log::formRule(array('field' => array(1 => $invalidStatus)), array(), NULL);
    $this->assertArrayHasKey('field[1][volunteer_status]', $errors);

    $this->assertTrue(CRM_Volunteer_Form_Log::formRule(array('field' => array(1 => $validRow)), array(), NULL));
  }

  /**
   * The real QuickForm build registers both the existing assignment row and a
   * spare walk-in row after preProcess has loaded the project.
   */
  public function testBuildQuickFormRegistersExistingAndSpareRows(): void {
    list($projectId, , ) = $this->buildProjectWithAssignment();
    $_REQUEST['vid'] = $_GET['vid'] = $projectId;
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );

    $form = $this->newForm();
    $form->preProcess();
    $form->buildQuickForm();

    foreach (array(
      'field[1][contact_id]',
      'field[1][activity_id]',
      'field[2][contact_id]',
      'field[2][volunteer_status]',
    ) as $elementName) {
      $this->assertTrue($form->elementExists($elementName), "$elementName must be registered by buildQuickForm().");
    }
  }

  /**
   * getCompletedRows keeps only rows naming a contact.
   */
  public function testGetCompletedRowsFiltersContactlessRows(): void {
    $rows = array(
      1 => array('contact_id' => 5, 'actual_duration' => '30'),
      2 => array('contact_id' => '', 'actual_duration' => '30'),
      3 => array('actual_duration' => '30'),
    );
    $completed = CRM_Volunteer_Form_Log::getCompletedRows($rows);
    $this->assertSame(array(1), array_keys($completed));
  }

  /**
   * Existing assignments are defaulted into the first rows; spare rows
   * default to the Completed status so a walk-in needs only a contact.
   */
  public function testSetDefaultValuesSeedsExistingAndSpareRows(): void {
    list($projectId, , ) = $this->buildProjectWithAssignment();
    $_REQUEST['vid'] = $_GET['vid'] = $projectId;

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );

    $form = $this->newForm();
    $form->preProcess();

    $defaults = $form->setDefaultValues();
    $this->assertNotEmpty($defaults['field'][1]['activity_id']);
    $this->assertNotEmpty($defaults['field'][1]['contact_id']);

    $completedId = $this->getOptionValue('activity_status', 'Completed');
    $this->assertSame($completedId, (int) $defaults['field'][2]['volunteer_status']);
    $this->assertArrayNotHasKey('activity_id', $defaults['field'][2]);
  }

  /**
   * The full write path: an existing assignment's minutes are recorded, and
   * a walk-in row without an activity id becomes a new assignment on the
   * project's flexible need.
   */
  public function testPostProcessRecordsExistingAndWalkInRows(): void {
    list($projectId, , $assignmentId) = $this->buildProjectWithAssignment();
    $walkInContactId = $this->individualCreate();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );
    CRM_Core_Session::singleton()->getStatus(TRUE);

    $form = $this->buildProcessedForm($projectId, array(
      1 => array(
        'contact_id' => $this->volunteerContactId,
        'activity_id' => $assignmentId,
        'volunteer_status' => $completedStatusId,
        'scheduled_duration' => '120',
        'actual_duration' => '95',
      ),
      2 => array(
        'contact_id' => $walkInContactId,
        'volunteer_status' => $completedStatusId,
        'actual_duration' => '45',
      ),
    ));

    $flexibleNeedId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId);

    // VolunteerAssignment::get exposes only Scheduled/Available rows, and
    // row 1 has just been marked Completed; getHourEntries is the surface
    // that carries historical statuses.
    $entries = VolunteerAssignment::getHourEntries(FALSE)
      ->setProjectId($projectId)
      ->execute()
      ->single();
    $rowsById = array();
    foreach ($entries['rows'] as $row) {
      $rowsById[(int) $row['id']] = $row;
    }

    $this->assertArrayHasKey($assignmentId, $rowsById);
    $this->assertSame(95, (int) $rowsById[$assignmentId]['time_completed_minutes']);
    $this->assertSame(120, (int) $rowsById[$assignmentId]['time_scheduled_minutes']);

    $walkIns = array_values(array_filter($entries['rows'], static function(array $row) use ($walkInContactId, $flexibleNeedId) {
      return (int) $row['assignee_contact_id'] === $walkInContactId
        && (int) $row['volunteer_need_id'] === $flexibleNeedId;
    }));
    $this->assertCount(1, $walkIns, 'The walk-in row must land on the project flexible need.');
    $statuses = CRM_Core_Session::singleton()->getStatus(TRUE);
    $this->assertContains(
      array(
        'text' => 'Volunteer hours have been logged.',
        'title' => 'Saved',
        'type' => 'success',
        'options' => NULL,
      ),
      $statuses,
      'A successful save must set its own status message.'
    );
  }

  /**
   * The batch is one aggregate: a failure on row N must leave nothing saved,
   * including rows that were individually valid.
   */
  public function testPostProcessRollsBackTheWholeBatch(): void {
    list($projectId, , $assignmentId) = $this->buildProjectWithAssignment();
    $completedStatusId = $this->getOptionValue('activity_status', 'Completed');

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );

    // A non-volunteer activity id in an update row: createVolunteerActivity
    $foreignActivityId = \Civi\Api4\Activity::create(FALSE)
      ->addValue('activity_type_id:name', 'Meeting')
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->addValue('subject', 'Not a volunteer assignment')
      ->execute()
      ->single()['id'];

    $form = $this->buildProcessedFormWithoutSaving($projectId, array(
      1 => array(
        'contact_id' => $this->volunteerContactId,
        'activity_id' => $assignmentId,
        'volunteer_status' => $completedStatusId,
        'actual_duration' => '95',
      ),
      2 => array(
        'contact_id' => $this->volunteerContactId,
        'activity_id' => $foreignActivityId,
        'volunteer_status' => $completedStatusId,
        'actual_duration' => '45',
      ),
    ));

    try {
      $form->postProcess();
      $this->fail('A failing row must fail the whole batch.');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('could not be logged', $e->getMessage());
    }

    // Rolled back: the assignment is back to Scheduled with no recorded
    // hours, which is exactly what VolunteerAssignment::get exposes.
    $after = VolunteerAssignment::get(FALSE)
      ->addSelect('status_id', 'time_completed_minutes')
      ->addWhere('id', '=', $assignmentId)
      ->execute()
      ->single();
    $this->assertSame(
      $this->getOptionValue('activity_status', 'Scheduled'),
      (int) $after['status_id'],
      'The valid row must be rolled back with the batch.'
    );
    $this->assertEmpty($after['time_completed_minutes']);
  }

  /**
   * A form that has been through preProcess, wired to a controller that
   * replays the given submitted rows, with postProcess already run.
   *
   * @param int $projectId
   * @param array $submittedRows
   *   Rows in the shape of $params['field'].
   * @return CRM_Volunteer_Form_Log
   */
  private function buildProcessedForm(int $projectId, array $submittedRows): CRM_Volunteer_Form_Log {
    $form = $this->buildProcessedFormWithoutSaving($projectId, $submittedRows);
    $form->postProcess();
    return $form;
  }

  /**
   * The same wiring, but postProcess is left to the caller (for example to
   * assert the failure path).
   */
  private function buildProcessedFormWithoutSaving(int $projectId, array $submittedRows): CRM_Volunteer_Form_Log {
    $_REQUEST['vid'] = $_GET['vid'] = $projectId;
    $form = $this->newForm();
    $form->controller = new class($submittedRows) {
      private array $values;
      private array $scope = array();

      public function __construct(array $rows) {
        $this->values = array('field' => $rows);
      }

      public function set($name, $value) {
        $this->scope[$name] = $value;
      }

      public function get($name) {
        return $this->scope[$name] ?? NULL;
      }

      public function exportValues($name = NULL) {
        return $this->values;
      }
    };

    $form->preProcess();
    return $form;
  }

  /**
   * The form stores request values through its controller
   * (CRM_Core_Form::set() delegates there), so even preProcess needs one.
   */
  private function newForm(): CRM_Volunteer_Form_Log {
    $form = new CRM_Volunteer_Form_Log();
    $form->controller = new class {
      private array $scope = array();

      public function set($name, $value) {
        $this->scope[$name] = $value;
      }

      public function get($name) {
        return $this->scope[$name] ?? NULL;
      }

      public function exportValues($name = NULL) {
        return array();
      }
    };
    return $form;
  }

}
