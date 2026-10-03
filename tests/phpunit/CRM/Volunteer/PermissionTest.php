<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * Regression tests for the extension's permission-bypass contract.
 *
 * @group headless
 */
class CRM_Volunteer_PermissionTest extends VolunteerTestAbstract {

  public function testPermissionChecksAreEnabledByDefault(): void {
    $this->assertTrue(CRM_Volunteer_Permission::shouldCheckPermissions(array()));
    $this->assertTrue(CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => TRUE)));
    $this->assertTrue(CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => 'false')));
    $this->assertTrue(CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => NULL)));
  }

  public function testRequestParametersCannotBypassPermissionChecks(): void {
    $this->assertTrue(CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => FALSE)));
    $this->assertTrue(CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => 0)));
    $this->assertTrue(CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => '0')));
  }

  public function testTrustedInternalScopeCanBypassPermissionChecks(): void {
    $result = CRM_Volunteer_Permission::withInternalBypass(function() {
      return array(
        CRM_Volunteer_Permission::isInternalBypassActive(),
        CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => FALSE)),
        CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => 0)),
        CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => '0')),
      );
    });

    $this->assertSame(array(TRUE, FALSE, FALSE, FALSE), $result);
    $this->assertFalse(CRM_Volunteer_Permission::isInternalBypassActive());
    $this->assertTrue(CRM_Volunteer_Permission::shouldCheckPermissions(array('check_permissions' => FALSE)));
  }

  public function testInternalScopeIsResetAfterAnException(): void {
    try {
      CRM_Volunteer_Permission::withInternalBypass(function() {
        throw new RuntimeException('Expected test exception');
      });
      $this->fail('The callback should have thrown an exception.');
    }
    catch (RuntimeException $e) {
      $this->assertSame('Expected test exception', $e->getMessage());
    }

    $this->assertFalse(CRM_Volunteer_Permission::isInternalBypassActive());
  }

  public function testPublicParametersCannotSupplyApiChains(): void {
    $params = array(
      'id' => 7,
      'api.Contact.get' => array('check_permissions' => FALSE),
      'api.Address.getsingle' => array('check_permissions' => FALSE),
      'options' => array('limit' => 0),
    );

    $this->assertSame(array(
      'id' => 7,
      'options' => array('limit' => 0),
    ), CRM_Volunteer_Permission::stripChainedApiParams($params));
  }

  public function testManagerApiChainsCannotDisableNestedPermissions(): void {
    $params = array(
      'id' => 7,
      'api.VolunteerProjectContact.get' => array(
        'check_permissions' => FALSE,
        'api.Contact.get' => array(
          'checkPermissions' => 0,
          'return' => 'display_name',
        ),
      ),
    );

    $sanitized = CRM_Volunteer_Permission::enforceChainedApiPermissions($params);
    $this->assertTrue($sanitized['api.VolunteerProjectContact.get']['check_permissions']);
    $this->assertTrue($sanitized['api.VolunteerProjectContact.get']['api.Contact.get']['checkPermissions']);
    $this->assertSame('display_name', $sanitized['api.VolunteerProjectContact.get']['api.Contact.get']['return']);
  }

  /**
   * APIv3 filters arrive in several shapes; authorization must see them all.
   */
  public function testExtractRequestedIdsEnumeratesEveryShape(): void {
    $this->assertSame(array(), CRM_Volunteer_Permission::extractRequestedIds(NULL));
    $this->assertSame(array(), CRM_Volunteer_Permission::extractRequestedIds(''));
    $this->assertSame(array(), CRM_Volunteer_Permission::extractRequestedIds(array()));
    $this->assertSame(array(7), CRM_Volunteer_Permission::extractRequestedIds(7));
    $this->assertSame(array(7), CRM_Volunteer_Permission::extractRequestedIds('7'));
    $this->assertSame(array(4, 5), CRM_Volunteer_Permission::extractRequestedIds('4,5'));
    $this->assertSame(array(4, 5), CRM_Volunteer_Permission::extractRequestedIds(array(4, 5)));
    $this->assertSame(array(4, 5), CRM_Volunteer_Permission::extractRequestedIds(array('IN' => array(4, 5))));
    $this->assertSame(array(9), CRM_Volunteer_Permission::extractRequestedIds(array('=' => 9)));
  }

  /**
   * A filter that cannot be enumerated must report "scope unknown", not guess.
   *
   * Returning the endpoints of a range would silently under-approximate the
   * set of projects being read, which is exactly how an authorization check
   * gets bypassed.
   */
  public function testExtractRequestedIdsRefusesToGuessUnenumerableFilters(): void {
    $this->assertNull(CRM_Volunteer_Permission::extractRequestedIds(array('BETWEEN' => array(1, 9))));
    $this->assertNull(CRM_Volunteer_Permission::extractRequestedIds(array('>' => 3)));
    $this->assertNull(CRM_Volunteer_Permission::extractRequestedIds(array('NOT IN' => array(1))));
    $this->assertNull(CRM_Volunteer_Permission::extractRequestedIds(array('LIKE' => '%1%')));
    $this->assertNull(CRM_Volunteer_Permission::extractRequestedIds('not-an-id'));
    $this->assertNull(CRM_Volunteer_Permission::extractRequestedIds(0));
    $this->assertNull(CRM_Volunteer_Permission::extractRequestedIds(-3));
  }

  /**
   * Project resolution is batched and ignores unknown rows.
   */
  public function testProjectIdsForRecordsResolvesOwningProjects(): void {
    $projectA = $this->createProject(array('title' => 'Scope A'));
    $projectB = $this->createProject(array('title' => 'Scope B'));
    $needA = $this->createNeed(array(
      'project_id' => $projectA['id'],
      'start_time' => '2026-12-17 16:00:00',
      'is_flexible' => 0,
      'quantity' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));
    $needB = $this->createNeed(array(
      'project_id' => $projectB['id'],
      'start_time' => '2026-12-18 16:00:00',
      'is_flexible' => 0,
      'quantity' => 1,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
    ));

    $resolved = CRM_Volunteer_Permission::projectIdsForRecords(
      'CRM_Volunteer_DAO_Need',
      array($needA['id'], $needB['id'], 999999)
    );
    sort($resolved);
    $expected = array((int) $projectA['id'], (int) $projectB['id']);
    sort($expected);
    $this->assertSame($expected, $resolved);
    $this->assertSame(array(), CRM_Volunteer_Permission::projectIdsForRecords('CRM_Volunteer_DAO_Need', array()));
  }

  /**
   * The project-contact cache must not outlive a relationship change.
   *
   * checkProjectPerms() memoises a project's contacts for the request, so an
   * ownership change made after an earlier check has to invalidate it -- or a
   * later check in the same request answers from a stale list.
   */
  public function testProjectContactCacheIsInvalidatedOnWrite(): void {
    $ownerId = $this->getMockedContactId();
    $project = $this->createProject(array(
      'title' => 'Cache invalidation',
      'project_contacts' => array('volunteer_owner' => array($ownerId)),
    ));
    $projectId = (int) $project['id'];

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );

    // Prime the cache: the acting contact owns the project.
    $this->assertTrue(
      CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $projectId)
    );

    // Hand ownership to somebody else. The trusted (checkPermissions = FALSE)
    // commit is required here: validateCreateParams() would otherwise silently
    // drop project_contacts, because the restricted permission set above lacks
    // 'edit volunteer project relationships'.
    $strangerId = $this->individualCreate();
    \Civi\Api4\VolunteerProject::commit(FALSE)
      ->setValues(array(
        'id' => $projectId,
        'project_contacts' => array('volunteer_owner' => array($strangerId)),
      ))
      ->execute();
    $this->assertSame(
      array($strangerId),
      CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, 'volunteer_owner'),
      'Test setup failed to transfer ownership.'
    );

    $this->assertFalse(
      CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $projectId),
      'A stale project-contact cache kept granting update access after ownership moved.'
    );
  }

  public function testProjectContactCacheIsInvalidatedOnDirectDelete(): void {
    $ownerId = $this->getMockedContactId();
    $project = $this->createProject(array(
      'title' => 'Direct delete cache invalidation',
      'project_contacts' => array('volunteer_owner' => array($ownerId)),
    ));
    $projectId = (int) $project['id'];
    $ownerTypeId = $this->getOptionValue('volunteer_project_relationship', 'volunteer_owner');
    $projectContact = \Civi\Api4\VolunteerProjectContact::get(FALSE)
      ->addWhere('project_id', '=', $projectId)
      ->addWhere('contact_id', '=', $ownerId)
      ->addWhere('relationship_type_id', '=', $ownerTypeId)
      ->execute()
      ->single();

    // 'view all contacts' is required in addition to the volunteer grants:
    // API4's generic delete selects its targets through a permissioned get,
    // and CRM_Core_DAO::addSelectWhereClause() applies contact ACLs to the
    // row's contact_id. The deprecated APIv3 delete looked the row up by ID
    // directly and so never consulted them.
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'view all contacts',
      'edit own volunteer projects',
      'edit volunteer project relationships',
    );
    $this->assertTrue(CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $projectId));

    $deleted = \Civi\Api4\VolunteerProjectContact::delete()
      ->addWhere('id', '=', $projectContact['id'])
      ->execute();
    $this->assertCount(1, $deleted, 'The project-contact row was not deleted.');
    $this->assertSame(
      array(),
      CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, 'volunteer_owner'),
      'Test setup left another owner row in place.'
    );
    $this->assertFalse(CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $projectId));
  }

  public function testNonVolunteerEventAdministratorGetsNoVolunteerTabAccess(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('edit all events');
    $this->assertFalse(_volunteer_can_manage_event_project(999999));
  }

  public function testPublicEventHookReturnsBeforeReadingProjectsWithoutRegistrationPermission(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array();
    $page = new class {
      public function getVar($name) {
        throw new RuntimeException("The event page should not be inspected without registration permission: $name");
      }
    };

    _volunteer_civicrm_pageRun_CRM_Event_Page_EventInfo($page);
    $this->addToAssertionCount(1);
  }

  public function testCoreAuthorizedCallerCanUseExplicitTrustedProjectRead(): void {
    $project = $this->createProject(array('title' => 'Trusted activity label'));
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array();

    $loaded = CRM_Volunteer_Permission::withInternalBypass(function() use ($project) {
      return CRM_Volunteer_BAO_Project::retrieveByID(
        $project['id'],
        array('check_permissions' => FALSE)
      );
    });
    $this->assertSame((int) $project['id'], (int) $loaded->id);
  }

  /**
   * A project owner may view its roster.
   *
   * VIEW_ROSTER previously accepted only edit-all or a project manager, so an
   * owner could define opportunities, assign volunteers and log hours for a
   * project yet be refused its roster -- which the manage grid links to anyway.
   */
  public function testProjectOwnerCanViewRoster(): void {
    $ownerId = $this->getMockedContactId();
    $project = $this->createProject(array(
      'title' => 'Owner roster access',
      'project_contacts' => array('volunteer_owner' => array($ownerId)),
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'create volunteer projects',
      'edit own volunteer projects',
    );

    $this->assertTrue(CRM_Volunteer_Permission::checkProjectPerms(
      CRM_Volunteer_Permission::VIEW_ROSTER,
      (int) $project['id']
    ));
  }

  /**
   * SR-006 (security review 2026-08-23): the alterAPIPermissions deny for
   * APIv3 `setvalue` must hold for ANY permission-checked call, not only the
   * AJAX layer. Direct APIv3 calls are trusted unless they opt in
   * with check_permissions=1 -- with checks on, an edit-own holder must be
   * denied, because setvalue writes through CRM_Core_DAO::setFieldValue()
   * and never reaches the project-scoped BAO authorization.
   */
  public function testApiV3SetValueIsDeniedWhenPermissionsAreChecked(): void {
    $project = $this->createProject(array('title' => 'setvalue deny target'));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'edit own volunteer projects',
    );
    // api.php's wrapper folds errors into exceptions; call the kernel the
    $result = \Civi::service('civi_api_kernel')->runSafe(
      'VolunteerProject',
      'setvalue',
      array(
        'version' => 3,
        'check_permissions' => 1,
        'id' => $project['id'],
        'field' => 'title',
        'value' => 'Should not be written',
      )
    );

    $this->assertNotEmpty($result['is_error'] ?? NULL, 'Expected setvalue to be denied.');
    $this->assertStringContainsString(
      'always deny',
      (string) ($result['error_message'] ?? ''),
      'The deny must come from the alterAPIPermissions map, not a generic failure.'
    );

    $title = CRM_Core_DAO::getFieldValue(
      'CRM_Volunteer_DAO_Project',
      $project['id'],
      'title'
    );
    $this->assertSame(
      'setvalue deny target',
      $title,
      'The denied write must not have landed.'
    );
  }

  /**
   * The API4 read scope is the shared row-authorization boundary behind
   * VolunteerProject/VolunteerNeed/VolunteerProjectContact gets.
   */
  public function testApi4ProjectReadScopeAdminSeesAll(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('edit all volunteer projects');
    $this->assertSame(
      array('all' => TRUE, 'project_ids' => array(), 'public' => FALSE),
      CRM_Volunteer_Permission::getApi4ProjectReadScope()
    );

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('delete all volunteer projects');
    $this->assertSame(
      array('all' => TRUE, 'project_ids' => array(), 'public' => FALSE),
      CRM_Volunteer_Permission::getApi4ProjectReadScope(),
      'The delete-all grant must also unlock the administrative read.'
    );
  }

  public function testApi4ProjectReadScopeOwnerIsScopedToOwnedProjects(): void {
    $ownerId = $this->getMockedContactId();
    $strangerProject = $this->createProject(array(
      'title' => 'Scope stranger project',
      'project_contacts' => array('volunteer_owner' => array($this->individualCreate())),
    ));
    $mine = $this->createProject(array(
      'title' => 'Scope own project',
      'project_contacts' => array('volunteer_owner' => array($ownerId)),
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('edit own volunteer projects');

    $scope = CRM_Volunteer_Permission::getApi4ProjectReadScope();
    $this->assertFalse($scope['all']);
    $this->assertFalse($scope['public']);
    $this->assertSame(array((int) $mine['id']), $scope['project_ids']);
    $this->assertNotContains((int) $strangerProject['id'], $scope['project_ids']);
  }

  /**
   * A named project manager reads a project's rows without holding any
   * edit grant at all.
   */
  public function testApi4ProjectReadScopeManagerRelationshipWithoutEditGrant(): void {
    $actingContactId = $this->getMockedContactId();
    $managed = $this->createProject(array(
      'title' => 'Managed project',
      'project_contacts' => array('volunteer_manager' => array($actingContactId)),
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');

    $scope = CRM_Volunteer_Permission::getApi4ProjectReadScope();
    $this->assertFalse($scope['all']);
    $this->assertSame(array((int) $managed['id']), $scope['project_ids']);
  }

  /**
   * Without an edit-own grant, an owner relationship alone does not widen
  the read scope; only the manager relationship is grant-free.
   */
  public function testApi4ProjectReadScopeOwnerRelationshipNeedsTheEditGrant(): void {
    $ownerId = $this->getMockedContactId();
    $this->createProject(array(
      'title' => 'Owned but unreadable',
      'project_contacts' => array('volunteer_owner' => array($ownerId)),
    ));

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('register to volunteer');

    $scope = CRM_Volunteer_Permission::getApi4ProjectReadScope();
    $this->assertSame(array(), $scope['project_ids'], 'Ownership without the edit grant must not widen the scope.');
    $this->assertTrue($scope['public'], 'The caller falls back to the public read.');
  }

  public function testApi4ProjectReadScopePublicReaderAndStranger(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('register to volunteer');
    $this->assertSame(
      array('all' => FALSE, 'project_ids' => array(), 'public' => TRUE),
      CRM_Volunteer_Permission::getApi4ProjectReadScope()
    );

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('access CiviCRM');
    try {
      CRM_Volunteer_Permission::getApi4ProjectReadScope();
      $this->fail('A caller with no volunteer access must be refused a read scope.');
    }
    catch (CRM_Core_Exception $e) {
      $this->addToAssertionCount(1);
    }
  }

  /**
   * runApi4Write() is the trusted-write bridge: the checked path forwards
   * empty permission params (defaulting the BAO to "check"), the trusted
   * path forwards an explicit bypass.
   */
  public function testRunApi4WriteBridgesPermissionParams(): void {
    $seen = array();
    $bypassState = NULL;

    $result = CRM_Volunteer_Permission::runApi4Write(TRUE, function(array $permissionParams) use (&$seen, &$bypassState) {
      $seen[] = $permissionParams;
      $bypassState = CRM_Volunteer_Permission::isInternalBypassActive();
      return 'checked';
    });
    $this->assertSame('checked', $result);
    $this->assertSame(array(array()), $seen, 'The checked path forwards empty params, so BAO defaults apply.');
    $this->assertFalse($bypassState, 'The checked path must not open the internal bypass.');

    $seen = array();
    $result = CRM_Volunteer_Permission::runApi4Write(FALSE, function(array $permissionParams) use (&$seen, &$bypassState) {
      $seen[] = $permissionParams;
      $bypassState = CRM_Volunteer_Permission::isInternalBypassActive();
      return 'trusted';
    });
    $this->assertSame('trusted', $result);
    $this->assertSame(array(array('check_permissions' => FALSE)), $seen);
    $this->assertTrue($bypassState, 'The trusted path must open the internal bypass for the callback.');
    $this->assertFalse(CRM_Volunteer_Permission::isInternalBypassActive(), 'The bypass must close again after the callback.');
  }

}
