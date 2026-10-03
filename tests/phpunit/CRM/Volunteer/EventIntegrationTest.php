<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * CiviEvent integration.
 *
 * Nothing in the suite used to create a real Event, so the whole integration --
 * the tabset hook, the event-tab bootstrap, getEntityAttributes(), the event
 * copy and delete behaviour -- went unexercised.
 *
 * @group headless
 */
class CRM_Volunteer_EventIntegrationTest extends VolunteerTestAbstract {

  public function setUp(): void {
    $this->quickCleanup(array('civicrm_volunteer_need', 'civicrm_volunteer_project'));
    parent::setUp();
    CRM_Volunteer_BAO_Project::flushEventProjectCache();
  }

  public function tearDown(): void {
    unset($_REQUEST['snippet'], $_GET['snippet'], $_REQUEST['id'], $_GET['id']);
    CRM_Volunteer_BAO_Project::flushEventProjectCache();
    parent::tearDown();
  }

  private function linkedProject(array $event, array $values = array()): array {
    return $this->createProject($values + array(
      'is_active' => 1,
      'entity_table' => 'civicrm_event',
      'entity_id' => $event['id'],
    ));
  }

  private function tabsForEvent($eventId): array {
    $tabs = array(
      'settings' => array('title' => 'Info'),
      'location' => array('title' => 'Location'),
      'fee' => array('title' => 'Fees'),
      'registration' => array('title' => 'Online Registration'),
      'reminder' => array('title' => 'Reminders'),
    );
    // Snippet mode means the tab body is being fetched, not the tabset; the
    // hook only bootstraps Angular in the latter case.
    $_REQUEST['snippet'] = $_GET['snippet'] = '5';
    volunteer_civicrm_tabset('civicrm/event/manage', $tabs, array('event_id' => $eventId));
    return $tabs;
  }

  /**
   * The Volunteers tab lands at position 4, between Registration and Reminders.
   */
  public function testTabsetInsertsVolunteerTabAtPositionFour(): void {
    $event = $this->createEvent();
    $tabs = $this->tabsForEvent($event['id']);

    $this->assertArrayHasKey('volunteer', $tabs);
    $this->assertSame(4, array_search('volunteer', array_keys($tabs), TRUE));
    $this->assertSame('Volunteers', $tabs['volunteer']['title']);
    $this->assertStringContainsString(
      'civicrm/event/manage/volunteer',
      $tabs['volunteer']['link']
    );
  }

  /**
   * `valid` is what greys the tab out, and it tracks whether the event has an
   * active project.
   */
  public function testTabValidityTracksTheProject(): void {
    $event = $this->createEvent();

    $tabs = $this->tabsForEvent($event['id']);
    $this->assertFalse($tabs['volunteer']['valid'], 'No project yet, so nothing to visit.');

    $this->linkedProject($event);
    CRM_Volunteer_BAO_Project::flushEventProjectCache();
    $tabs = $this->tabsForEvent($event['id']);
    $this->assertTrue($tabs['volunteer']['valid']);
  }

  /**
   * The Manage Events listing asks per row whether the event has volunteers.
   */
  public function testRowsTabsetFlagsEventsWithVolunteers(): void {
    $withProject = $this->createEvent();
    $without = $this->createEvent();
    $this->linkedProject($withProject);
    CRM_Volunteer_BAO_Project::flushEventProjectCache();

    foreach (array($withProject['id'] => 1, $without['id'] => NULL) as $eventId => $expected) {
      $tabs = array($eventId => array());
      volunteer_civicrm_tabset('civicrm/event/manage/rows', $tabs, array('event_id' => $eventId));
      $this->assertEquals(
        $expected,
        $tabs[$eventId]['is_volunteer'],
        "Unexpected is_volunteer flag for event $eventId."
      );
    }
  }

  /**
   * getEntityAttributes() against a real event row.
   */
  public function testEntityAttributesReadTheEvent(): void {
    $event = $this->createEvent(array(
      'title' => 'Beach clean',
      'start_date' => '2026-11-05 08:30:00',
    ));
    $project = $this->linkedProject($event);

    $attributes = CRM_Volunteer_BAO_Project::retrieveByID($project['id'])->getEntityAttributes();

    $this->assertSame('Beach clean', $attributes['title']);
    $this->assertStringStartsWith('2026-11-05', $attributes['start_time']);
    $this->assertArrayHasKey('campaign_id', $attributes);
  }

