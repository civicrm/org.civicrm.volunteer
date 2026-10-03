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
 * Collection of upgrade steps
 */
class CRM_Volunteer_Upgrader extends CRM_Extension_Upgrader_Base {

  const customContactGroupName = 'Volunteer_Information';
  const customContactTypeName = 'Volunteer';
  const skillLevelOptionGroupName = 'skill_level';

  public function postInstall() {
    // Managed entities (managed/Registry.mgd.php) own the Volunteer activity
    // type and the Available / No-show activity statuses, and CiviCRM
    // reconciles them during installation before this hook runs. This call is
    // therefore a get-or-create acting as a lookup: it returns the managed
    // record's stored value, and only creates anything if reconciliation has
    // not happened yet.
    $volActivityTypeId = $this->createActivityType(CRM_Volunteer_BAO_Assignment::CUSTOM_ACTIVITY_TYPE);
    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign('volunteer_custom_activity_type_name', CRM_Volunteer_BAO_Assignment::CUSTOM_ACTIVITY_TYPE);
    $smarty->assign('volunteer_custom_group_name', CRM_Volunteer_BAO_Assignment::CUSTOM_GROUP_NAME);
    $smarty->assign('volunteer_custom_option_group_name', CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP);
    $smarty->assign('volunteer_activity_type_id', $volActivityTypeId);

    $customIDs = $this->findCustomGroupValueIDs();
    $smarty->assign('customIDs', $customIDs);
    $this->executeCustomDataTemplateFile('volunteer-customdata.xml.tpl');

    $this->createVolunteerContactType();
    $volContactTypeCustomGroupID = $this->createVolunteerContactCustomGroup();
    $this->createVolunteerContactCustomFields($volContactTypeCustomGroupID);

    $this->installCommendationActivityType();

    // xml/auto_install.xml holds the shipped volunteer_sign_up profile. Older
    // civix loaded that filename by convention; civix 25.10 does not, and
    // nothing replaced it -- so a fresh installation ended up with no signup
    // profile at all, even though the volunteer_default_profile setting and
    // CRM_Volunteer_BAO_Project's audience resolution both look for it. Sites
    // that reached 2.5 by upgrading still have it from years ago, which is why
    // this went unnoticed.
    $this->executeCustomDataFileByAbsPath($this->extensionDir . '/xml/auto_install.xml');

    $this->installVolMsgWorkflowTpls();
    // Fresh extension tables come from schema/*.entityType.php. Historical
    // migration helpers remain available to their numbered upgrade steps but
    // are not rerun against a canonical fresh installation.

    // uncomment the next line to insert sample data
    // $this->executeSqlFile('sql/volunteer_sample.mysql');

    // See VOL-237. Avoid order of operation problems by assigning a value to the
    // slider_widget_fields setting after the install, which is responsible for
    // creating both the setting and the custom field whose ID is used in the
    // initial value.
    $customFieldId = \Civi\Api4\CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id.name', '=', 'Volunteer_Information')
      ->addWhere('name', '=', 'camera_skill_level')
      ->execute()
      ->first()['id'] ?? NULL;
    if (!$customFieldId) {
      throw new CRM_Core_Exception(ts('The camera skill level custom field was not installed.', array('domain' => 'org.civicrm.volunteer')));
    }
    _volunteer_update_slider_fields(array(CRM_Core_Action::ADD => $customFieldId));
  }

  /**
   * Installs option group and options for project relationships.
   */
  public function installProjectRelationships() {
    $groupName = CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP;
    $optionGroup = \Civi\Api4\OptionGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', $groupName)
      ->execute()
      ->first();
    if (!$optionGroup) {
      $optionGroup = \Civi\Api4\OptionGroup::create(FALSE)
        ->addValue('name', $groupName)
        ->addValue('title', 'Volunteer Project Relationship')
        ->addValue('description', ts("Used to describe a contact's relationship to a project at large (e.g., beneficiary, manager). Not to be confused with contact-to-contact relationships.", array('domain' => 'org.civicrm.volunteer')))
        ->addValue('is_reserved', TRUE)
        ->addValue('is_active', TRUE)
        ->execute()
        ->first();
    }
    $optionGroupId = (int) $optionGroup['id'];

    $optionDefaults = array(
      'is_active' => 1,
      'is_reserved' => 1,
      'option_group_id' => $optionGroupId,
    );

    $options = array(
      array(
        'name' => 'volunteer_owner',
        'label' => ts('Owner', array('domain' => 'org.civicrm.volunteer')),
        'description' => ts('This contact owns the volunteer project. Useful if restricting edit/delete privileges.', array('domain' => 'org.civicrm.volunteer')),
        'value' => 1,
        'weight' => 1,
      ),
      array(
        'name' => 'volunteer_manager',
        'label' => ts('Manager', array('domain' => 'org.civicrm.volunteer')),
        'description' => ts('This contact manages the volunteers in a project and will receive related notifications, etc.', array('domain' => 'org.civicrm.volunteer')),
        'value' => 2,
        'weight' => 2,
      ),
      array(
        'name' => 'volunteer_beneficiary',
        'label' => ts('Beneficiary', array('domain' => 'org.civicrm.volunteer')),
        'description' => ts('This contact benefits from the volunteer project (e.g., if organizations are brokering volunteers to other orgs).', array('domain' => 'org.civicrm.volunteer')),
        'value' => 3,
        'weight' => 3,
      ),
    );

    foreach ($options as $opt) {
      // Managed records run before postInstall on a fresh installation. Match
      // only immutable identity fields so translated or administrator-edited
      // labels/descriptions do not make an existing option look absent.
      $existing = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('id')
        ->addWhere('option_group_id', '=', $optionGroupId)
        ->addWhere('name', '=', $opt['name'])
        ->execute()
        ->first();
      if (!$existing) {
        \Civi\Api4\OptionValue::create(FALSE)
          ->setValues(array_merge($optionDefaults, $opt))
          ->execute();
      }
    }
  }

