<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerUtil;

/**
 * Covers every action on the VolunteerUtil API4 entity.
 *
 * VolunteerUtil carries the UI-support reads the Angular screens
 * need. The implementation lives in CRM_Volunteer_BAO_VolunteerUtil; the
 * deprecated api/v3/VolunteerUtil.php actions are thin adapters over these.
 *
 * @group headless
 */
class api_v4_VolunteerUtilTest extends VolunteerTestAbstract {

  /**
   * Every action named in the entity's permission map exists.
   */
  public function testEveryDeclaredActionIsCallable(): void {
    $permissions = VolunteerUtil::permissions();
    unset($permissions['default']);
    $this->assertNotEmpty($permissions);
    foreach (array_keys($permissions) as $action) {
      $this->assertTrue(
        method_exists('\Civi\Api4\VolunteerUtil', $action),
        "VolunteerUtil::$action() is declared in permissions() but missing."
      );
    }
  }

  public function testGetPermissionsListsTheExtensionPermissions(): void {
    $rows = VolunteerUtil::getPermissions(FALSE)->execute();
    $this->assertNotEmpty($rows);

    $byName = $rows->indexBy('name')->getArrayCopy();
    $this->assertArrayHasKey('register to volunteer', $byName);
    foreach (array('description', 'label', 'name', 'safe_name') as $key) {
      $this->assertArrayHasKey($key, $byName['register to volunteer']);
    }
    // safe_name is what the crmVolPermToClass directive turns into a CSS class.
    $this->assertSame('register_to_volunteer', $byName['register to volunteer']['safe_name']);
  }

  public function testGetProfilesReturnsSelectableProfilesAndManagementFlags(): void {
    $active = $this->createProfile('AAA API4 active profile', TRUE);
    $inactive = $this->createProfile('ZZZ API4 inactive profile', FALSE);

    $bundle = VolunteerUtil::getProfiles(FALSE)->execute()->single();
    foreach (array('profiles', 'can_manage', 'create_url') as $key) {
      $this->assertArrayHasKey($key, $bundle);
    }
    // profiles is a title-sorted list, which is the order the picker renders.
    $listed = array_column($bundle['profiles'], NULL, 'id');
    $this->assertArrayHasKey($active, $listed);
    $this->assertArrayNotHasKey($inactive, $listed);
    $titles = array_column($bundle['profiles'], 'display_title');
    $sorted = $titles;
    usort($sorted, 'strcasecmp');
    $this->assertSame($sorted, $titles, 'Profiles are not sorted by title.');

    // An inactive profile still already assigned to a project must remain
    // selectable, otherwise saving the project would silently drop it.
    $withSelection = VolunteerUtil::getProfiles(FALSE)
      ->setProfileIds(array($inactive))
      ->execute()
      ->single();
    $selected = array_column($withSelection['profiles'], NULL, 'id');
    $this->assertArrayHasKey($inactive, $selected);
    $this->assertStringContainsString('Inactive', $selected[$inactive]['display_title']);

    // A selected profile which no longer exists is reported rather than lost.
    $missing = VolunteerUtil::getProfiles(FALSE)
      ->setProfileIds(array(999999999))
      ->execute()
      ->single();
    $missingRows = array_column($missing['profiles'], NULL, 'id');
    $this->assertArrayHasKey(999999999, $missingRows);
    $this->assertTrue($missingRows[999999999]['is_missing']);
  }

  public function testGetSupportingDataForTheProjectEditor(): void {
    $data = VolunteerUtil::getSupportingData(FALSE)
      ->setController('VolunteerProject')
      ->execute()
      ->single();

    foreach (array(
      'relationship_types',
      'phone_types',
      'volunteer_general_project_settings_help_text',
      'defaults',
      'profile_audience_types',
    ) as $key) {
      $this->assertArrayHasKey($key, $data, "getSupportingData omitted $key.");
    }
    $this->assertNotEmpty($data['relationship_types']);
    $this->assertSame(
      array('primary', 'additional', 'both'),
      array_keys($data['profile_audience_types'])
    );
  }

