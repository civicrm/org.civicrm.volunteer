<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\Contact;
use Civi\Api4\VolunteerAssignment;
use Civi\Api4\VolunteerCommendation;
use Civi\Api4\VolunteerNeed;
use Civi\Api4\VolunteerProject;
use Civi\Api4\VolunteerProjectContact;

/**
 * API4 and APIv3 compatibility coverage for CiviVolunteer-owned entities.
 *
 * @group headless
 */
class api_v4_VolunteerEntitiesTest extends VolunteerTestAbstract {

  public function testEntityMetadataIsDiscoverable(): void {
    $projectFields = VolunteerProject::getFields(FALSE)->execute()->indexBy('name');
    $needFields = VolunteerNeed::getFields(FALSE)->execute()->indexBy('name');
    $contactFields = VolunteerProjectContact::getFields(FALSE)->execute()->indexBy('name');

    $this->assertArrayHasKey('title', $projectFields);
    $this->assertArrayHasKey('loc_block_id', $projectFields);
    $this->assertArrayHasKey('project_id', $needFields);
    $this->assertSame('VolunteerProject', $needFields['project_id']['fk_entity']);
    $this->assertArrayHasKey('relationship_type_id', $contactFields);
    $this->assertTrue($contactFields['relationship_type_id']['options']);
  }

  /**
   * getActions must work on every entity this extension owns.
   *
   * Civi\Api4\Action\GetActions::getRecords() reflects every *public static*
   * method on an entity class as a candidate action, excluding only
   * `permissions`, `getInfo`, `getEntityName` and `_`-prefixed names. It then
   * has Civi\API\Request::create() invoke the method and calls ->set() on the
   * result, so any public static helper that is not an action factory is a
   * fatal -- "Call to a member function set() on array" -- for every authorized
   * caller introspecting the entity: cv api4, the API Explorer, SearchKit and
   * the afform admin screens. VolunteerHoursReport::getHoursEntryStateOptions()
   * did exactly that.
   *
   * @dataProvider ownedApi4Entities
   */
  public function testGetActionsIsSafeOnOwnedEntities(string $entity): void {
    $actions = civicrm_api4($entity, 'getActions', array(
      'checkPermissions' => FALSE,
      'select' => array('name'),
    ))->column('name');

    $this->assertNotEmpty($actions, "$entity reported no actions at all.");
    $this->assertContains('getFields', $actions);

    // Anything that is not an AbstractAction factory must not be listed.
    foreach ($actions as $action) {
      $request = \Civi\API\Request::create($entity, $action, array('version' => 4));
      $this->assertInstanceOf(
        \Civi\Api4\Generic\AbstractAction::class,
        $request,
        "$entity.$action is reflected as an action but does not build one."
      );
    }
  }

  /**
   * Every API4 entity class this extension ships.
   *
   * @return array<int, array<int, string>>
   */
  public static function ownedApi4Entities(): array {
    $entities = array();
    foreach (glob(dirname(__DIR__, 4) . '/Civi/Api4/*.php') as $file) {
      $entities[] = array(basename($file, '.php'));
    }
    return $entities;
  }

  public function testProjectCrudPreservesApiV3Compatibility(): void {
    $created = VolunteerProject::create(FALSE)
      ->addValue('title', 'API4 project compatibility test')
      ->addValue('description', 'Created through API4')
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    $projectId = (int) $created['id'];
    $this->assertGreaterThan(0, $projectId);
    $this->assertNotNull(CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId));

    // The aggregate action is the API4 equivalent of the deprecated
    // VolunteerProject.create; the DAO update is the field-level one. Both must
    // land on the same record.
    $searched = \Civi\Api4\VolunteerProject::search(FALSE)
      ->setFilters(array('id' => $projectId))
      ->execute()
      ->single();
    $this->assertSame('API4 project compatibility test', $searched['title']);

    $updated = VolunteerProject::update(FALSE)
      ->addWhere('id', '=', $projectId)
      ->addValue('title', 'Updated through API4')
      ->execute()
      ->first();
    $this->assertSame('Updated through API4', $updated['title']);

