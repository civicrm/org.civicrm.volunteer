<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Abstract class for Volunteer tests
 */
abstract class VolunteerTestAbstract extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * Request globals as they existed before the current test.
   *
   * Form and page tests write directly to PHP's request globals. Keep those
   * values test-local so a filtered or randomized run sees the same request
   * context as the normal suite.
   *
   * @var array<string, array>
   */
  private $requestGlobals = array();

  /**
   * The ID of a contact mocked as the acting user.
   *
   * Some volunteer code checks to see who the logged in user is -- e.g., to
   * fetch related contacts or owned projects. Tests will fail if
   * CRM_Core_Session::getLoggedInContactID() doesn't return a valid ID, so we
   * want to make sure to mock this.
   *
   * NOTE: To access this value, use getter getMockedContactId() instead of
   * accessing the property directly.
   *
   * NOTE: We expect that permissions management will be an unrelated process to
   * this mocking.
   *
   * @var int
   */
  protected $mockedContactId;
  protected $mockedContactParams = array(
    'contact_type' => 'Individual',
    'first_name' => 'Logged',
    'last_name' => 'In',
  );

  public function setUpHeadless() {
    $expectedTestDatabase = getenv('CIVICRM_TEST_DB');
    $resolvedTestDatabase = \Civi\Test::dsn('database');
    if ($expectedTestDatabase === FALSE
      || $resolvedTestDatabase !== $expectedTestDatabase
      || !preg_match('/^civivolunteer_(?:test|phpunit)(?:_|$)/i', $resolvedTestDatabase)
    ) {
      throw new \RuntimeException(sprintf(
        "Refusing to initialize CiviCRM database '%s'; expected the approved disposable database '%s'.",
        $resolvedTestDatabase,
        $expectedTestDatabase === FALSE ? '' : $expectedTestDatabase
      ));
    }
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
            ->install('authx')
            ->install('org.civicrm.afform')
            ->install('org.civicrm.search_kit')
            ->installMe(dirname(__DIR__, 2))
            ->callback(function() {
              $mockedContact = \CRM_Core_DAO::createTestObject('CRM_Contact_DAO_Contact', $this->mockedContactParams);
              $this->mockedContactId = $mockedContact->id;
            }, 'mockContact')
            ->callback(function() {
              // This is a hack that we hope to remove once we figure out why most settings
              // are nonexistent following installation.
              $params = array();
              $extSettings = include(__DIR__ . '/../../settings/volunteer.setting.php');
              foreach ($extSettings as $setting) {
                $params[$setting['name']] = $setting['default'];
              }
              CRM_Core_BAO_Setting::setItems($params);
            }, 'importSettings')
            ->callback(function() {
              // Build the database triggers a real install has.
              //
              // Without this the test database has none on the Activity
              // custom-value table, and their absence hid a defect that broke
              // VolunteerNeed.delete on every production site: core maintains an
              // AFTER UPDATE trigger there which writes civicrm_activity, so
              // `UPDATE custom_table ... JOIN civicrm_activity` is MySQL error
              // 1442. A harness missing the triggers cannot see it.
              CRM_Core_DAO::triggerRebuild();
            }, 'triggers')
            // CiviEnvBuilder's extension step signature contains only the
            // extension key. Version the environment explicitly so installer
            // or schema changes invalidate a previously built test database.
            ->callback(function() {}, 'civivolunteer-2.5.0-schema-2500-v12-signup-profile')
            ->apply();
  }

  public function setUp(): void {
    parent::setUp();

    $this->requestGlobals = array(
      'get' => $_GET ?? array(),
      'post' => $_POST ?? array(),
      'request' => $_REQUEST ?? array(),
    );
    $_GET = array();
    $_POST = array();
    $_REQUEST = array();

    // Status messages live in the process-wide CiviCRM session. Without an
    // explicit reset, a test can satisfy an assertNotEmpty() with a notice
    // produced by an unrelated test, and page tests render the whole backlog.
    \CRM_Core_Session::singleton()->getStatus(TRUE);
    // Individual permission tests replace the UnitTests permission list. The
    // CiviCRM permission service is a singleton, so reset it before every test
    // to prevent one test class from changing the outcome of another.
    $permissionClass = \CRM_Core_Config::singleton()->userPermissionClass;
    if ($permissionClass instanceof \CRM_Core_Permission_UnitTests) {
      $permissionClass->permissions = NULL;
    }
    // Re-apply this extension's default settings before every test. They are
    // seeded once at install, but a test that changes one can leak it: anything
    // issuing DDL mid-test commits the transaction, and a leaked
    // volunteer_project_default_campaign pointing at a since-deleted campaign
    // turns every project create into an FK "constraint violation" for the rest
    // of the database's life. Cheap to redo, and it makes the suite independent
    // of whatever an earlier run left behind.
    $extensionDefaults = array();
    foreach (include dirname(__DIR__, 2) . '/settings/volunteer.setting.php' as $setting) {
      $extensionDefaults[$setting['name']] = $setting['default'];
    }
    \CRM_Core_BAO_Setting::setItems($extensionDefaults);

    // Heal the shipped signup profile's name.
    //
    // testProjectCreateSurvivesMissingSignupProfile renames it on purpose and
    // restores it in a finally, but that is not enough: if anything commits the
    // transaction between the two (DDL does), the rename becomes permanent
    // while the restore lands in the *next* transaction and is rolled back. The
    // profile then stays renamed for the life of the database and every later
    // run fails that test's opening assertion. Healing here is idempotent and
    // survives whatever the previous run left behind.
    \CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_uf_group
          SET name = 'volunteer_sign_up'
        WHERE title = 'Volunteer Sign Up' AND name <> 'volunteer_sign_up'"
    );

    // Note on mail: mailing_backend cannot be set from here. The site's
    // civicrm.settings.php, which tests/phpunit/test-settings.php requires,
    // pins it from the CIVI_SMTP_* environment, which makes it a *mandatory*
    // setting that runtime set() silently ignores. With
    // CIVI_SMTP_OUTBOUND_OPTION=1 (sendmail) and no sendmail_path,
    // CRM_Utils_Mail::validOutBoundMail() -- which getSupportingData calls for
    // can_send_email -- raises "Undefined array key \"sendmail_path\"" and
    // PHPUnit promotes it to an error. The runner therefore exports
    // CIVI_SMTP_OUTBOUND_OPTION=2 (disabled); see tests/README.md, "Gotchas".

    // Apparently actions performed in setUpHeadless take place in a different
    // session, so our mocked contact's ID needs to be added into the session
    // before each test.
    \CRM_Core_Session::singleton()->set('userID', $this->getMockedContactId());
  }

  public function tearDown(): void {
    try {
      \CRM_Core_Session::singleton()->getStatus(TRUE);
      if (!empty($this->mockedContactId)) {
        \CRM_Core_Session::singleton()->set('userID', $this->mockedContactId);
      }

      $_GET = $this->requestGlobals['get'] ?? array();
      $_POST = $this->requestGlobals['post'] ?? array();
      $_REQUEST = $this->requestGlobals['request'] ?? array();
    }
    finally {
      parent::tearDown();
    }
  }

  public function getMockedContactId() {
    if (empty($this->mockedContactId)) {
      $where = array();
      foreach ($this->mockedContactParams as $field => $value) {
        $where[] = array($field, '=', $value);
      }
      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id')
        ->setWhere($where)
        ->execute()
        ->first();
      $this->assertNotNull($contact, 'The mocked acting contact is missing.');
      // cast as int for compatibility with \CRM_Core_DAO::createTestObject
      $this->mockedContactId = (int) $contact['id'];
    }

    return $this->mockedContactId;
  }

  /**
   * Resolve an option's stored value without legacy pseudo-constant helpers.
   */
  protected function getOptionValue(string $groupName, string $optionName): int {
    $option = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id.name', '=', $groupName)
      ->addWhere('name', '=', $optionName)
      ->execute()
      ->first();
    $this->assertNotNull($option, "Missing $optionName in $groupName.");
    return (int) $option['value'];
  }

  /**
   * Create an individual contact for extension tests.
   */
  protected function individualCreate(array $values = array()): int {
    static $sequence = 0;
    $sequence++;
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->setValues($values + array(
        'contact_type' => 'Individual',
        'first_name' => 'Volunteer test',
        'last_name' => 'Contact ' . $sequence,
      ))
      ->execute()
      ->first();
    return (int) $contact['id'];
  }

  /**
   * Switch CiviCampaign on for the duration of a test.
   *
   * The test database ships without it, which silently hollows out every
   * campaign assertion: campaign_id on both civicrm_activity and civicrm_event
   * carries 'component' => 'CiviCampaign', so API4 omits the field entirely and
   * \Civi\Api4\Campaign is not loadable at all. Callers must pair this with
   * disableCampaignComponent(); the setting itself rolls back with the test
   * transaction, but the metadata cache is process-global.
   */
  protected function enableCampaignComponent(): void {
    CRM_Core_BAO_ConfigSetting::enableComponent('CiviCampaign');
    \Civi::rebuild(array('metadata' => TRUE))->execute();
  }

  protected function disableCampaignComponent(): void {
    CRM_Core_BAO_ConfigSetting::disableComponent('CiviCampaign');
    \Civi::rebuild(array('metadata' => TRUE))->execute();
  }

  /**
   * Metadata only -- never 'triggers' => TRUE from inside a test.
   *
   * A trigger rebuild issues DDL, and DDL implicitly commits in MySQL, so it
   * tears down the transaction TransactionalInterface relies on and leaves
   * every row the test had created behind in the database. Triggers are built
   * once, in setUpHeadless(), where committing is what we want.
   */

  /**
   * Create a campaign through API4. Requires CiviCampaign to be enabled.
   *
   * Not CRM_Core_DAO::createTestObject('CRM_Campaign_BAO_Campaign'): its
   * generated names collide with each other, and rows do escape this harness
   * -- anything issuing DDL mid-test implicitly commits the transaction
   * TransactionalInterface depends on. A unique name per call survives whatever
   * an earlier run left behind.
   *
   * @param string $title
   * @return int
   */
  protected function createCampaign(string $title): int {
    static $sequence = 0;
    $sequence++;
    $campaign = \Civi\Api4\Campaign::create(FALSE)
      ->addValue('title', $title)
      ->addValue('name', uniqid('vol_test_campaign_', FALSE) . '_' . $sequence)
      ->execute()
      ->single();
    return (int) $campaign['id'];
  }

  /**
   * Create a CiviEvent to hang an event-linked volunteer project off.
   *
   * The extension's whole CiviEvent integration keys off
   * (entity_table, entity_id), and nothing in the suite used to create a real
   * event -- so getEntityAttributes(), the tabset hook and the event-tab
   * bootstrap were never exercised against one.
   *
   * @param array $values
   * @return array
   *   The saved event record.
   */
  protected function createEvent(array $values = array()): array {
    static $sequence = 0;
    $sequence++;
    return \Civi\Api4\Event::create(FALSE)
      ->setValues($values + array(
        'title' => 'Volunteer test event ' . $sequence,
        'event_type_id:name' => 'Fundraiser',
        'start_date' => '2026-12-17 09:00:00',
        'is_active' => TRUE,
        'is_public' => TRUE,
      ))
      ->execute()
      ->single();
  }

  /**
   * Create a volunteer project through the API4 aggregate action.
   *
   * VolunteerProject.commit is the API4 equivalent of the deprecated
   * VolunteerProject.create APIv3 action: it accepts the nested
   * project_contacts, profiles and location payloads.
   *
   * @param array $values
   * @param bool $checkPermissions
   *   TRUE to exercise the guarded path.
   * @return array
   *   The saved project record.
   */
  protected function createProject(array $values = array(), bool $checkPermissions = FALSE): array {
    static $sequence = 0;
    $sequence++;
    return \Civi\Api4\VolunteerProject::commit($checkPermissions)
      ->setValues($values + array('title' => 'Volunteer test project ' . $sequence))
      ->execute()
      ->single();
  }

  /**
   * Create a volunteer need through API4.
   *
   * @param array $values
   * @param bool $checkPermissions
   * @return array
   *   The saved need record.
   */
  protected function createNeed(array $values, bool $checkPermissions = FALSE): array {
    return \Civi\Api4\VolunteerNeed::create($checkPermissions)
      ->setValues($values)
      ->execute()
      ->single();
  }

  /**
   * Create a volunteer assignment through API4.
   *
   * @param array $values
   * @param bool $checkPermissions
   * @return array
   *   The saved assignment record.
   */
  protected function createAssignment(array $values, bool $checkPermissions = FALSE): array {
    return \Civi\Api4\VolunteerAssignment::create($checkPermissions)
      ->setValues($values)
      ->execute()
      ->single();
  }

  /**
   * Transactionally remove rows from a small allow-list of test tables.
   */
  protected function quickCleanup(array $tables): void {
    $allowedTables = array(
      'civicrm_volunteer_need',
      'civicrm_volunteer_project',
      'civicrm_volunteer_project_contact',
    );
    foreach ($tables as $table) {
      if (!in_array($table, $allowedTables, TRUE)) {
        throw new \InvalidArgumentException("Table $table is not allowed in CiviVolunteer test cleanup.");
      }
      \CRM_Core_DAO::executeQuery("DELETE FROM `$table`");
    }
  }

}
