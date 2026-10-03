<?php

class CRM_Volunteer_Permission {

  const VIEW_ROSTER = 'volunteer_view_roster'; // A number unused by CRM_Core_Action

  /**
   * Nesting depth for trusted server-side permission bypasses.
   *
   * A request parameter alone must never disable domain authorization. Internal
   * aggregate code can enter this scope only after checking the outer action.
   *
   * @var int
   */
  private static $internalBypassDepth = 0;

  /**
   * Returns an array of permissions defined by this extension. Modeled off of
   * CRM_Core_Permission::getCorePermissions().
   *
   * @return array Keyed by machine names with human-readable labels for values
   */
  public static function getVolunteerPermissions() {
    $domain = array('domain' => 'org.civicrm.volunteer');
    $prefix = ts('CiviVolunteer', $domain) . ': ';
    return array(
      'register to volunteer' => array(
        'label' => $prefix . ts('register to volunteer', $domain),
        'description' => ts('Access public-facing volunteer opportunity listings and registration forms', $domain),
      ),
      'log own hours' => array(
        'label' => $prefix . ts('log own hours', $domain),
        'description' => ts('Access forms to self-report performed volunteer hours', $domain),
      ),
      'create volunteer projects' => array(
        'label' => $prefix . ts('create volunteer projects', $domain),
        'description' => ts('Create a new volunteer project record in CiviCRM', $domain),
      ),
      'edit own volunteer projects' => array(
        'label' => $prefix . ts('edit own volunteer projects', $domain),
        'description' => ts('Edit volunteer project records for which the user is specified as the Owner', $domain),
      ),
      'edit all volunteer projects' => array(
        'label' => $prefix . ts('edit all volunteer projects', $domain),
        'description' => ts('Edit all volunteer project records, regardless of ownership', $domain),
      ),
      'delete own volunteer projects' => array(
        'label' => $prefix . ts('delete own volunteer projects', $domain),
        'description' => ts('Delete volunteer project records for which the user is specified as the Owner', $domain),
      ),
      'delete all volunteer projects' => array(
        'label' => $prefix . ts('delete all volunteer projects', $domain),
        'description' => ts('Delete any volunteer project record, regardless of ownership', $domain),
      ),
      'edit volunteer project relationships' => array(
        'label' => $prefix . ts('edit volunteer project relationships', $domain),
        'description' => ts('Override system-wide default project relationships for a particular volunteer project', $domain),
      ),
      'edit volunteer registration profiles' => array(
        'label' => $prefix . ts('edit volunteer registration profiles', $domain),
        'description' => ts('Override system-wide default registration profiles for a particular volunteer project', $domain),
      ),
    );
  }

  /**
   * Given a permission string or array, check for access requirements.
   *
   * @param mixed $permissions
   *   The permission(s) to check as an array or string. See parent class for examples.
   * @return boolean
   */
  public static function check($permissions) {
    $permissions = (array) $permissions;

    $permClass = CRM_Core_Config::singleton()->userPermissionClass;
    $skipCheck = !$permClass->isModulePermissionSupported() && !is_a($permClass, 'CRM_Core_Permission_UnitTests');

    // Both of these were recomputed once per walked element: the permission map
    // rebuilds nine entries with nineteen ts() calls, and the edit-all check
    // re-entered this method. Resolve each at most once per call instead. Note
    // the edit-all lookup must not itself recurse through the rewriting walk.
    $volunteerPermissions = $skipCheck ? self::getVolunteerPermissions() : array();
    $hasEditAll = NULL;

    array_walk_recursive($permissions, function(&$v, $k) use ($skipCheck, $volunteerPermissions, &$hasEditAll) {
      // For VOL-71, if this is a permissions-challenged Joomla instance, don't
      // enforce CiviVolunteer-defined permissions.
      if ($skipCheck && array_key_exists($v, $volunteerPermissions)) {
        $v = CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION;
        return;
      }

      // Ensure that checks for "edit own" pass if user has "edit all."
      if ($v === 'edit own volunteer projects') {
        if ($hasEditAll === NULL) {
          $hasEditAll = CRM_Core_Permission::check('edit all volunteer projects');
        }
        if ($hasEditAll) {
          $v = CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION;
        }
      }
    });

    return CRM_Core_Permission::check($permissions);
  }

