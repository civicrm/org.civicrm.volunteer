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
class CRM_Volunteer_Upgrader extends CRM_Volunteer_Upgrader_Base {

  const customContactGroupName = 'Volunteer_Information';
  const customContactTypeName = 'Volunteer';
  const skillLevelOptionGroupName = 'skill_level';

  public function install() {
    $volActivityTypeId = $this->createActivityType(CRM_Volunteer_BAO_Assignment::CUSTOM_ACTIVITY_TYPE);
    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign('volunteer_custom_activity_type_name', CRM_Volunteer_BAO_Assignment::CUSTOM_ACTIVITY_TYPE);
    $smarty->assign('volunteer_custom_group_name', CRM_Volunteer_BAO_Assignment::CUSTOM_GROUP_NAME);
    $smarty->assign('volunteer_custom_option_group_name', CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP);
    $smarty->assign('volunteer_activity_type_id', $volActivityTypeId);

    $customIDs = $this->findCustomGroupValueIDs();
    $smarty->assign('customIDs', $customIDs);
    $this->executeCustomDataTemplateFile('volunteer-customdata.xml.tpl');

    $this->createVolunteerActivityStatus();

    $this->createVolunteerContactType();
    $volContactTypeCustomGroupID = $this->createVolunteerContactCustomGroup();
    $this->createVolunteerContactCustomFields($volContactTypeCustomGroupID);

    $this->installCommendationActivityType();

    $this->schemaUpgrade20();
    $this->addNeedEndDate();
    $this->installVolMsgWorkflowTpls();

    // uncomment the next line to insert sample data
    // $this->executeSqlFile('sql/volunteer_sample.mysql');
  }

