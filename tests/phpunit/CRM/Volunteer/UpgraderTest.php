<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * Schema parity between a fresh install and the 2500 upgrade.
 *
 * @group headless
 */
class CRM_Volunteer_UpgraderTest extends VolunteerTestAbstract {

  private const TABLES = array(
    'civicrm_volunteer_project',
    'civicrm_volunteer_project_contact',
    'civicrm_volunteer_need',
  );

  /**
   * Index and foreign-key names present on the extension's tables.
   */
  private function schemaSignature(): array {
    $signature = array();
    foreach (self::TABLES as $table) {
      $indices = array();
      $dao = CRM_Core_DAO::executeQuery("SHOW INDEX FROM `$table`");
      while ($dao->fetch()) {
        $indices[$dao->Key_name][] = $dao->Column_name;
        $indices[$dao->Key_name] = array_values(array_unique($indices[$dao->Key_name]));
      }
      ksort($indices);

      $fks = array();
      $fkDao = CRM_Core_DAO::executeQuery(
        'SELECT CONSTRAINT_NAME AS name, REFERENCED_TABLE_NAME AS ref
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %1
            AND REFERENCED_TABLE_NAME IS NOT NULL',
        array(1 => array($table, 'String'))
      );
      while ($fkDao->fetch()) {
        $fks[$fkDao->name] = $fkDao->ref;
      }
      ksort($fks);

      $signature[$table] = array('indices' => $indices, 'foreign_keys' => $fks);
    }
    return $signature;
  }

  private function createUpgrader(): CRM_Volunteer_Upgrader {
    $upgrader = new CRM_Volunteer_Upgrader();
    $upgrader->init(array(
      'key' => 'org.civicrm.volunteer',
      'files' => 'CRM_Volunteer_Upgrader',
    ));
    $context = new CRM_Queue_TaskContext();
    $context->log = CRM_Core_Error::createDebugLogger();
    $ctxProperty = new ReflectionProperty('CRM_Extension_Upgrader_Base', 'ctx');
    $ctxProperty->setAccessible(TRUE);
    $ctxProperty->setValue($upgrader, $context);
    return $upgrader;
  }

  /**
   * A fresh install already satisfies everything upgrade_2500 adds.
   *
   * The install schema is generated from schema/*.entityType.php while
   * upgrade_2500 applies the same objects by hard-coded name to an existing
   * database. Those are two independent expressions of one schema, so assert
   * that re-running the upgrade against a fresh install is a no-op -- that is
   * what proves the two paths agree, and it also covers the "repeated upgrade"
   * case that had no test.
   */
  public function testUpgrade2500IsIdempotentOnAFreshInstall(): void {
    $before = $this->schemaSignature();

    $upgrader = $this->createUpgrader();

    $this->assertTrue($upgrader->upgrade_2500(), 'upgrade_2500() should report success.');

    $this->assertSame(
      $before,
      $this->schemaSignature(),
      'Re-running upgrade_2500 on a fresh install changed the schema, so the '
      . 'install and upgrade paths disagree.'
    );
  }

  /**
   * The objects upgrade_2500 is responsible for are all present after install.
   */
  public function testFreshInstallHasTheExpectedSchemaObjects(): void {
    $signature = $this->schemaSignature();

    $this->assertArrayHasKey(
      'index_volunteer_project_entity',
      $signature['civicrm_volunteer_project']['indices']
    );
    $this->assertArrayHasKey(
      'index_volunteer_need_search',
      $signature['civicrm_volunteer_need']['indices']
    );
    $this->assertSame(
      array('project_id', 'contact_id', 'relationship_type_id'),
      $signature['civicrm_volunteer_project_contact']['indices']['UI_project_contact_rel'] ?? array(),
      'The unique project-contact index is missing or has the wrong columns.'
    );

    $this->assertSame(
      array(
        'FK_civicrm_volunteer_project_campaign_id' => 'civicrm_campaign',
        'FK_civicrm_volunteer_project_loc_block_id' => 'civicrm_loc_block',
      ),
      $signature['civicrm_volunteer_project']['foreign_keys']
    );
    $this->assertSame(
      array('FK_civicrm_volunteer_need_project_id' => 'civicrm_volunteer_project'),
      $signature['civicrm_volunteer_need']['foreign_keys']
    );
    $this->assertSame(
      array(
        'FK_civicrm_volunteer_project_contact_contact_id' => 'civicrm_contact',
        'FK_civicrm_volunteer_project_contact_project_id' => 'civicrm_volunteer_project',
      ),
      $signature['civicrm_volunteer_project_contact']['foreign_keys']
    );
  }

