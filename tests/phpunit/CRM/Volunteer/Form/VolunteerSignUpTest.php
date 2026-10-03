<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

/**
 * Tests for the public signup form's capacity gate.
 *
 * @group headless
 */
class CRM_Volunteer_Form_VolunteerSignUpTest extends VolunteerTestAbstract {

  public function setUp(): void {
    $this->quickCleanup(array('civicrm_volunteer_need', 'civicrm_volunteer_project'));
    parent::setUp();
  }

  public function tearDown(): void {
    // The destination tests read `dest` out of the request superglobals, which
    // PHPUnit does not reset between tests in the same process.
    unset($_REQUEST['dest'], $_GET['dest']);
    parent::tearDown();
  }

  /**
   * Build two projects, each with one dated public need of the given capacity.
   *
   * The project titles sort in the opposite order to their IDs so that
   * buildQuickForm()'s "order by project title" usort() genuinely reorders the
   * needs rather than coincidentally leaving them in place.
   *
   * @return int[]
   *   Need IDs, in creation (ID) order.
   */
  private function createSignupNeeds(int $quantity): array {
    $needIds = array();
    foreach (array('Zulu project', 'Alpha project') as $title) {
      $project = $this->createProject(array(
        'title' => $title,
        'is_active' => 1,
      ));
      $need = $this->createNeed(array(
        'project_id' => $project['id'],
        'start_time' => '2026-12-17 16:00:00',
        'duration' => 60,
        'is_flexible' => 0,
        'quantity' => $quantity,
        'visibility_id' => $this->getOptionValue('visibility', 'public'),
        'is_active' => 1,
      ));
      $needIds[] = (int) $need['id'];
    }
    return $needIds;
  }

  /**
   * Load needs the way preProcess() does, then reindex as buildQuickForm() does.
   *
   * preProcess() indexes the API4 result by need ID, so $_needs arrives as an
   * ID-keyed map. buildQuickForm() then calls usort() on it to order by project
   * title, which discards those keys and renumbers from zero. Every consumer
   * that runs later -- i.e. all of postProcess() -- therefore sees a list, not
   * an ID-keyed map. array_values() reproduces exactly that state.
   *
   * @return array
   *   [$form, $needsAsPreProcessLoadsThem]
   */
  private function buildFormWithReindexedNeeds(array $needIds): array {
    $needs = \Civi\Api4\VolunteerNeed::get()
      ->addSelect('*')
      ->addWhere('id', 'IN', $needIds)
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();

    // Precondition: preProcess() really does build an ID-keyed map. If that
    // ever changes, the rest of the test is testing the wrong thing.
    $this->assertEquals(
      $needIds,
      array_map('intval', array_keys($needs)),
      'Expected VolunteerNeed.get to return records keyed by need ID.'
    );

    // Skip the constructor: QuickForm setup is irrelevant here, and the defect
    // under test lives entirely in how $_needs is consumed.
    $form = (new ReflectionClass('CRM_Volunteer_Form_VolunteerSignUp'))
      ->newInstanceWithoutConstructor();

    $needsProperty = new ReflectionProperty('CRM_Volunteer_Form_VolunteerSignUp', '_needs');
    $needsProperty->setAccessible(TRUE);
    $needsProperty->setValue($form, array_values($needs));

    return array($form, $needs);
  }

  private function invokeCapacityCheck($form, int $requestedPlaces): void {
    $method = new ReflectionMethod('CRM_Volunteer_Form_VolunteerSignUp', 'assertSignupCapacity');
    $method->setAccessible(TRUE);
    $method->invoke($form, $requestedPlaces);
  }

  private function invokeGetSelectedNeedIds($form): array {
    $method = new ReflectionMethod('CRM_Volunteer_Form_VolunteerSignUp', 'getSelectedNeedIds');
    $method->setAccessible(TRUE);
    return $method->invoke($form);
  }

  /**
   * Regression: the capacity gate must survive buildQuickForm()'s reindexing.
   *
   * Reading array_keys($this->_needs) after the usort() yields 0, 1, 2 ...,
   * and need ID 0 never exists, so the gate rejected every single signup.
   */
  public function testCapacityCheckSurvivesFormReindexing(): void {
    $needIds = $this->createSignupNeeds(5);
    [$form] = $this->buildFormWithReindexedNeeds($needIds);

    $this->invokeCapacityCheck($form, 1);
    $this->addToAssertionCount(1);
  }

  /**
   * The real need IDs are recovered from the records, not the array keys.
   */
  public function testSelectedNeedIdsAreRecoveredAfterReindexing(): void {
    $needIds = $this->createSignupNeeds(5);
    [$form] = $this->buildFormWithReindexedNeeds($needIds);

    $resolved = $this->invokeGetSelectedNeedIds($form);

    $expected = $needIds;
    sort($expected);
    $this->assertSame($expected, $resolved);
    $this->assertNotContains(0, $resolved, 'Array offsets must never be treated as need IDs.');
  }

  /**
   * Capacity is still enforced -- the fix must not turn the gate into a no-op.
   */
  public function testCapacityCheckRejectsOversubscription(): void {
    $needIds = $this->createSignupNeeds(2);
    [$form] = $this->buildFormWithReindexedNeeds($needIds);

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('There are not enough remaining places');
    $this->invokeCapacityCheck($form, 3);
  }

