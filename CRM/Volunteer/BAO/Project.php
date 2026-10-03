<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

class CRM_Volunteer_BAO_Project extends CRM_Volunteer_DAO_Project {

  /**
   * Internal retrieve() parameter holding a mandatory project-contact filter.
   *
   * Unlike `project_contacts`, whose clauses are OR-ed together as a
   * user-facing filter, this constraint is AND-ed onto the query and must
   * hold for every returned row. It exists so that an "edit own projects"
   * caller cannot widen its own ownership restriction by supplying additional
   * relationship filters. Never accept it from request input.
   */
  const REQUIRED_CONTACTS_PARAM = '_required_project_contacts';

  /**
   * Internal flag: skip the per-project associated-entity lookup.
   *
   * getEntityAttributes() issues one Civi\Api4\Event::get() per event-linked
   * project. Callers that do not render the associated entity -- the project
   * overview, which dropped that column -- set this to avoid the N+1. Like
   * REQUIRED_CONTACTS_PARAM this is internal state, never request input.
   */
  const SKIP_ENTITY_ATTRIBUTES_PARAM = '_skip_entity_attributes';

  /**
   * Resolved location types, cached per request.
   *
   * @var int|null
   */
  private static $primaryLocationTypeId = NULL;

  private static $secondaryLocationTypeId = NULL;

  /**
   * Array of attributes on the related entity, translated to a common vocabulary.
   *
   * For example, an event's 'start_date' property is standardized to
   * 'start_time.'
   *
   * @see CRM_Volunteer_BAO_Project::getEntityAttributes()
   * @var array
   */
  private $entityAttributes = array();

  /**
   * The ID of the flexible Need for this Project. Accessible via __get method.
   *
   * @var int
   */
  private $flexible_need_id;

  /**
   * Array of associated Needs. Accessible via __get method.
   *
   * @var array
   */
  private $needs = array();

  /**
   * Array of profile IDs associated with the project.
   *
   * TODO: Should this property really be public?
   *
   * @var array
   */
  public $profileIds = array();

  /**
   * Array of associated Roles. Accessible via __get method.
   *
   * @var array Role labels keyed by IDs
   */
  private $roles = array();

  /**
   * Array of open needs. Open means:
   * <ol>
   *   <li>that the number of volunteer assignments associated with the need is
   *    fewer than quantity specified for the need</li>
   *   <li>that the need's start time or end time is in the future</li>
   *   <li>that the need is active</li>
   *   <li>that the need is visible</li>
   *   <li>that the need has a start_time (i.e., is not flexible)</li>
   * </ol>
   * Accessible via __get method.
   *
   * @var array Keyed by Need ID, with a subarray keyed by 'label' and 'role_id'
   */
  private $open_needs = array();

  /**
   * The start_date of the Project, inherited from its associated entity
   *
   * @var string
   * @access public (via __get method)
   */
  private $start_date;

  /**
   * The end_date of the Project, inherited from its associated entity
   *
   * @var string
   * @access public (via __get method)
   */
  private $end_date;


  /**
   * class constructor
   */
  function __construct($params=null) {
    parent::__construct();

    if (!empty($params)) {
      if (is_a($params, 'CRM_Core_DAO')) {
        $daoClone = clone $params; // seems uncessary: lost in the fog of war
        $params = get_object_vars($daoClone); // get an array
      }
      $this->copyValues($params);
    }
  }

  /**
   * Implementation of PHP's magic __get() function.
   *
   * @param string $name The inaccessible property
   * @return mixed Result of fetcher method
   */
  function __get($name) {
    $f = "_get_$name";
    if (method_exists($this, $f)) {
      return $this->$f();
    }
  }

  /**
   * Implementation of PHP's magic __isset() function.
   *
   * @param string $name The inaccessible property
   * @return boolean
   */
  function __isset($name) {
    $result = FALSE;
    $f = "_get_$name";
    if (method_exists($this, $f)) {
      $v = $this->$f();
      $result = !empty($v);
    }
    return $result;
  }

  /**
   * Gets related contacts of a specified type for a project.
   *
   * @param int $projectId
   * @param mixed $relationshipType
   *   Use either the value or the machine name for the optionValue
   * @return array
   *   Array of contact IDs
   */
  public static function getContactsByRelationship($projectId, $relationshipType) {
    $contactIds = array();

    if (!is_numeric($relationshipType)) {
      $relationshipType = CRM_Core_PseudoConstant::getKey(
        'CRM_Volunteer_BAO_ProjectContact',
        'relationship_type_id',
        $relationshipType
      );
    }
    if (!$relationshipType) {
      return $contactIds;
    }

    $dao = CRM_Core_DAO::executeQuery(
      'SELECT contact_id FROM civicrm_volunteer_project_contact WHERE project_id = %1 AND relationship_type_id = %2',
      array(
        1 => array((int) $projectId, 'Integer'),
        2 => array((int) $relationshipType, 'Integer'),
      )
    );
    while ($dao->fetch()) {
      $contactIds[] = (int) $dao->contact_id;
    }

    return $contactIds;
  }

  /**
   * Strips invalid params, throws exception in case of unusable params.
   *
   * @param array $params
   *   Params for self::create().
   * @return array
   *   Filtered params.
   *
   * @throws Exception
   *   Via delegate.
   */
  private static function validateCreateParams(array $params) {
    if (empty($params['id']) && empty($params['title'])) {
      throw new CRM_Core_Exception(ts('A title is required to create a volunteer project.', array('domain' => 'org.civicrm.volunteer')));
    }

    // Only police these when the caller is subject to permission checks at all;
    // trusted server-side code (cron, imports, the aggregate services) must be
    // able to set them.
    //
    // An unauthorized value is dropped rather than rejected, on both create and
    // update. That is the documented APIv3 contract (see
    // api_v3_VolunteerProjectTest::testCoordProjectPerms*) and rejecting would
    // be worse in practice: a coordinator whose client happens to include a
    // profiles array -- the Angular editor strips it, but other clients may not
    // -- would have an otherwise-valid title change refused outright.
    //
    // The defect worth fixing is the *silence*: on create supplyDefaults() then
    // substitutes the system defaults, and on update the request reports success
    // while changing nothing. Both are now logged so the substitution is
    // observable. Turning either into a hard error is a deliberate
    // compatibility break and belongs in a release note, not here.
    if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
      $restricted = array(
        'profiles' => 'edit volunteer registration profiles',
        'project_contacts' => 'edit volunteer project relationships',
      );
      foreach ($restricted as $field => $permission) {
        if (!array_key_exists($field, $params) || CRM_Volunteer_Permission::check($permission)) {
          continue;
        }
        Civi::log()->info(
          'CiviVolunteer discarded "{field}" on project {project}: the caller lacks "{permission}". {consequence}',
          array(
            'field' => $field,
            'project' => empty($params['id']) ? 'create' : (int) $params['id'],
            'permission' => $permission,
            'consequence' => empty($params['id'])
              ? 'System defaults were applied instead.'
              : 'The stored value is unchanged.',
          )
        );
        unset($params[$field]);
      }
    }

    foreach (array('profiles', 'project_contacts', 'location') as $aggregateField) {
      if (isset($params[$aggregateField]) && !is_array($params[$aggregateField])) {
        throw new CRM_Core_Exception(ts('%1 must be supplied as an array.', array(
          1 => $aggregateField,
          'domain' => 'org.civicrm.volunteer',
        )));
      }
    }