  /**
   * Checks whether the logged in user has permission to perform an action
   * against a specified project.
   *
   * @param int $op
   *   See the constants in CRM_Core_Action and CRM_Volunteer_Page_Roster.
   * @param int $projectId
   *   Required for some but not all operations.
   * @return boolean
   *   TRUE is the action is allowed; else FALSE.
   */
  /**
   * Request-scoped cache of project contacts, keyed "projectId:relationship".
   *
   * checkProjectPerms() is called repeatedly while rendering a project list or
   * authorizing a batch, and each call issued a fresh SELECT. Project
   * relationships cannot change midway through a permission decision, so cache
   * them for the request and invalidate on write.
   *
   * @var array
   */
  private static $projectContactCache = array();

  /**
   * Forget cached project relationships.
   *
   * Called after a project-contact write so a later check in the same request
   * cannot answer from a stale list.
   *
   * @param int|null $projectId
   *   Limit to one project, or NULL to clear everything.
   */
  public static function flushProjectContactCache($projectId = NULL) {
    if ($projectId === NULL) {
      self::$projectContactCache = array();
      return;
    }
    $prefix = (int) $projectId . ':';
    foreach (array_keys(self::$projectContactCache) as $key) {
      if (strpos($key, $prefix) === 0) {
        unset(self::$projectContactCache[$key]);
      }
    }
  }