  /**
   * A disabled need is rejected even though its ID resolves.
   */
  public function testCapacityCheckRejectsDisabledNeed(): void {
    $needIds = $this->createSignupNeeds(5);
    [$form] = $this->buildFormWithReindexedNeeds($needIds);

    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_volunteer_need SET is_active = 0 WHERE id = %1',
      array(1 => array($needIds[0], 'Integer'))
    );

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('no longer available');
    $this->invokeCapacityCheck($form, 1);
  }

  /**
   * Regression: the signup page must show project beneficiaries.
   *
   * fetchProjectDetails() reads them through a chained
   * VolunteerProjectContact.get. The child carried check_permissions => FALSE,
   * but APIv3's ChainSubscriber overwrites each child's flag with the parent's,
   * so the bypass never applied and the public branch silently returned nothing.
   */
  public function testFetchProjectDetailsIncludesBeneficiaries(): void {
    $beneficiaryId = $this->individualCreate(array(
      'first_name' => 'Ben',
      'last_name' => 'Eficiary',
    ));
    $project = $this->createProject(array(
      'title' => 'Signup beneficiary project',
      'is_active' => 1,
      'project_contacts' => array(
        'volunteer_owner' => array($this->getMockedContactId()),
        'volunteer_beneficiary' => array($beneficiaryId),
      ),
    ));
    $projectId = (int) $project['id'];

    $form = (new ReflectionClass('CRM_Volunteer_Form_VolunteerSignUp'))
      ->newInstanceWithoutConstructor();
    $projects = new ReflectionProperty('CRM_Volunteer_Form_VolunteerSignUp', '_projects');
    $projects->setAccessible(TRUE);
    $projects->setValue($form, array($projectId => array()));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'register to volunteer',
    );

    $fetch = new ReflectionMethod('CRM_Volunteer_Form_VolunteerSignUp', 'fetchProjectDetails');
    $fetch->setAccessible(TRUE);
    $fetch->invoke($form);

    $details = $projects->getValue($form);
    $this->assertNotEmpty(
      $details[$projectId]['beneficiaries'] ?? array(),
      'Beneficiaries were dropped from the signup page.'
    );
    $this->assertContains('Ben Eficiary', $details[$projectId]['beneficiaries']);
  }

  /**
   * A valid hash-relative context survives with its filters, minus junk.
   */
  public function testSanitizeReturnContextKeepsFiltersAndSelections(): void {
    $context = CRM_Volunteer_Form_VolunteerSignUp::sanitizeReturnContext(
      '/volunteer/opportunities?timeFilter=weekends&date_start=2026-08-01'
      . '&role_id[]=3&role_id[]=5&selected[]=9&selected[]=notanid'
      . '&project=7&beneficiary=12,14&proximity[radius]=5&proximity[unit]=km&proximity[city]=Austin'
      . '&proximity[street_address]=100+Congress+Ave&proximity[bogus]=x'
      . '&malicious=<script>'
    );

    $this->assertStringStartsWith(
      CRM_Volunteer_Form_VolunteerSignUp::RETURN_CONTEXT_PATH . '?',
      $context
    );
    parse_str((string) parse_url($context, PHP_URL_QUERY), $params);

    $this->assertSame('weekends', $params['timeFilter']);
    $this->assertSame('2026-08-01', $params['date_start']);
    $this->assertSame(array('3', '5'), $params['role_id']);
    $this->assertSame(array('9'), $params['selected'], 'Non-numeric selections must be dropped.');
    $this->assertSame('7', $params['project']);
    $this->assertSame('12,14', $params['beneficiary']);
    $this->assertSame('km', $params['proximity']['unit']);
    $this->assertSame('5', $params['proximity']['radius']);
    $this->assertSame('Austin', $params['proximity']['city']);
    // Street address feeds CRM_Utils_Geocode_Google::format(); dropping it from
    // the whitelist silently degraded every restored proximity search.
    $this->assertSame('100 Congress Ave', $params['proximity']['street_address']);
    $this->assertArrayNotHasKey(
      'bogus',
      $params['proximity'],
      'Unknown proximity keys must not survive sanitization.'
    );
    $this->assertArrayNotHasKey('malicious', $params, 'Unknown parameters must not survive sanitization.');
  }

  /**
   * Drive setDestination() without QuickForm.
   *
   * CRM_Utils_Request::retrieve() writes the value it found back through
   * $store->set(), which on a real CRM_Core_Form reaches the controller. A
   * reflection-instantiated form has none, so the subclass supplies its own
   * scratch store. Everything else -- fetchProjectDetails(), setDestination()
   * -- is the production code path.
   */
  private function signupFormForProjects(array $projectIds) {
    $form = new class extends CRM_Volunteer_Form_VolunteerSignUp {

      private $store = array();

      public function __construct() {
      }

      public function set($name, $value) {
        $this->store[$name] = $value;
      }

      public function get($name) {
        return $this->store[$name] ?? NULL;
      }

      public function loadProjects(array $projectIds) {
        $this->_projects = array_fill_keys($projectIds, array());
        $this->fetchProjectDetails();
      }

      public function loadedProjects() {
        return $this->_projects;
      }

      public function resolveDestination() {
        $this->setDestination();
        return $this->_destination;
      }

    };
    $form->loadProjects($projectIds);
    return $form;
  }

  /**
   * Regression: signing up from an event's "Volunteer Now" button must return
   * the volunteer to that event.
   *
   * fetchProjectDetails() reads projects through the *public* API, whose row
   * reduction in CRM_Volunteer_BAO_Project::searchProjects() omits
   * entity_table and entity_id. setDestination() read $project['entity_id']
   * off that reduced row, so every event signup redirected to
   * civicrm/event/info?reset=1&id= with an empty id.
   */
  public function testEventDestinationReturnsToTheEvent(): void {
    $event = $this->createEvent();
    $project = $this->createProject(array(
      'is_active' => 1,
      'entity_table' => 'civicrm_event',
      'entity_id' => $event['id'],
    ));
    $_REQUEST['dest'] = $_GET['dest'] = 'event';

    $form = $this->signupFormForProjects(array((int) $project['id']));

    // Precondition: the public read really does withhold the association. If
    // it ever stops doing so, this test is no longer covering the defect.
    $loaded = $form->loadedProjects();
    $this->assertArrayNotHasKey(
      'entity_id',
      reset($loaded),
      'The public project read is expected to omit entity_id.'
    );

    $destination = $form->resolveDestination();
    $this->assertStringContainsString('civicrm/event/info', $destination);
    // The defect produced a URL ending in a bare `id=`, so anchoring on the end
    // is what distinguishes the fix. CRM_Utils_System::url() HTML-escapes its
    // separators, hence no `&` in the expectation.
    $this->assertStringEndsWith(
      'id=' . (int) $event['id'],
      $destination,
      "Expected the event id in the return URL; got $destination"
    );
  }

  /**
   * A standalone project has no event to return to, so the opportunity listing
   * is the only sensible destination.
   */
  public function testEventDestinationFallsBackForStandaloneProject(): void {
    $project = $this->createProject(array('is_active' => 1));
    $_REQUEST['dest'] = $_GET['dest'] = 'event';

    $destination = $this->signupFormForProjects(array((int) $project['id']))
      ->resolveDestination();

    $this->assertStringNotContainsString('civicrm/event/info', $destination);
    $this->assertStringContainsString('volunteer/opportunities', $destination);
  }

  /**
   * More than one project means no single event to return to.
   */
  public function testEventDestinationFallsBackForMultipleProjects(): void {
    $event = $this->createEvent();
    $first = $this->createProject(array(
      'is_active' => 1,
      'entity_table' => 'civicrm_event',
      'entity_id' => $event['id'],
    ));
    $second = $this->createProject(array('is_active' => 1));
    $_REQUEST['dest'] = $_GET['dest'] = 'event';

    $destination = $this
      ->signupFormForProjects(array((int) $first['id'], (int) $second['id']))
      ->resolveDestination();

    $this->assertStringNotContainsString('civicrm/event/info', $destination);
    $this->assertStringContainsString('volunteer/opportunities', $destination);
  }

  /**
   * Return contexts are hash-relative only; anything else is discarded.
   */
  public function testSanitizeReturnContextRejectsForeignValues(): void {
    $default = CRM_Volunteer_Form_VolunteerSignUp::RETURN_CONTEXT_PATH;
    $form = 'CRM_Volunteer_Form_VolunteerSignUp';

    $this->assertSame($default, $form::sanitizeReturnContext('javascript:alert(1)'));
    $this->assertSame($default, $form::sanitizeReturnContext('//evil.example/volunteer/opportunities'));
    $this->assertSame($default, $form::sanitizeReturnContext('/civicrm/event/info?id=7'));
    $this->assertSame($default, $form::sanitizeReturnContext('/volunteer/opportunities/../../../admin'));
    $this->assertSame($default, $form::sanitizeReturnContext('/volunteer/opportunities#javascript:alert(1)'));
    // A syntactically valid path whose only parameter is bogus still yields
    // the bare route.
    $this->assertSame($default, $form::sanitizeReturnContext('/volunteer/opportunities?timeFilter=pwned&project=abc'));
  }

  /**
   * Back to shifts restores filters but rewrites selected[] to the IDs that
   * are still available on this form.
   */
  public function testWithSelectedNeedsReplacesSelection(): void {
    $context = '/volunteer/opportunities?timeFilter=evenings&selected[]=1&selected[]=2&project=4';

    $rewritten = CRM_Volunteer_Form_VolunteerSignUp::withSelectedNeeds($context, array(5, 7, 5, 'junk'));
    parse_str((string) parse_url($rewritten, PHP_URL_QUERY), $params);
    $this->assertSame('evenings', $params['timeFilter']);
    $this->assertSame('4', $params['project']);
    $this->assertSame(array('5', '7'), $params['selected'], 'Selections must deduplicate and drop non-numeric IDs.');

    $emptied = CRM_Volunteer_Form_VolunteerSignUp::withSelectedNeeds($context, array());
    parse_str((string) parse_url($emptied, PHP_URL_QUERY), $params);
    $this->assertArrayNotHasKey('selected', $params);
    $this->assertSame('evenings', $params['timeFilter'], 'Filters must survive an empty selection.');
  }

  /**
   * The Back to shifts URL points at the Angular route and carries the
   * stored context with this form's selection.
   */
  public function testBackToShiftsUrlRestoresContextAndSelection(): void {
    $needIds = $this->createSignupNeeds(5);
    [$form] = $this->buildFormWithReindexedNeeds($needIds);

    $returnProperty = new ReflectionProperty('CRM_Volunteer_Form_VolunteerSignUp', '_returnContext');
    $returnProperty->setAccessible(TRUE);
    $returnProperty->setValue($form, '/volunteer/opportunities?date_start=2026-09-01&selected[]=999');

    $method = new ReflectionMethod('CRM_Volunteer_Form_VolunteerSignUp', 'buildBackToShiftsUrl');
    $method->setAccessible(TRUE);
    $url = rawurldecode(html_entity_decode((string) $method->invoke($form)));

    $this->assertStringContainsString('/volunteer/opportunities', $url, 'Back to shifts must target the opportunities route.');
    $this->assertStringNotContainsString('selected[]=999', $url, 'Stale selections must not survive into the back URL.');
    foreach ($needIds as $needId) {
      $this->assertStringContainsString('selected[]=' . $needId, $url);
    }
    $this->assertStringContainsString('date_start=2026-09-01', $url, 'Stored filters must be restored.');
  }

  /**
   * Build needs covering every schedule type across two projects and check
   * the grouped sidebar summaries.
   */
  public function testCommitmentGroupsGroupByProjectAndSummarizeSchedules(): void {
    $alphaProject = $this->createProject(array('title' => 'Alpha project', 'is_active' => 1));
    $zuluProject = $this->createProject(array('title' => 'Zulu project', 'is_active' => 1));
    $publicVisibility = $this->getOptionValue('visibility', 'public');

    $fixedNeed = $this->createNeed(array(
      'project_id' => $alphaProject['id'],
      'start_time' => '2026-12-17 16:00:00',
      'duration' => 60,
      'end_time' => NULL,
      'is_flexible' => 0,
      'quantity' => 5,
      'visibility_id' => $publicVisibility,
      'is_active' => 1,
    ));
    $windowNeed = $this->createNeed(array(
      'project_id' => $alphaProject['id'],
      'start_time' => '2026-12-18 09:00:00',
      'end_time' => '2026-12-18 12:00:00',
      'duration' => NULL,
      'is_flexible' => 0,
      'quantity' => 5,
      'visibility_id' => $publicVisibility,
      'is_active' => 1,
    ));
    // Projects arrive with exactly one flexible need; reuse it rather than
    // creating a second one.
    $flexibleNeedId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $alphaProject['id']);
    $this->assertGreaterThan(0, $flexibleNeedId, 'Project creation must seed the flexible need.');
    $ongoingNeed = $this->createNeed(array(
      'project_id' => $zuluProject['id'],
      'start_time' => '2026-12-19 08:00:00',
      'end_time' => NULL,
      'duration' => NULL,
      'is_flexible' => 0,
      'quantity' => 5,
      'visibility_id' => $publicVisibility,
      'is_active' => 1,
    ));

    $needRows = \Civi\Api4\VolunteerNeed::get()
      ->addSelect('*')
      ->addWhere('id', 'IN', array(
        (int) $fixedNeed['id'], (int) $windowNeed['id'],
        $flexibleNeedId, (int) $ongoingNeed['id'],
      ))
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();

    // Pass rows in a scrambled order: grouping follows project IDs, and the
    // summaries must not depend on input order.
    $scrambled = array(
      $needRows[$flexibleNeedId],
      $needRows[(int) $ongoingNeed['id']],
      $needRows[(int) $windowNeed['id']],
      $needRows[(int) $fixedNeed['id']],
    );
    $projects = array(
      (int) $alphaProject['id'] => array(
        'title' => 'Alpha project',
        'beneficiaries' => array('Ben Eficiary'),
        'description' => '',
      ),
      (int) $zuluProject['id'] => array(
        'title' => 'Zulu project',
        'beneficiaries' => array(),
        'description' => '',
      ),
    );

    $groups = CRM_Volunteer_Form_VolunteerSignUp::compileCommitmentGroups($scrambled, $projects);
    $this->assertCount(2, $groups, 'Commitments must be grouped by project.');

    $byProject = array();
    foreach ($groups as $group) {
      $byProject[$group['title']] = $group;
    }
    $this->assertArrayHasKey('Alpha project', $byProject);
    $this->assertArrayHasKey('Zulu project', $byProject);
    $this->assertSame('Ben Eficiary', $byProject['Alpha project']['organizers']);
    $this->assertSame('', $byProject['Zulu project']['organizers']);

    $alphaCommitments = array();
    foreach ($byProject['Alpha project']['commitments'] as $commitment) {
      $alphaCommitments[$commitment['schedule_type']] = $commitment;
    }
    $this->assertCount(3, $alphaCommitments, 'Alpha must hold fixed, window, and flexible commitments.');

    $fixed = $alphaCommitments['fixed'];
    $this->assertSame($needRows[(int) $fixedNeed['id']]['display_time'], $fixed['schedule_summary']);
    $this->assertStringContainsString('4:00 PM', $fixed['schedule_summary']);
    $this->assertStringContainsString('5:00 PM', $fixed['schedule_summary'], 'A fixed shift summarizes its computed end.');

    $window = $alphaCommitments['window'];
    $this->assertSame($needRows[(int) $windowNeed['id']]['display_time'], $window['schedule_summary']);
    $this->assertStringContainsString('9:00 AM', $window['schedule_summary']);
    $this->assertStringContainsString('12:00 PM', $window['schedule_summary'], 'A window summarizes its start-end range.');

    $flexible = $alphaCommitments['flexible'];
    $this->assertSame('General availability', $flexible['schedule_summary']);

    $ongoing = $byProject['Zulu project']['commitments'][0];
    $this->assertSame('ongoing', $ongoing['schedule_type']);
    $this->assertStringContainsString('Ongoing from', $ongoing['schedule_summary']);
    $this->assertStringContainsString($needRows[(int) $ongoingNeed['id']]['display_time'], $ongoing['schedule_summary']);
  }

  /**
   * The schedule classifier's defensive branch: a non-flexible need without a
   * start time cannot be summarized finer than "ongoing".
   */
  public function testScheduleTypeClassification(): void {
    $form = 'CRM_Volunteer_Form_VolunteerSignUp';
    $this->assertSame('flexible', $form::getNeedScheduleType(array('is_flexible' => 1, 'start_time' => '2026-12-17 16:00:00')));
    $this->assertSame('flexible', $form::getNeedScheduleType(array('is_flexible' => 1)));
    $this->assertSame('window', $form::getNeedScheduleType(array('start_time' => '2026-12-17 16:00:00', 'end_time' => '2026-12-17 18:00:00')));
    $this->assertSame('fixed', $form::getNeedScheduleType(array('start_time' => '2026-12-17 16:00:00', 'duration' => 90)));
    $this->assertSame('ongoing', $form::getNeedScheduleType(array('start_time' => '2026-12-17 16:00:00')));
    $this->assertSame('ongoing', $form::getNeedScheduleType(array()));
  }

  /**
   * Display order: dated needs by start time, general availability last.
   */
  public function testCompareNeedsPutsDatedFirstAndFlexibleLast(): void {
    $form = 'CRM_Volunteer_Form_VolunteerSignUp';
    $compare = new ReflectionMethod($form, 'compareNeedsForDisplay');
    $compare->setAccessible(TRUE);

    $late = array('id' => 1, 'start_time' => '2026-12-18 09:00:00');
    $early = array('id' => 2, 'start_time' => '2026-12-17 16:00:00');
    $flexible = array('id' => 3, 'is_flexible' => 1);

    $this->assertGreaterThan(0, $compare->invoke(NULL, $late, $early));
    $this->assertLessThan(0, $compare->invoke(NULL, $early, $late));
    $this->assertGreaterThan(0, $compare->invoke(NULL, $flexible, $early));
    $this->assertLessThan(0, $compare->invoke(NULL, $early, $flexible));
    $this->assertSame(0, $compare->invoke(NULL, $late, $late));
  }

  /**
   * An unchecked "I am bringing other people" disclosure can never create
   * additional volunteers, whatever the client posts.
   */
  public function testAdditionalVolunteerQuantityRequiresDisclosure(): void {
    $form = (new ReflectionClass('CRM_Volunteer_Form_VolunteerSignUp'))
      ->newInstanceWithoutConstructor();
    $method = new ReflectionMethod('CRM_Volunteer_Form_VolunteerSignUp', 'getAdditionalVolunteerQuantity');
    $method->setAccessible(TRUE);

    $this->assertSame(0, $method->invoke($form, array('additionalVolunteerQuantity' => '3')));
    $this->assertSame(0, $method->invoke($form, array('additionalVolunteerQuantity' => '999999')));
    $this->assertSame(3, $method->invoke($form, array(
      'bringingAdditionalVolunteers' => '1',
      'additionalVolunteerQuantity' => '3',
    )));
    $this->assertSame(0, $method->invoke($form, array(
      'bringingAdditionalVolunteers' => '1',
      'additionalVolunteerQuantity' => 'not a number',
    )));
    $this->assertSame(0, $method->invoke($form, array(
      'bringingAdditionalVolunteers' => '1',
    )));
  }

  /**
   * Finite shifts cap the additional-volunteer maximum at the fewest open
   * places; flexible-only selections impose no client-side maximum.
   */
  public function testMaxAdditionalVolunteersFollowsFiniteCapacityOnly(): void {
    $form = (new ReflectionClass('CRM_Volunteer_Form_VolunteerSignUp'))
      ->newInstanceWithoutConstructor();
    $needsProperty = new ReflectionProperty('CRM_Volunteer_Form_VolunteerSignUp', '_needs');
    $needsProperty->setAccessible(TRUE);
    $method = new ReflectionMethod('CRM_Volunteer_Form_VolunteerSignUp', 'getMaxAdditionalVolunteers');
    $method->setAccessible(TRUE);

    // Finite needs of capacity 4 and 6: the primary volunteer takes one of
    // the four, leaving three additional places.
    $needsProperty->setValue($form, array(
      array('id' => 1, 'quantity_available' => 4),
      array('id' => 2, 'quantity_available' => 6),
    ));
    $this->assertSame(3, $method->invoke($form));

    // Flexible-only selections have no finite shift to cap against.
    $needsProperty->setValue($form, array(
      array('id' => 1, 'quantity_available' => NULL, 'is_flexible' => 1),
    ));
    $this->assertNull($method->invoke($form), 'Flexible-only selections must not impose a client-side maximum.');

    // Mixed selections are still capped by the finite shift.
    $needsProperty->setValue($form, array(
      array('id' => 1, 'quantity_available' => NULL, 'is_flexible' => 1),
      array('id' => 2, 'quantity_available' => 2),
    ));
    $this->assertSame(1, $method->invoke($form));
  }

  /**
   * Flexible needs pass preprocessing without a finite quantity and are not
   * treated as full.
   */
  public function testFlexibleNeedIsOpenWithoutQuantity(): void {
    $project = $this->createProject(array('title' => 'Flexible project', 'is_active' => 1));
    $flexibleNeedId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID((int) $project['id']);
    $this->assertGreaterThan(0, $flexibleNeedId);

    $needRow = \Civi\Api4\VolunteerNeed::get(FALSE)
      ->addSelect('*')
      ->addWhere('id', '=', $flexibleNeedId)
      ->execute()
      ->first();
    $this->assertSame(1, (int) $needRow['is_flexible']);
    $this->assertNull($needRow['quantity'], 'A flexible need carries no finite quantity.');

    // Flexible needs are validated by is_flexible, not by open_needs
    // membership: preProcessNeeds() admits them regardless of capacity.
    $openNeeds = CRM_Volunteer_BAO_Project::retrieveByID((int) $project['id'])->open_needs;
    $this->assertArrayNotHasKey($flexibleNeedId, $openNeeds);

    $form = 'CRM_Volunteer_Form_VolunteerSignUp';
    $this->assertSame('flexible', $form::getNeedScheduleType($needRow));
    $this->assertSame('General availability', $form::getNeedScheduleSummary($needRow));

    // A flexible-only selection therefore produces no finite client-side cap.
    $needsProperty = new ReflectionProperty($form, '_needs');
    $needsProperty->setAccessible(TRUE);
    $signupForm = (new ReflectionClass($form))->newInstanceWithoutConstructor();
    $needRow['quantity_available'] = NULL;
    $needsProperty->setValue($signupForm, array($needRow));
    $maxMethod = new ReflectionMethod($form, 'getMaxAdditionalVolunteers');
    $maxMethod->setAccessible(TRUE);
    $this->assertNull($maxMethod->invoke($signupForm));
  }

  /**
   * A posted quantity is bounded before anything acts on it.
   *
   * buildQuickForm() builds one Profile per person and postProcess() creates
   * one contact per person, so an unbounded quantity turns a single request
   * into arbitrary work. assertSignupCapacity() still refuses anything a
   * finite shift cannot seat; this stops the work happening first, which
   * matters most for flexible-only selections that have no finite shift.
   */
  public function testAdditionalVolunteerQuantityIsBounded(): void {
    $form = (new ReflectionClass(CRM_Volunteer_Form_VolunteerSignUp::class))
      ->newInstanceWithoutConstructor();

    $this->assertSame(
      CRM_Volunteer_Form_VolunteerSignUp::MAX_ADDITIONAL_VOLUNTEERS,
      $form->getAdditionalVolunteerQuantity(array(
        'bringingAdditionalVolunteers' => 1,
        'additionalVolunteerQuantity' => '100000',
      ))
    );
    $this->assertSame(
      3,
      $form->getAdditionalVolunteerQuantity(array(
        'bringingAdditionalVolunteers' => 1,
        'additionalVolunteerQuantity' => '3',
      )),
      'An ordinary quantity is untouched.'
    );
    $this->assertSame(
      0,
      $form->getAdditionalVolunteerQuantity(array(
        'additionalVolunteerQuantity' => '100000',
      )),
      'Without the disclosure the quantity is still ignored entirely.'
    );
  }

  /**
   * A non-scalar return context is discarded rather than cast.
   *
   * ?return[]=x makes the value an array, and CRM_Utils_Type::validate() hands
   * a 'String' array straight back, so casting it raised a PHP warning.
   */
  public function testArrayReturnContextIsRejected(): void {
    $this->assertSame(
      CRM_Volunteer_Form_VolunteerSignUp::RETURN_CONTEXT_PATH,
      CRM_Volunteer_Form_VolunteerSignUp::sanitizeReturnContext('')
    );
    $this->assertSame(
      CRM_Volunteer_Form_VolunteerSignUp::RETURN_CONTEXT_PATH,
      CRM_Volunteer_Form_VolunteerSignUp::sanitizeReturnContext('/somewhere/else')
    );
  }

  /**
   * SR-003 (security review 2026-08-23): beneficiary display names arrive
   * HTML-decoded from Contact::get and the template renders the organizers
   * string through a {ts 1=...} placeholder, which applies no escaping.
   * compileCommitmentGroups() must therefore escape each name itself.
   */
  public function testCommitmentGroupOrganizersAreEscaped(): void {
    $need = array('id' => 1, 'project_id' => 5, 'role_label' => 'Greeter');
    $projects = array(5 => array(
      'title' => 'Safe title',
      'description' => '',
      'beneficiaries' => array(
        'Ben<script>alert("x")</script> Eficiary',
        'Plain Name',
        'O\'Brien & Sons "Ltd"',
      ),
    ));

    $groups = CRM_Volunteer_Form_VolunteerSignUp::compileCommitmentGroups(array($need), $projects);

    $this->assertCount(1, $groups);
    $this->assertStringNotContainsString(
      '<script',
      $groups[0]['organizers'],
      'A crafted beneficiary display name must not reach the template as markup.'
    );
    $this->assertStringContainsString(
      'Ben&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Eficiary',
      $groups[0]['organizers'],
      'The crafted name should be present, entity-encoded.'
    );
    $this->assertStringContainsString(
      'O&#039;Brien &amp; Sons &quot;Ltd&quot;',
      $groups[0]['organizers'],
      'Quotes and ampersands are escaped too.'
    );
    $this->assertSame('Safe title', $groups[0]['title']);
  }

  /**
   * SR-005 (security review 2026-08-23): a project whose only profiles serve
   * additional volunteers (or which has none at all) must be refused by the
   * signup form -- submitting it used to persist a contactless activity.
   */
  public function testProjectWithoutPrimaryProfileIsRefused(): void {
    $form = (new ReflectionClass(CRM_Volunteer_Form_VolunteerSignUp::class))
      ->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($form, 'projectHasPrimaryProfile');
    $method->setAccessible(TRUE);

    $primary = array('profiles' => array(array(
      'uf_group_id' => 1,
      'module_data' => '{"audience":"primary"}',
    )));
    $this->assertTrue(
      $method->invoke($form, $primary),
      'A primary-audience profile satisfies the requirement.'
    );

    $additionalOnly = array('profiles' => array(array(
      'uf_group_id' => 1,
      'module_data' => '{"audience":"additional"}',
    )));
    $this->assertFalse(
      $method->invoke($form, $additionalOnly),
      'Additional-audience profiles alone cannot collect the primary volunteer.'
    );

    $this->assertFalse(
      $method->invoke($form, array('profiles' => array())),
      'A project with no profiles at all must be refused.'
    );
    $this->assertFalse(
      $method->invoke($form, array()),
      'A project row without a profiles key must be refused.'
    );
  }

  /**
   * @return array{0: int, 1: string}
   *   Profile ID and its audience JSON payload.
   */
  private function createSignupProfile(string $audience = 'primary'): array {
    $suffix = uniqid('', FALSE);
    $group = \Civi\Api4\UFGroup::create(FALSE)
      ->addValue('name', 'signup_profile_' . $suffix)
      ->addValue('title', 'Signup profile ' . $suffix)
      ->addValue('is_active', TRUE)
      ->execute()
      ->single();
    foreach (array('first_name', 'last_name', 'email') as $fieldName) {
      \Civi\Api4\UFField::create(FALSE)
        ->addValue('uf_group_id', $group['id'])
        ->addValue('field_name', $fieldName)
        ->addValue('is_active', TRUE)
        ->execute();
    }
    return array(
      (int) $group['id'],
      json_encode(array('audience' => $audience)),
    );
  }

  /**
   * The fixture for postProcess(): a project whose signup profile collects
   * first/last/email, one public scheduled need, and the needs loaded the
   * way preProcess()/buildQuickForm() leave them.
   *
   * @param array $extraValues
   *   Extra createProject() values (quantity overrides, extra profiles).
   * @return array
   *   [$projectId, $needId, $profilesPayload]
   */
  private function buildSignupProject(array $extraValues = array()): array {
    list($profileId, $moduleData) = $this->createSignupProfile('primary');
    $project = $this->createProject($extraValues + array(
      'title' => 'Signup postProcess project',
      'is_active' => 1,
      'profiles' => array(array('uf_group_id' => $profileId, 'module_data' => $moduleData)),
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('+30 days 16:00')),
      'duration' => 90,
      'is_flexible' => 0,
      'quantity' => 4,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));
    return array(
      (int) $project['id'],
      (int) $need['id'],
      array(array('uf_group_id' => $profileId, 'module_data' => $moduleData)),
    );
  }

  /**
   * Wire a constructor-less form the way preProcess()/buildQuickForm() leave
   * it, with a controller replaying the given submission.
   */
  private function buildSubmittedSignupForm(int $projectId, int $needId, array $profilesPayload, array $submittedValues): CRM_Volunteer_Form_VolunteerSignUp {
    $needs = \Civi\Api4\VolunteerNeed::get()
      ->addSelect('*')
      ->addWhere('id', '=', $needId)
      ->execute()
      ->getArrayCopy();
    $form = (new ReflectionClass('CRM_Volunteer_Form_VolunteerSignUp'))
      ->newInstanceWithoutConstructor();

    $needsProperty = new ReflectionProperty('CRM_Volunteer_Form_VolunteerSignUp', '_needs');
    $needsProperty->setAccessible(TRUE);
    $needsProperty->setValue($form, $needs);

    $projectsProperty = new ReflectionProperty('CRM_Volunteer_Form_VolunteerSignUp', '_projects');
    $projectsProperty->setAccessible(TRUE);
    $projectsProperty->setValue($form, array($projectId => array('profiles' => $profilesPayload)));

    $form->controller = new class($submittedValues) {
      private array $values;

      public function __construct(array $values) {
        $this->values = $values;
      }

      public function exportValues($name = NULL) {
        return $this->values;
      }
    };

    // The public signup path is anonymous: postProcess() keys its dedupe on
    // the absence of a session contact. The harness logs a contact in, so
    // emulate the anonymous visitor the screen actually serves.
    $_SESSION['CiviCRM']['userID'] = NULL;

    return $form;
  }

  /**
   * Drive the public form through its real preProcess/build/validation path.
   */
  private function buildLifecycleSignupForm(int $needId, array $submittedValues): CRM_Volunteer_Form_VolunteerSignUp {
    $_REQUEST['needs'] = $_GET['needs'] = (string) $needId;
    $_SESSION['CiviCRM']['userID'] = NULL;
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'register to volunteer',
      'profile create',
    );

    $form = new CRM_Volunteer_Form_VolunteerSignUp();
    $form->controller = new class($submittedValues) {
      private array $values;
      private array $scope = array();

      public function __construct(array $values) {
        $this->values = $values;
      }

      public function exportValues($name = NULL) {
        return $this->values;
      }

      public function set($name, $value) {
        $this->scope[$name] = $value;
      }

      public function get($name) {
        return $this->scope[$name] ?? NULL;
      }
    };

    $form->preProcess();
    $form->buildQuickForm();
    $submitValues = new ReflectionProperty('HTML_QuickForm', '_submitValues');
    $submitValues->setAccessible(TRUE);
    $submitValues->setValue($form, $submittedValues);
    return $form;
  }

  private function signupSubmission(array $overrides = array()): array {
    return $overrides + array(
      'first_name' => 'Ann',
      'last_name' => 'Argyle',
      'email-Primary' => 'ann.argyle@example.org',
    );
  }

  /**
   * The anonymous write path end-to-end: a contact is created from the
   * profile data and a Scheduled assignment lands on the selected need.
   */
  public function testPostProcessCreatesAssignmentForAnonymousSignup(): void {
    list($projectId, $needId, $profiles) = $this->buildSignupProject();
    $form = $this->buildLifecycleSignupForm($needId, $this->signupSubmission());

    $this->assertTrue($form->validate(), 'The complete anonymous submission must pass the real QuickForm validation path.');
    CRM_Core_Session::singleton()->getStatus(TRUE);
    $form->postProcess();

    $contact = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', 'first_name', 'last_name')
      ->addWhere('first_name', '=', 'Ann')
      ->addWhere('last_name', '=', 'Argyle')
      ->execute()
      ->single();

    $assignment = \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addSelect('status_id', 'time_scheduled_minutes', 'volunteer_need_id')
      ->addWhere('assignee_contact_id', '=', $contact['id'])
      ->addWhere('volunteer_need_id', '=', $needId)
      ->execute()
      ->single();
    $this->assertSame($this->getOptionValue('activity_status', 'Scheduled'), (int) $assignment['status_id']);
    $this->assertSame(90, (int) $assignment['time_scheduled_minutes']);

    $statuses = CRM_Core_Session::singleton()->getStatus(TRUE);
    $successStatuses = array_values(array_filter($statuses, static function(array $status): bool {
      return $status['type'] === 'success';
    }));
    $this->assertSame(
      array('You are scheduled to volunteer. Thank you!'),
      array_column($successStatuses, 'text'),
      'A successful signup must set its own scheduled status message.'
    );
  }

  /**
   * Submitted profile data matching an existing contact dedupes onto it
   * instead of creating a second record.
   */
  public function testPostProcessDedupesSubmittedContactData(): void {
    list($projectId, $needId, $profiles) = $this->buildSignupProject();

    $existing = \Civi\Api4\Contact::create(FALSE)
      ->setValues(array(
        'contact_type' => 'Individual',
        'first_name' => 'Ann',
        'last_name' => 'Argyle',
      ))
      ->execute()
      ->single();
    \Civi\Api4\Email::create(FALSE)
      ->addValue('contact_id', $existing['id'])
      ->addValue('email', 'ann.argyle@example.org')
      ->addValue('location_type_id:name', 'Main')
      ->execute();

    $form = $this->buildSubmittedSignupForm($projectId, $needId, $profiles, $this->signupSubmission());
    $form->postProcess();

    $duplicates = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('first_name', '=', 'Ann')
      ->addWhere('last_name', '=', 'Argyle')
      ->execute();
    $this->assertCount(1, $duplicates, 'A matching submission must dedupe onto the existing contact.');

    $assignment = \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addSelect('id')
      ->addWhere('assignee_contact_id', '=', $existing['id'])
      ->addWhere('volunteer_need_id', '=', $needId)
      ->execute();
    $this->assertCount(1, $assignment, 'The assignment must land on the deduplicated contact.');
  }

  /**
   * Additional volunteers fan out: each submitted companion creates a
   * contact and an assignment, and the capacity gate counts them all.
   */
  public function testPostProcessFansOutAdditionalVolunteers(): void {
    list($primaryProfileId, $primaryModuleData) = $this->createSignupProfile('primary');
    list($additionalProfileId, $additionalModuleData) = $this->createSignupProfile('additional');
    $project = $this->createProject(array(
      'title' => 'Fan-out project',
      'is_active' => 1,
      'profiles' => array(
        array('uf_group_id' => $primaryProfileId, 'module_data' => $primaryModuleData),
        array('uf_group_id' => $additionalProfileId, 'module_data' => $additionalModuleData),
      ),
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-18 10:00:00',
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 5,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));
    $profilesPayload = array(
      array('uf_group_id' => $primaryProfileId, 'module_data' => $primaryModuleData),
      array('uf_group_id' => $additionalProfileId, 'module_data' => $additionalModuleData),
    );

    $form = $this->buildSubmittedSignupForm((int) $project['id'], (int) $need['id'], $profilesPayload, $this->signupSubmission(array(
      'bringingAdditionalVolunteers' => '1',
      'additionalVolunteerQuantity' => '2',
      'additionalVolunteers_0' => array(
        'first_name' => 'Bea',
        'last_name' => 'Bringalong',
        'email-Primary' => 'bea@example.org',
      ),
      'additionalVolunteers_1' => array(
        'first_name' => 'Carl',
        'last_name' => 'Companion',
        'email-Primary' => 'carl@example.org',
      ),
    )));

    $form->postProcess();

    $assignees = \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addSelect('assignee_contact_id')
      ->addWhere('volunteer_need_id', '=', $need['id'])
      ->execute()
      ->column('assignee_contact_id');
    $this->assertCount(3, $assignees, 'The primary and both additional volunteers must be assigned.');

    foreach (array('Bea' => 'Bringalong', 'Carl' => 'Companion') as $first => $last) {
      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id')
        ->addWhere('first_name', '=', $first)
        ->addWhere('last_name', '=', $last)
        ->execute()
        ->single();
      $this->assertContains((int) $contact['id'], array_map('intval', $assignees));
    }
  }

  /**
   * The capacity gate at write time: a submission against a full shift is
   * refused as a whole, with nothing partially saved.
   */
  public function testPostProcessRejectsOversubscriptionAtWriteTime(): void {
    list($projectId, $needId, $profiles) = $this->buildSignupProject(array());
    // Fill the single place before submitting.
    \Civi\Api4\VolunteerNeed::update(FALSE)
      ->addWhere('id', '=', $needId)
      ->addValue('quantity', 1)
      ->execute();
    $this->createAssignment(array(
      'volunteer_need_id' => $needId,
      'assignee_contact_id' => $this->individualCreate(),
      'source_contact_id' => $this->getMockedContactId(),
    ));

    $form = $this->buildSubmittedSignupForm($projectId, $needId, $profiles, $this->signupSubmission());

    try {
      $form->postProcess();
      $this->fail('A signup against a full shift must be refused.');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('could not be completed', $e->getMessage());
    }

    $this->assertCount(
      0,
      \Civi\Api4\Contact::get(FALSE)
        ->addWhere('first_name', '=', 'Ann')
        ->addWhere('last_name', '=', 'Argyle')
        ->execute(),
      'The refused signup must not have created its contact.'
    );
    $assignments = \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addWhere('volunteer_need_id', '=', $needId)
      ->execute();
    $this->assertCount(1, $assignments, 'Only the pre-existing assignment may remain.');
  }

  /**
   * A public flexible need signs the volunteer up as Available (general
   * availability) rather than Scheduled.
   */
  public function testPostProcessMarksFlexibleSignupsAvailable(): void {
    list($projectId, , $profiles) = $this->buildSignupProject();
    $flexibleNeedId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId);
    \Civi\Api4\VolunteerNeed::update(FALSE)
      ->addWhere('id', '=', $flexibleNeedId)
      ->addValue('visibility_id', $this->getOptionValue('visibility', 'public'))
      ->addValue('is_active', 1)
      ->execute();

    $form = $this->buildSubmittedSignupForm($projectId, $flexibleNeedId, $profiles, $this->signupSubmission());
    $form->postProcess();

    $assignment = \Civi\Api4\VolunteerAssignment::get(FALSE)
      ->addSelect('status_id')
      ->addWhere('volunteer_need_id', '=', $flexibleNeedId)
      ->execute()
      ->single();
    $this->assertSame($this->getOptionValue('activity_status', 'Available'), (int) $assignment['status_id']);
  }

}