    return $params;
  }

  /**
   * Per-request memo for getEventProject(), keyed by event ID.
   *
   * @var array<int, CRM_Volunteer_BAO_Project|null>
   */
  private static $eventProjects = array();

  /**
   * Helper method to supply default values to a new project missing properties.
   *
   * @param array $params
   *   Parameters for project create.
   * @return array
   */
  private static function supplyDefaults(array $params) {
    // Defaults only matter in the case of a create; in the case of an edit we
    // assume replacement params have been passed or that no change is desired.
    if (!empty($params['id'])) {
      return $params;
    }

    $defaults = self::composeDefaultSettingsArray();

    if (!array_key_exists('campaign_id', $params)) {
      $params['campaign_id'] = $defaults['campaign_id'];
    }
    if (!array_key_exists('is_active', $params)) {
      $params['is_active'] = $defaults['is_active'];
    }
    if (!array_key_exists('loc_block_id', $params)) {
      $params['loc_block_id'] = $defaults['loc_block_id'];
    }
    if (!array_key_exists('profiles', $params)) {
      $params['profiles'] = $defaults['profiles'];
    }
    if (!array_key_exists('project_contacts', $params)) {
      $params['project_contacts'] = $defaults['relationships'];
    }

    return $params;
  }

  /**
   * Create a Volunteer Project
   *
   * Takes an associative array and creates a Project object. This method is
   * invoked from the API layer.
   *
   * As a convenience, this method also allows creation of ancillary entities
   * for relating the Project to Contacts and Profiles. Specifying these
   * relationships during project edit is a replacement operation; pre-existing
   * associations will be deleted. For finer-grained control over these
   * relationships, instead use api.VolunteerProjectContact and api.UFJoin,
   * respectively.
   *
   * To associate Contacts with a Project:
   *   $params['project_contacts'] = array(
   *     $relationship_type_name_or_id => $arr_contact_ids,
   *   );
   * To associate Profiles with a Project:
   *   $params['profiles'] = array($paramsToUFJoinCreate1, $paramsToUFJoinCreate2);
   *
   * @param array $params
   *   an assoc array of name/value pairs
   *
   * @return CRM_Volunteer_BAO_Project object
   */
  public static function create(array $params) {
    $projectId = $params['id'] ?? NULL;
    $op = empty($projectId) ? CRM_Core_Action::ADD : CRM_Core_Action::UPDATE;

    if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
      CRM_Volunteer_Permission::assertProjectPerms($op, $projectId);
    }

    $params = self::validateCreateParams($params);
    $params = self::supplyDefaults($params);

    $transaction = CRM_Core_Transaction::create(TRUE);
    try {
      $supersededLocBlockId = NULL;
      if (!empty($params['location'])) {
        if (!empty($params['id'])) {
          $supersededLocBlockId = (int) CRM_Core_DAO::getFieldValue(
            'CRM_Volunteer_DAO_Project',
            $params['id'],
            'loc_block_id'
          );
        }
        $params['loc_block_id'] = self::saveLocationBlock($params['location']);
      }
      unset($params['location']);

      $project = new CRM_Volunteer_BAO_Project($params);
      $project->save();

      // The project now points at the new block, so the old one is orphaned
      // unless something else still uses it.
      if ($supersededLocBlockId && $supersededLocBlockId !== (int) $project->loc_block_id) {
        self::deleteUnreferencedLocBlock($supersededLocBlockId);
      }

      $customData = CRM_Core_BAO_CustomField::postProcess($params, $project->id, 'VolunteerProject');
      if (!empty($customData)) {
        CRM_Core_BAO_CustomValueTable::store($customData, 'civicrm_volunteer_project', $project->id);
      }

      // VOL-269: create flexible need during project creation.
      if ($op === CRM_Core_Action::ADD) {
        CRM_Volunteer_Permission::withInternalBypass(function() use ($project) {
          CRM_Volunteer_BAO_Need::create(array(
            'check_permissions' => FALSE,
            'is_flexible' => TRUE,
            'project_id' => $project->id,
            'visibility_id' => 'admin',
          ));
        });
      }

      // Relationship and profile parameters are aggregate replacement data.
      $updateVPC = ($op === CRM_Core_Action::UPDATE) && array_key_exists('project_contacts', $params);
      $updateProfiles = ($op === CRM_Core_Action::UPDATE) && array_key_exists('profiles', $params);
      if ($updateVPC) {
        CRM_Volunteer_Permission::flushProjectContactCache($project->id);
        CRM_Core_DAO::executeQuery(
          'DELETE FROM civicrm_volunteer_project_contact WHERE project_id = %1',
          array(1 => array((int) $project->id, 'Integer'))
        );
      }
      if ($updateProfiles) {
        CRM_Core_DAO::executeQuery(
          "DELETE FROM civicrm_uf_join WHERE entity_table = 'civicrm_volunteer_project' AND entity_id = %1 AND module = 'CiviVolunteer'",
          array(1 => array((int) $project->id, 'Integer'))
        );
      }

      if ($updateVPC || $op === CRM_Core_Action::ADD) {
        foreach ($params['project_contacts'] as $relationshipType => $contactIds) {
          $contactIds = array_unique(self::validateContactFormat($contactIds));
          // Callers key these by option name ('volunteer_owner') or by raw
          // option value. APIv3 resolved either through the pseudo-constant;
          // API4 only does so when the name suffix is requested explicitly.
          $relationshipField = is_numeric($relationshipType)
            ? 'relationship_type_id'
            : 'relationship_type_id:name';
          foreach ($contactIds as $id) {
            CRM_Volunteer_Permission::withInternalBypass(function() use ($id, $project, $relationshipType, $relationshipField) {
              \Civi\Api4\VolunteerProjectContact::create(FALSE)
                ->addValue('contact_id', $id)
                ->addValue('project_id', $project->id)
                ->addValue($relationshipField, $relationshipType)
                ->execute();
            });
          }
        }
      }
      if ($updateProfiles || $op === CRM_Core_Action::ADD) {
        foreach ($params['profiles'] as $profile) {
          if (!is_array($profile) || empty($profile['uf_group_id']) || (int) $profile['uf_group_id'] < 1) {
            throw new CRM_Core_Exception(ts('Every volunteer registration profile must have a valid profile ID.', array('domain' => 'org.civicrm.volunteer')));
          }
          $moduleData = $profile['module_data'] ?? array('audience' => 'primary');
          if (is_string($moduleData)) {
            $moduleData = json_decode($moduleData, TRUE);
            if (!is_array($moduleData)) {
              throw new CRM_Core_Exception(ts('Volunteer registration profile metadata is not valid JSON.', array('domain' => 'org.civicrm.volunteer')));
            }
          }
          elseif (is_object($moduleData)) {
            $moduleData = (array) $moduleData;
          }
          if (!is_array($moduleData)) {
            throw new CRM_Core_Exception(ts('Volunteer registration profile metadata must be an array.', array('domain' => 'org.civicrm.volunteer')));
          }
          $audience = $moduleData['audience'] ?? 'primary';
          if (!array_key_exists($audience, self::getProjectProfileAudienceTypes())) {
            throw new CRM_Core_Exception(ts('The volunteer registration profile audience is invalid.', array('domain' => 'org.civicrm.volunteer')));
          }
          $moduleData['audience'] = $audience;

          $profile['is_active'] = 1;
          $profile['module'] = 'CiviVolunteer';
          $profile['entity_table'] = 'civicrm_volunteer_project';
          $profile['entity_id'] = $project->id;
          // UFJoin.module_data is declared SERIALIZE_JSON, so API4 encodes it.
          // Handing it a pre-encoded string stored ["{...}"] -- an array
          // wrapping the JSON -- which read back with no `audience` key, so
          // every profile silently reverted to the primary audience.
          $profile['module_data'] = $moduleData;
          // Project/profile authorization has already been enforced at the
          // aggregate boundary; project editors need not also hold core's
          // broad profile-administration API permission merely to save UFJoin.
          unset($profile['id']);
          \Civi\Api4\UFJoin::create(FALSE)
            ->setValues($profile)
            ->execute();
        }
      }

      if ($op === CRM_Core_Action::UPDATE && array_key_exists('campaign_id', $params)) {
        $project->updateAssociatedActivities();
      }

      // The event-tab bootstrap reads the project through getEventProject() in
      // the same request that a save can land in.
      self::flushEventProjectCache();

      $transaction->commit();
      return $project;
    }
    catch (Throwable $e) {
      $transaction->rollback()->commit();
      throw $e;
    }
  }

  /**
   * Location type for a project's primary address/email/phone.
   *
   * Was hard-coded to 1. civicrm_location_type.id carries no guaranteed
   * meaning -- sites rename, delete and renumber types -- so resolve the
   * installation's default instead, which is id 1 on a stock install.
   *
   * @return int
   */
  public static function getPrimaryLocationTypeId() {
    if (self::$primaryLocationTypeId === NULL) {
      self::$primaryLocationTypeId = (int) (CRM_Core_DAO::singleValueQuery(
        'SELECT id FROM civicrm_location_type WHERE is_default = 1 AND is_active = 1 ORDER BY id LIMIT 1'
      ) ?: CRM_Core_DAO::singleValueQuery(
        'SELECT id FROM civicrm_location_type WHERE is_active = 1 ORDER BY id LIMIT 1'
      ));
    }
    return self::$primaryLocationTypeId;
  }

  /**
   * Delete an empty volunteer project and all aggregate-owned records.
   */
  public static function deleteProject($projectId, $checkPermissions = TRUE) {
    $projectId = (int) $projectId;
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::DELETE, $projectId);
    }

    $transaction = CRM_Core_Transaction::create(TRUE);
    try {
      CRM_Core_DAO::executeQuery(
        'SELECT id FROM civicrm_volunteer_need WHERE project_id = %1 FOR UPDATE',
        array(1 => array($projectId, 'Integer'))
      );
      if (CRM_Volunteer_BAO_Assignment::getProjectAssignmentCount($projectId)) {
        throw new CRM_Core_Exception(ts('This project has volunteer assignments and cannot be deleted. Disable it instead to preserve volunteer history.', array('domain' => 'org.civicrm.volunteer')));
      }

      CRM_Core_DAO::executeQuery(
        "DELETE FROM civicrm_uf_join WHERE entity_table = 'civicrm_volunteer_project' AND entity_id = %1 AND module = 'CiviVolunteer'",
        array(1 => array($projectId, 'Integer'))
      );
      CRM_Volunteer_Permission::flushProjectContactCache($projectId);
      CRM_Core_DAO::executeQuery(
        'DELETE FROM civicrm_volunteer_project_contact WHERE project_id = %1',
        array(1 => array($projectId, 'Integer'))
      );
      CRM_Core_DAO::executeQuery(
        'DELETE FROM civicrm_volunteer_need WHERE project_id = %1',
        array(1 => array($projectId, 'Integer'))
      );

      $project = new CRM_Volunteer_DAO_Project();
      $project->id = $projectId;
      if (!$project->find(TRUE)) {
        throw new CRM_Core_Exception(ts('The volunteer project does not exist.', array('domain' => 'org.civicrm.volunteer')));
      }
      $project->delete();
      $transaction->commit();
      return TRUE;
    }
    catch (Throwable $e) {
      $transaction->rollback()->commit();
      throw $e;
    }
  }

  /**
   * Search volunteer projects, applying this extension's read authorization.
   *
   * This is the shared implementation behind both the deprecated
   * `VolunteerProject.get` APIv3 action and the `VolunteerProject.search` API4
   * action. It accepts the rich filters that CRM_Volunteer_BAO_Project::retrieve()
   * understands -- `project_contacts`, `proximity`, `beneficiary` and friends --
   * which the DAO-backed `VolunteerProject.get` API4 action cannot express.
   *
   * @param array $params
   *   retrieve() filters, plus an optional `context`. A context of 'edit'
   *   restricts the result to projects the caller may edit and returns the full
   *   record; any other context is a public read, limited to active projects and
   *   to publicly safe fields.
   * @param bool $checkPermissions
   * @return array
   *   Project records keyed by project ID.
   */
  public static function searchProjects(array $params = array(), $checkPermissions = TRUE) {
    $context = $params['context'] ?? NULL;
    unset($params['context'], $params['check_permissions']);

    $publicRead = $context !== 'edit';
    $skipEntityAttributes = !empty($params[self::SKIP_ENTITY_ATTRIBUTES_PARAM]);
    // The mandatory-ownership filter is internal state, never request input.
    unset($params[self::REQUIRED_CONTACTS_PARAM], $params[self::SKIP_ENTITY_ATTRIBUTES_PARAM]);
    if ($checkPermissions && $context === 'edit') {
      if (!CRM_Volunteer_Permission::check('edit all volunteer projects')
        && !CRM_Volunteer_Permission::check('edit own volunteer projects')) {
        CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $params['id'] ?? NULL);
      }
      if (!CRM_Volunteer_Permission::check('edit all volunteer projects')) {
        // "edit own" means owned by me, full stop. This has to be a separate
        // AND-ed constraint: clauses inside `project_contacts` are OR-ed with
        // each other, so merging the owner filter in there would let a caller
        // widen the result set just by adding another relationship filter
        // (e.g. project_contacts[volunteer_beneficiary][]=<any contact>).
        // A missing contact ID yields no join and retrieve() then returns
        // nothing, which is the correct failure direction.
        $params[self::REQUIRED_CONTACTS_PARAM] = array(
          'volunteer_owner' => array(CRM_Core_Session::getLoggedInContactID()),
        );
      }
    }
    elseif ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::VIEW);
      $params['is_active'] = 1;
    }

    // retrieve() applies its own project-level authorization, keyed off
    // check_permissions, so a trusted read has to say so explicitly -- and the
    // flag is only honoured inside a trusted scope.
    $projects = CRM_Volunteer_Permission::runApi4Write(
      $checkPermissions,
      function(array $permissionParams) use ($params) {
        return self::retrieve($permissionParams + $params);
      }
    );
    $projectIds = array_map('intval', array_keys($projects));

    // Profile assignments were fetched with one UFJoin.get per project. Fetch
    // them for the whole result set in a single call and group by entity_id.
    $profilesByProject = array();
    if ($projectIds) {
      $joins = \Civi\Api4\UFJoin::get(FALSE)
        ->addSelect('*')
        ->addWhere('entity_id', 'IN', $projectIds)
        ->addWhere('entity_table', '=', 'civicrm_volunteer_project')
        ->addWhere('module', '=', 'CiviVolunteer')
        ->execute();
      foreach ($joins as $join) {
        // API4 deserializes module_data because UFJoin declares it serialized;
        // every consumer of this result -- the signup form, the project editor
        // and the deprecated APIv3 action -- expects APIv3's JSON string.
        if (isset($join['module_data']) && !is_string($join['module_data'])) {
          $join['module_data'] = json_encode($join['module_data']);
        }
        $profilesByProject[(int) $join['entity_id']][] = $join;
      }
    }

    $result = array();
    foreach ($projects as $key => $project) {
      $row = $project->toArray();
      if (!$publicRead && !$skipEntityAttributes) {
        $row['entity_attributes'] = $project->getEntityAttributes();
      }
      $row['profiles'] = $profilesByProject[(int) $project->id] ?? array();
      if ($publicRead) {
        $row['description'] = CRM_Utils_String::purifyHTML($row['description'] ?? '');
        $row['profiles'] = array_values(array_filter($row['profiles'], function(array $profile) {
          return !empty($profile['is_active']);
        }));
        $row['profiles'] = array_map(function(array $profile) {
          $moduleData = json_decode((string) ($profile['module_data'] ?? ''), TRUE);
          $audience = is_array($moduleData) ? ($moduleData['audience'] ?? 'primary') : 'primary';
          $audience = is_scalar($audience) ? (string) $audience : 'primary';
          if (!array_key_exists($audience, self::getProjectProfileAudienceTypes())) {
            $audience = 'primary';
          }
          return array(
            'uf_group_id' => (int) $profile['uf_group_id'],
            'module_data' => json_encode(array('audience' => $audience)),
            'weight' => (int) $profile['weight'],
          );
        }, $row['profiles']);
        $row = array_intersect_key($row, array_flip(array(
          'id', 'title', 'description', 'is_active', 'loc_block_id',
          'campaign_id', 'profiles',
        )));
      }
      $result[$key] = $row;
    }
    return $result;
  }

  /**
   * Project data for the Angular project-management screens.
   *
   * Adds the beneficiary and location payloads that the manage listing renders
   * on top of searchProjects()'s authorization and profile handling. Only an
   * editing context receives them; a public read is limited to safe fields.
   *
   * @param array $params
   * @param bool $checkPermissions
   * @return array
   */
  public static function getManageData(array $params = array(), $checkPermissions = TRUE) {
    $context = $params['context'] ?? 'edit';
    $params['context'] = $context;
    $rows = self::searchProjects($params, $checkPermissions);
    if ($context !== 'edit' || !$rows) {
      return $rows;
    }

    $beneficiariesByProject = self::getBeneficiariesByProject(array_keys($rows));
    $locationIds = array();
    foreach ($rows as $row) {
      if (!empty($row['loc_block_id'])) {
        $locationIds[] = (int) $row['loc_block_id'];
      }
    }
    $locationsByProject = array();
    if ($locationIds) {
      $locations = \Civi\Api4\LocBlock::get(FALSE)
        ->addSelect(
          '*',
          'address_id.name', 'address_id.street_address',
          'address_id.supplemental_address_1', 'address_id.supplemental_address_2',
          'address_id.city', 'address_id.postal_code', 'address_id.postal_code_suffix'
        )
        ->addWhere('id', 'IN', array_unique($locationIds))
        ->execute();
      foreach ($locations as $location) {
        $locationsByProject[(int) $location['id']] = self::nestLocBlockJoins($location);
      }
    }

    foreach ($rows as $key => $row) {
      $rows[$key]['beneficiaries'] = $beneficiariesByProject[(int) $row['id']] ?? array();
      $rows[$key]['location'] = !empty($row['loc_block_id'])
        ? ($locationsByProject[(int) $row['loc_block_id']] ?? array())
        : array();
    }
    return $rows;
  }

  /**
   * Display-ready data for the volunteer-project list and dashboard.
   *
   * The base project query remains the authority for row-level access. All
   * subsequent reads are constrained to those project IDs and use internal
   * API calls only to enrich already-authorized rows.
   *
   * @param array $params
   * @param bool $checkPermissions
   * @param \DateTimeImmutable|null $now
   *   Primarily useful to make rolling-window boundary tests deterministic.
   *
   * @return array
   */
  public static function getManageOverview(array $params = array(), $checkPermissions = TRUE, ?\DateTimeImmutable $now = NULL) {
    $params['context'] = 'edit';
    // The list and dashboard render no associated-entity column, so the
    // per-project Event lookup behind it is pure cost on every load and on
    // every post-bulk refresh.
    $params[self::SKIP_ENTITY_ATTRIBUTES_PARAM] = TRUE;
    $projects = self::getManageData($params, $checkPermissions);
    $timezone = new \DateTimeZone(date_default_timezone_get());
    $now = $now ? $now->setTimezone($timezone) : new \DateTimeImmutable('now', $timezone);
    $weekEnd = $now->modify('+7 days');
    $fortnightEnd = $now->modify('+14 days');

    $overview = array(
      'summary' => array(
        'active_projects' => 0,
        'filled_spots_14_days' => 0,
        'total_spots_14_days' => 0,
        'projects_short' => 0,
        'shifts_awaiting_hours' => 0,
      ),
      'projects' => array(),
      'attention' => array(),
      'up_next' => array(),
      'this_week' => array(),
      'generated_at' => $now->format('Y-m-d H:i:s'),
      'timezone' => $timezone->getName(),
    );
    if (!$projects) {
      return $overview;
    }

    $projectIds = array_map('intval', array_keys($projects));
    $beneficiaryIds = array();
    foreach ($projects as $project) {
      if (!empty($project['is_active'])) {
        $overview['summary']['active_projects']++;
      }
      foreach (($project['beneficiaries'] ?? array()) as $contactId) {
        $beneficiaryIds[(int) $contactId] = (int) $contactId;
      }
    }

    $beneficiaryNames = array();
    if ($beneficiaryIds) {
      $contacts = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id', 'display_name')
        ->addWhere('id', 'IN', array_values($beneficiaryIds))
        ->execute();
      foreach ($contacts as $contact) {
        $beneficiaryNames[(int) $contact['id']] = $contact['display_name'];
      }
    }

    $campaignIds = array_values(array_unique(array_filter(array_map(function(array $project) {
      return (int) ($project['campaign_id'] ?? 0);
    }, $projects))));
    $campaignLabels = array();
    // Civi\Api4\Campaign ships in the civi_campaign extension, not core, so
    // calling it is a fatal on a site where CiviCampaign is switched off. A
    // project can still carry a campaign_id from a period when it was on.
    if ($campaignIds && CRM_Core_Component::isEnabled('CiviCampaign')) {
      $campaigns = \Civi\Api4\Campaign::get(FALSE)
        ->addSelect('id', 'title')
        ->addWhere('id', 'IN', $campaignIds)
        ->execute();
      foreach ($campaigns as $campaign) {
        $campaignLabels[(int) $campaign['id']] = $campaign['title'];
      }
    }

    // The associated-entity column the redesign dropped, restored as one
    // batched read rather than the per-project getEntityAttributes() call it
    // used to be -- which is why SKIP_ENTITY_ATTRIBUTES_PARAM is still set
    // above. Without this there is no way in the new UI to see, or reach, the
    // event a project belongs to.
    $eventIds = array();
    foreach ($projects as $project) {
      if (($project['entity_table'] ?? NULL) === 'civicrm_event' && !empty($project['entity_id'])) {
        $eventIds[(int) $project['entity_id']] = (int) $project['entity_id'];
      }
    }
    $eventTitles = array();
    // Same reasoning as the campaign guard above: Civi\Api4\Event ships in the
    // civi_event extension, and a project can still carry an entity_id from a
    // period when CiviEvent was switched on.
    if ($eventIds && CRM_Core_Component::isEnabled('CiviEvent')) {
      $events = \Civi\Api4\Event::get(FALSE)
        ->addSelect('id', 'title')
        ->addWhere('id', 'IN', array_values($eventIds))
        ->execute();
      foreach ($events as $event) {
        $eventTitles[(int) $event['id']] = $event['title'];
      }
    }

    $needs = \Civi\Api4\VolunteerNeed::get(FALSE)
      ->addSelect('*')
      ->addWhere('project_id', 'IN', $projectIds)
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('is_flexible', '=', FALSE)
      ->addOrderBy('start_time', 'ASC')
      ->execute()
      ->getArrayCopy();

    $needIds = array_map('intval', array_column($needs, 'id'));
    $assignmentsByNeed = array();
    if ($needIds) {
      $customGroup = CRM_Volunteer_BAO_Assignment::getCustomGroup();
      $customFields = CRM_Volunteer_BAO_Assignment::getCustomFields();
      $customTable = $customGroup['table_name'];
      $needColumn = $customFields['volunteer_need_id']['column_name'];
      $completedColumn = $customFields['time_completed_minutes']['column_name'];
      $assignmentDao = CRM_Core_DAO::executeQuery(sprintf(
        'SELECT a.status_id, cv.`%s` AS need_id, cv.`%s` AS completed_minutes
           FROM civicrm_activity a
           INNER JOIN `%s` cv ON cv.entity_id = a.id
          WHERE a.activity_type_id = %d
            AND a.is_deleted = 0
            AND cv.`%s` IN (%s)',
        $needColumn,
        $completedColumn,
        $customTable,
        (int) CRM_Volunteer_BAO_Assignment::getActivityTypeId(),
        $needColumn,
        implode(',', $needIds)
      ));
      while ($assignmentDao->fetch()) {
        $needId = (int) $assignmentDao->need_id;
        $assignmentsByNeed[$needId][] = array(
          'status_id' => (int) $assignmentDao->status_id,
          // Preserve the distinction between no entry and a deliberately
          // logged zero-minute entry.
          'completed_minutes' => $assignmentDao->completed_minutes === NULL
            ? NULL
            : (int) $assignmentDao->completed_minutes,
        );
      }
    }

    $statusIds = self::getManageOverviewStatusIds();
    $needsByProject = array_fill_keys($projectIds, array());
    foreach ($needs as $need) {
      $needsByProject[(int) $need['project_id']][] = $need;
    }

    $projectRows = array();
    $upNext = array();
    $thisWeek = array();
    $attention = array();
    $shortProjectIds = array();
    $awaitingNeedIds = array();

    foreach ($projects as $projectId => $project) {
      $projectId = (int) $projectId;
      $isActiveProject = !empty($project['is_active']);
      // Emit the ID->name pairing explicitly. Returning the names as a
      // positional list alongside 'beneficiaries' left the caller to zip the
      // two by index, and array_filter() drops a blank display_name as readily
      // as an unresolvable one -- so the list re-packed and every later name
      // shifted onto the wrong contact.
      $project['beneficiary_options'] = array();
      foreach (($project['beneficiaries'] ?? array()) as $contactId) {
        $contactId = (int) $contactId;
        if (isset($beneficiaryNames[$contactId])) {
          $project['beneficiary_options'][$contactId] = $beneficiaryNames[$contactId];
        }
      }
      $project['beneficiary_names'] = array_values($project['beneficiary_options']);
      $project['campaign_label'] = $campaignLabels[(int) ($project['campaign_id'] ?? 0)] ?? NULL;
      $project['associated_entity'] = NULL;
      if (($project['entity_table'] ?? NULL) === 'civicrm_event' && !empty($project['entity_id'])) {
        $project['associated_entity'] = array(
          'entity_table' => 'civicrm_event',
          'entity_id' => (int) $project['entity_id'],
          // NULL when the event has been deleted or CiviEvent is off; the
          // client falls back to showing the ID so the link is still usable.
          'title' => $eventTitles[(int) $project['entity_id']] ?? NULL,
        );
      }
      $project['upcoming_roles'] = array();
      $project['next_shift'] = NULL;
      $project['staffing'] = array('filled' => 0, 'total' => 0, 'open' => 0);
      $project['needs_volunteers'] = FALSE;

      foreach ($needsByProject[$projectId] as $need) {
        $needId = (int) $need['id'];
        if (empty($need['start_time'])) {
          continue;
        }
        try {
          $start = new \DateTimeImmutable($need['start_time'], $timezone);
        }
        catch (\Throwable $e) {
          continue;
        }
        $end = self::getManageOverviewNeedEnd($need, $timezone);
        $filled = 0;
        $missingHours = 0;
        foreach (($assignmentsByNeed[$needId] ?? array()) as $assignment) {
          if (in_array($assignment['status_id'], array($statusIds['Scheduled'], $statusIds['Available']), TRUE)) {
            $filled++;
          }
          if (in_array($assignment['status_id'], array($statusIds['Scheduled'], $statusIds['Completed']), TRUE)
            && $assignment['completed_minutes'] === NULL) {
            $missingHours++;
          }
        }

        $quantity = $need['quantity'] === NULL || $need['quantity'] === ''
          ? NULL
          : (int) $need['quantity'];
        $isFinite = $quantity !== NULL && $quantity > 0;
        $open = $isFinite ? max(0, $quantity - $filled) : 0;
        $isFuture = $start >= $now;

        if ($isActiveProject && $isFuture) {
          if ($project['next_shift'] === NULL) {
            $project['next_shift'] = self::manageOverviewNeedRow($need, $filled, $quantity, $open);
          }
          if (!empty($need['role_label']) && !in_array($need['role_label'], $project['upcoming_roles'], TRUE)) {
            $project['upcoming_roles'][] = $need['role_label'];
          }
          if ($isFinite) {
            $project['staffing']['filled'] += $filled;
            $project['staffing']['total'] += $quantity;
            $project['staffing']['open'] += $open;
          }
          if ($open > 0) {
            $project['needs_volunteers'] = TRUE;
            $shortProjectIds[$projectId] = TRUE;
            $attention[] = array(
              'project_id' => $projectId,
              'need_id' => $needId,
              'action_type' => 'staffing',
              'action_date' => $start->format('Y-m-d H:i:s'),
              'project_title' => $project['title'],
              'role_label' => $need['role_label'] ?? '',
              'display_time' => $need['display_time'] ?? '',
              'filled' => $filled,
              'total' => $quantity,
              'open' => $open,
              'missing_hours' => 0,
            );
          }
          if ($start < $fortnightEnd && $isFinite) {
            $overview['summary']['filled_spots_14_days'] += $filled;
            $overview['summary']['total_spots_14_days'] += $quantity;
          }
          if ($start < $weekEnd) {
            $weekRow = self::manageOverviewNeedRow($need, $filled, $quantity, $open);
            $weekRow['project_id'] = $projectId;
            $weekRow['project_title'] = $project['title'];
            $thisWeek[] = $weekRow;
          }
        }

        // An ongoing need has no defined completion moment, so reaching its
        // nominal start date does not make its hours overdue.
        if ($isActiveProject && $end && $end <= $now && $missingHours > 0) {
          $awaitingNeedIds[$needId] = TRUE;
          $attention[] = array(
            'project_id' => $projectId,
            'need_id' => $needId,
            'action_type' => 'hours',
            'action_date' => $end->format('Y-m-d H:i:s'),
            'project_title' => $project['title'],
            'role_label' => $need['role_label'] ?? '',
            'display_time' => $need['display_time'] ?? '',
            'filled' => $filled,
            'total' => $quantity,
            'open' => $open,
            'missing_hours' => $missingHours,
          );
        }
      }

      if ($isActiveProject && $project['next_shift']) {
        $next = $project['next_shift'];
        $next['project_id'] = $projectId;
        $next['project_title'] = $project['title'];
        $next['filled'] = $project['staffing']['filled'];
        $next['total'] = $project['staffing']['total'];
        $next['open'] = $project['staffing']['open'];
        $upNext[] = $next;
      }
      $projectRows[] = $project;
    }

    $sortByStart = static function(array $a, array $b) {
      $dateComparison = strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''));
      return $dateComparison ?: strcmp((string) ($a['project_title'] ?? ''), (string) ($b['project_title'] ?? ''));
    };
    usort($upNext, $sortByStart);
    usort($thisWeek, $sortByStart);
    $nowTimestamp = $now->getTimestamp();
    usort($attention, static function(array $a, array $b) use ($nowTimestamp) {
      $aTimestamp = strtotime($a['action_date']);
      $bTimestamp = strtotime($b['action_date']);
      $distance = abs($aTimestamp - $nowTimestamp) <=> abs($bTimestamp - $nowTimestamp);
      if ($distance !== 0) {
        return $distance;
      }
      $aOverdue = $aTimestamp <= $nowTimestamp;
      $bOverdue = $bTimestamp <= $nowTimestamp;
      if ($aOverdue !== $bOverdue) {
        return $aOverdue ? -1 : 1;
      }
      return $aTimestamp <=> $bTimestamp;
    });

    $overview['summary']['projects_short'] = count($shortProjectIds);
    $overview['summary']['shifts_awaiting_hours'] = count($awaitingNeedIds);
    $overview['projects'] = $projectRows;
    $overview['attention'] = array_slice($attention, 0, 3);
    $overview['up_next'] = array_slice($upNext, 0, 4);
    $overview['this_week'] = array_slice($thisWeek, 0, 5);
    return $overview;
  }

  /**
   * Activity statuses used by dashboard capacity and hour-state calculations.
   */
  private static function getManageOverviewStatusIds() {
    $statuses = array_column(
      \Civi::entity('Activity')->getOptions('status_id', array(), TRUE) ?? array(),
      'name',
      'id'
    );
    $ids = array();
    foreach (array('Scheduled', 'Available', 'Completed') as $name) {
      $ids[$name] = (int) CRM_Utils_Array::key($name, $statuses);
    }
    return $ids;
  }

  /**
   * Resolve a shift's real completion time; ongoing needs have none.
   */
  private static function getManageOverviewNeedEnd(array $need, \DateTimeZone $timezone) {
    try {
      if (!empty($need['end_time'])) {
        return new \DateTimeImmutable($need['end_time'], $timezone);
      }
      if (!empty($need['duration']) && !empty($need['start_time'])) {
        return (new \DateTimeImmutable($need['start_time'], $timezone))
          ->modify('+' . (int) $need['duration'] . ' minutes');
      }
    }
    catch (\Throwable $e) {
      return NULL;
    }
    return NULL;
  }

  /**
   * Normalize one need for dashboard/list display.
   */
  private static function manageOverviewNeedRow(array $need, $filled, $quantity, $open) {
    return array(
      'need_id' => (int) $need['id'],
      'start_time' => $need['start_time'] ?? NULL,
      'end_time' => $need['end_time'] ?? NULL,
      'duration' => $need['duration'] === NULL || $need['duration'] === '' ? NULL : (int) $need['duration'],
      'display_time' => $need['display_time'] ?? '',
      'role_label' => $need['role_label'] ?? '',
      'filled' => (int) $filled,
      'total' => $quantity,
      'open' => (int) $open,
    );
  }

  /**
   * Beneficiary contact IDs grouped by project ID.
   *
   * Replaces the former api.VolunteerProjectContact.get chains on the
   * management screen; display names are resolved by the caller through
   * Contact::get, keeping this read a single query.
   *
   * @param array $projectIds
   * @return array
   *   Shape: [(int) project_id => [(int) contact_id, ...]]
   */
  private static function getBeneficiariesByProject(array $projectIds) {
    $beneficiaryTypeId = CRM_Core_PseudoConstant::getKey(
      'CRM_Volunteer_BAO_ProjectContact',
      'relationship_type_id',
      'volunteer_beneficiary'
    );
    $byProject = array();
    if (!$beneficiaryTypeId) {
      return $byProject;
    }
    $rows = \Civi\Api4\VolunteerProjectContact::get(FALSE)
      ->addSelect('project_id', 'contact_id')
      ->addWhere('project_id', 'IN', array_map('intval', $projectIds))
      ->addWhere('relationship_type_id', '=', (int) $beneficiaryTypeId)
      ->execute();
    foreach ($rows as $row) {
      $byProject[(int) $row['project_id']][] = (int) $row['contact_id'];
    }
    return $byProject;
  }

  /**
   * Everything the project workflow header and steps read for one project.
   *
   * The Angular workflow used to issue six or seven requests on every step
   * change: the project, its needs, its assignments, its capacity, the
   * supporting data and a two-step beneficiary name lookup. This bundles the
   * same reads into one response. Each piece still comes from the guarded
   * read it always came from, with $checkPermissions passed through, so the
   * caller sees exactly what the separate calls would have returned.
   *
   * @param int $projectId
   * @param bool $checkPermissions
   *
   * @return array
   *   Keys: project, needs, assignments, capacity (NULL when the summary is
   *   unavailable), supporting (workflow and project bundles) and
   *   beneficiary_names.
   *
   * @throws CRM_Core_Exception
   *   When the project does not exist or the caller may not edit it.
   */
  public static function getWorkflowContext($projectId, $checkPermissions = TRUE) {
    $projectId = (int) $projectId;

    $projects = \Civi\Api4\VolunteerProject::search($checkPermissions)
      ->setContext('edit')
      ->setFilters(array('id' => $projectId))
      ->execute();
    if (!count($projects)) {
      throw new CRM_Core_Exception(
        ts('The volunteer project does not exist.', array('domain' => 'org.civicrm.volunteer'))
      );
    }
    $project = $projects->first();

    $needs = \Civi\Api4\VolunteerNeed::get($checkPermissions)
      ->addWhere('project_id', '=', $projectId)
      ->addOrderBy('start_time', 'ASC')
      ->addOrderBy('id', 'ASC')
      ->execute()
      ->getArrayCopy();

    $assignments = \Civi\Api4\VolunteerAssignment::get($checkPermissions)
      ->addWhere('project_id', '=', $projectId)
      ->execute()
      ->getArrayCopy();

    // The header falls back to counting the rows above when the summary is
    // unavailable, so a failure here degrades rather than aborts.
    try {
      $capacity = CRM_Volunteer_BAO_Assignment::getCapacitySummary($projectId, $checkPermissions);
    }
    catch (CRM_Core_Exception $e) {
      $capacity = NULL;
    }

    $supporting = array(
      'workflow' => CRM_Volunteer_BAO_VolunteerUtil::getSupportingData('VolunteerWorkflow', $checkPermissions),
      'project' => CRM_Volunteer_BAO_VolunteerUtil::getSupportingData('VolunteerProject', $checkPermissions),
    );

    return array(
      'project' => $project,
      'needs' => array_values($needs),
      'assignments' => array_values($assignments),
      'capacity' => $capacity,
      'supporting' => $supporting,
      'beneficiary_names' => self::getBeneficiaryDisplayNames($projectId, $checkPermissions),
    );
  }

  /**
   * Display names of a project's beneficiaries, for the workflow header.
   *
   * A name the caller may not read is left out; if the contact read fails
   * outright every beneficiary is labelled by ID instead so the header still
   * shows that the project has beneficiaries.
   *
   * @param int $projectId
   * @param bool $checkPermissions
   * @return string[]
   */
  private static function getBeneficiaryDisplayNames($projectId, $checkPermissions) {
    $beneficiaryTypeId = CRM_Core_PseudoConstant::getKey(
      'CRM_Volunteer_BAO_ProjectContact',
      'relationship_type_id',
      'volunteer_beneficiary'
    );
    if (!$beneficiaryTypeId) {
      return array();
    }
    $rows = \Civi\Api4\VolunteerProjectContact::get($checkPermissions)
      ->addSelect('contact_id')
      ->addWhere('project_id', '=', (int) $projectId)
      ->addWhere('relationship_type_id', '=', (int) $beneficiaryTypeId)
      ->execute();
    $contactIds = array();
    foreach ($rows as $row) {
      $contactIds[] = (int) $row['contact_id'];
    }
    $contactIds = array_values(array_unique($contactIds));
    if (!$contactIds) {
      return array();
    }
    try {
      $contacts = \Civi\Api4\Contact::get($checkPermissions)
        ->addSelect('id', 'display_name')
        ->addWhere('id', 'IN', $contactIds)
        ->execute();
    }
    catch (\Throwable $e) {
      return array_map(function($contactId) {
        return ts('Contact %1', array(1 => $contactId, 'domain' => 'org.civicrm.volunteer'));
      }, $contactIds);
    }
    return array_values($contacts->column('display_name'));
  }

  /**
   * Return the location choices which are valid for a project editor.
   */
  public static function getLocationOptions($projectId = NULL, $checkPermissions = TRUE) {
    $projectId = (int) $projectId;
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(
        $projectId ? CRM_Core_Action::UPDATE : CRM_Core_Action::ADD,
        $projectId ?: NULL
      );
    }
    $defaultLocationId = CRM_Volunteer_Api4::getSetting('volunteer_project_default_locblock');
    $locationIds = array_filter(array(
      $projectId ? CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Project', $projectId, 'loc_block_id') : NULL,
      $defaultLocationId,
    ));
    if (!$locationIds) {
      return array();
    }
    $query = "SELECT CONCAT_WS(' :: ', ca.name, ca.street_address, ca.city, sp.name, ca.supplemental_address_1, ca.supplemental_address_2) title, lb.id
      FROM civicrm_loc_block lb
      INNER JOIN civicrm_address ca ON lb.address_id = ca.id
      LEFT JOIN civicrm_state_province sp ON ca.state_province_id = sp.id
      WHERE lb.id IN (" . implode(',', array_map('intval', $locationIds)) . ")
      ORDER BY sp.name, ca.city, ca.street_address";
    $locations = array();
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $locations[] = array('id' => (int) $dao->id, 'title' => $dao->title);
    }
    return $locations;
  }

  /**
   * Return a location block only when it belongs to the project or defaults.
   */
  public static function getLocationData($id, $projectId = NULL, $checkPermissions = TRUE) {
    $id = (int) $id;
    $projectId = (int) $projectId;
    if (!$id) {
      return array();
    }
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(
        $projectId ? CRM_Core_Action::UPDATE : CRM_Core_Action::ADD,
        $projectId ?: NULL
      );
    }
    $projectLocationId = $projectId
      ? CRM_Core_DAO::getFieldValue('CRM_Volunteer_DAO_Project', $projectId, 'loc_block_id')
      : NULL;
    $defaultLocationId = CRM_Volunteer_Api4::getSetting('volunteer_project_default_locblock');
    if ($id !== (int) $projectLocationId && $id !== (int) $defaultLocationId) {
      throw new CRM_Core_Exception(ts('The requested location does not belong to this volunteer project.', array('domain' => 'org.civicrm.volunteer')), 403);
    }
    $rows = \Civi\Api4\LocBlock::get(FALSE)
      ->addSelect(
        '*',
        'address_id.*', 'address_2_id.*',
        'email_id.*', 'email_2_id.*',
        'phone_id.*', 'phone_2_id.*',
        'im_id.*', 'im_2_id.*'
      )
      ->addWhere('id', '=', $id)
      ->execute()
      ->getArrayCopy();
    return array_map([self::class, 'nestLocBlockJoins'], $rows);
  }

  /**
   * Convert API4's flattened FK-joined LocBlock columns (e.g.
   * "address_id.city") back into the nested sub-entity arrays ("address",
   * "email", ...) the location editor and the API3 LocBlock shape are built
   * on. Sub-entity keys are omitted when the foreign key is unset.
   *
   * @param array $row
   * @return array
   */
  private static function nestLocBlockJoins(array $row) {
    foreach (['address', 'address_2', 'email', 'email_2', 'phone', 'phone_2', 'im', 'im_2'] as $base) {
      $fk = $base . '_id';
      $prefix = $fk . '.';
      $nested = [];
      foreach ($row as $key => $value) {
        if (strpos($key, $prefix) === 0) {
          $nested[substr($key, strlen($prefix))] = $value;
          unset($row[$key]);
        }
      }
      if (!empty($row[$fk])) {
        $row[$base] = $nested;
      }
    }
    return $row;
  }

  /**
   * Remove one profile assignment after validating project ownership.
   */
  public static function removeProfile($id, $projectId, $checkPermissions = TRUE) {
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $projectId);
      if (!CRM_Volunteer_Permission::check('edit volunteer registration profiles')) {
        throw new CRM_Core_Exception(ts('You do not have permission to edit volunteer registration profiles.', array('domain' => 'org.civicrm.volunteer')), 403);
      }
    }
    $join = \Civi\Api4\UFJoin::get(FALSE)
      ->addSelect('id', 'entity_table', 'entity_id', 'module')
      ->addWhere('id', '=', (int) $id)
      ->execute()
      ->single();
    if ($join['entity_table'] !== 'civicrm_volunteer_project'
      || (int) $join['entity_id'] !== (int) $projectId
      || $join['module'] !== 'CiviVolunteer') {
      throw new CRM_Core_Exception(ts('The profile assignment does not belong to this volunteer project.', array('domain' => 'org.civicrm.volunteer')));
    }
    \Civi\Api4\UFJoin::delete(FALSE)
      ->addWhere('id', '=', (int) $id)
      ->execute();
    return TRUE;
  }

  /**
   * Location type for a project's secondary address/email/phone.
   *
   * Prefer the conventional Work type, then Other, before falling back to a
   * stable active type other than the primary. This avoids silently storing a
   * secondary address under an unrelated site-defined type merely because its
   * numeric ID happened to sort first.
   *
   * @param int $primaryTypeId
   *
   * @return int
   */
  public static function getSecondaryLocationTypeId($primaryTypeId) {
    if (self::$secondaryLocationTypeId === NULL) {
      $secondary = CRM_Core_DAO::singleValueQuery(
        'SELECT id FROM civicrm_location_type
          WHERE is_active = 1 AND id <> %1
          ORDER BY CASE
            WHEN LOWER(name) = \'work\' THEN 0
            WHEN LOWER(name) = \'other\' THEN 1
            ELSE 2
          END, is_reserved DESC, id ASC
          LIMIT 1',
        array(1 => array((int) $primaryTypeId, 'Integer'))
      );
      self::$secondaryLocationTypeId = (int) ($secondary ?: $primaryTypeId);
    }
    return self::$secondaryLocationTypeId;
  }

  /**
   * Delete a location block that nothing references any more.
   *
   * saveLocationBlock() deliberately creates a fresh block on every save so
   * that editing a project cannot mutate a location shared with another
   * entity. Without this, each edit left the previous block -- plus up to two
   * addresses, two emails and two phones -- orphaned forever, since the
   * project's foreign key is ON DELETE SET NULL.
   *
   * @param int $locBlockId
   */
  private static function deleteUnreferencedLocBlock($locBlockId) {
    $locBlockId = (int) $locBlockId;
    if ($locBlockId < 1) {
      return;
    }
    try {
      $locBlock = new CRM_Core_DAO_LocBlock();
      $locBlock->id = $locBlockId;
      $refCounts = $locBlock->find(TRUE) ? $locBlock->getReferenceCounts() : array();
    }
    catch (Throwable $e) {
      // Never let cleanup break the save it follows.
      return;
    }
    foreach ($refCounts as $reference) {
      if (!empty($reference['count'])) {
        return;
      }
    }
    try {
      CRM_Core_BAO_Location::deleteLocBlock($locBlockId);
    }
    catch (Throwable $e) {
      // Orphan collection is best-effort and must never undo the project save.
      Civi::log()->warning(
        'CiviVolunteer could not remove unreferenced location block {id}: {message}',
        array('id' => $locBlockId, 'message' => $e->getMessage())
      );
    }
  }

  /**
   * Create a private location block from project editor input.
   *
   * Existing location IDs are deliberately discarded. Editing a project must
   * not mutate a location block which may also belong to another entity.
   *
   * @param array $params
   *   LocBlock.create parameters.
   *
   * @return int
   *   The newly-created location block ID.
   */
  public static function saveLocationBlock(array $params) {
    $primaryType = self::getPrimaryLocationTypeId();
    $secondaryType = self::getSecondaryLocationTypeId($primaryType);

    $fieldDefinitions = array(
      'address' => array($primaryType, array(
        'name', 'street_address', 'supplemental_address_1',
        'supplemental_address_2', 'supplemental_address_3', 'city',
        'postal_code', 'postal_code_suffix', 'state_province_id',
        'country_id', 'timezone',
      )),
      'address_2' => array($secondaryType, array(
        'name', 'street_address', 'supplemental_address_1',
        'supplemental_address_2', 'supplemental_address_3', 'city',
        'postal_code', 'postal_code_suffix', 'state_province_id',
        'country_id', 'timezone',
      )),
      'email' => array($primaryType, array('email')),
      'email_2' => array($secondaryType, array('email')),
      'phone' => array($primaryType, array('phone', 'phone_ext', 'phone_type_id')),
      'phone_2' => array($secondaryType, array('phone', 'phone_ext', 'phone_type_id')),
    );

    $locationParams = array();
    foreach ($fieldDefinitions as $field => [$locationTypeId, $allowedFields]) {
      if (!empty($params[$field]) && is_array($params[$field])) {
        // Nested IDs and contact ownership fields must never be copied from a
        // shared location. Only editor-supported value fields are cloned.
        $joinField = $field . '_id';
        foreach (array_intersect_key($params[$field], array_flip($allowedFields)) as $name => $value) {
          $locationParams[$joinField . '.' . $name] = $value;
        }
        $locationParams[$joinField . '.location_type_id'] = $locationTypeId;
      }
    }

    $location = \Civi\Api4\LocBlock::create(FALSE)
      ->setValues($locationParams)
      ->execute()
      ->first();
    if (empty($location['id'])) {
      throw new CRM_Core_Exception(ts('The volunteer project location could not be saved.', array('domain' => 'org.civicrm.volunteer')));
    }

    return (int) $location['id'];
  }

  /**
   * Facilitates propagatation of changes in a Project to associated Activities.
   *
   * This method takes no arguments because the Assignment BAO handles
   * propagation internally.
   *
   * @see CRM_Volunteer_BAO_Assignment::setActivityDefaults()
   */
  public function updateAssociatedActivities () {
    // Not Assignment::retrieve(): its SQL is restricted to Scheduled and
    // Available, the statuses that consume a need's capacity. Re-deriving a
    // project-owned value has nothing to do with capacity, so restricting it
    // that way meant a campaign change never reached the assignments anyone
    // had actually attended -- and the Hours workflow exists precisely to move
    // assignments to Completed. Same reasoning as getCapacity() and
    // getHourEntriesData(), which had to stop using retrieve() for this.
    //
    // Re-saving with nothing but an id is safe for a historical row:
    // setActivityDefaults() only supplies status_id, activity_date_time,
    // subject, source and target contacts on ADD, and time_completed_minutes
    // is never in the default set, so logged hours, attendance status and
    // notes all survive.
    foreach (CRM_Volunteer_BAO_Assignment::getProjectAssignmentIds($this->id) as $activityId) {
      CRM_Volunteer_BAO_Assignment::createVolunteerActivity(array(
        'id' => $activityId,
      ));
    }
  }

  /**
   * Duplicate an event's volunteer project onto a copy of that event.
   *
   * Copying a CiviCRM event used to produce an event with no volunteer setup at
   * all, silently: the extension implemented no hook_civicrm_copy, so an
   * organiser duplicating last year's fundraiser got the fees, the profiles and
   * the reminders but none of the shifts.
   *
   * Shifts and roles are copied; *assignments are not*. A volunteer who signed
   * up for last year's event has not signed up for this one, and the whole
   * point of a copy is an empty roster.
   *
   * @param int $sourceEventId
   * @param int $targetEventId
   * @return CRM_Volunteer_BAO_Project|null
   *   The new project, or NULL if the source event had none.
   */
  public static function copyForEvent($sourceEventId, $targetEventId) {
    $source = self::getEventProject($sourceEventId);
    if (!$source || !$targetEventId) {
      return NULL;
    }

    // The caller is CiviCRM's own event copy, which has already authorized the
    // event duplication; the projects on either side are ours to read.
    return CRM_Volunteer_Permission::withInternalBypass(function() use ($source, $targetEventId) {
      $rows = self::searchProjects(array(
        'check_permissions' => FALSE,
        'context' => 'edit',
        'id' => $source->id,
      ), FALSE);
      $sourceRow = current($rows) ?: array();

      $projectContacts = array();
      $contactRows = \Civi\Api4\VolunteerProjectContact::get(FALSE)
        ->addSelect('contact_id', 'relationship_type_id')
        ->addWhere('project_id', '=', $source->id)
        ->execute();
      foreach ($contactRows as $contactRow) {
        $projectContacts[$contactRow['relationship_type_id']][] = $contactRow['contact_id'];
      }

      $profiles = array();
      foreach (($sourceRow['profiles'] ?? array()) as $profile) {
        $profiles[] = array(
          'uf_group_id' => $profile['uf_group_id'],
          'module_data' => $profile['module_data'] ?? NULL,
          'weight' => $profile['weight'] ?? NULL,
          'is_active' => $profile['is_active'] ?? 1,
        );
      }

      $copy = self::create(array(
        'check_permissions' => FALSE,
        'title' => $source->title,
        'description' => $source->description,
        'is_active' => $source->is_active,
        'campaign_id' => $source->campaign_id,
        // The location block is referenced, not duplicated. That matches what
        // the project editor already lets an administrator do -- its location
        // select offers every existing block -- so a shared block is an
        // expected state rather than a new one.
        'loc_block_id' => $source->loc_block_id,
        'entity_table' => 'civicrm_event',
        'entity_id' => (int) $targetEventId,
        'profiles' => $profiles,
        'project_contacts' => $projectContacts,
      ));

      self::copyNeeds((int) $source->id, (int) $copy->id);
      return $copy;
    });
  }

  /**
   * Copy a project's needs onto another project.
   *
   * create() has already made the target its own flexible need (VOL-269), so
   * that one is updated in place rather than duplicated -- otherwise the copy
   * would end up with two, and getFlexibleNeedID() would be answering from a
   * set of two.
   *
   * @param int $sourceProjectId
   * @param int $targetProjectId
   */
  private static function copyNeeds($sourceProjectId, $targetProjectId) {
    $needs = \Civi\Api4\VolunteerNeed::get(FALSE)
      ->addSelect('*')
      ->addWhere('project_id', '=', $sourceProjectId)
      ->addOrderBy('id', 'ASC')
      ->execute();

    $targetFlexibleNeedId = self::getFlexibleNeedID($targetProjectId);
    foreach ($needs as $need) {
      unset($need['id'], $need['created'], $need['last_updated']);
      $need['project_id'] = $targetProjectId;
      $need['check_permissions'] = FALSE;

      if (!empty($need['is_flexible']) && $targetFlexibleNeedId) {
        $need['id'] = $targetFlexibleNeedId;
        unset($need['is_flexible'], $need['project_id']);
      }
      CRM_Volunteer_BAO_Need::create($need);
    }
  }

  /**
   * Entity tables a volunteer project can be associated with.
   *
   * Pseudoconstant behind the entity_table field; @see
   * schema/VolunteerProject.entityType.php. civicrm_event is the only one the
   * extension has ever implemented -- every consumer is a
   * `switch ($entity_table) { case 'civicrm_event': }` -- and it is listed
   * whether or not CiviEvent is currently enabled, because a stored row can
   * hold the value either way.
   *
   * @return array
   */
  public static function getEntityTables() {
    return array(
      'civicrm_event' => ts('Event', array('domain' => 'org.civicrm.volunteer')),
    );
  }

  /**
   * The volunteer project attached to an event, or NULL if there is none.
   *
   * Memoized for the request. Core calls
   * hook_civicrm_tabset('civicrm/event/manage/rows') once per row of the Manage
   * Events listing, and both the tab's permission gate and its is_volunteer
   * flag need this same lookup -- so unmemoized it cost two project reads plus
   * a project-contact permission read for every event on the page.
   *
   * Read inside the trusted scope deliberately: the caller's authorization is
   * *decided from* the result (see
   * CRM_Volunteer_Permission::checkEventProjectManagement()), so the read
   * itself cannot be the thing that enforces it -- a guarded read would turn an
   * access decision into an exception. isActive() is left guarded for any
   * caller that is not making that decision.
   *
   * @param int|string $eventId
   * @return CRM_Volunteer_BAO_Project|null
   */
  public static function getEventProject($eventId) {
    $eventId = (int) $eventId;
    if ($eventId < 1) {
      return NULL;
    }

    if (!array_key_exists($eventId, self::$eventProjects)) {
      $projects = CRM_Volunteer_Permission::withInternalBypass(function() use ($eventId) {
        return self::retrieve(array(
          'check_permissions' => FALSE,
          'entity_id' => $eventId,
          // The literal, not CRM_Event_DAO_Event::getTableName(): that class
          // ships in the civi_event extension and this is reachable from a
          // stored entity_table value on a site where it is switched off.
          'entity_table' => 'civicrm_event',
        ));
      });
      self::$eventProjects[$eventId] = current($projects) ?: NULL;
    }

    return self::$eventProjects[$eventId];
  }

  /**
   * Detach any volunteer project from an event that is about to be deleted.
   *
   * Deleting an event used to leave the project pointing at a row that no
   * longer exists. There is no FK on entity_id -- the pair is a polymorphic
   * pointer with no declared dynamic FK -- so nothing cascaded and the only
   * symptom was one warning per read from getEntityAttributes().
   *
   * The project is unlinked, not deleted. Its needs and the volunteer
   * activities hanging off them are the organisation's record of who turned up
   * and for how long; discarding that because an event row was tidied away
   * would be data loss nobody asked for. The project simply becomes
   * standalone, which upgrade_2302 made a first-class state.
   *
   * @param int $eventId
   * @return int
   *   Number of projects detached.
   */
  public static function detachFromEvent($eventId) {
    $eventId = (int) $eventId;
    if ($eventId < 1) {
      return 0;
    }

    $detached = array();
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT id, title FROM civicrm_volunteer_project
        WHERE entity_table = 'civicrm_event' AND entity_id = %1",
      array(1 => array($eventId, 'Integer'))
    );
    while ($dao->fetch()) {
      $detached[(int) $dao->id] = $dao->title;
    }
    if (!$detached) {
      return 0;
    }

    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_volunteer_project
          SET entity_table = NULL, entity_id = NULL
        WHERE entity_table = 'civicrm_event' AND entity_id = %1",
      array(1 => array($eventId, 'Integer'))
    );
    self::flushEventProjectCache();

    \Civi::log()->info(sprintf(
      'Event %d is being deleted; volunteer project(s) %s are now standalone rather than orphaned.',
      $eventId,
      implode(', ', array_keys($detached))
    ));

    return count($detached);
  }

  /**
   * Discard the per-request event-project memo.
   */
  public static function flushEventProjectCache() {
    self::$eventProjects = array();
  }

  /**
   * Find out if a project is active
   *
   * @param $entityId
   * @param $entityTable
   * @return boolean|null Boolean if project exists, null otherwise
   */
  static function isActive($entityId, $entityTable) {
    $params['entity_id'] = $entityId;
    $params['entity_table'] = $entityTable;
    $projects = self::retrieve($params);

    if (count($projects) === 1) {
      $p = current($projects);
      return $p->is_active;
    }
    return NULL;
  }

  /**
   * Helper function to determine whether the current user should be allowed
   * to retrieve a project.
   *
   * @param int $projectId
   * @return boolean
   */
  private static function allowedToRetrieve($projectId = NULL) {
    // CRM_Core_Action::VIEW is satisfied by 'register to volunteer' or
    // 'edit all volunteer projects'. Project managers hold neither by
    // necessity, so checking VIEW alone locked an "edit own projects" owner out
    // of reading even their own projects -- which is what the manage-projects
    // grid does. Admit anyone who may manage projects; the caller is still
    // responsible for row-level scoping (see REQUIRED_CONTACTS_PARAM).
    $userCanView = CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::VIEW)
      || CRM_Volunteer_Permission::checkProjectManagement();

    $userCanViewRoster = FALSE;
    if (!$userCanView && !empty($projectId)) {
      $userCanViewRoster = CRM_Volunteer_Permission::checkProjectPerms(CRM_Volunteer_Permission::VIEW_ROSTER, $projectId);
    }

    return ($userCanView || $userCanViewRoster);
  }

  /**
   * Get a list of Projects filtered by project-fields
   * or related entities: Project-Contacts and Proximity (loc_block)
   * NOTE: related entities are not returned, just available for filtering.
   *
   * This function is invoked from within the web form layer and also from the
   * API layer.
   *
   * @see CRM_Volunteer_BAO_Project::create(), CRM_Volunteer_BAO_Project::buildContactJoin(), CRM_Volunteer_BAO_Project::buildProximityWhere()
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  public static function retrieve(array $params) {
    $result = array();

    $projectId = $params['id'] ?? NULL;
    // Absent means "check", consistently with
    // CRM_Volunteer_Permission::shouldCheckPermissions(). Reading the key
    // directly made a missing flag mean "do not check", so every direct BAO
    // reader -- retrieveByID(), the roster page, the log form -- was unguarded.
    if (CRM_Volunteer_Permission::shouldCheckPermissions($params)
      && !self::allowedToRetrieve($projectId)) {
      // Throw rather than calling CRM_Utils_System::permissionDenied(), which
      // only throws on some CMSes; this method is documented to return an
      // array, and returning NULL made callers iterate over NULL.
      throw new CRM_Core_Exception(
        ts('You do not have permission to view this volunteer project.', array('domain' => 'org.civicrm.volunteer')),
        CRM_Core_Exception::UNAUTHORIZED
      );
    }

    $query = CRM_Utils_SQL_Select::from('`civicrm_volunteer_project` vp')
      ->select('DISTINCT vp.*');

    if (!empty($params['project_contacts'])) {
      $contactJoin = self::buildContactJoin($params['project_contacts']);
      if ($contactJoin) {
        $query->join('vpc', $contactJoin);
      }
    }

    if (array_key_exists(self::REQUIRED_CONTACTS_PARAM, $params)) {
      $requiredJoin = self::buildContactJoin(
        (array) $params[self::REQUIRED_CONTACTS_PARAM],
        'vpc_required'
      );
      if (!$requiredJoin) {
        // The mandatory constraint could not be expressed at all -- for
        // instance there is no logged-in contact to own anything. Returning
        // every project would be the exact opposite of what was asked for, so
        // fail closed.
        return $result;
      }
      $query->join('vpc_required', $requiredJoin);
    }

    if (!empty($params['proximity']) && is_array($params['proximity'])) {
      self::applyLocationFilter($query, $params['proximity']);
    }

    if (isset($params['is_active'])) {
      if (CRM_Volunteer_BAO_Project::isOff($params['is_active'])) {
        $params['is_active'] = 0;
      } else {
        $params['is_active'] = 1;
      }
    }

    // normalize field names and get DAO defaults:
    $project = new CRM_Volunteer_BAO_Project($params);
    foreach ($project->fields() as $field) {
      $fieldName = $field['name'];
      if (isset($project->$fieldName)) {
        // Qualify with the table alias. civicrm_volunteer_project_contact also
        // has an `id`, so an unqualified column is ambiguous as soon as a join
        // is present -- which it is for the ownership filter applied to a user
        // holding only 'edit own volunteer projects', and for any
        // project_contacts or proximity filter.
        $query->where('!column = @value', array(
          'column' => 'vp.' . $fieldName,
          'value' => $project->$fieldName,
        ));
      }
    }

    $dao = self::executeQuery($query->toSQL());

    while ($dao->fetch()) {
      $result[(int) $dao->id] = new CRM_Volunteer_BAO_Project($dao);
    }
    $dao->free();

    return $result;
  }

  /**
   * Helper method to filter Projects by related contact.
   *
   * Conditionally invoked by CRM_Volunteer_BAO_Project::retrieve().
   *
   * @param array $projectContacts
   *   @see CRM_Volunteer_BAO_Project::create() for details on this parameter
   * @return mixed
   *   Boolean FALSE if no projects have the specified contact relationships;
   *   String SQL fragment otherwise
   */
  private static function buildContactJoin(array $projectContacts, $alias = 'vpc') {
    $result = FALSE;
    $onClauses = array();
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $alias)) {
      throw new CRM_Core_Exception('Invalid project-contact join alias.');
    }

    $relTypes = CRM_Core_OptionGroup::values(
      CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
      TRUE, FALSE, FALSE, NULL, 'name');

    foreach ($projectContacts as $relType => $contactIds) {
      if (!CRM_Utils_Type::validate($relType, 'Integer', FALSE)) {
        $relType = $relTypes[$relType] ?? NULL;
      }
      $relType = (int) $relType;
      if ($relType < 1) {
        continue;
      }

      $contactIds = array_values(array_unique(array_filter(
        array_map('intval', self::validateContactFormat($contactIds)),
        function($contactId) {
          return $contactId > 0;
        }
      )));
      if (!$contactIds) {
        continue;
      }
      $contactIds = implode(',', $contactIds);

      $onClauses[] = "($alias.contact_id IN ($contactIds) AND $alias.relationship_type_id = $relType)";
    }

    if (count($onClauses)) {
      // Clauses within one filter are OR-ed: "projects where contact X is a
      // beneficiary or contact Y is a manager". A constraint that must always
      // hold therefore cannot be merged into this group -- it needs its own
      // AND-ed join. See REQUIRED_CONTACTS_PARAM.
      $strOnClauses = implode(' OR ', $onClauses);
      $result = "INNER JOIN `civicrm_volunteer_project_contact` $alias
        ON vp.id = $alias.project_id AND ($strOnClauses)";
    }

    return $result;
  }

  /**
   * Add ordinary address or geocoded proximity conditions to a project query.
   *
   * @param array $params
   *   Address fields plus optional radius/unit and legacy lat/lon values.
   * @return bool
   *   TRUE when a location join/filter was added.
   */
  private static function applyLocationFilter(CRM_Utils_SQL_Select $query, array $params) {
    $location = self::normalizeLocationFilter($params);
    $radiusProvided = $location['radius'] !== NULL;
    $addressProvided = self::hasLocationAddress($location);
    $proximityAvailable = CRM_Volunteer_BAO_VolunteerUtil::isProximitySearchAvailable();

    // A stale/bookmarked radius cannot activate a feature which is not
    // available. The remaining address values still behave as normal filters.
    if (!$addressProvided && !($proximityAvailable && $radiusProvided)) {
      return FALSE;
    }

    $query->join('loc', 'INNER JOIN `civicrm_loc_block` loc ON loc.id = vp.loc_block_id')
      ->join('civicrm_address', 'INNER JOIN `civicrm_address` ON civicrm_address.id = loc.address_id');

    if ($proximityAvailable && $radiusProvided) {
      $query->where(self::buildProximityWhere($location));
      return TRUE;
    }

    if ($location['street_address'] !== '') {
      $query->where('civicrm_address.street_address LIKE @street_address', array(
        'street_address' => '%' . self::escapeLikeValue($location['street_address']) . '%',
      ));
    }
    if ($location['city'] !== '') {
      $query->where('LOWER(civicrm_address.city) = LOWER(@city)', array('city' => $location['city']));
    }
    if ($location['postal_code'] !== '') {
      $query->where('civicrm_address.postal_code LIKE @postal_code', array(
        'postal_code' => self::escapeLikeValue($location['postal_code']) . '%',
      ));
    }
    if ($location['state_province_id']) {
      $query->where('civicrm_address.state_province_id = #state_province_id', array(
        'state_province_id' => $location['state_province_id'],
      ));
    }
    if ($location['country_id']) {
      $query->where('civicrm_address.country_id = #country_id', array(
        'country_id' => $location['country_id'],
      ));
    }

    return TRUE;
  }

  /**
   * Normalize public location-search input without widening invalid filters.
   */
  private static function normalizeLocationFilter(array $params) {
    $location = array(
      'street_address' => trim((string) ($params['street_address'] ?? '')),
      'city' => trim((string) ($params['city'] ?? '')),
      'postal_code' => trim((string) ($params['postal_code'] ?? '')),
      'state_province_id' => NULL,
      'country_id' => NULL,
      'radius' => NULL,
      'unit' => strtolower(trim((string) ($params['unit'] ?? ''))),
      'lat' => $params['lat'] ?? NULL,
      'lon' => $params['lon'] ?? NULL,
    );

    $stateProvince = $params['state_province_id'] ?? NULL;
    if ($stateProvince !== NULL && $stateProvince !== '') {
      if (!CRM_Utils_Type::validate($stateProvince, 'Positive', FALSE)) {
        throw new CRM_Core_Exception(ts('Select a valid State/Province.', array('domain' => 'org.civicrm.volunteer')));
      }
      $location['state_province_id'] = (int) $stateProvince;
    }

    $country = $params['country_id'] ?? ($params['country'] ?? NULL);
    if ($country !== NULL && $country !== '') {
      $location['country_id'] = self::resolveCountryId($country);
      if (!$location['country_id']) {
        throw new CRM_Core_Exception(ts('Select a valid country.', array('domain' => 'org.civicrm.volunteer')));
      }
    }

    if (array_key_exists('radius', $params) && $params['radius'] !== NULL && trim((string) $params['radius']) !== '') {
      $location['radius'] = $params['radius'];
    }
    if ($location['unit'] === 'mile') {
      $location['unit'] = 'miles';
    }

    return $location;
  }

  /**
   * Resolve the current numeric country selector and legacy name/ISO values.
   */
  private static function resolveCountryId($country) {
    if (CRM_Utils_Type::validate($country, 'Positive', FALSE)) {
      return (int) $country;
    }

    $country = trim((string) $country);
    foreach (CRM_Core_PseudoConstant::country() as $id => $name) {
      if (strcasecmp($country, $name) === 0) {
        return (int) $id;
      }
    }
    foreach (CRM_Core_PseudoConstant::countryIsoCode() as $id => $isoCode) {
      if (strcasecmp($country, $isoCode) === 0) {
        return (int) $id;
      }
    }
    return NULL;
  }

  /**
   * Whether any user-facing address field can define a filter/search center.
   */
  private static function hasLocationAddress(array $location) {
    return $location['street_address'] !== ''
      || $location['city'] !== ''
      || $location['postal_code'] !== ''
      || !empty($location['state_province_id'])
      || !empty($location['country_id']);
  }

  /**
   * Build a proximity SQL fragment using core's configured geocoder and math.
   */
  private static function buildProximityWhere(array $location) {
    if (!CRM_Utils_Rule::numeric($location['radius']) || (float) $location['radius'] <= 0) {
      throw new CRM_Core_Exception(ts('Enter a distance greater than zero.', array('domain' => 'org.civicrm.volunteer')));
    }
    if (!in_array($location['unit'], array('km', 'miles'), TRUE)) {
      throw new CRM_Core_Exception(ts('Choose miles or kilometers.', array('domain' => 'org.civicrm.volunteer')));
    }

    $latitude = $location['lat'];
    $longitude = $location['lon'];
    if (!CRM_Utils_Rule::numeric($latitude) || !CRM_Utils_Rule::numeric($longitude)) {
      if (!self::hasLocationAddress($location)) {
        throw new CRM_Core_Exception(ts('Enter a location to search around.', array('domain' => 'org.civicrm.volunteer')));
      }

      try {
        $coordinates = \Civi\Api4\Address::getCoordinates(FALSE)
          ->setAddress(self::buildGeocodableAddress($location))
          ->execute()
          ->first();
      }
      catch (Throwable $e) {
        $coordinates = NULL;
      }
      $latitude = $coordinates['geo_code_1'] ?? NULL;
      $longitude = $coordinates['geo_code_2'] ?? NULL;
      if (!CRM_Utils_Rule::numeric($latitude) || !CRM_Utils_Rule::numeric($longitude)) {
        throw new CRM_Core_Exception(ts(
          "We couldn't find that location. Check the address and try again.",
          array('domain' => 'org.civicrm.volunteer')
        ));
      }
    }

    $distance = (float) $location['radius'] * ($location['unit'] === 'miles' ? 1609.344 : 1000.0);
    return CRM_Contact_BAO_ProximityQuery::where(
      (float) $latitude,
      (float) $longitude,
      $distance,
      'civicrm_address'
    );
  }

  /**
   * Compose a free-form address without making a postal code look like a
   * street number to providers such as Nominatim.
   *
   * Full addresses retain their conventional street/city/state/postal order.
   * When neither a street nor city is present, putting the postal code first
   * makes it the primary search term instead of allowing the state name to be
   * interpreted as a street in another part of the country.
   */
  private static function buildGeocodableAddress(array $location): string {
    $stateProvince = $location['state_province_id']
      ? CRM_Core_PseudoConstant::stateProvince($location['state_province_id'])
      : NULL;
    $country = $location['country_id']
      ? CRM_Core_PseudoConstant::country($location['country_id'])
      : NULL;

    if ($location['postal_code'] !== '' && $location['street_address'] === '' && $location['city'] === '') {
      $addressParts = array($location['postal_code'], $stateProvince, $country);
    }
    else {
      $addressParts = array(
        $location['street_address'],
        $location['city'],
        $stateProvince,
        $location['postal_code'],
        $country,
      );
    }

    return implode(', ', array_filter($addressParts, static function($value) {
      return $value !== NULL && $value !== '';
    }));
  }

  /**
   * Escape LIKE metacharacters while leaving SQL quoting to SQL_Select.
   */
  private static function escapeLikeValue($value) {
    return strtr((string) $value, array('\\' => '\\\\', '%' => '\\%', '_' => '\\_'));
  }

  /**
   * Wrapper method for retrieve
   *
   * @param mixed $id Int or int-like string representing project ID
   * @param array $params
   *   Optional trusted retrieval parameters. `id` is always replaced by the
   *   validated method argument.
   * @return CRM_Volunteer_BAO_Project
   */
  static function retrieveByID($id, array $params = array()) {
    $id = (int) CRM_Utils_Type::validate($id, 'Integer');

    $params['id'] = $id;
    $projects = self::retrieve($params);

    if (!array_key_exists($id, $projects)) {
      throw new CRM_Core_Exception(ts('No volunteer project with ID %1 exists.', array(1 => $id, 'domain' => 'org.civicrm.volunteer')));
    }

    return $projects[$id];
  }

  /**
   * Convert truthy to Boolean.
   * Empty or null return FALSE (on)
   * FALSE, 0, '0' return TRUE (Off)
   *
   * @param type $value
   * @return boolean
   * @access public
   */
  public static function isOff($value) {
    return in_array($value, array(FALSE, 0, '0'), TRUE);
  }

  /**
   * Fetches attributes for the associated entity and puts them in
   * $this->entityAttributes, using a common vocabulary defined in $arrayKeys.
   *
   * @see CRM_Volunteer_BAO_Project::$entityAttributes
   * @return array
   */
  public function getEntityAttributes() {
    if (!$this->entityAttributes) {
      $arrayKeys = array('start_time', 'title', 'campaign_id');
      $this->entityAttributes = array_fill_keys($arrayKeys, NULL);

      if ($this->entity_table && $this->entity_id) {
        try {
          switch ($this->entity_table) {
            case 'civicrm_event' :
              // get(FALSE), matching _get_start_date() and _get_end_date().
              // The permission-checked form was the odd one out and it failed
              // silently: a volunteer manager without `access CiviEvent` got a
              // logged warning and a NULL title per event-linked project,
              // which on the event tab left the *required* project title
              // field unprefilled. Authorization to read the project has
              // already been established by whoever loaded it.
              // civicrm_event.campaign_id carries 'component' => 'CiviCampaign',
              // so API4 omits the field outright when the component is off.
              // Asking for it explicitly only when it exists keeps the
              // behaviour legible instead of depending on that omission.
              $select = array('title', 'start_date');
              if (CRM_Core_Component::isEnabled('CiviCampaign')) {
                $select[] = 'campaign_id';
              }
              $result = self::getEvent($this->entity_id, $select);
              if (!$result) {
                // getEvent() returns NULL for a missing row as well as for a
                // disabled component, so the diagnostic moved here from the
                // catch block below: ->single() used to raise the missing-row
                // case as an exception.
                throw new CRM_Core_Exception('No associated entity');
              }
              $this->entityAttributes['title'] = $result['title'];
              $this->entityAttributes['start_time'] = $result['start_date'];
              // civicrm_event has a campaign of its own, and it is a far better
              // default for the event's volunteer project than the site-wide
              // volunteer_project_default_campaign setting.
              $this->entityAttributes['campaign_id'] = $result['campaign_id'] ?? NULL;
              break;
          }
        }
        catch (Exception $e) {
          $format = 'Could not fetch entity attributes for volunteer project with ID %d. '
            . 'No %s with ID %d is readable; perhaps it has been deleted, or its '
            . 'component is disabled.';
          $msg = sprintf($format, $this->id, $this->entity_table, $this->entity_id);
          \Civi::log()->warning($msg);
        }
      }
    }
    return $this->entityAttributes;
  }

  /**
   * Flexible-need ID for a project, read under a row lock.
   *
   * getFlexibleNeedID() is a plain SELECT, so under InnoDB's default
   * REPEATABLE READ it answers from the transaction's read view. That view is
   * established by the transaction's *first* consistent read, not by a
   * subsequent `SELECT ... FOR UPDATE`, and ROLLBACK TO SAVEPOINT does not
   * reset it. In a nested transaction -- Project::create() calling
   * Need::create(), for example -- the uniqueness check could therefore answer
   * from a snapshot predating a concurrent writer's commit and let two
   * flexible needs through.
   *
   * A locking read always sees the latest committed row, which is what the
   * uniqueness decision requires.
   *
   * @param int $project_id
   *
   * @return int|null
   */
  public static function getFlexibleNeedIDForUpdate($project_id) {
    if (!CRM_Utils_Type::validate($project_id, 'Positive', FALSE)) {
      return NULL;
    }

    $needId = CRM_Core_DAO::singleValueQuery(
      'SELECT id FROM civicrm_volunteer_need
        WHERE project_id = %1 AND is_flexible = 1
        ORDER BY id LIMIT 1
        FOR UPDATE',
      array(1 => array((int) $project_id, 'Integer'))
    );

    return $needId ? (int) $needId : NULL;
  }

  /**
   * Given project_id, return ID of flexible Need
   *
   * @param int $project_id
   * @return mixed Integer on success, else NULL
   */
  public static function getFlexibleNeedID ($project_id) {
    if (!CRM_Utils_Type::validate($project_id, 'Positive', FALSE)) {
      return NULL;
    }

    $needId = CRM_Core_DAO::singleValueQuery(
      'SELECT id FROM civicrm_volunteer_need
        WHERE project_id = %1 AND is_flexible = 1
        ORDER BY id LIMIT 1',
      array(1 => array((int) $project_id, 'Integer'))
    );

    return $needId ? (int) $needId : NULL;
  }

  /**
   * This function takes a list of contact ids as either a
   * single string, array of string, comma separated string
   * array of ints or single int and returns it as an array that
   * create contacts can accept.
   *
   * @param $contacts
   */
  public static function validateContactFormat($contacts) {
    if(is_string($contacts)) {
      $contacts = explode(",",$contacts);
    }
    if(!is_array($contacts)) {
      $contacts = array($contacts);
    }
    return $contacts;
  }

  /**
   * This function fetches the defaults from civicrm settings
   * And puts them into the appropriate data format to return
   * to the angular front-end
   *
   * @return array
   * @throws CRM_Core_Exception
   */
  public static function composeDefaultSettingsArray() {
    $defaults = array();

    $defaults['is_active'] = CRM_Volunteer_Api4::getSetting('volunteer_project_default_is_active');
    $defaults['campaign_id'] = CRM_Volunteer_Api4::getSetting('volunteer_project_default_campaign');
    $defaults['loc_block_id'] = CRM_Volunteer_Api4::getSetting('volunteer_project_default_locblock');

    // No uf_group_id here: every profile below overrides it from the setting,
    // and resolving the volunteer_sign_up profile eagerly meant an
    // UFGroup.getvalue() -- which throws when the profile has been renamed or
    // deleted -- ran on every single project create for a value that was then
    // discarded.
    $coreDefaultProfile = array(
      "is_active" => "1",
      "module" => "CiviVolunteer",
      "entity_table" => "civicrm_volunteer_project",
      "weight" => 1,
      "module_data" => array("audience" => "primary"),
    );

    $profiles = array();
    $profileByType = CRM_Volunteer_Api4::getSetting('volunteer_project_default_profiles');

    // The default_profiles setting has no static default (see
    // settings/volunteer.setting.php), so resolve the shipped volunteer_sign_up
    // profile here when an administrator has not chosen one. "Not chosen"
    // includes the shape the settings form stores when every audience is left
    // blank -- an array of empty lists -- not only a missing setting.
    $hasChosenProfile = FALSE;
    foreach ((array) $profileByType as $profileForType) {
      if (!empty($profileForType)) {
        $hasChosenProfile = TRUE;
        break;
      }
    }
    if (!$hasChosenProfile) {
      $signupProfileId = self::getDefaultSignupProfileId();
      if ($signupProfileId) {
        $profileByType = array('primary' => array($signupProfileId));
      }
    }

    // The settings form stores NULL for an audience with no selection
    // (CRM_Volunteer_Form_Settings::postProcess), so both levels need casting:
    // foreach over NULL is a PHP 8 warning.
    foreach ((array) $profileByType as $audience => $profileForType) {
      foreach ((array) $profileForType as $profileId) {
        $profile = $coreDefaultProfile;
        $profile['uf_group_id'] = $profileId;
        $profile['weight'] = count($profiles) + 1;
        $profile['module_data']['audience'] = $audience;
        $profiles[] = $profile;
      }
    }

    $defaults['profiles'] = $profiles;
    $defaults['relationships'] = self::getDefaultProjectContacts();

    return $defaults;
  }

  /**
   * ID of the profile shipped for volunteer signup, or NULL if it is gone.
   *
   * Never throws: administrators rename and delete profiles, and that must not
   * be able to break project creation or a settings-metadata rebuild.
   *
   * @return int|null
   */
  public static function getDefaultSignupProfileId() {
    try {
      $profile = \Civi\Api4\UFGroup::get(FALSE)
        ->addSelect('id')
        ->addWhere('name', '=', 'volunteer_sign_up')
        ->setLimit(1)
        ->execute()
        ->first();
    }
    catch (Throwable $e) {
      return NULL;
    }
    return empty($profile['id']) ? NULL : (int) $profile['id'];
  }

  /**
   * Get default contacts for a new Volunteer Project.
   *
   * @return array
   *   An array of the default contact IDs for a project, keyed by the type of
   *   volunteer project relationship. The type is represented as an INT, the
   *   value of the option in the volunteer_project_relationship option group.
   */
  public static function getDefaultProjectContacts() {
    $defaults = array();
    $optionMap = CRM_Core_OptionGroup::values("volunteer_project_relationship", TRUE, FALSE, FALSE, NULL, 'name');
    $projectContactsSetting = CRM_Volunteer_Api4::getSetting('volunteer_project_default_contacts');

    // the array of settings is keyed by the name of the volunteer project relationship
    foreach ((array) $projectContactsSetting as $optionName => $defaultConfig) {
      $contactIds = array();
      switch ($defaultConfig['mode'] ?? NULL) {
        case 'contact':
          $contactIds = explode(',', $defaultConfig['value']);
          break;
        case 'relationship':
          list($relationshipTypeId, $direction) = explode('_', $defaultConfig['value']);
          $reverseDirection = ($direction === 'a' ? 'b' : 'a');
          $api = \Civi\Api4\Relationship::get(FALSE)
            ->addSelect("contact_id_{$reverseDirection}")
            ->addWhere("contact_id_{$direction}", '=', CRM_Core_Session::getLoggedInContactID())
            ->addWhere('is_active', '=', TRUE)
            ->addWhere('relationship_type_id', '=', $relationshipTypeId)
            ->execute();

          $contactIds = array();
          foreach ($api as $r) {
            $contactIds[] = $r["contact_id_{$reverseDirection}"];
          }

          break;
        case 'acting_contact':
          $contactIds = array(CRM_Core_Session::getLoggedInContactID());
          break;
      }

      $optionValue = $optionMap[$optionName] ?? NULL;
      if (!$optionValue) {
        continue;
      }
      $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds), function($contactId) {
        return $contactId > 0;
      })));
      $defaults[(int) $optionValue] = $contactIds;
    }

    return $defaults;
  }

  /**
   * The types of
   *
   * @var array
   */
  public static function getProjectProfileAudienceTypes()
  {
    return array(
      "primary" => array(
        "type" => "primary",
        "description" => ts("Profile(s) for Individual Registration", array('domain' => 'org.civicrm.volunteer')),
        "label" => ts("Individual Registration", array('domain' => 'org.civicrm.volunteer'))
      ),
      "additional" => array(
        "type" => "additional",
        "description" => ts("Profile(s) for Group Registration", array('domain' => 'org.civicrm.volunteer')),
        "label" => ts("Group Registration", array('domain' => 'org.civicrm.volunteer'))
      ),
      "both" => array(
        "type" => "both",
        "description" => ts("Profile(s) for Both Individual and Group Registration", array('domain' => 'org.civicrm.volunteer')),
        "label" => ts("Both", array('domain' => 'org.civicrm.volunteer'))
      ),
    );
  }
  /**
   * Sets and returns the start date of the entity associated with this Project
   *
   * @access private
   */
  private function _get_start_date() {
    if (!$this->start_date) {
      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $result = self::getEvent($this->entity_id, array('start_date'));
            $this->start_date = $result['start_date'] ?? NULL;
            break;
        }
      }
    }
    return $this->start_date;
  }

  /**
   * Sets and returns the end date of the entity associated with this Project
   *
   * @access private
   */
  private function _get_end_date() {
    if (!$this->end_date) {
      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $result = self::getEvent($this->entity_id, array('end_date'));
            $this->end_date = $result['end_date'] ?? NULL;
            break;
        }
      }
    }
    return $this->end_date;
  }

  /**
   * Read one event, or NULL if there is nothing to read it with.
   *
   * \Civi\Api4\Event lives in the civi_event extension, not core -- exactly
   * like \Civi\Api4\Campaign, which getManageOverview() and
   * CRM_Volunteer_BAO_NeedSearch already guard for. Calling it on a site with
   * CiviEvent switched off is a fatal, and a project can still carry an
   * entity_id from a period when the component was on. There was no such guard
   * anywhere, and two of the three call sites were outside any try/catch.
   *
   * @param int|string $eventId
   * @param string[] $select
   * @return array|null
   */
  private static function getEvent($eventId, array $select) {
    if (!$eventId || !CRM_Core_Component::isEnabled('CiviEvent')) {
      return NULL;
    }

    return \Civi\Api4\Event::get(FALSE)
      ->setSelect($select)
      ->addWhere('id', '=', $eventId)
      ->execute()
      ->first();
  }

  /**
   * Sets $this->needs and returns the Needs associated with this Project. Delegate of __get().
   * Note: only active, visible needs are returned.
   *
   * @return array Needs as returned by API
   */
  private function _get_needs() {
    if (empty($this->needs)) {
      $result = \Civi\Api4\VolunteerNeed::get(FALSE)
        ->addSelect('*')
        ->addWhere('is_active', '=', TRUE)
        ->addWhere('project_id', '=', $this->id)
        ->addWhere('visibility_id', '=', CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public'))
        ->addOrderBy('start_time', 'ASC')
        ->execute();
      $this->needs = array();
      foreach ($result as $need) {
        $this->needs[(int) $need['id']] = $need;
      }
      $this->needs = CRM_Volunteer_BAO_Need::addDisplayFields($this->needs);
      foreach (array_keys($this->needs) as $need_id) {
        $this->needs[$need_id]['quantity_assigned'] = CRM_Volunteer_BAO_Need::getAssignmentCount($need_id);
      }
    }

    return $this->needs;
  }

  /**
   * Sets $this->roles and returns the Roles associated with this Project. Delegate of __get().
   * Note: only roles for active, visible needs are returned.
   *
   * @return array Roles, labels keyed by IDs
   */
  private function _get_roles() {
    if (empty($this->roles)) {
      $roles = array();

      if (empty($this->needs)) {
        $this->_get_needs();
      }

      $roleIds = array_values(array_unique(array_filter(array_map(
        function($need) {
          return empty($need['is_flexible']) ? (int) ($need['role_id'] ?? 0) : 0;
        },
        $this->needs
      ))));
      $roleLabels = array();
      if ($roleIds) {
        $optionValues = \Civi\Api4\OptionValue::get(FALSE)
          ->addSelect('value', 'label')
          ->addWhere('option_group_id.name', '=', CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP)
          ->addWhere('value', 'IN', $roleIds)
          ->execute();
        foreach ($optionValues as $optionValue) {
          $roleLabels[(int) $optionValue['value']] = $optionValue['label'];
        }
      }

      foreach ($this->needs as $need) {
        if (!empty($need['is_flexible'])) {
          $roles[CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID] = CRM_Volunteer_BAO_Need::getFlexibleRoleLabel();
        } else {
          $role_id = $need['role_id'] ?? NULL;
          $roles[$role_id] = $roleLabels[(int) $role_id] ?? (string) $role_id;
        }
      }
      asort($roles);
      $this->roles = $roles;
    }

    return $this->roles;
  }


  /**
   * Sets and returns $this->open_needs. Delegate of __get().
   *
   * @return array Keyed by Need ID, with a subarray keyed by 'label' and 'role_id'
   */
  private function _get_open_needs() {
    if (empty($this->open_needs)) {

      if (empty($this->needs)) {
        $this->_get_needs();
      }

      $now = time();
      foreach ($this->needs as $id => $need) {
        if (
          // open needs must have a start time; this disqualifies flexible needs
          !empty($need['start_time'])
          // open needs must not have all positions assigned
          && ($need['quantity'] > $need['quantity_assigned'])
          // open needs must either:
          && (
            // 1) start after now,
            strtotime($need['start_time']) >= $now
            // 2) end after now, or
            || strtotime($need['end_time'] ?? '') >= $now
            // 3) be open until filled
            || (empty($need['end_time']) && empty($need['duration']))
          )
        ) {
          $this->open_needs[$id] = $need;
        }
      }
    }

    return $this->open_needs;
  }

  /**
   * Sets and returns $this->flexible_need_id. Delegate of __get().
   *
   * @return mixed Integer if project has a flexible need, else NULL
   */
  private function _get_flexible_need_id() {
    return self::getFlexibleNeedID($this->id);
  }

}