  /**
   * Project contacts for a relationship, cached for the request.
   *
   * @param int $projectId
   * @param string $relationship
   *
   * @return int[]
   */
  private static function getCachedProjectContacts($projectId, $relationship) {
    $key = (int) $projectId . ':' . $relationship;
    if (!array_key_exists($key, self::$projectContactCache)) {
      self::$projectContactCache[$key] = CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, $relationship);
    }
    return self::$projectContactCache[$key];
  }

  public static function checkProjectPerms($op, $projectId = NULL) {
    $opsRequiringProjectId = array(CRM_Core_Action::UPDATE, CRM_Core_Action::DELETE, self::VIEW_ROSTER,);
    if (in_array($op, $opsRequiringProjectId) && empty($projectId)) {
      return FALSE;
    }

    $contactId = CRM_Core_Session::getLoggedInContactID();

    switch ($op) {
      case CRM_Core_Action::ADD:
        return self::check('create volunteer projects');

      case CRM_Core_Action::UPDATE:
        if (self::check('edit all volunteer projects')) {
          return TRUE;
        }

        $projectOwners = self::getCachedProjectContacts($projectId, 'volunteer_owner');
        if ($contactId && self::check('edit own volunteer projects')
          && in_array((int) $contactId, $projectOwners, TRUE)) {
          return TRUE;
        }
        break;
      case CRM_Core_Action::DELETE:
        if (self::check('delete all volunteer projects')) {
          return TRUE;
        }

        $projectOwners = self::getCachedProjectContacts($projectId, 'volunteer_owner');
        if ($contactId && self::check('delete own volunteer projects')
          && in_array((int) $contactId, $projectOwners, TRUE)) {
          return TRUE;
        }
        break;
      case CRM_Core_Action::VIEW:
        if (self::check('register to volunteer') || self::check('edit all volunteer projects')) {
          return TRUE;
        }
        break;
      case self::VIEW_ROSTER:
        // Anyone who may update the project can see its roster. Previously this
        // accepted only edit-all or a project manager, so a project *owner*
        // holding 'edit own volunteer projects' could define opportunities,
        // assign volunteers and log hours for a project but was refused its
        // roster -- while the manage grid offered them the link regardless.
        if (self::checkProjectPerms(CRM_Core_Action::UPDATE, $projectId)) {
          return TRUE;
        }

        $projectManagers = self::getCachedProjectContacts($projectId, 'volunteer_manager');
        if ($contactId && in_array((int) $contactId, $projectManagers, TRUE)) {
          return TRUE;
        }
        break;
    }

    return FALSE;
  }

  /**
   * Determine whether an API/BAO invocation should enforce permissions.
   *
   * Permission checking is on by default. A false request parameter is ignored
   * unless trusted server-side code has entered withInternalBypass() after an
   * equivalent authorization check at the aggregate boundary.
   *
   * @param array $params
   *   API or BAO parameters.
   *
   * @return bool
   */
  public static function shouldCheckPermissions(array $params) {
    if (!array_key_exists('check_permissions', $params)) {
      return TRUE;
    }

    $explicitlyDisabled = in_array($params['check_permissions'], array(FALSE, 0, '0'), TRUE);
    return !$explicitlyDisabled || self::$internalBypassDepth === 0;
  }

  /**
   * Run trusted aggregate work with explicit check_permissions=FALSE enabled.
   *
   * The scope is process-local, exception-safe, and cannot be activated by an
   * API request parameter. Callers must authorize the outer operation first.
   *
   * @return mixed
   */
  public static function withInternalBypass(callable $callback) {
    self::$internalBypassDepth++;
    try {
      return $callback();
    }
    finally {
      self::$internalBypassDepth--;
    }
  }

  /**
   * Run an API4 write through the guarded domain services.
   *
   * API4 expresses "trusted caller" as checkPermissions(FALSE), but this
   * extension deliberately ignores a request-supplied check_permissions=FALSE
   * (see shouldCheckPermissions()). Copying the flag into the value set
   * therefore had the opposite of the intended effect: cron, CLI, queue and
   * Afform callers passing FALSE still hit a full permission check and failed,
   * because there is no logged-in contact in those contexts.
   *
   * Honouring the contract means entering the trusted scope explicitly. Both
   * halves are still required -- the scope *and* an explicit FALSE -- so that
   * unrelated code reached while the scope is open does not silently inherit
   * the bypass.
   *
   * @param bool $checkPermissions
   *   The API4 action's checkPermissions setting.
   * @param callable $callback
   *   Receives the parameters to merge into the write ($permissionParams).
   *
   * @return mixed
   */
  public static function runApi4Write($checkPermissions, callable $callback) {
    if ($checkPermissions) {
      return $callback(array());
    }
    return self::withInternalBypass(function() use ($callback) {
      return $callback(array('check_permissions' => FALSE));
    });
  }

  /**
   * Determine whether trusted aggregate code has enabled the internal scope.
   *
   * This is intended for sanitizing API parameters at public boundaries. It
   * does not itself bypass any permission check.
   *
   * @return bool
   */
  public static function isInternalBypassActive() {
    return self::$internalBypassDepth > 0;
  }

  /**
   * Remove APIv3 chained-action parameters from an untrusted public request.
   *
   * A chained core API action can include its own check_permissions=FALSE.
   * Public extension APIs therefore must not pass arbitrary chains through to
   * the API framework after applying only project-level authorization.
   *
   * @param array $params
   *   API parameters.
   *
   * @return array
   *   Parameters with all api.* action keys removed.
   */
  public static function stripChainedApiParams(array $params) {
    foreach (array_keys($params) as $key) {
      if (is_string($key) && strncmp($key, 'api.', 4) === 0) {
        unset($params[$key]);
      }
    }
    return $params;
  }

  /**
   * Preserve authenticated APIv3 chains while forcing their permission checks.
   *
   * Manager interfaces use a small number of legitimate chains. They may be
   * retained, but an external caller must not smuggle a permission bypass into
   * a nested custom or core action.
   *
   * @param array $params
   *   API parameters.
   *
   * @return array
   */
  public static function enforceChainedApiPermissions(array $params) {
    foreach ($params as $key => &$value) {
      if (is_string($key) && strncmp($key, 'api.', 4) === 0 && is_array($value)) {
        self::forceNestedPermissionChecks($value);
      }
    }
    unset($value);
    return $params;
  }

  /**
   * Recursively force permission checks in one APIv3 chained action.
   */
  private static function forceNestedPermissionChecks(array &$params): void {
    foreach ($params as $key => &$value) {
      if ($key === 'check_permissions' || $key === 'checkPermissions') {
        $value = TRUE;
      }
      elseif (is_array($value)) {
        self::forceNestedPermissionChecks($value);
      }
    }
    unset($value);
  }

  /**
   * Exhaustively enumerate the record IDs an API parameter asks for.
   *
   * APIv3 accepts a scalar, a comma-separated string, or an operator array
   * such as ['IN' => [1, 2]]. Authorization must see *every* requested ID, not
   * just the first one, and core's CRM_Core_DAO::getFieldValue() raises a
   * TypeError when handed an array, so callers must normalize before deriving
   * the owning project.
   *
   * @param mixed $value
   *   Raw API parameter value.
   *
   * @return int[]|null
   *   The complete set of requested IDs, or NULL when the filter cannot be
   *   enumerated (a range, a negation, a pattern match). NULL means "scope
   *   unknown" and callers must treat it as unscoped rather than guessing.
   */
  public static function extractRequestedIds($value) {
    if ($value === NULL || $value === '' || $value === array()) {
      return array();
    }

    if (is_array($value)) {
      $ids = array();
      foreach ($value as $key => $item) {
        // A string key is an APIv3 operator. Only membership and equality can
        // be enumerated exactly; anything else leaves the scope unknown.
        if (is_string($key) && !in_array(strtoupper($key), array('IN', '='), TRUE)) {
          return NULL;
        }
        $nested = self::extractRequestedIds($item);
        if ($nested === NULL) {
          return NULL;
        }
        $ids = array_merge($ids, $nested);
      }
      return array_values(array_unique($ids));
    }

    if (is_string($value) && strpos($value, ',') !== FALSE) {
      return self::extractRequestedIds(explode(',', $value));
    }

    if (!is_scalar($value) || !CRM_Utils_Type::validate($value, 'Positive', FALSE)) {
      return NULL;
    }
    return array((int) $value);
  }

  /**
   * Distinct project IDs owning the supplied records.
   *
   * @param string $daoName
   *   A CiviVolunteer DAO class with a project_id column.
   * @param int[] $ids
   *   Record IDs, already normalized to positive integers.
   *
   * @return int[]
   */
  public static function projectIdsForRecords($daoName, array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
      return array();
    }
    if (!is_callable(array($daoName, 'getTableName'))) {
      throw new CRM_Core_Exception('Unknown volunteer DAO: ' . $daoName);
    }
    $table = $daoName::getTableName();
    $projectIds = array();
    $dao = CRM_Core_DAO::executeQuery(
      sprintf('SELECT DISTINCT project_id FROM `%s` WHERE id IN (%s)', $table, implode(',', $ids))
    );
    while ($dao->fetch()) {
      if ($dao->project_id !== NULL) {
        $projectIds[] = (int) $dao->project_id;
      }
    }
    return $projectIds;
  }

  /**
   * Determine the project rows available to a permission-checked API4 read.
   *
   * API4's coarse permission map cannot express project ownership or manager
   * relationships. Get actions use this scope to add mandatory SQL clauses
   * before the caller-supplied query is executed.
   *
   * @return array{all: bool, project_ids: int[], public: bool}
   *   `all` grants an unrestricted administrative read. `project_ids` lists
   *   the projects the active contact owns or manages. `public` means the
   *   caller has only public opportunity access and responses must be reduced
   *   to public fields.
   */
  public static function getApi4ProjectReadScope() {
    if (self::check('edit all volunteer projects') || self::check('delete all volunteer projects')) {
      return array('all' => TRUE, 'project_ids' => array(), 'public' => FALSE);
    }

    $contactId = (int) CRM_Core_Session::getLoggedInContactID();
    $relationshipNames = array();
    if ($contactId) {
      // A named project manager may view the project roster even without an
      // edit grant. Give API4 the same relationship-aware read boundary.
      $relationshipNames[] = 'volunteer_manager';
      if (self::check('edit own volunteer projects') || self::check('delete own volunteer projects')) {
        $relationshipNames[] = 'volunteer_owner';
      }
    }

    $projectIds = array();
    if ($relationshipNames) {
      $quotedNames = array();
      $queryParams = array(1 => array($contactId, 'Integer'));
      foreach (array_values(array_unique($relationshipNames)) as $index => $relationshipName) {
        $paramIndex = $index + 2;
        $quotedNames[] = '%' . $paramIndex;
        $queryParams[$paramIndex] = array($relationshipName, 'String');
      }
      $dao = CRM_Core_DAO::executeQuery(
        'SELECT DISTINCT pc.project_id
           FROM civicrm_volunteer_project_contact pc
           INNER JOIN civicrm_option_value ov ON ov.value = pc.relationship_type_id
           INNER JOIN civicrm_option_group og ON og.id = ov.option_group_id
          WHERE pc.contact_id = %1
            AND og.name = \'volunteer_project_relationship\'
            AND ov.name IN (' . implode(', ', $quotedNames) . ')',
        $queryParams
      );
      while ($dao->fetch()) {
        $projectIds[] = (int) $dao->project_id;
      }
    }

    if ($projectIds) {
      return array(
        'all' => FALSE,
        'project_ids' => array_values(array_unique($projectIds)),
        'public' => FALSE,
      );
    }

    if (self::check('register to volunteer')) {
      return array('all' => FALSE, 'project_ids' => array(), 'public' => TRUE);
    }

    throw new CRM_Core_Exception(
      ts('You do not have permission to view volunteer projects.', array('domain' => 'org.civicrm.volunteer')),
      CRM_Core_Exception::UNAUTHORIZED
    );
  }

  /**
   * Assert permission to perform an operation against a project.
   *
   * @param int|string $op
   *   A CRM_Core_Action constant, or self::VIEW_ROSTER.
   * @param int|null $projectId
   *   Project ID, when required for the operation.
   *
   * @throws CRM_Core_Exception
   */
  public static function assertProjectPerms($op, $projectId = NULL) {
    if (!self::checkProjectPerms($op, $projectId)) {
      throw new CRM_Core_Exception(
        ts('You do not have permission to perform this action on the volunteer project.', array('domain' => 'org.civicrm.volunteer')),
        CRM_Core_Exception::UNAUTHORIZED
      );
    }
  }

  /**
   * Access callback for the administrative Angular shells.
   *
   * @return bool
   */
  public static function checkProjectManagement() {
    return self::check('create volunteer projects')
      || self::check('edit own volunteer projects')
      || self::check('edit all volunteer projects');
  }

  /**
   * Project-level authorization behind an event's Volunteer tab.
   *
   * Lifted out of _volunteer_can_manage_event_project() so the tab and the
   * route it links to can share one decision. They previously disagreed: the
   * tab was gated here while civicrm/event/manage/volunteer was still guarded
   * by `access CiviEvent,edit all events` alone.
   *
   * @param int|null $eventId
   * @return bool
   */
  public static function checkEventProjectManagement($eventId) {
    if (!$eventId) {
      return self::checkProjectPerms(CRM_Core_Action::ADD);
    }

    // Event administrators do not necessarily hold any CiviVolunteer grant.
    // In that case the caller should simply omit the tab, not enter a guarded
    // BAO read which would turn an access decision into an exception.
    if (!self::checkProjectManagement()) {
      return FALSE;
    }

    $project = CRM_Volunteer_BAO_Project::getEventProject($eventId);
    return $project
      ? self::checkProjectPerms(CRM_Core_Action::UPDATE, $project->id)
      : self::checkProjectPerms(CRM_Core_Action::ADD);
  }

  /**
   * Access callback for the event Volunteer tab's own route.
   *
   * Reaching civicrm/event/manage/volunteer without the volunteer grant that
   * puts the tab there landed the visitor on a page whose Angular bootstrap
   * (CRM_Volunteer_Angular_Tab_Event::prepareTab()) never ran, so
   * CRM.vars['org.civicrm.volunteer'] was unset and the tab's JavaScript threw.
   *
   * @return bool
   */
  public static function checkEventTabAccess() {
    if (!CRM_Core_Component::isEnabled('CiviEvent')
      || !CRM_Core_Permission::check('access CiviEvent')) {
      return FALSE;
    }

    return self::checkEventProjectManagement(
      CRM_Utils_Request::retrieve('id', 'Positive', NULL, FALSE)
    );
  }

  /**
   * Access callback for the project roster page.
   *
   * @return bool
   */
  public static function checkRosterAccess() {
    $projectId = CRM_Utils_Request::retrieve('project_id', 'Positive', NULL, FALSE);
    return $projectId && self::checkProjectPerms(self::VIEW_ROSTER, $projectId);
  }

}