  /**
   * Installs option group and options for project relationships.
   */
  public function installProjectRelationships() {
    try {
      $optionGroup = civicrm_api3('OptionGroup', 'create', array(
        'name' => CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
        'title' => 'Volunteer Project Relationship',
        'description' => ts("Used to describe a contact's relationship to a project at large (e.g., beneficiary, manager). Not to be confused with contact-to-contact relationships.", array('domain' => 'org.civicrm.volunteer')),
        'is_reserved' => 1,
        'is_active' => 1,
      ));
      $optionGroupId = $optionGroup['id'];
    } catch (Exception $e) {
      // if an exception is thrown, most likely the option group already exists,
      // in which case we'll just use that one
      $optionGroupId = civicrm_api3('OptionGroup', 'getvalue', array(
        'name' => CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
        'return' => 'id',
      ));
    }

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
      civicrm_api3('OptionValue', 'create', $optionDefaults + $opt);
    }
  }

  public function installVolMsgWorkflowTpls() {
    try {
      $optionGroup = civicrm_api3('OptionGroup', 'create', array(
        'name' => 'msg_tpl_workflow_volunteer',
        'title' => ts("Message Template Workflow for Volunteers", array('domain' => 'org.civicrm.volunteer')),
        'description' => ts("Message Template Workflow for Volunteers", array('domain' => 'org.civicrm.volunteer')),
        'is_reserved' => 1,
        'is_active' => 1,
      ));
      $optionGroupId = $optionGroup['id'];
    } catch (Exception $e) {
      // if an exception is thrown, most likely the option group already exists,
      // in which case we'll just use that one
      $optionGroupId = civicrm_api3('OptionGroup', 'getvalue', array(
        'name' => 'msg_tpl_workflow_volunteer',
        'return' => 'id',
      ));
    }

    $optionDefaults = array(
      'is_active' => 1,
      'is_reserved' => 1,
      'option_group_id' => $optionGroupId,
    );

    $options = array(
      array(
        'name' => 'volunteer_registration',
        'label' => ts('Volunteer - Registration (on-line)', array('domain' => 'org.civicrm.volunteer')),
        'description' => ts('Email sent to volunteers who sign themselves up for volunteer opportunities.', array('domain' => 'org.civicrm.volunteer')),
        'value' => 1,
        'weight' => 1,
      ),
    );

    $baseDir = CRM_Extension_System::singleton()->getMapper()->keyToBasePath('org.civicrm.volunteer') . '/';
    foreach ($options as $opt) {
      civicrm_api3('OptionValue', 'create', $optionDefaults + $opt);

      $txt = $baseDir . 'CRM/Volunteer/Upgrader/2.0.alpha1.msg_template/' . $opt['name'] . '_text.tpl';
      $html = $baseDir . 'CRM/Volunteer/Upgrader/2.0.alpha1.msg_template/' . $opt['name'] . '_html.tpl';

      // do something to import these into the DB...
    }
  }

  /**
   * Makes schema changes to accommodate 2.0 functionality/refactoring.
   *
   * Used in both the install and the upgrade.
   */
  public function schemaUpgrade20() {
    $this->installProjectRelationships();
    $this->executeSqlFile('sql/volunteer_upgrade_2.0.sql');
  }

  /**
   * Makes schema changes to support fuzzy dates for needs (VOL-142).
   *
   * Used in both the install and the upgrade.
   */
  public function addNeedEndDate() {
    $this->executeSqlFile('sql/volunteer_need_end_date.sql');
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

    $customGroupID = NULL;
    try {
      $get = civicrm_api3('CustomGroup', 'getsingle', array(
        'extends' => 'Activity',
        'name' => CRM_Volunteer_BAO_Commendation::CUSTOM_GROUP_NAME,
        'return' => 'id',
      ));
      $customGroupID = $get['id'];
    } catch (Exception $e) {
      $create = civicrm_api('CustomGroup', 'create', array(
        'extends' => 'Activity',
        'extends_entity_column_value' => $activityTypeID,
        'is_reserved' => 1,
        'name' => CRM_Volunteer_BAO_Commendation::CUSTOM_GROUP_NAME,
        'title' => ts('Volunteer Commendation', array('domain' => 'org.civicrm.volunteer')),
        'version' => 3,
      ));
      if (CRM_Utils_Array::value('is_error', $create)) {
        CRM_Core_Error::debug_var('customGroupResult', $create);
        throw new CRM_Core_Exception('Failed to register custom group for commendation active type');
      }

      $customGroupID = $create['id'];
    }

    $create = civicrm_api3('customField', 'create', array(
      'custom_group_id' => $customGroupID,
      'data_type' => 'Int',
      'html_type' => 'Text',
      'is_searchable' => 0,
      'label' => ts('Volunteer Project ID', array('domain' => 'org.civicrm.volunteer')),
      'name' => CRM_Volunteer_BAO_Commendation::PROJECT_REF_FIELD_NAME,
    ));

    $this->fieldCreateCheckForError($create);
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
      ADD FOREIGN KEY (`target_contact_id`)
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
    $this->createVolunteerContactCustomFields($volContactTypeCustomGroupID);
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

    $domainContactId = civicrm_api3('Domain', 'getvalue', array(
      'current_domain' => 1,
      'return' => "contact_id",
    ));
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

  /**
   * Example: Run an external SQL script when the module is uninstalled
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

  public function findCustomGroupValueIDs() {
    $result = array();

    $query = "SELECT `table_name`, `AUTO_INCREMENT` FROM `information_schema`.`TABLES`
      WHERE `table_schema` = DATABASE()
      AND `table_name` IN ('civicrm_custom_group', 'civicrm_custom_field')";
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $result[$dao->table_name] = (int) $dao->AUTO_INCREMENT;
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
    $id = NULL;
    $optionGroup = civicrm_api3('OptionGroup', 'getsingle', array(
      'name' => 'activity_type',
      'return' => 'id'
    ));

    try {
      $optionValue = civicrm_api3('OptionValue', 'getsingle', array(
        'name' => $machineName,
        'option_group_id' => $optionGroup['id'],
        'return' => 'value'
      ));
      $id = $optionValue['value'];
    } catch(Exception $e) {
      if (is_null($label)) {
        $label = $machineName;
      }

      $result = civicrm_api('ActivityType', 'create', array(
        'name' => $machineName,
        'label' => $label,
        'is_active' => '1',
        'version' => 3,
        'weight' => 0,
      ));
      if (CRM_Utils_Array::value('is_error', $result, FALSE)) {
        CRM_Core_Error::debug_var('activityTypeResult', $result);
        throw new CRM_Core_Exception('Failed to register activity type ' . $machineName);
      }
      $id = $result['values'][$result['id']]['value'];
    }

    return (int) $id;
  }

  /**
   * Creates the Volunteer contact type, unless it already exists, in which case
   * the ID is returned.
   *
   * @return int
   * @throws CRM_Core_Exception
   */
  public function createVolunteerContactType() {
    $id = NULL;
    $get = civicrm_api3('ContactType', 'get', array(
      'name' => self::customContactTypeName,
      'return' => 'id',
      'sequential' => 1,
    ));

    if ($get['count']) {
      $id = $get['values'][0]['id'];
    } else {
      $create = civicrm_api3('ContactType', 'create', array(
        'label' => ts('Volunteer', array('domain' => 'org.civicrm.volunteer')),
        'name' => self::customContactTypeName,
        'parent_id' => civicrm_api3('ContactType', 'getvalue', array(
          'name' => 'Individual',
          'return' => 'id',
         )),
      ));
      if (CRM_Utils_Array::value('is_error', $create)) {
        CRM_Core_Error::debug_var('contactTypeResult', $create);
        throw new CRM_Core_Exception('Failed to register contact type');
      }

      $id = $create['id'];
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
    $id = NULL;
    $get = civicrm_api3('CustomGroup', 'get', array(
      'name' => self::customContactGroupName,
      'return' => 'id',
      'sequential' => 1,
    ));

    if ($get['count']) {
      $id = $get['values'][0]['id'];
    } else {
      $create = civicrm_api3('CustomGroup', 'create', array(
        'extends' => 'Individual',
        'extends_entity_column_value' => 'Volunteer',
        'name' => self::customContactGroupName,
        'title' => ts('Volunteer Information', array('domain' => 'org.civicrm.volunteer')),
      ));
      if (CRM_Utils_Array::value('is_error', $create)) {
        CRM_Core_Error::debug_var('customGroupResult', $create);
        throw new CRM_Core_Exception('Failed to register custom group for volunteer subtype');
      }

      $id = $create['id'];
    }

    return (int) $id;
  }

  /**
   * @param int $customGroupID The group to which the field should be added
   * @throws CRM_Core_Exception
   */
  public function createVolunteerContactCustomFields($customGroupID) {
    if (!is_int($customGroupID)) {
      throw new CRM_Core_Exception('Non-numeric custom group ID provided.');
    }

// The code below is preferable, but it's blocked by CRM-15542.

//    $check = civicrm_api3('OptionGroup', 'get', array(
//      'name' => self::skillLevelOptionGroupName,
//    ));
//
//    // don't create the optionGroup (or the options) if an optionGroup with the same name already exists
//    if ($check['count'] === 0) {
//      $values = array(
//        1 => ts('Not interested', array('domain' => 'org.civicrm.volunteer')),
//        2 => ts('Teach me', array('domain' => 'org.civicrm.volunteer')),
//        3 => ts('Apprentice', array('domain' => 'org.civicrm.volunteer')),
//        4 => ts('Journeyman', array('domain' => 'org.civicrm.volunteer')),
//        5 => ts('Master', array('domain' => 'org.civicrm.volunteer')),
//      );
//
//      $og = civicrm_api3('OptionGroup', 'create', array(
//        'is_active' => 1,
//        'name' => self::skillLevelOptionGroupName,
//        'title' => ts('Skill Level', array('domain' => 'org.civicrm.volunteer')),
//      ));
//
//      $weight = 1;
//      foreach ($values as $k => $v) {
//        civicrm_api3('OptionValue', 'create', array(
//          'is_active' => 1,
//          'label' => $v,
//          'option_group_id' => $og['id'],
//          'value' => $k,
//          'weight' => $weight++,
//        ));
//      }
//    }

    $create = civicrm_api3('customField', 'create', array(
      'custom_group_id' => $customGroupID,
      'data_type' => 'String',
      'html_type' => 'Multi-select',
      'is_searchable' => 1,
      'label' => ts('Camera Skill Level', array('domain' => 'org.civicrm.volunteer')),
      'name' => 'camera_skill_level',
//      'option_group_id' => $og['id'], // blocked by CRM-15542, so instead use option_values
      'option_values' => array(
        5 => array(
          'is_active' => 1,
          'label' => ts('Master', array('domain' => 'org.civicrm.volunteer')),
          'value' => 5,
          'weight' => 5,
        ),
        4 => array(
          'is_active' => 1,
          'label' => ts('Journeyman', array('domain' => 'org.civicrm.volunteer')),
          'value' => 4,
          'weight' => 4,
        ),
        3 => array(
          'is_active' => 1,
          'label' => ts('Apprentice', array('domain' => 'org.civicrm.volunteer')),
          'value' => 3,
          'weight' => 3,
        ),
        2 => array(
          'is_active' => 1,
          'label' => ts('Teach me', array('domain' => 'org.civicrm.volunteer')),
          'value' => 2,
          'weight' => 2,
        ),
        1 => array(
          'is_active' => 1,
          'label' => ts('Not interested', array('domain' => 'org.civicrm.volunteer')),
          'value' => 1,
          'weight' => 1,
        ),
      ),
    ));

    // hack for CRM-15542 - The custom field create API doesn't allow an existing option
    // group to specified; the options must be created with the field. We want to give
    // this option group a meaningful name and label so it's obvious it's intended to be
    // reused, so we rename it below.
    civicrm_api3('OptionGroup', 'create', array(
      'id' => $create['values'][$create['id']]['option_group_id'],
      'is_active' => 1,
      'name' => self::skillLevelOptionGroupName,
      'title' => ts('Skill Level', array('domain' => 'org.civicrm.volunteer')),
    ));
    // end hack for CRM-15542

    _volunteer_update_slider_fields(array(CRM_Core_Action::ADD => $create['id']));

    $this->fieldCreateCheckForError($create);
  }

  /**
   * Helper function
   *
   * Sets status message if field already exists, throws exception in case of
   * other error, does nothing on success
   *
   * @param array $apiResult
   * @throws CRM_Core_Exception
   */
  private function fieldCreateCheckForError(array $apiResult) {
    if (CRM_Utils_Array::value('is_error', $apiResult)) {
      if ($apiResult['error_code'] == 'already exists') {
        CRM_Core_Session::setStatus(
          ts('CiviVolunteer tried to create a custom field named %1, but it already exists. This may lead to unexpected behavior.', array('domain' => 'org.civicrm.volunteer', 1 => 'camera_skill_level')),
          ts('Field already exists', array('domain' => 'org.civicrm.volunteer'))
        );
      } else {
        CRM_Core_Error::debug_var('customFieldResult', $apiResult);
        throw new CRM_Core_Exception('Failed to create custom field for volunteer subtype');
      }
    }
  }

  /**                                                                                                                                                                                                        * @return int                                                                                                                                                                                            * @throws CRM_Core_Exception                                                                                                                                                                             */
  public function createVolunteerActivityStatus() {
    $activityStatus = civicrm_api('OptionGroup', 'Get', array(
      'version' => 3,
      'name' => 'activity_status',
      'return' => 'id'
    ));
    $activityStatusID = $activityStatus['id'];

    $activityStatuses = array(
      'Available' => ts('Available', array('domain' => 'org.civicrm.volunteer')),
      'No_show' => ts('No-show', array('domain' => 'org.civicrm.volunteer')),
    );

    foreach($activityStatuses as $name => $label) {
      $activityStatus = civicrm_api('OptionValue', 'Get', array(
        'version' => 3,
        'name' => $name,
        'option_group_id' => $activityStatusID,
        'return' => 'value'
      ));

      if (!$activityStatus['count']) {
        $params = array(
          'version' => 3,
          'sequential' => 1,
          'option_group_id'=> $activityStatusID,
          'name' => $name,
          'label' => $label,
        );
        $result = civicrm_api('OptionValue', 'create', $params);

        if (CRM_Utils_Array::value('is_error', $result, FALSE)) {
          CRM_Core_Error::debug_var('activityStatusResult', $result);
          throw new CRM_Core_Exception('Failed to register activity status');
        }
      }
    }
  }

  public function executeCustomDataTemplateFile($relativePath) {
      $smarty = CRM_Core_Smarty::singleton();
      $xmlCode = $smarty->fetch($relativePath);
      $xml = simplexml_load_string($xmlCode);

      require_once 'CRM/Utils/Migrate/Import.php';
      $import = new CRM_Utils_Migrate_Import();
      $import->runXmlElement($xml);
      return TRUE;
  }
}