  /**
   * A standalone project has no entity to read, and must not pretend otherwise.
   */
  public function testEntityAttributesAreEmptyForStandaloneProject(): void {
    $project = $this->createProject();

    $attributes = CRM_Volunteer_BAO_Project::retrieveByID($project['id'])->getEntityAttributes();

    $this->assertNull($attributes['title']);
    $this->assertNull($attributes['start_time']);
  }

  /**
   * A deleted event degrades to NULLs and a log line, not an exception.
   */
  public function testEntityAttributesSurviveADeletedEvent(): void {
    $event = $this->createEvent();
    $project = $this->linkedProject($event);
    // Bypass the delete hook: the point here is the unreadable-row path, and
    // detachFromEvent() would remove the very association under test.
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_event WHERE id = %1', array(
      1 => array($event['id'], 'Integer'),
    ));

    $attributes = CRM_Volunteer_BAO_Project::retrieveByID($project['id'])->getEntityAttributes();

    $this->assertNull($attributes['title']);
    $this->assertNull($attributes['start_time']);
  }

  /**
   * The event tab's bootstrap publishes the workflow's starting route and its
   * form context.
   */
  public function testPrepareTabPublishesEventContext(): void {
    $event = $this->createEvent(array('title' => 'Harvest supper'));
    $project = $this->linkedProject($event);

    CRM_Volunteer_Angular_Tab_Event::prepareTab($event['id']);
    $vars = CRM_Core_Resources::singleton()->getSettings()['vars']['org.civicrm.volunteer'];

    $this->assertEquals($project['id'], $vars['projectId']);
    $this->assertSame('civicrm_event', $vars['entityTable']);
    $this->assertEquals($event['id'], $vars['entityId']);
    $this->assertSame('Harvest supper', $vars['entityTitle']);
    $this->assertSame('eventTab', $vars['context']);
    $this->assertSame('#/volunteer/manage/' . $project['id'] . '/details', $vars['hash']);
    $this->assertArrayHasKey('entityCampaignId', $vars);
  }

  /**
   * With no project yet, the tab bootstraps a create form for project 0 and
   * still seeds the event's title and campaign.
   */
  public function testPrepareTabSeedsANewProjectFromTheEvent(): void {
    $this->enableCampaignComponent();
    try {
      $campaignId = $this->createCampaign('Winter appeal');
      $event = $this->createEvent(array(
        'title' => 'Winter appeal day',
        'campaign_id' => $campaignId,
      ));

      CRM_Volunteer_Angular_Tab_Event::prepareTab($event['id']);
      $vars = CRM_Core_Resources::singleton()->getSettings()['vars']['org.civicrm.volunteer'];

      $this->assertEquals(0, $vars['projectId']);
      $this->assertSame('Winter appeal day', $vars['entityTitle']);
      $this->assertEquals(
        $campaignId,
        $vars['entityCampaignId'],
        "An event's own campaign is a better default for its volunteer project than the site-wide setting."
      );
    }
    finally {
      $this->disableCampaignComponent();
    }
  }

  /**
   * With CiviCampaign switched off there is no campaign to inherit, and asking
   * for one must not fail.
   */
  public function testPrepareTabSeedsNoCampaignWhenComponentDisabled(): void {
    $this->assertFalse(
      CRM_Core_Component::isEnabled('CiviCampaign'),
      'This test relies on the harness shipping without CiviCampaign.'
    );
    $event = $this->createEvent();

    CRM_Volunteer_Angular_Tab_Event::prepareTab($event['id']);
    $vars = CRM_Core_Resources::singleton()->getSettings()['vars']['org.civicrm.volunteer'];

    $this->assertArrayHasKey('entityCampaignId', $vars);
    $this->assertNull($vars['entityCampaignId']);
  }

  /**
   * The (entity_table, entity_id) pair is declared as a dynamic foreign key, so
   * API4 -- and therefore SearchKit and the deferred Afform work -- can reach
   * the event from the project. Before it was declared, this join was
   * impossible and entity_table had no option list.
   */
  public function testProjectJoinsToItsEventThroughTheDynamicForeignKey(): void {
    $event = $this->createEvent(array('title' => 'Joinable event'));
    $project = $this->createProject(array(
      'entity_table' => 'civicrm_event',
      'entity_id' => $event['id'],
    ));

    $field = \Civi\Api4\VolunteerProject::getFields(FALSE)
      ->addWhere('name', '=', 'entity_id')
      ->execute()
      ->single();
    $this->assertSame(array('civicrm_event' => 'Event'), $field['dfk_entities']);

    $entityTables = \Civi\Api4\VolunteerProject::getFields(FALSE)
      ->setLoadOptions(TRUE)
      ->addWhere('name', '=', 'entity_table')
      ->execute()
      ->single();
    $this->assertArrayHasKey('civicrm_event', $entityTables['options']);

    $row = \Civi\Api4\VolunteerProject::get(FALSE)
      ->addSelect('id', 'event.title')
      ->addJoin(
        'Event AS event',
        'LEFT',
        array('entity_id', '=', 'event.id'),
        array('entity_table', '=', "'civicrm_event'")
      )
      ->addWhere('id', '=', $project['id'])
      ->execute()
      ->single();
    $this->assertSame('Joinable event', $row['event.title']);
  }

  /**
   * Copying an event must carry its volunteer setup across -- shifts and roles,
   * but not the previous event's volunteers.
   */
  public function testEventCopyDuplicatesTheProjectButNotItsAssignments(): void {
    $source = $this->createEvent();
    $target = $this->createEvent();
    $project = $this->linkedProject($source, array('title' => 'Marshals'));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'duration' => 90,
      'is_flexible' => 0,
      'quantity' => 4,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));
    $this->createAssignment(array(
      'assignee_contact_id' => $this->individualCreate(),
      'source_contact_id' => $this->getMockedContactId(),
      'volunteer_need_id' => $need['id'],
    ));

    $copy = CRM_Volunteer_BAO_Project::copyForEvent($source['id'], $target['id']);

    $this->assertNotNull($copy);
    $this->assertNotEquals($project['id'], $copy->id);
    $this->assertSame('Marshals', $copy->title);
    $this->assertEquals($target['id'], $copy->entity_id);
    $this->assertSame('civicrm_event', $copy->entity_table);

    $copiedNeeds = \Civi\Api4\VolunteerNeed::get(FALSE)
      ->addSelect('*')
      ->addWhere('project_id', '=', $copy->id)
      ->execute()
      ->getArrayCopy();
    $flexible = array_values(array_filter($copiedNeeds, function(array $row) {
      return !empty($row['is_flexible']);
    }));
    $dated = array_values(array_filter($copiedNeeds, function(array $row) {
      return empty($row['is_flexible']);
    }));
    $this->assertCount(1, $flexible, 'The copy must end up with exactly one flexible need.');
    $this->assertCount(1, $dated);
    $this->assertEquals(4, $dated[0]['quantity']);
    $this->assertEquals(90, $dated[0]['duration']);

    $this->assertSame(
      array(),
      CRM_Volunteer_BAO_Assignment::getProjectAssignmentIds($copy->id),
      'A copied project must start with an empty roster.'
    );
  }

  /**
   * Nothing to copy is not an error.
   */
  public function testEventCopyIsANoOpWithoutAProject(): void {
    $source = $this->createEvent();
    $target = $this->createEvent();

    $this->assertNull(CRM_Volunteer_BAO_Project::copyForEvent($source['id'], $target['id']));
  }

  /**
   * Deleting an event unlinks its project rather than orphaning or destroying
   * it: the logged hours are the organisation's record.
   */
  public function testEventDeletionDetachesRatherThanOrphans(): void {
    $event = $this->createEvent();
    $project = $this->linkedProject($event);
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));
    $assignment = $this->createAssignment(array(
      'assignee_contact_id' => $this->individualCreate(),
      'source_contact_id' => $this->getMockedContactId(),
      'volunteer_need_id' => $need['id'],
    ));

    \Civi\Api4\Event::delete(FALSE)
      ->addWhere('id', '=', $event['id'])
      ->execute();

    $stored = CRM_Volunteer_BAO_Project::retrieveByID($project['id']);
    $this->assertNotNull($stored, 'The project itself must survive.');
    $this->assertEmpty($stored->entity_table);
    $this->assertEmpty($stored->entity_id);
    $this->assertContains(
      (int) $assignment['id'],
      CRM_Volunteer_BAO_Assignment::getProjectAssignmentIds($project['id']),
      'The volunteer history must survive the event it was recorded against.'
    );
  }

}