  /**
   * The unique project-contact index is enforced by the database.
   */
  public function testProjectContactUniquenessIsEnforcedBySchema(): void {
    $project = $this->createProject(array('title' => 'Uniqueness enforcement'));
    $contactId = $this->individualCreate();
    $ownerType = $this->getOptionValue('volunteer_project_relationship', 'volunteer_owner');

    CRM_Core_DAO::executeQuery(
      'INSERT INTO civicrm_volunteer_project_contact (project_id, contact_id, relationship_type_id)
       VALUES (%1, %2, %3)',
      array(
        1 => array($project['id'], 'Integer'),
        2 => array($contactId, 'Integer'),
        3 => array($ownerType, 'Integer'),
      )
    );

    $this->expectException(PEAR_Exception::class);
    CRM_Core_DAO::executeQuery(
      'INSERT INTO civicrm_volunteer_project_contact (project_id, contact_id, relationship_type_id)
       VALUES (%1, %2, %3)',
      array(
        1 => array($project['id'], 'Integer'),
        2 => array($contactId, 'Integer'),
        3 => array($ownerType, 'Integer'),
      )
    );
  }

  /**
   * Simulate the schema objects present at revision 2302 and prove that the
   * 2500 migration restores the canonical fresh-install signature.
   */
  public function testUpgradeFromPre2500SchemaRestoresCanonicalObjects(): void {
    $expected = $this->schemaSignature();

    try {
      $this->stripTwentyFiveSchemaObjects();

      $legacy = $this->schemaSignature();
      $this->assertArrayNotHasKey('index_volunteer_project_entity', $legacy['civicrm_volunteer_project']['indices']);
      $this->assertArrayNotHasKey('index_volunteer_need_search', $legacy['civicrm_volunteer_need']['indices']);
      $this->assertSame(array(), $legacy['civicrm_volunteer_project_contact']['foreign_keys']);

      $this->assertTrue($this->createUpgrader()->upgrade_2500());
      $this->assertSame($expected, $this->schemaSignature());
    }
    finally {
      $this->restoreTwentyFiveSchemaObjects();
    }
  }

  /**
   * SR-004 (security review 2026-08-23): migrateProjectTitles() splices the
   * DB-sourced entity_table value into SQL as a table identifier. A corrupt
   * row must be skipped, not executed.
   */
  public function testMigrateProjectTitlesSkipsCorruptEntityTable(): void {
    $this->quickCleanup(array('civicrm_volunteer_project'));
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_volunteer_project (title, is_active, entity_table, entity_id)
       VALUES (%1, 1, %2, 1)",
      array(
        1 => array('Untouched title', 'String'),
        2 => array('civicrm_event` WHERE 1=1; -- ', 'String'),
      )
    );

    $upgrader = (new ReflectionClass(CRM_Volunteer_Upgrader::class))
      ->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($upgrader, 'migrateProjectTitles');
    $method->setAccessible(TRUE);
    $method->invoke($upgrader);

