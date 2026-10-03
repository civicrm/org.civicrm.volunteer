<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

/**
 * Tests CRM_Volunteer_Page_Roster, the printable roster screen.
 *
 * @group headless
 */
class CRM_Volunteer_Page_RosterTest extends VolunteerTestAbstract {

  /**
   * Run the real page controller without sending its rendered template to the
   * PHPUnit process output.
   */
  private function runPage(CRM_Volunteer_Page_Roster $page): void {
    $bufferLevel = ob_get_level();
    ob_start();
    try {
      $page->run();
    }
    finally {
      while (ob_get_level() > $bufferLevel) {
        ob_end_clean();
      }
    }
  }

  /**
   * @return array{0: int, 1: array<int, string>}
   *   Project ID and contact IDs (sorted last names reversed, to prove the
   *   in-group alphabetical ordering).
   */
  private function buildRosterProject(): array {
    $project = $this->createProject(array(
      'title' => 'Roster page project',
      'is_active' => 1,
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('+1 day')),
      'duration' => 90,
      'is_flexible' => 0,
      'quantity' => 4,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'role_id' => $this->getOptionValue('volunteer_role', 'Ticket_taker'),
    ));

    $zed = $this->individualCreate(array('first_name' => 'Zed', 'last_name' => 'Zedson'));
    $abe = $this->individualCreate(array('first_name' => 'Abe', 'last_name' => 'Abelson'));
    foreach (array($zed, $abe) as $contactId) {
      \Civi\Api4\VolunteerAssignment::create(FALSE)
        ->addValue('volunteer_need_id', $need['id'])
        ->addValue('assignee_contact_id', $contactId)
        ->addValue('source_contact_id', $this->getMockedContactId())
        ->execute();
    }

    return array((int) $project['id'], array($abe, $zed));
  }

  /**
   * The page reads through the permission-checked assignment get, so a
   * caller with no volunteer access is refused.
   */
  public function testRunIsRefusedWithoutVolunteerAccess(): void {
    list($projectId, ) = $this->buildRosterProject();
    $_REQUEST['project_id'] = $_GET['project_id'] = $projectId;

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');

    $page = new CRM_Volunteer_Page_Roster();
    try {
      $this->runPage($page);
      $this->fail('The roster served a caller with no volunteer access.');
    }
    catch (CRM_Core_Exception $e) {
      $this->addToAssertionCount(1);
    }
  }

  /**
   * The roster groups by timeslot, sorts volunteers alphabetically within a
   * group, and hides assignments whose shift has already ended.
   */
  public function testRunGroupsAndSortsTheRoster(): void {
    list($projectId, $volunteers) = $this->buildRosterProject();
    $pastNeed = $this->createNeed(array(
      'project_id' => $projectId,
      'start_time' => date('Y-m-d H:i:s', strtotime('-2 days')),
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $pastVolunteer = $this->individualCreate();
    \Civi\Api4\VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $pastNeed['id'])
      ->addValue('assignee_contact_id', $pastVolunteer)
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute();

    $_REQUEST['project_id'] = $_GET['project_id'] = $projectId;
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );

    $page = new CRM_Volunteer_Page_Roster();
    $this->runPage($page);

    $templateVars = $page->getTemplate()->get_template_vars();
    $this->assertSame(2, $templateVars['assignmentCount'], 'Only the future shift\'s volunteers are listed.');
    $this->assertSame(1, $templateVars['shiftCount']);
    $this->assertSame('Roster page project', $templateVars['projectTitle']);

    $groups = $templateVars['sortedResults'];
    $this->assertCount(1, $groups);
    $group = reset($groups);
    $names = array_column($group['values'], 'name');
    // Zed was created first; the roster must still list Abe first.
    $this->assertSame(array('Abe Abelson', 'Zed Zedson'), $names);
  }

}