    \Civi\Api4\VolunteerProject::commit(FALSE)
      ->setValues(array(
        'id' => $projectId,
        'description' => 'Updated through commit',
      ))
      ->execute();
    $api4 = VolunteerProject::get(FALSE)
      ->addSelect('id', 'title', 'description')
      ->addWhere('id', '=', $projectId)
      ->execute()
      ->single();
    $this->assertSame('Updated through API4', $api4['title']);
    $this->assertSame('Updated through commit', $api4['description']);

    VolunteerProject::delete(FALSE)
      ->addWhere('id', '=', $projectId)
      ->execute();
    $this->assertCount(
      0,
      VolunteerProject::get(FALSE)->addWhere('id', '=', $projectId)->execute()
    );
  }

  public function testNeedCrudPreservesOwningProjectAndFlexibleNeed(): void {
    $project = VolunteerProject::create(FALSE)
      ->addValue('title', 'API4 need test')
      ->execute()
      ->first();
    $projectId = (int) $project['id'];
    $flexibleNeedId = (int) CRM_Volunteer_BAO_Project::getFlexibleNeedID($projectId);

    $created = VolunteerNeed::create(FALSE)
      ->addValue('project_id', $projectId)
      ->addValue('start_time', date('Y-m-d H:i:s', strtotime('+1 week noon')))
      ->addValue('duration', 90)
      ->addValue('is_flexible', FALSE)
      ->addValue('quantity', 3)
      ->addValue('visibility_id', $this->getOptionValue('visibility', 'public'))
      ->addValue('role_id', $this->getAnyOptionValue('volunteer_role'))
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    $needId = (int) $created['id'];
    $this->assertNotSame($flexibleNeedId, $needId);

    $updated = VolunteerNeed::update(FALSE)
      ->addWhere('id', '=', $needId)
      ->addValue('quantity', 5)
      ->execute()
      ->first();
    $this->assertSame(5, (int) $updated['quantity']);

    $reread = VolunteerNeed::get(FALSE)
      ->addSelect('*')
      ->addWhere('id', '=', $needId)
      ->execute()
      ->single();
    $this->assertSame($projectId, (int) $reread['project_id']);
    $this->assertSame(5, (int) $reread['quantity']);
    // The shared display enrichment is applied to every need read.
    $this->assertArrayHasKey('display_time', $reread);
    $this->assertArrayHasKey('role_label', $reread);

    VolunteerNeed::delete(FALSE)->addWhere('id', '=', $needId)->execute();
    $this->assertCount(0, VolunteerNeed::get(FALSE)->addWhere('id', '=', $needId)->execute());
    $this->assertCount(
      1,
      VolunteerNeed::get(FALSE)->addWhere('id', '=', $flexibleNeedId)->execute()
    );
  }

  public function testProjectContactCrudPreservesApiV3Compatibility(): void {
    $project = VolunteerProject::create(FALSE)
      ->addValue('title', 'API4 project-contact test')
      ->execute()
      ->first();
    $contact = Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'API4')
      ->addValue('last_name', 'Volunteer contact')
      ->execute()
      ->first();
    $ownerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_owner');
    $managerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_manager');

    $created = VolunteerProjectContact::create(FALSE)
      ->addValue('project_id', $project['id'])
      ->addValue('contact_id', $contact['id'])
      ->addValue('relationship_type_id', $ownerTypeId)
      ->execute()
      ->first();
    $projectContactId = (int) $created['id'];

    $reread = VolunteerProjectContact::get(FALSE)
      ->addSelect('project_id', 'relationship_type_id:name')
      ->addWhere('id', '=', $projectContactId)
      ->execute()
      ->single();
    $this->assertSame((int) $project['id'], (int) $reread['project_id']);
    $this->assertSame('volunteer_owner', $reread['relationship_type_id:name']);

    $updated = VolunteerProjectContact::update(FALSE)
      ->addWhere('id', '=', $projectContactId)
      ->addValue('relationship_type_id', $managerTypeId)
      ->execute()
      ->first();
    $this->assertSame((int) $project['id'], (int) $updated['project_id']);
    $this->assertSame((int) $contact['id'], (int) $updated['contact_id']);
    $this->assertSame($managerTypeId, (int) $updated['relationship_type_id']);

    VolunteerProjectContact::delete(FALSE)
      ->addWhere('id', '=', $projectContactId)
      ->execute();
    $this->assertCount(
      0,
      VolunteerProjectContact::get(FALSE)
        ->addWhere('id', '=', $projectContactId)
        ->execute()
    );
  }

  private function getAnyOptionValue(string $groupName): int {
    $option = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id.name', '=', $groupName)
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('weight', 'ASC')
      ->setLimit(1)
      ->execute()
      ->first();
    $this->assertNotNull($option, "No active option values exist in $groupName.");
    return (int) $option['value'];
  }

  /**
   * checkPermissions(FALSE) must actually mean "trusted caller".
   *
   * The extension ignores a request-supplied check_permissions=FALSE by design,
   * and the API4 actions used to express trust by copying that same flag into
   * the value set -- so it was ignored too. Cron, CLI, queue runners and Afform
   * all pass FALSE and have no logged-in contact, so every such write failed
   * the project permission check. The actions now enter the trusted scope
   * explicitly instead.
   */
  public function testTrustedApi4WritesSucceedWithoutAnyPermissions(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array();

    $project = VolunteerProject::create(FALSE)
      ->addValue('title', 'Created by a trusted caller')
      ->execute()
      ->first();
    $this->assertNotEmpty($project['id']);

    $updated = VolunteerProject::update(FALSE)
      ->addWhere('id', '=', $project['id'])
      ->addValue('title', 'Renamed by a trusted caller')
      ->execute()
      ->first();
    $this->assertSame('Renamed by a trusted caller', $updated['title']);

    $need = VolunteerNeed::create(FALSE)
      ->addValue('project_id', $project['id'])
      ->addValue('start_time', '2026-12-17 16:00:00')
      ->addValue('is_flexible', FALSE)
      ->addValue('quantity', 2)
      ->addValue('visibility_id', $this->getOptionValue('visibility', 'public'))
      ->execute()
      ->first();
    $this->assertNotEmpty($need['id']);

    $updatedNeed = VolunteerNeed::update(FALSE)
      ->addWhere('id', '=', $need['id'])
      ->addValue('quantity', 4)
      ->setReload(array('*'))
      ->execute()
      ->single();
    $this->assertSame(4, (int) $updatedNeed['quantity']);
    $this->assertSame((int) $project['id'], (int) $updatedNeed['project_id']);

    $contactId = $this->individualCreate();
    $ownerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_owner');
    $managerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_manager');
    $projectContact = VolunteerProjectContact::create(FALSE)
      ->addValue('project_id', $project['id'])
      ->addValue('contact_id', $contactId)
      ->addValue('relationship_type_id', $ownerTypeId)
      ->execute()
      ->single();
    $updatedContact = VolunteerProjectContact::update(FALSE)
      ->addWhere('id', '=', $projectContact['id'])
      ->addValue('relationship_type_id', $managerTypeId)
      ->setReload(array('*'))
      ->execute()
      ->single();
    $this->assertSame($managerTypeId, (int) $updatedContact['relationship_type_id']);

    VolunteerProjectContact::delete(FALSE)
      ->addWhere('id', '=', $projectContact['id'])
      ->execute();
    VolunteerNeed::delete(FALSE)->addWhere('id', '=', $need['id'])->execute();
    VolunteerProject::delete(FALSE)->addWhere('id', '=', $project['id'])->execute();
  }

  /**
   * The permission-checked path is still refused for an unprivileged caller.
   */
  public function testCheckedApi4WriteIsRefusedWithoutPermissions(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array();

    $this->expectException(CRM_Core_Exception::class);
    VolunteerProject::create(TRUE)
      ->addValue('title', 'Should not be created')
      ->execute();
  }

  /**
   * API4's generic save must not be an unguarded back door.
   *
   * CRM_Volunteer_BAO_ProjectContact had no create()/add(), so
   * DAOActionTrait::write() fell through to CRM_Core_DAO::writeRecords() and
   * wrote rows with no project authorization whenever checkPermissions was
   * FALSE -- which is exactly how the ALWAYS_DENY on save was bypassed.
   */
  public function testGenericSaveCannotBypassProjectAuthorization(): void {
    $project = VolunteerProject::create(FALSE)
      ->addValue('title', 'Save bypass target')
      ->execute()
      ->first();
    $contactId = $this->individualCreate();
    $ownerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_owner');

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array();

    $threw = FALSE;
    try {
      VolunteerProjectContact::save(FALSE)
        ->setRecords(array(array(
          'project_id' => $project['id'],
          'contact_id' => $contactId,
          'relationship_type_id' => $ownerTypeId,
        )))
        ->execute();
    }
    catch (Throwable $e) {
      $threw = TRUE;
    }

    $this->assertTrue($threw, 'Generic save wrote a project contact with no authorization.');
    $this->assertCount(
      0,
      VolunteerProjectContact::get(FALSE)
        ->addWhere('project_id', '=', $project['id'])
        ->addWhere('contact_id', '=', $contactId)
        ->execute(),
      'A project contact row survived the refused save.'
    );
  }

  /**
   * A project owner can delete its own project through API4.
   *
   * The declared read permission gates API4 writes as well, because
   * AbstractBatchAction resolves delete/update targets through a real API4
   * `get`. Declaring only 'edit all volunteer projects' therefore made API4
   * delete unusable for the "delete own projects" role that APIv3 honours.
   */
  public function testProjectOwnerCanDeleteOwnProjectThroughApi4(): void {
    $ownContactId = $this->getMockedContactId();
    $project = VolunteerProject::create(FALSE)
      ->addValue('title', 'Owner deletes this')
      ->execute()
      ->first();
    // BAO_Project::create() already applies the default project contacts, which
    // normally make the acting user the owner. Only add the row if it is absent
    // -- the unique index on (project_id, contact_id, relationship_type_id)
    // rejects a duplicate.
    $ownerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_owner');
    $owners = CRM_Volunteer_BAO_Project::getContactsByRelationship(
      (int) $project['id'],
      'volunteer_owner'
    );
    if (!in_array($ownContactId, $owners, TRUE)) {
      VolunteerProjectContact::create(FALSE)
        ->addValue('project_id', $project['id'])
        ->addValue('contact_id', $ownContactId)
        ->addValue('relationship_type_id', $ownerTypeId)
        ->execute();
    }
    $this->assertContains(
      $ownContactId,
      CRM_Volunteer_BAO_Project::getContactsByRelationship((int) $project['id'], 'volunteer_owner'),
      'Test setup failed to establish ownership.'
    );

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'delete own volunteer projects',
    );

    VolunteerProject::delete(TRUE)
      ->addWhere('id', '=', $project['id'])
      ->execute();

    $this->assertCount(
      0,
      VolunteerProject::get(FALSE)->addWhere('id', '=', $project['id'])->execute()
    );
  }

  /**
   * A project manager can read projects through API4 without the edit-all grant.
   */
  public function testProjectOwnerCanReadThroughApi4(): void {
    $project = VolunteerProject::create(FALSE)
      ->addValue('title', 'Readable by a manager')
      ->execute()
      ->first();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'edit own volunteer projects',
    );

    $read = VolunteerProject::get(TRUE)
      ->addWhere('id', '=', $project['id'])
      ->execute();
    $this->assertCount(1, $read);
  }

  public function testProjectManagerRelationshipCanReadThroughApi4(): void {
    $actingContactId = $this->getMockedContactId();
    $strangerId = $this->individualCreate();
    $project = $this->createProject(array(
      'title' => 'Readable by a named manager',
      'project_contacts' => array(
        'volunteer_owner' => array($strangerId),
        'volunteer_manager' => array($actingContactId),
        'volunteer_beneficiary' => array($strangerId),
      ),
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'register to volunteer',
    );

    $read = VolunteerProject::get(TRUE)
      ->addWhere('id', '=', $project['id'])
      ->execute();
    $this->assertCount(1, $read);
    $this->assertArrayHasKey('entity_table', $read->single(), 'Manager reads should receive the full project record.');
  }

  public function testCheckedApi4ReadsAreScopedToOwnedProjects(): void {
    $actingContactId = $this->getMockedContactId();
    $strangerId = $this->individualCreate();
    $mine = $this->createProject(array(
      'title' => 'Owned project',
      'project_contacts' => array(
        'volunteer_owner' => array($actingContactId),
        'volunteer_manager' => array($strangerId),
        'volunteer_beneficiary' => array($strangerId),
      ),
    ));
    $theirs = $this->createProject(array(
      'title' => 'Unrelated project',
      'project_contacts' => array(
        'volunteer_owner' => array($strangerId),
        'volunteer_manager' => array($strangerId),
        'volunteer_beneficiary' => array($strangerId),
      ),
    ));
    $mineNeed = $this->createNeed(array(
      'project_id' => $mine['id'],
      'start_time' => '2026-12-20 10:00:00',
      'is_flexible' => 0,
      'visibility_id' => $this->getOptionValue('visibility', 'admin'),
    ));
    $theirsNeed = $this->createNeed(array(
      'project_id' => $theirs['id'],
      'start_time' => '2026-12-21 10:00:00',
      'is_flexible' => 0,
      'visibility_id' => $this->getOptionValue('visibility', 'admin'),
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'edit own volunteer projects',
      'view all contacts',
    );

    $storedContacts = VolunteerProjectContact::get(FALSE)
      ->addWhere('project_id', '=', $mine['id'])
      ->execute();
    $this->assertNotEmpty($storedContacts, 'Test setup did not create project-contact rows for the owned project.');
    $readScope = CRM_Volunteer_Permission::getApi4ProjectReadScope();
    $this->assertContains((int) $mine['id'], $readScope['project_ids'], 'Owned project is missing from the API4 read scope.');

    $projects = VolunteerProject::get(TRUE)
      ->addWhere('id', 'IN', array($mine['id'], $theirs['id']))
      ->execute()
      ->column('id');
    $this->assertSame(array((int) $mine['id']), array_map('intval', $projects));

    $needs = VolunteerNeed::get(TRUE)
      ->addWhere('id', 'IN', array($mineNeed['id'], $theirsNeed['id']))
      ->execute()
      ->column('id');
    $this->assertSame(array((int) $mineNeed['id']), array_map('intval', $needs));

    $contacts = VolunteerProjectContact::get(TRUE)->execute();
    $this->assertNotEmpty($contacts);
    $this->assertSame(array((int) $mine['id']), array_values(array_unique(array_map(
      'intval',
      $contacts->column('project_id')
    ))));
  }

  public function testPublicApi4ReadsExposeOnlyActivePublicData(): void {
    $strangerId = $this->individualCreate();
    $contacts = array(
      'volunteer_owner' => array($strangerId),
      'volunteer_manager' => array($strangerId),
      'volunteer_beneficiary' => array($strangerId),
    );
    $active = $this->createProject(array(
      'title' => 'Public API4 project',
      'description' => '<p>Public description</p><script>bad()</script>',
      'is_active' => 1,
      'project_contacts' => $contacts,
    ));
    $inactive = $this->createProject(array(
      'title' => 'Inactive API4 project',
      'is_active' => 0,
      'project_contacts' => $contacts,
    ));
    $publicNeed = $this->createNeed(array(
      'project_id' => $active['id'],
      'start_time' => '2026-12-22 10:00:00',
      'is_flexible' => 0,
      'is_active' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $adminNeed = $this->createNeed(array(
      'project_id' => $active['id'],
      'start_time' => '2026-12-23 10:00:00',
      'is_flexible' => 0,
      'is_active' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'admin'),
    ));
    $inactiveNeed = $this->createNeed(array(
      'project_id' => $inactive['id'],
      'start_time' => '2026-12-24 10:00:00',
      'is_flexible' => 0,
      'is_active' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('register to volunteer');

    $projects = VolunteerProject::get(TRUE)
      ->addWhere('id', 'IN', array($active['id'], $inactive['id']))
      ->execute();
    $this->assertCount(1, $projects);
    $publicProject = $projects->single();
    $this->assertSame((int) $active['id'], (int) $publicProject['id']);
    $this->assertArrayNotHasKey('entity_table', $publicProject);
    $this->assertStringNotContainsString('<script', $publicProject['description']);

    $needs = VolunteerNeed::get(TRUE)
      ->addWhere('id', 'IN', array($publicNeed['id'], $adminNeed['id'], $inactiveNeed['id']))
      ->execute();
    $this->assertSame(array((int) $publicNeed['id']), array_map('intval', $needs->column('id')));
    $this->assertArrayNotHasKey('created', $needs->single());

    $this->assertCount(
      0,
      VolunteerProjectContact::get(TRUE)
        ->addWhere('project_id', '=', $active['id'])
        ->execute()
    );
  }

  /**
   * The permission-checked Need write path refuses an unprivileged caller,
   * and still works for a caller holding the project-edit grant.
   *
   * VolunteerNeed::search's gate is covered in VolunteerNeedSearchTest; this
   * pins the CRUD side, where only the trusted path was ever exercised.
   */
  public function testCheckedNeedWritesRespectProjectPermissions(): void {
    $project = $this->createProject(array('title' => 'Guarded need writes'));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'is_flexible' => 0,
      'quantity' => 3,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');

    $refusedWrites = array(
      'create' => function() use ($project) {
        VolunteerNeed::create(TRUE)
          ->addValue('project_id', $project['id'])
          ->addValue('start_time', '2026-12-18 09:00:00')
          ->addValue('is_flexible', 0)
          ->addValue('quantity', 1)
          ->addValue('visibility_id', $this->getOptionValue('visibility', 'public'))
          ->execute();
      },
      'update' => function() use ($need) {
        VolunteerNeed::update(TRUE)
          ->addWhere('id', '=', $need['id'])
          ->addValue('quantity', 9)
          ->execute();
      },
      'delete' => function() use ($need) {
        VolunteerNeed::delete(TRUE)
          ->addWhere('id', '=', $need['id'])
          ->execute();
      },
    );

    foreach ($refusedWrites as $action => $write) {
      try {
        $write();
        $this->fail("VolunteerNeed::$action(TRUE) succeeded for a caller with only access CiviCRM.");
      }
      catch (CRM_Core_Exception $e) {
        $this->addToAssertionCount(1);
      }
    }

    $needsAfter = VolunteerNeed::get(FALSE)
      ->addSelect('id', 'quantity')
      ->addWhere('project_id', '=', $project['id'])
      ->execute();
    // The project's flexible need plus the fixture need: a refused create
    // must not have added a third row.
    $this->assertCount(2, $needsAfter);
    $fixtureNeed = array_values(array_filter($needsAfter->getArrayCopy(), static function(array $row) use ($need) {
      return (int) $row['id'] === (int) $need['id'];
    }));
    $this->assertSame(3, (int) $fixtureNeed[0]['quantity'], 'A refused update must not have changed the quantity.');

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('edit all volunteer projects');

    VolunteerNeed::create(TRUE)
      ->addValue('project_id', $project['id'])
      ->addValue('start_time', '2026-12-19 09:00:00')
      ->addValue('is_flexible', 0)
      ->addValue('quantity', 2)
      ->addValue('visibility_id', $this->getOptionValue('visibility', 'public'))
      ->execute();
    VolunteerNeed::update(TRUE)
      ->addWhere('id', '=', $need['id'])
      ->addValue('quantity', 5)
      ->execute();
    $this->assertSame(5, (int) VolunteerNeed::get(FALSE)
      ->addSelect('quantity')
      ->addWhere('id', '=', $need['id'])
      ->execute()
      ->single()['quantity']);
  }

  /**
   * Project-contact writes are row-authorized: a caller who satisfies the
   * API4 permission map may still only touch projects in their read scope.
   *
   * The caller holds both map-level grants ('edit own volunteer projects'
   * and 'edit volunteer project relationships'), so refusals below come from
   * row-level authorization: create is asserted inside
   * CRM_Volunteer_BAO_ProjectContact, while update/delete resolve their
   * batch through the scoped VolunteerProjectContact get, which makes a
   * foreign row invisible. Either way the database state must not change.
   */
  public function testProjectContactWritesAreRowAuthorizedAtTheApiBoundary(): void {
    $otherContactId = $this->individualCreate();
    $foreignContactId = $this->individualCreate();
    $mine = $this->createProject(array('title' => 'Own project for contact writes'));
    $theirs = $this->createProject(array(
      'title' => 'Foreign project for contact writes',
      'project_contacts' => array('volunteer_owner' => array($otherContactId)),
    ));
    $managerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_manager');
    $ownerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_owner');

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'edit own volunteer projects',
      'edit volunteer project relationships',
    );

    $own = VolunteerProjectContact::create(TRUE)
      ->addValue('project_id', $mine['id'])
      ->addValue('contact_id', $otherContactId)
      ->addValue('relationship_type_id', $managerTypeId)
      ->execute()
      ->single();
    $this->assertGreaterThan(0, (int) $own['id']);

    $foreignRow = VolunteerProjectContact::create(FALSE)
      ->addValue('project_id', $theirs['id'])
      ->addValue('contact_id', $foreignContactId)
      ->addValue('relationship_type_id', $ownerTypeId)
      ->execute()
      ->single();

    $attemptedWrites = array(
      'create' => function() use ($theirs, $otherContactId, $managerTypeId) {
        VolunteerProjectContact::create(TRUE)
          ->addValue('project_id', $theirs['id'])
          ->addValue('contact_id', $otherContactId)
          ->addValue('relationship_type_id', $managerTypeId)
          ->execute();
      },
      'update' => function() use ($foreignRow, $managerTypeId) {
        VolunteerProjectContact::update(TRUE)
          ->addWhere('id', '=', $foreignRow['id'])
          ->addValue('relationship_type_id', $managerTypeId)
          ->execute();
      },
      'delete' => function() use ($foreignRow) {
        VolunteerProjectContact::delete(TRUE)
          ->addWhere('id', '=', $foreignRow['id'])
          ->execute();
      },
    );

    foreach ($attemptedWrites as $action => $write) {
      try {
        $write();
        // A silent return is acceptable only because the batch resolution of
        // update/delete goes through the scoped get: a foreign row never
        // resolves, so there is nothing to mutate. The state assertions below
        // are what carry the contract.
      }
      catch (CRM_Core_Exception $e) {
        $this->addToAssertionCount(1);
      }
    }

    $rowsOnForeignProject = VolunteerProjectContact::get(FALSE)
      ->addSelect('id', 'contact_id', 'relationship_type_id')
      ->addWhere('project_id', '=', $theirs['id'])
      ->execute();
    // The owner row created with the project plus the foreign fixture row:
    // neither the refused create (a third row) nor the refused delete may
    // have changed anything.
    $this->assertCount(2, $rowsOnForeignProject);
    $survivor = array_values(array_filter($rowsOnForeignProject->getArrayCopy(), static function(array $row) use ($foreignRow) {
      return (int) $row['id'] === (int) $foreignRow['id'];
    }));
    $this->assertCount(1, $survivor, 'The foreign row must survive a refused delete.');
    $this->assertSame((int) $ownerTypeId, (int) $survivor[0]['relationship_type_id'], 'A refused write must not have moved the foreign row.');
    $this->assertSame((int) $foreignContactId, (int) $survivor[0]['contact_id']);
  }

  /**
   * Every action class shipped in Civi/Api4/Action must be named in its
   * entity's permission map. A missing entry is silently downgraded to
   * 'administer CiviCRM' by AbstractAction::getPermissions(), which is why
   * this is asserted rather than left to the individual action tests.
   *
   * @dataProvider customActionEntityProvider
   * @param string $entity
   */
  public function testEveryCustomActionDeclaresPermissions(string $entity): void {
    $entityClass = 'Civi\\Api4\\' . $entity;
    $declared = $entityClass::permissions();

    foreach (glob(dirname(__DIR__, 4) . "/Civi/Api4/Action/$entity/*.php") as $file) {
      $action = lcfirst(basename($file, '.php'));
      $this->assertArrayHasKey(
        $action,
        $declared,
        "$entity::permissions() has no entry for '$action'; it would fall "
        . 'back to administer CiviCRM.'
      );
    }
  }

  /**
   * @return array<int, array<int, string>>
   *   One row per entity that ships custom action classes.
   */
  public static function customActionEntityProvider(): array {
    $entities = array();
    foreach (glob(dirname(__DIR__, 4) . '/Civi/Api4/Action/*', GLOB_ONLYDIR) as $dir) {
      $entities[] = array(basename($dir));
    }
    if (!$entities) {
      throw new RuntimeException('No custom action entities were found; the scan is broken.');
    }
    return $entities;
  }

  /**
   * API4's generic save/replace bypass the aggregate services' own entry
   * points on every entity this extension ships, so every map must deny
   * them. Runtime behaviour is pinned for VolunteerProjectContact in
   * testGenericSaveCannotBypassProjectAuthorization and asserted per entity
   * in the Assignment/Commendation suites; this pins the map itself.
   */
  public function testGenericWriteActionsAreDeniedEverywhere(): void {
    $entities = array(
      'VolunteerProject',
      'VolunteerNeed',
      'VolunteerProjectContact',
      'VolunteerAssignment',
      'VolunteerCommendation',
    );
    foreach ($entities as $entity) {
      $class = 'Civi\\Api4\\' . $entity;
      $permissions = $class::permissions();
      foreach (array('save', 'replace') as $action) {
        $this->assertSame(
          CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
          $permissions[$action],
          "$entity::$action must stay ALWAYS_DENY; the aggregate actions are the guarded entry points."
        );
      }
    }
  }

  /**
   * autocomplete is what SearchKit entity-ref fields and the API Explorer
   * call; it must answer (not fatal) on every owned entity that offers it.
   */
  public function testAutocompleteIsCallableOnOwnedEntities(): void {
    $project = $this->createProject(array('title' => 'Autocomplete fixture project'));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'is_flexible' => 0,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $assignment = VolunteerAssignment::create(FALSE)
      ->addValue('volunteer_need_id', $need['id'])
      ->addValue('assignee_contact_id', $this->individualCreate())
      ->addValue('source_contact_id', $this->getMockedContactId())
      ->execute()
      ->single();
    $commendation = VolunteerCommendation::create(FALSE)
      ->addValue('volunteer_project_id', $project['id'])
      ->addValue('volunteer_contact_id', $this->individualCreate())
      ->execute()
      ->single();

    $expectedIds = array(
      'VolunteerProject' => (int) $project['id'],
      'VolunteerNeed' => (int) $need['id'],
      'VolunteerAssignment' => (int) $assignment['id'],
      'VolunteerCommendation' => (int) $commendation['id'],
    );
    foreach ($expectedIds as $entity => $fixtureId) {
      $class = 'Civi\\Api4\\' . $entity;
      $matches = $class::autocomplete(FALSE)
        ->setInput((string) $fixtureId)
        ->execute();
      $this->assertContains(
        $fixtureId,
        array_map('intval', $matches->column('id')),
        "$entity::autocomplete did not find its own fixture row."
      );
    }
  }

}