  public function installVolMsgWorkflowTpls() {
    // Reuse the option group when it already exists rather than relying on a
    // create attempt failing: API4 reports a duplicate as a DB-level error
    // whose code cannot be matched as reliably as APIv3's 'already exists'.
    $optionGroupId = \Civi\Api4\OptionGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'msg_tpl_workflow_volunteer')
      ->execute()
      ->first()['id'] ?? NULL;
    if (!$optionGroupId) {
      $optionGroupId = \Civi\Api4\OptionGroup::create(FALSE)
        ->addValue('name', 'msg_tpl_workflow_volunteer')
        ->addValue('title', ts("Message Template Workflow for Volunteers", array('domain' => 'org.civicrm.volunteer')))
        ->addValue('description', ts("Message Template Workflow for Volunteers", array('domain' => 'org.civicrm.volunteer')))
        ->addValue('is_reserved', TRUE)
        ->addValue('is_active', TRUE)
        ->execute()
        ->first()['id'];

      // VOL-288: Prevent caching-related CRM_Core_Exception: "N is not a valid option for field option_group_id"
      Civi::rebuild(array('metadata' => TRUE))->execute();
    }

    $msgTplDefaults = array(
      'is_active' => 1,
      'is_default' => 1,
      'is_reserved' => 0,
    );

    $msgTpls = array(
      array(
        'description' => ts('Email sent to volunteers who sign themselves up for volunteer opportunities.', array('domain' => 'org.civicrm.volunteer')),
        'label' => ts('Volunteer - Registration (on-line)', array('domain' => 'org.civicrm.volunteer')),
        'name' => 'volunteer_registration',
        'subject' => ts("Volunteer Confirmation", array('domain' => 'org.civicrm.volunteer')),
      ),
    );