    $title = CRM_Core_DAO::singleValueQuery(
      'SELECT title FROM civicrm_volunteer_project ORDER BY id DESC LIMIT 1'
    );
    $this->assertSame(
      'Untouched title',
      $title,
      'The corrupt entity_table row must be skipped, leaving the project untouched.'
    );
  }

  /**
   * SR-007 (security review 2026-08-23): the shipped Volunteer Report
   * instance must require the extension's project-administration grant; core
   * treats an empty instance permission as available to every user who can
   * reach the report route.
   */
  public function testVolunteerReportInstanceRequiresProjectAdminPermission(): void {
    // Direct SQL: the source guard forbids APIv3 calls outside the adapter
    // test, and the row's column is exactly what managed reconciliation
    // is responsible for.
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT id, permission FROM civicrm_report_instance WHERE report_id = 'volunteer'"
    );
    $count = 0;
    while ($dao->fetch()) {
      $count++;
      $this->assertSame(
        'edit all volunteer projects',
        $dao->permission,
        "Report instance {$dao->id} must require the project-administration grant."
      );
    }
    $this->assertGreaterThanOrEqual(
      1,
      $count,
      'The managed Volunteer Report instance should exist after installation.'
    );
  }

  /**
   * The steps a 2.4.x database walks on its way to 2.5 are either guarded
   * no-ops or ALTERs that converge on the shipped schema, so re-running them
   * against a current database must be harmless.
   *
   * upgrade_1300/1400/1404/2001 are pre-2.0 steps that operate on schema
   * objects which no longer exist, and 1401/2003 are create-only imports:
   * those are exercised by the fresh-install contract test instead.
   */
  public function testHistoricalUpgradeStepsAreHarmlessOnCurrentSchema(): void {
    $before = $this->schemaSignature();
    $upgrader = $this->createUpgrader();

    foreach (array('upgrade_1403', 'upgrade_2002', 'upgrade_2004', 'upgrade_2005', 'upgrade_2200', 'upgrade_2201', 'upgrade_2202', 'upgrade_2300', 'upgrade_2301', 'upgrade_2302', 'upgrade_2500') as $step) {
      $this->assertTrue(
        $upgrader->$step(),
        "$step must succeed as a no-op on the current schema."
      );
    }

    $this->assertSame($before, $this->schemaSignature(), 'Re-running the guarded steps must not change the schema.');

    // The create-guarded steps must not have manufactured duplicates of the
    // objects they own.
    $this->assertCount(
      1,
      \Civi\Api4\CustomGroup::get(FALSE)->addWhere('name', '=', 'volunteer_commendation')->execute(),
      'upgrade_1403 must not create a second commendation custom group.'
    );
    $this->assertCount(
      0,
      \Civi\Api4\OptionGroup::get(FALSE)->addWhere('name', '=', 'msg_tpl_workflow_volunteer_2')->execute()
    );
  }

  /**
   * What a fresh install owes the rest of the extension: the custom storage
   * the API4 layer reads, the workflow template the signup form sends, and
   * the contact subtype and option groups the BAOs assume exist.
   */
  public function testFreshInstallCreatesCanonicalManagedObjects(): void {
    $activityGroup = \Civi\Api4\CustomGroup::get(FALSE)
      ->addSelect('id', 'table_name')
      ->addWhere('name', '=', 'CiviVolunteer')
      ->addWhere('extends', '=', 'Activity')
      ->execute()
      ->single();
    $fieldNames = \Civi\Api4\CustomField::get(FALSE)
      ->addSelect('name', 'column_name')
      ->addWhere('custom_group_id', '=', $activityGroup['id'])
      ->execute()
      ->column('name');
    foreach (array('Volunteer_Need_Id', 'Time_Scheduled_Minutes', 'Time_Completed_Minutes') as $fieldName) {
      $this->assertContains($fieldName, $fieldNames, "The CiviVolunteer custom group must carry $fieldName.");
    }
    // The hours-report view reads these columns physically.
    $columns = array();
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %1',
      array(1 => array($activityGroup['table_name'], 'String'))
    );
    while ($dao->fetch()) {
      $columns[] = $dao->c;
    }
    $this->assertNotEmpty($columns, 'The CiviVolunteer custom value table must physically exist.');

    $this->assertCount(
      1,
      \Civi\Api4\CustomGroup::get(FALSE)->addWhere('name', '=', 'volunteer_commendation')->execute(),
      'The commendation custom group must exist after install.'
    );

    $this->assertCount(
      1,
      \Civi\Api4\ContactType::get(FALSE)->addWhere('name', '=', 'Volunteer')->execute(),
      'The Volunteer contact subtype must exist after install.'
    );

    $workflow = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('id')
      ->addWhere('option_group_id:name', '=', 'msg_tpl_workflow_volunteer')
      ->addWhere('name', '=', 'volunteer_registration')
      ->execute();
    $this->assertCount(1, $workflow, 'The volunteer_registration workflow must exist exactly once.');
    $templates = \Civi\Api4\MessageTemplate::get(FALSE)
      ->addSelect('id', 'is_default')
      ->addWhere('workflow_id', '=', $workflow->single()['id'])
      ->execute();
    $this->assertCount(1, $templates, 'Exactly one registration template must own the workflow.');
    $this->assertTrue((bool) $templates->single()['is_default']);

    $this->assertCount(
      1,
      \Civi\Api4\OptionGroup::get(FALSE)->addWhere('name', '=', 'skill_level')->execute(),
      'The skill_level option group must exist after install.'
    );
  }

  /**
   * A database at revision 2302 walks the guarded 2.4/2.5 steps to the
   * canonical schema: strip the 2.5 objects exactly as the pre-2500 test
   * does, then run the steps in queue order rather than just 2500.
   */
  public function testUpgradeFromTwentyThreeZeroTwoRunsThrough(): void {
    $expected = $this->schemaSignature();
    try {
      $this->stripTwentyFiveSchemaObjects();

      $upgrader = $this->createUpgrader();
      foreach (array('upgrade_2300', 'upgrade_2301', 'upgrade_2302', 'upgrade_2500') as $step) {
        $this->assertTrue($upgrader->$step(), "$step must succeed on the 2302 schema.");
      }

      $this->assertSame($expected, $this->schemaSignature(), 'The 2302-to-current walk must restore the canonical schema.');
    }
    finally {
      $this->restoreTwentyFiveSchemaObjects();
    }
  }

  /**
   * Drop the indexes and foreign keys upgrade_2500 creates, simulating the
   * schema at revision 2302.
   */
  private function stripTwentyFiveSchemaObjects(): void {
    $foreignKeys = array(
      'civicrm_volunteer_project_contact' => array(
        'FK_civicrm_volunteer_project_contact_project_id',
        'FK_civicrm_volunteer_project_contact_contact_id',
      ),
      'civicrm_volunteer_need' => array('FK_civicrm_volunteer_need_project_id'),
      'civicrm_volunteer_project' => array(
        'FK_civicrm_volunteer_project_campaign_id',
        'FK_civicrm_volunteer_project_loc_block_id',
      ),
    );
    foreach ($foreignKeys as $table => $constraints) {
      foreach ($constraints as $constraint) {
        CRM_Core_DAO::executeQuery("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
      }
    }
    CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_volunteer_project DROP INDEX index_volunteer_project_entity');
    CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_volunteer_need DROP INDEX index_volunteer_need_search');
    CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_volunteer_project_contact DROP INDEX UI_project_contact_rel');
  }

  /**
   * Directly restore the canonical schema after a destructive migration test.
   *
   * This deliberately does not call upgrade_2500(): the test must heal the
   * shared disposable database even when the method under test is what failed.
   */
  private function restoreTwentyFiveSchemaObjects(): void {
    $signature = $this->schemaSignature();
    $indexes = array(
      array('civicrm_volunteer_project_contact', 'UI_project_contact_rel', 'ALTER TABLE civicrm_volunteer_project_contact ADD UNIQUE INDEX UI_project_contact_rel (project_id, contact_id, relationship_type_id)'),
      array('civicrm_volunteer_project', 'index_volunteer_project_entity', 'ALTER TABLE civicrm_volunteer_project ADD INDEX index_volunteer_project_entity (entity_table, entity_id)'),
      array('civicrm_volunteer_need', 'index_volunteer_need_search', 'ALTER TABLE civicrm_volunteer_need ADD INDEX index_volunteer_need_search (is_active, is_flexible, start_time, end_time)'),
    );
    foreach ($indexes as [$table, $name, $sql]) {
      if (!isset($signature[$table]['indices'][$name])) {
        CRM_Core_DAO::executeQuery($sql);
      }
    }

    $signature = $this->schemaSignature();
    $foreignKeys = array(
      array('civicrm_volunteer_project', 'FK_civicrm_volunteer_project_loc_block_id', 'ALTER TABLE civicrm_volunteer_project ADD CONSTRAINT FK_civicrm_volunteer_project_loc_block_id FOREIGN KEY (loc_block_id) REFERENCES civicrm_loc_block (id) ON DELETE SET NULL'),
      array('civicrm_volunteer_project', 'FK_civicrm_volunteer_project_campaign_id', 'ALTER TABLE civicrm_volunteer_project ADD CONSTRAINT FK_civicrm_volunteer_project_campaign_id FOREIGN KEY (campaign_id) REFERENCES civicrm_campaign (id) ON DELETE SET NULL'),
      array('civicrm_volunteer_need', 'FK_civicrm_volunteer_need_project_id', 'ALTER TABLE civicrm_volunteer_need ADD CONSTRAINT FK_civicrm_volunteer_need_project_id FOREIGN KEY (project_id) REFERENCES civicrm_volunteer_project (id) ON DELETE SET NULL'),
      array('civicrm_volunteer_project_contact', 'FK_civicrm_volunteer_project_contact_project_id', 'ALTER TABLE civicrm_volunteer_project_contact ADD CONSTRAINT FK_civicrm_volunteer_project_contact_project_id FOREIGN KEY (project_id) REFERENCES civicrm_volunteer_project (id) ON DELETE CASCADE'),
      array('civicrm_volunteer_project_contact', 'FK_civicrm_volunteer_project_contact_contact_id', 'ALTER TABLE civicrm_volunteer_project_contact ADD CONSTRAINT FK_civicrm_volunteer_project_contact_contact_id FOREIGN KEY (contact_id) REFERENCES civicrm_contact (id) ON DELETE CASCADE'),
    );
    foreach ($foreignKeys as [$table, $name, $sql]) {
      if (!isset($signature[$table]['foreign_keys'][$name])) {
        CRM_Core_DAO::executeQuery($sql);
      }
    }
  }

}