  public function testGetSupportingDataForTheOpportunityBrowser(): void {
    $data = VolunteerUtil::getSupportingData(FALSE)
      ->setController('VolOppsCtrl')
      ->execute()
      ->single();

    $this->assertArrayHasKey('roles', $data);
    $this->assertArrayHasKey('proximity_available', $data);
    $this->assertIsBool($data['proximity_available']);
    $this->assertArrayHasKey('profile_audience_types', $data);
    // The editor-only payload must not leak into the public browser.
    $this->assertArrayNotHasKey('defaults', $data);
    $this->assertArrayNotHasKey('relationship_types', $data);
  }

  public function testWorkflowSupportingDataProvidesSiteLocalShiftFilterPresets(): void {
    $originalWeekBegins = Civi::settings()->get('weekBegins');
    $originalTimeFunc = getenv('TIME_FUNC');
    putenv('TIME_FUNC=frozen');
    CRM_Utils_Time::setTime('2026-08-19 14:30:00');
    Civi::settings()->set('weekBegins', '1');

    try {
      $data = VolunteerUtil::getSupportingData(FALSE)
        ->setController('VolunteerWorkflow')
        ->execute()
        ->single();
      $this->assertSame(array(
        'now' => '2026-08-19 14:30:00',
        'today' => array('from' => '2026-08-19 00:00:00', 'to' => '2026-08-19 23:59:59'),
        'this_week' => array('from' => '2026-08-17 00:00:00', 'to' => '2026-08-23 23:59:59'),
        'next_week' => array('from' => '2026-08-24 00:00:00', 'to' => '2026-08-30 23:59:59'),
        'last_week' => array('from' => '2026-08-10 00:00:00', 'to' => '2026-08-16 23:59:59'),
      ), $data['shift_filter_presets']);
    }
    finally {
      Civi::settings()->set('weekBegins', $originalWeekBegins);
      CRM_Utils_Time::resetTime();
      if ($originalTimeFunc === FALSE) {
        putenv('TIME_FUNC');
      }
      else {
        putenv('TIME_FUNC=' . $originalTimeFunc);
      }
    }
  }

  public function testGetSupportingDataRejectsAnUnknownController(): void {
    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('Unsupported volunteer interface');
    VolunteerUtil::getSupportingData(FALSE)->setController('NoSuchController')->execute();
  }

  public function testGetSupportingDataRequiresProjectManagementForTheEditor(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'register to volunteer',
    );

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('permission to manage volunteer projects');
    VolunteerUtil::getSupportingData(TRUE)->setController('VolunteerProject')->execute();
  }

  /**
   * getCountries() returns one row per country, so callers can index by ID.
   */
  public function testGetCountriesReturnsRowsWithADefaultFlag(): void {
    $rows = VolunteerUtil::getCountries(FALSE)->execute();
    $this->assertNotEmpty($rows);

    $first = $rows->first();
    foreach (array('id', 'name', 'iso_code', 'is_active', 'is_default') as $key) {
      $this->assertArrayHasKey($key, $first);
    }
    // The former APIv3 shape quoted booleans; templates compare against "1".
    $this->assertContains($first['is_default'], array('0', '1'));

    $byId = $rows->indexBy('id')->getArrayCopy();
    $this->assertSame(array_keys($byId), array_map('intval', array_keys($byId)));
  }

  public function testGetCountriesRefusesCallersWithoutVolunteerAccess(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('permission to access volunteer interfaces');
    VolunteerUtil::getCountries(TRUE)->execute();
  }

  public function testGetCustomFieldsReturnsSearchableVolunteerFields(): void {
    $rows = VolunteerUtil::getCustomFields(FALSE)->execute();
    $this->assertNotEmpty($rows, 'The installer should provide at least one searchable volunteer field.');

    $names = $rows->column('name');
    $this->assertContains('camera_skill_level', $names);

    $cameraField = NULL;
    foreach ($rows as $row) {
      if ($row['name'] === 'camera_skill_level') {
        $cameraField = $row;
      }
    }
    // Callers need the group name to build API4 field expressions
    // (CustomGroupName.field_name) and the option list to render choices.
    $this->assertSame('Volunteer_Information', $cameraField['custom_group_id.name']);
    $this->assertNotEmpty($cameraField['options']);
  }

  /**
   * @param string $title
   * @param bool $isActive
   * @return int
   */
  private function createProfile(string $title, bool $isActive): int {
    $group = \Civi\Api4\UFGroup::create(FALSE)
      ->addValue('title', $title)
      ->addValue('is_active', $isActive)
      ->execute()
      ->single();
    return (int) $group['id'];
  }

}