    $baseDir = CRM_Extension_System::singleton()->getMapper()->keyToBasePath('org.civicrm.volunteer') . '/';
    foreach ($msgTpls as $i => $msgTpl) {
      $optionValue = \Civi\Api4\OptionValue::create(FALSE)
        ->setValues(array(
          'description' => $msgTpl['description'],
          'is_active' => TRUE,
          'is_reserved' => TRUE,
          'label' => $msgTpl['label'],
          'name' => $msgTpl['name'],
          'option_group_id' => $optionGroupId,
          'value' => ++$i,
          'weight' => $i,
        ))
        ->execute()
        ->first();
      $txt = file_get_contents($baseDir . 'CRM/Volunteer/Upgrader/2.0.alpha1.msg_template/' . $msgTpl['name'] . '_text.tpl');
      $html = file_get_contents($baseDir . 'CRM/Volunteer/Upgrader/2.0.alpha1.msg_template/' . $msgTpl['name'] . '_html.tpl');

      $params = array_merge($msgTplDefaults, array(
        'msg_title' => $msgTpl['label'],
        'msg_subject' => $msgTpl['subject'],
        'msg_text' => $txt,
        'msg_html' => $html,
        'workflow_id' => $optionValue['id'],
      ));
      \Civi\Api4\MessageTemplate::create(FALSE)
        ->setValues($params)
        ->execute();
    }
  }

  /**
   * Makes schema changes to accommodate 2.0 functionality/refactoring.
   *
   * Used in both the install and the upgrade.
   */
  public function schemaUpgrade20() {
    $this->installProjectRelationships();
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_volunteer_project', 'target_contact_id', FALSE)) {
      $this->executeSqlFile('sql/volunteer_upgrade_2.0.sql');
    }
  }

  /**
   * Makes schema changes to support fuzzy dates for needs (VOL-142).
   *
   * Used in both the install and the upgrade.
   */
  public function addNeedEndDate() {
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_volunteer_need', 'end_time', FALSE)) {
      $this->executeSqlFile('sql/volunteer_need_end_date.sql');
    }
  }

  /**
   * Migration of project titles into civicrm_volunteer_project.
   *
   * Populates the title field of existing projects based on the title of the
   * associated entity (probably civicrm_event).
   */
  private function migrateProjectTitles() {
    $dao = CRM_Core_DAO::executeQuery('
      SELECT DISTINCT `entity_table`
      FROM `civicrm_volunteer_project`
    ');
    while ($dao->fetch()) {
      // SR-004 (security review 2026-08-23): the value is spliced into the
      // query below as a table identifier. It is only ever written by this
      // extension ('civicrm_event' or NULL), but validate before use so a
      // corrupted row cannot inject through this legacy upgrade step.
      if (!preg_match('/^[a-z_][a-z0-9_]*$/', (string) $dao->entity_table)) {
        continue;
      }
      $query = '
        UPDATE `civicrm_volunteer_project` AS `project`
        INNER JOIN ' . $dao->entity_table . ' AS `entity`
        ON `project`.`entity_id` = `entity`.`id`
        SET `project`.`title` = `entity`.`title`
        WHERE `project`.`entity_table` = %1';
      CRM_Core_DAO::executeQuery($query, array(
        1 => array($dao->entity_table, 'String')
      ));
    }
  }

  public function installCommendationActivityType() {
    $activityTypeID = $this->createActivityType(CRM_Volunteer_BAO_Commendation::CUSTOM_ACTIVITY_TYPE,
      ts('Volunteer Commendation', array('domain' => 'org.civicrm.volunteer'))
    );

    $customGroup = $this->createPossibleDuplicateRecord('CustomGroup', array(
      'extends' => 'Activity',
      'extends_entity_column_value' => $activityTypeID,
      'is_reserved' => 1,
      'name' => CRM_Volunteer_BAO_Commendation::CUSTOM_GROUP_NAME,
      'title' => ts('Volunteer Commendation', array('domain' => 'org.civicrm.volunteer')),
    ));

    // 'CustomField', not 'customField': createPossibleDuplicateRecord() scopes
    // its duplicate lookup by owning group only for the exact entity name, so
    // the lowercase spelling searched every custom group for the field name.
    //
    // And the group's ID, not its name. APIv3 resolved a group name in
    // custom_group_id; API4 requires the integer and rejects the string with
    // "One of the parameters (value: volunteer_commendation) is not of the type
    // Int". install() calls this method, so a fresh installation of the
    // extension failed outright -- invisible on sites that reached 2.5 by
    // upgrading, because upgrade_1403 had already created the field years
    // earlier and the lookup above short-circuits.
    $this->createPossibleDuplicateRecord('CustomField', array(
      'custom_group_id' => $customGroup['id'],
      'data_type' => 'Int',
      'html_type' => 'Text',
      'is_searchable' => 0,
      'label' => ts('Volunteer Project ID', array('domain' => 'org.civicrm.volunteer')),
      'name' => CRM_Volunteer_BAO_Commendation::PROJECT_REF_FIELD_NAME,
    ));
  }

  /**
   * CiviVolunteer 1.3 introduces target contacts for volunteer projects. The
   * requisite schema change is made here.
   *
   * @return boolean TRUE on success
   */
  public function upgrade_1300() {
    $this->ctx->log->info('Applying update 1300');
    CRM_Core_DAO::executeQuery('
      ALTER TABLE `civicrm_volunteer_project`
      ADD `target_contact_id` INT(10) UNSIGNED DEFAULT NULL
      COMMENT "FK to contact id. Represents the target or beneficiary of the volunteer project."
      AFTER  `entity_id`
    ');
    CRM_Core_DAO::executeQuery('
      ALTER TABLE `civicrm_volunteer_project`
      ADD CONSTRAINT `FK_civicrm_volunteer_project_target_contact_id`
      FOREIGN KEY (`target_contact_id`)
      REFERENCES `civicrm_contact` (`id`)
      ON DELETE SET NULL
    ');
    return TRUE;
  }

  /**
   * @return boolean TRUE on success
   */
  public function upgrade_1400() {
    $this->ctx->log->info('Applying update 1400 - creating volunteer contact subtype and related custom fields');
    $this->createVolunteerContactType();
    $volContactTypeCustomGroupID = $this->createVolunteerContactCustomGroup();
    $customFieldId = $this->createVolunteerContactCustomFields($volContactTypeCustomGroupID);
    _volunteer_update_slider_fields(array(CRM_Core_Action::ADD => $customFieldId));
    return TRUE;
  }

  /**
   * @return boolean TRUE on success
   */
  public function upgrade_1401() {
    $this->ctx->log->info('Applying update 1401 - creating volunteer_interest profile');
    $this->executeCustomDataFileByAbsPath($this->extensionDir . '/xml/volunteer_interest_install.xml');
    return TRUE;
  }

  // removed by VOL-91; do not reuse
  // public function upgrade_1402() {}

  /**
   * @return boolean TRUE on success
   */
  public function upgrade_1403() {
    $this->ctx->log->info('Applying update 1403 - creating commendation activity type and related custom fields');
    $this->installCommendationActivityType();
    return TRUE;
  }

  /**
   * Fix for VOL-89.
   *
   * @return boolean TRUE on success
   */
  public function upgrade_1404() {
    $this->ctx->log->info('Applying update 1404 - Replacing null values in
      civicrm_volunteer_project.target_contact_id with the ID of the default organization');

    $domainContactId = \Civi\Api4\Domain::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('id', '=', CRM_Core_Config::domainID())
      ->execute()
      ->first()['contact_id'] ?? NULL;
    $placeholders = array(
      1 => array($domainContactId, 'Integer'),
    );
    $query = CRM_Core_DAO::executeQuery('UPDATE civicrm_volunteer_project SET target_contact_id = %1 WHERE target_contact_id IS NULL', $placeholders);

    return !is_a($query, 'DB_Error');
  }

  public function upgrade_2001() {
    $this->ctx->log->info('Applying update 2001 - Upgrading schema to 2.0');
    $this->schemaUpgrade20();
    $this->migrateProjectTitles();
    return TRUE;
  }

  public function upgrade_2002() {
    $this->ctx->log->info('Applying update 2002 - Adding end_date to civicrm_volunteer_need');
    $this->addNeedEndDate();
    return TRUE;
  }

  public function upgrade_2003() {
    $this->ctx->log->info('Applying update 2003 - Installing Volunteer message workflow templates');
    $this->installVolMsgWorkflowTpls();
    return TRUE;
  }

  public function upgrade_2004() {
    $this->ctx->log->info('Applying update 2004 - Setting module_data for volunteer profiles');
    $query = CRM_Core_DAO::executeQuery('UPDATE civicrm_uf_join SET module_data = %1
          WHERE module_data IS NULL AND entity_table = %2 AND module = %3', array(
            1 => array(json_encode(array('audience' => 'primary')), 'String'),
            2 => array('civicrm_volunteer_project', 'String'),
            3 => array('CiviVolunteer', 'String'),
          ));
    return !is_a($query, 'DB_Error');
  }

  /**
   * Create a flexible need for projects that don't have one.
   *
   * See VOL-140. This is probably not needed for upgrades from 1.x to 2.x, but
   * anyone who created incomplete data during the alpha/beta period will
   * benefit from running this code.
   *
   * @return boolean
   */
  public function upgrade_2005() {
    $this->ctx->log->info('Applying update 2005 - Ensuring each project has a flexible need');

    $query = CRM_Core_DAO::executeQuery('
      INSERT INTO `civicrm_volunteer_need` (`is_flexible`, `project_id`)
        SELECT 1, p.id
        FROM `civicrm_volunteer_project` p
        LEFT JOIN `civicrm_volunteer_need` n
        ON n.project_id = p.id
          AND n.is_flexible = 1
        WHERE n.id IS NULL');
    return !is_a($query, 'DB_Error');
  }

  /**
   * Notify administrators of new permissions.
   */
  public function upgrade_2200() {
    $this->ctx->log->info('Applying update 2200 - CiviVolunteer Upgrade Notice');

    $message = ts('This upgrade introduces two new permissions ("Edit Volunteer Project Relationships" and "Edit Volunteer Registration Profiles"). Grant these to allow users more control over the volunteer project create/edit workflow. Revoke them to streamline the process. Volunteer projects created by users lacking these privileges will use the defaults set by the system administrator.', array('domain' => 'org.civicrm.volunteer'));
    $title = ts('CiviVolunteer Upgrade Notice', array('domain' => 'org.civicrm.volunteer'));
    CRM_Core_Session::setStatus($message, $title, 'info', array('expires' => 0));
    return TRUE;
  }

  /**
   * Notify administrators that this version of CiviVolunteer requires an
   * upgraded version of CiviCRM.
   */
  public function upgrade_2201() {
    $this->ctx->log->info('Applying update 2201 - Compatibility check');

    if (!class_exists('\Civi\Angular\AngularLoader')) {
      $message = ts('This version of CiviVolunteer will not function without features that were introduced in CiviCRM v4.7.21. It is recommended that you upgrade CiviCRM.', array('domain' => 'org.civicrm.volunteer'));
      $title = ts('Incompatible Versions', array('domain' => 'org.civicrm.volunteer'));
      CRM_Core_Session::setStatus($message, $title, 'info', array('expires' => 0));
    }

    return TRUE;
  }

  /**
   * Notify administrators of problems they might experience due to CRM-21210.
   */
  public function upgrade_2202() {
    $this->ctx->log->info('Applying update 2202 - CiviVolunteer Upgrade Notice');

    $message = ts("Some users have reported that their CiviVolunteer settings \"disappear\" after an upgrade. This is due to an issue with CiviCRM's extension system, but can usually be resolved by flushing CiviCRM's caches. For more information, see <a href=\"https://issues.civicrm.org/jira/browse/CRM-21210\">CRM-21210</a>.", array('domain' => 'org.civicrm.volunteer'));
    $title = ts('Post-Upgrade Steps May Be Required', array('domain' => 'org.civicrm.volunteer'));
    CRM_Core_Session::setStatus($message, $title, 'info', array('expires' => 0));
    return TRUE;
  }

  private function installNeedMetaDateFields() {
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_volunteer_need', 'created', FALSE)) {
      return TRUE;
    }
    $query = CRM_Core_DAO::executeQuery('
      ALTER TABLE `civicrm_volunteer_need`
      ADD `created` TIMESTAMP NULL
      AFTER `is_active`,
      ADD `last_updated` TIMESTAMP on update CURRENT_TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
      AFTER `created`;');
    return !is_a($query, 'DB_Error');
  }

  public function upgrade_2300() {
    $this->ctx->log->info('Applying update 2300 - Adding date meta data fields to volunteer opportunities');
    return $this->installNeedMetaDateFields();
  }

  /**
   * Add default value to volunteer_need.created column
   */
  public function upgrade_2301() {
    $this->ctx->log->info('Applying update 2301 - Add default value to volunteer_need.created column');
    CRM_Core_DAO::executeQuery('ALTER TABLE `civicrm_volunteer_need` CHANGE COLUMN `created` `created` timestamp DEFAULT CURRENT_TIMESTAMP');
    return TRUE;
  }

  /**
   * Allow volunteer projects which are not associated with another entity.
   */
  public function upgrade_2302() {
    $this->ctx->log->info('Applying update 2302 - Allowing standalone volunteer projects');
    CRM_Core_DAO::executeQuery("
      ALTER TABLE `civicrm_volunteer_project`
      MODIFY `entity_table` VARCHAR(64) NULL
        COMMENT 'Entity table for entity_id (initially civicrm_event)',
      MODIFY `entity_id` INT UNSIGNED NULL
        COMMENT 'Implicit FK project entity (initially eventID).'
    ");
    return TRUE;
  }

  /**
   * Register PHP entity metadata and reconcile additive schema constraints.
   *
   * This upgrade is intentionally additive. It validates invariants before
   * adding constraints and never recreates an extension table or chooses a
   * duplicate record on the administrator's behalf.
   *
   * @return bool
   * @throws CRM_Core_Exception
   */
  public function upgrade_2500() {
    $this->ctx->log->info('Applying update 2500 - Registering API4 entity metadata and reconciling schema indexes');

    $tables = [
      'civicrm_volunteer_project',
      'civicrm_volunteer_need',
      'civicrm_volunteer_project_contact',
    ];
    foreach ($tables as $table) {
      if (!CRM_Core_DAO::checkTableExists($table)) {
        throw new CRM_Core_Exception(ts('CiviVolunteer cannot upgrade because the required table %1 is missing.', [
          1 => $table,
          'domain' => 'org.civicrm.volunteer',
        ]));
      }
    }

    $duplicateContacts = CRM_Core_DAO::executeQuery('SELECT project_id, contact_id, relationship_type_id, GROUP_CONCAT(id ORDER BY id) AS row_ids
      FROM civicrm_volunteer_project_contact
      GROUP BY project_id, contact_id, relationship_type_id
      HAVING COUNT(*) > 1
      LIMIT 10');
    $contactConflicts = [];
    while ($duplicateContacts->fetch()) {
      $contactConflicts[] = $duplicateContacts->row_ids;
    }
    if ($contactConflicts) {
      throw new CRM_Core_Exception(ts('CiviVolunteer found duplicate project-contact relationships in row sets %1. Merge these records before retrying the upgrade.', [
        1 => implode('; ', $contactConflicts),
        'domain' => 'org.civicrm.volunteer',
      ]));
    }

    // project_id is nullable with ON DELETE SET NULL, so every project deleted
    // before this release left its needs behind with a NULL project_id. MySQL
    // collapses all NULLs into a single GROUP BY bucket, so without the IS NOT
    // NULL guard a site that had deleted two projects would fail this check
    // against unrelated orphans -- and the error would name row IDs that have
    // nothing to do with duplicate flexible needs.
    $duplicateFlexibleNeeds = CRM_Core_DAO::executeQuery('SELECT project_id, GROUP_CONCAT(id ORDER BY id) AS row_ids
      FROM civicrm_volunteer_need
      WHERE is_flexible = 1 AND project_id IS NOT NULL
      GROUP BY project_id
      HAVING COUNT(*) > 1
      LIMIT 10');
    $needConflicts = [];
    while ($duplicateFlexibleNeeds->fetch()) {
      $needConflicts[] = $duplicateFlexibleNeeds->row_ids;
    }
    if ($needConflicts) {
      throw new CRM_Core_Exception(ts('CiviVolunteer found projects with multiple flexible needs in row sets %1. Resolve these records before retrying the upgrade.', [
        1 => implode('; ', $needConflicts),
        'domain' => 'org.civicrm.volunteer',
      ]));
    }

    $this->addIndexIfMissing(
      'civicrm_volunteer_project_contact',
      'UI_project_contact_rel',
      ['project_id', 'contact_id', 'relationship_type_id'],
      TRUE,
      'ALTER TABLE civicrm_volunteer_project_contact ADD UNIQUE INDEX UI_project_contact_rel (project_id, contact_id, relationship_type_id)'
    );
    $this->addIndexIfMissing(
      'civicrm_volunteer_project',
      'index_volunteer_project_entity',
      ['entity_table', 'entity_id'],
      FALSE,
      'ALTER TABLE civicrm_volunteer_project ADD INDEX index_volunteer_project_entity (entity_table, entity_id)'
    );
    $this->addIndexIfMissing(
      'civicrm_volunteer_need',
      'index_volunteer_need_search',
      ['is_active', 'is_flexible', 'start_time', 'end_time'],
      FALSE,
      'ALTER TABLE civicrm_volunteer_need ADD INDEX index_volunteer_need_search (is_active, is_flexible, start_time, end_time)'
    );

    $this->assertNoOrphans('civicrm_volunteer_project', 'loc_block_id', 'civicrm_loc_block');
    $this->addForeignKeyIfMissing(
      'civicrm_volunteer_project',
      'FK_civicrm_volunteer_project_loc_block_id',
      'loc_block_id',
      'civicrm_loc_block',
      'id',
      'SET NULL',
      'ALTER TABLE civicrm_volunteer_project ADD CONSTRAINT FK_civicrm_volunteer_project_loc_block_id FOREIGN KEY (loc_block_id) REFERENCES civicrm_loc_block (id) ON DELETE SET NULL'
    );
    if (CRM_Core_DAO::checkTableExists('civicrm_campaign')) {
      $this->assertNoOrphans('civicrm_volunteer_project', 'campaign_id', 'civicrm_campaign');
      $this->addForeignKeyIfMissing(
        'civicrm_volunteer_project',
        'FK_civicrm_volunteer_project_campaign_id',
        'campaign_id',
        'civicrm_campaign',
        'id',
        'SET NULL',
        'ALTER TABLE civicrm_volunteer_project ADD CONSTRAINT FK_civicrm_volunteer_project_campaign_id FOREIGN KEY (campaign_id) REFERENCES civicrm_campaign (id) ON DELETE SET NULL'
      );
    }
    $this->assertNoOrphans('civicrm_volunteer_need', 'project_id', 'civicrm_volunteer_project');
    $this->addForeignKeyIfMissing(
      'civicrm_volunteer_need',
      'FK_civicrm_volunteer_need_project_id',
      'project_id',
      'civicrm_volunteer_project',
      'id',
      'SET NULL',
      'ALTER TABLE civicrm_volunteer_need ADD CONSTRAINT FK_civicrm_volunteer_need_project_id FOREIGN KEY (project_id) REFERENCES civicrm_volunteer_project (id) ON DELETE SET NULL'
    );
    $this->assertNoOrphans('civicrm_volunteer_project_contact', 'project_id', 'civicrm_volunteer_project');
    $this->addForeignKeyIfMissing(
      'civicrm_volunteer_project_contact',
      'FK_civicrm_volunteer_project_contact_project_id',
      'project_id',
      'civicrm_volunteer_project',
      'id',
      'CASCADE',
      'ALTER TABLE civicrm_volunteer_project_contact ADD CONSTRAINT FK_civicrm_volunteer_project_contact_project_id FOREIGN KEY (project_id) REFERENCES civicrm_volunteer_project (id) ON DELETE CASCADE'
    );
    $this->assertNoOrphans('civicrm_volunteer_project_contact', 'contact_id', 'civicrm_contact');
    $this->addForeignKeyIfMissing(
      'civicrm_volunteer_project_contact',
      'FK_civicrm_volunteer_project_contact_contact_id',
      'contact_id',
      'civicrm_contact',
      'id',
      'CASCADE',
      'ALTER TABLE civicrm_volunteer_project_contact ADD CONSTRAINT FK_civicrm_volunteer_project_contact_contact_id FOREIGN KEY (contact_id) REFERENCES civicrm_contact (id) ON DELETE CASCADE'
    );

    Civi::rebuild([
      'metadata' => TRUE,
      'entities' => TRUE,
    ])->execute();

    return TRUE;
  }

  /**
   * Add an index unless an equivalent index already exists.
   */
  private function addIndexIfMissing(string $table, string $index, array $columns, bool $unique, string $sql): void {
    $indices = CRM_Core_DAO::executeQuery('SELECT INDEX_NAME AS index_name,
        NON_UNIQUE AS non_unique,
        GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = %1
      GROUP BY INDEX_NAME, NON_UNIQUE', [
          1 => [$table, 'String'],
        ]);
    $expectedColumns = implode(',', $columns);
    $namedIndexExists = FALSE;
    while ($indices->fetch()) {
      $namedIndexExists = $namedIndexExists || $indices->index_name === $index;
      $isEquivalent = $indices->indexed_columns === $expectedColumns
        && (!$unique || !(int) $indices->non_unique);
      if ($isEquivalent) {
        return;
      }
    }
    if ($namedIndexExists) {
      throw new CRM_Core_Exception(ts('CiviVolunteer cannot add index %1 because an index with that name has different columns. Rename the existing index before retrying the upgrade.', [
        1 => $index,
        'domain' => 'org.civicrm.volunteer',
      ]));
    }
    CRM_Core_DAO::executeQuery($sql);
  }

  /**
   * Add a foreign key unless an equivalent constraint already exists.
   */
  private function addForeignKeyIfMissing(
    string $table,
    string $constraint,
    string $column,
    string $referencedTable,
    string $referencedColumn,
    string $deleteRule,
    string $sql
  ): void {
    $constraints = CRM_Core_DAO::executeQuery('SELECT kcu.CONSTRAINT_NAME AS constraint_name,
        kcu.COLUMN_NAME AS column_name,
        kcu.REFERENCED_TABLE_NAME AS referenced_table_name,
        kcu.REFERENCED_COLUMN_NAME AS referenced_column_name,
        rc.DELETE_RULE AS delete_rule
      FROM information_schema.KEY_COLUMN_USAGE kcu
      INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
        ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
        AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
        AND rc.TABLE_NAME = kcu.TABLE_NAME
      WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
        AND kcu.TABLE_NAME = %1
        AND kcu.REFERENCED_TABLE_NAME IS NOT NULL', [
          1 => [$table, 'String'],
        ]);
    $namedConstraintExists = FALSE;
    while ($constraints->fetch()) {
      $namedConstraintExists = $namedConstraintExists || $constraints->constraint_name === $constraint;
      if ($constraints->column_name === $column
        && $constraints->referenced_table_name === $referencedTable
        && $constraints->referenced_column_name === $referencedColumn
        && strtoupper($constraints->delete_rule) === strtoupper($deleteRule)) {
        return;
      }
    }
    if ($namedConstraintExists) {
      throw new CRM_Core_Exception(ts('CiviVolunteer cannot add foreign key %1 because a constraint with that name has a different definition. Rename the existing constraint before retrying the upgrade.', [
        1 => $constraint,
        'domain' => 'org.civicrm.volunteer',
      ]));
    }
    CRM_Core_DAO::executeQuery($sql);
  }

  /**
   * Fail with actionable row IDs before MySQL emits a generic FK error.
   */
  private function assertNoOrphans(string $table, string $column, string $referencedTable): void {
    $rows = CRM_Core_DAO::executeQuery("SELECT child.id
      FROM {$table} child
      LEFT JOIN {$referencedTable} parent ON parent.id = child.{$column}
      WHERE child.{$column} IS NOT NULL AND parent.id IS NULL
      ORDER BY child.id LIMIT 10");
    $ids = [];
    while ($rows->fetch()) {
      $ids[] = (int) $rows->id;
    }
    if ($ids) {
      throw new CRM_Core_Exception(ts('CiviVolunteer found orphaned rows in %1 for %2 (row IDs: %3). Repair these references before retrying the upgrade.', [
        1 => $table,
        2 => $column,
        3 => implode(', ', $ids),
        'domain' => 'org.civicrm.volunteer',
      ]));
    }
  }

  public function uninstall() {
    $customgroup_ids = \Civi\Api4\CustomGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', 'IN', [
        'CiviVolunteer',
        'Volunteer_Information',
        'volunteer_commendation',
      ])
      ->execute()
      ->column('id');
    if ($customgroup_ids) {
      // Found one or more of our custom groups.
      // Lookup fields for these and delete those first.
      $customfield_ids = \Civi\Api4\CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id', 'IN', $customgroup_ids)
        ->execute()
        ->column('id');
      foreach ($customfield_ids as $customfield_id) {
        \Civi\Api4\CustomField::delete(FALSE)
          ->addWhere('id', '=', $customfield_id)
          ->execute();
      }

      // Now delete the groups themselves.
      foreach ($customgroup_ids as $customgroup_id) {
        \Civi\Api4\CustomGroup::delete(FALSE)
          ->addWhere('id', '=', $customgroup_id)
          ->execute();
      }
    }
    // The message templates reference the workflow option values below;
    // deleting the option group alone leaves them orphaned.
    $workflowValueIds = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('id')
      ->addWhere('option_group_id:name', '=', 'msg_tpl_workflow_volunteer')
      ->execute()
      ->column('id');
    if ($workflowValueIds) {
      \Civi\Api4\MessageTemplate::delete(FALSE)
        ->addWhere('workflow_id', 'IN', $workflowValueIds)
        ->execute();
    }

    $optiongroup_ids = \Civi\Api4\OptionGroup::get(FALSE)
      ->addWhere('name', 'IN', [
        'skill_level',
        'volunteer_project_relationship',
        'msg_tpl_workflow_volunteer',
        'volunteer_role',
      ])
      ->execute()
      ->column('id');
    if ($optiongroup_ids) {
      // Found one or more of our option groups.
      // Lookup values for these and delete those first.
      $optionvalue_ids = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('id')
        ->addWhere('option_group_id', 'IN', $optiongroup_ids)
        ->execute()
        ->column('id');
      foreach ($optionvalue_ids as $optionvalue_id) {
        \Civi\Api4\OptionValue::delete(FALSE)
          ->addWhere('id', '=', $optionvalue_id)
          ->execute();
      }

      // Now delete the groups themselves.
      foreach ($optiongroup_ids as $optiongroup_id) {
        \Civi\Api4\OptionGroup::delete(FALSE)
          ->addWhere('id', '=', $optiongroup_id)
          ->execute();
      }
    }
  }

  // Removed here: the civix enable/disable/upgrade_420x examples.
  //
  // Each was "commented out" by opening a docblock and never terminating it --
  // the closing marker sat on the example function's own brace line -- so the
  // blocks chained into one another and the whole run was only terminated by
  // the last one. Deleting any single block therefore silently swallowed the
  // next real method (findCustomGroupValueIDs) while still passing php -l, and
  // PHPStan had been attaching an example's "@return TRUE" to it. They also
  // carried placeholder SQL against tables named foo and bang.
  //
  // See the civix documentation for current install/upgrade task patterns.

  public function findCustomGroupValueIDs() {
    $result = array();

    $query = "SELECT `TABLE_NAME` AS cv_table_name,
        `AUTO_INCREMENT` AS cv_auto_increment
      FROM `information_schema`.`TABLES`
      WHERE `table_schema` = DATABASE()
      AND `table_name` IN ('civicrm_custom_group', 'civicrm_custom_field')";
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $result[$dao->cv_table_name] = (int) $dao->cv_auto_increment;
    }

    return $result;
  }

  /**
   * Creates an activity type, unless one with the provided machine name already
   * exists, in which case no changes are made to the database.
   *
   * @param string $machineName Machine name for the new activity type
   * @param string $label Human-readable name (optional, defaults to machine name)
   * @return int ID of Activity type (i.e., the value of the OptionValue)
   * @throws CRM_Core_Exception
   */
  public function createActivityType($machineName, $label = NULL) {
    $optionValue = \Civi\Api4\OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'activity_type')
      ->addWhere('name', '=', $machineName)
      ->execute()
      ->first();

    if (!empty($optionValue)) {
      return $optionValue['value'];
    }

    if (is_null($label)) {
      $label = $machineName;
    }

    $result = \Civi\Api4\OptionValue::create(FALSE)
      ->addValue('option_group_id.name', 'activity_type')
      ->addValue('name', $machineName)
      ->addValue('label', $label)
      ->addValue('is_active', TRUE)
      ->addValue('weight', 0)
      ->execute()
      ->first();

    return (int) $result['value'];
  }

  /**
   * Creates the Volunteer contact type, unless it already exists, in which case
   * the ID is returned.
   *
   * @return int
   * @throws CRM_Core_Exception
   */
  public function createVolunteerContactType() {
    $id = \Civi\Api4\ContactType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', self::customContactTypeName)
      ->execute()
      ->first()['id'] ?? NULL;

    if (!$id) {
      $parentId = \Civi\Api4\ContactType::get(FALSE)
        ->addSelect('id')
        ->addWhere('name', '=', 'Individual')
        ->execute()
        ->first()['id'] ?? NULL;
      $id = \Civi\Api4\ContactType::create(FALSE)
        ->addValue('label', ts('Volunteer', array('domain' => 'org.civicrm.volunteer')))
        ->addValue('name', self::customContactTypeName)
        ->addValue('parent_id', $parentId)
        ->execute()
        ->first()['id'];
    }

    return (int) $id;
  }

  /**
   * Creates the custom field group for the Volunteer contact type, unless it
   * already exists, in which case the ID is returned.
   *
   * @return int
   * @throws CRM_Core_Exception
   */
  public function createVolunteerContactCustomGroup() {
    $id = \Civi\Api4\CustomGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', self::customContactGroupName)
      ->execute()
      ->first()['id'] ?? NULL;

    if (!$id) {
      $id = \Civi\Api4\CustomGroup::create(FALSE)
        ->addValue('extends', 'Individual')
        ->addValue('extends_entity_column_value', array('Volunteer'))
        ->addValue('name', self::customContactGroupName)
        ->addValue('title', ts('Volunteer Information', array('domain' => 'org.civicrm.volunteer')))
        ->execute()
        ->first()['id'];
    }

    return (int) $id;
  }

  /**
   * @param int $customGroupID The group to which the field should be added
   * @return String
   *   Int-like string representing the ID of the just created custom field
   * @throws CRM_Core_Exception
   */
  public function createVolunteerContactCustomFields($customGroupID) {
    if (!is_int($customGroupID)) {
      throw new CRM_Core_Exception('Non-numeric custom group ID provided.');
    }

    $skillLevelOptionGroup = $this->createPossibleDuplicateRecord('OptionGroup', array(
      'is_active' => 1,
      'name' => self::skillLevelOptionGroupName,
      'title' => ts('Skill Level', array('domain' => 'org.civicrm.volunteer')),
    ));
    $skillLevelOptionGroupId = $skillLevelOptionGroup['id'];

    $values = array(
      1 => ts('Not interested', array('domain' => 'org.civicrm.volunteer')),
      2 => ts('Teach me', array('domain' => 'org.civicrm.volunteer')),
      3 => ts('Apprentice', array('domain' => 'org.civicrm.volunteer')),
      4 => ts('Journeyman', array('domain' => 'org.civicrm.volunteer')),
      5 => ts('Master', array('domain' => 'org.civicrm.volunteer')),
    );

    $weight = 1;
    foreach ($values as $k => $v) {
      \Civi\Api4\OptionValue::create(FALSE)
        ->setValues(array(
          'is_active' => TRUE,
          'label' => $v,
          'option_group_id' => $skillLevelOptionGroupId,
          'value' => $k,
          'weight' => $weight++,
        ))
        ->execute();
    }

    $customField = $this->createPossibleDuplicateRecord('CustomField', array(
      'custom_group_id' => $customGroupID,
      'data_type' => 'String',
      'html_type' => 'Select',
      'serialize' => 1,
      'is_searchable' => 1,
      'label' => ts('Camera Skill Level', array('domain' => 'org.civicrm.volunteer')),
      'name' => 'camera_skill_level',
      'option_group_id' => $skillLevelOptionGroupId,
    ));

    return $customField['id'];
  }

  /**
   * Creates a record, reusing an identically named one when it already exists.
   *
   * APIv3 signalled a duplicate with the error code 'already exists', which the
   * former implementation caught; API4 surfaces the same collision as a
   * DB-level error with no comparable code. Looking the record up first is both
   * more portable and cheaper, and it always yields the record's ID -- the
   * APIv3 version returned an empty array and left callers to re-query.
   *
   * @param string $entityType
   *   An API4 entity name.
   * @param array $params
   *   Values for the new record. `name` identifies it; for CustomField the
   *   owning `custom_group_id` scopes that name.
   * @return array
   *   The existing or newly created record, including its ID.
   * @throws CRM_Core_Exception
   */
  private function createPossibleDuplicateRecord($entityType, array $params) {
    $where = array();
    if (isset($params['name'])) {
      $where[] = array('name', '=', $params['name']);
    }
    if ($entityType === 'CustomField' && isset($params['custom_group_id'])) {
      $where[] = array('custom_group_id', '=', $params['custom_group_id']);
    }
    if (!$where) {
      throw new CRM_Core_Exception("Cannot identify an existing $entityType without a name.");
    }

    $existing = civicrm_api4($entityType, 'get', array(
      'checkPermissions' => FALSE,
      'select' => array('id'),
      'where' => $where,
    ))->first();
    if ($existing) {
      CRM_Core_Session::setStatus(
        ts('CiviVolunteer tried to create a(n) %1 named %2, but it already exists. This may lead to unexpected behavior.',
            array(
              1 => $entityType,
              2 => $params['name'] ?? NULL,
              'domain' => 'org.civicrm.volunteer',
            )),
        ts('Field already exists', array('domain' => 'org.civicrm.volunteer'))
      );
      return $existing;
    }

    try {
      return civicrm_api4($entityType, 'create', array(
        'checkPermissions' => FALSE,
        'values' => $params,
      ))->single();
    }
    catch (Throwable $e) {
      // Carry the cause's message. Without it an install or upgrade failure
      // here reports only "Failed to create CustomField.", which says nothing
      // about which field or why.
      throw new CRM_Core_Exception(
        sprintf(
          'Failed to create %s %s: %s',
          $entityType,
          $params['name'] ?? '(unnamed)',
          $e->getMessage()
        ),
        0,
        array(),
        $e
      );
    }
  }

  /**
   * @throws CRM_Core_Exception
   */

  public function executeCustomDataTemplateFile($relativePath) {
      $smarty = CRM_Core_Smarty::singleton();
      $xmlCode = $smarty->fetch($relativePath);
      $xml = simplexml_load_string($xmlCode);

      $import = new CRM_Utils_Migrate_Import();
      $import->runXmlElement($xml);
      return TRUE;
  }

}
