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

  const customActivityTypeName = 'Volunteer';
  const customGroupName = 'CiviVolunteer';
  const customOptionGroupName = 'volunteer_role';

  public function install() {

    $activityTypeId = $this->findCreateVolunteerActivityType();
    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign('volunteer_custom_activity_type_name', self::customActivityTypeName);
    $smarty->assign('volunteer_custom_group_name', self::customGroupName);
    $smarty->assign('volunteer_custom_option_group_name', self::customOptionGroupName);
    $smarty->assign('volunteer_activity_type_id', $activityTypeId);

    $customIDs = $this->findCustomGroupValueIDs();
    $smarty->assign('customIDs', $customIDs);
    $this->executeCustomDataTemplateFile('volunteer-customdata.xml.tpl');

    $this->createVolunteerActivityStatus();

    $unmet = CRM_Volunteer_Upgrader::checkExtensionDependencies();
    self::displayDependencyErrors($unmet);

    // uncomment the next line to insert sample data
    // $this->executeSqlFile('sql/volunteer_sample.mysql');
  }

  /**
   * CiviVolunteer 1.3 introduces target contacts for volunteer projects. The
   * requisite schema change is made here.
   *
   * @return boolean TRUE on success
   * @throws Exception
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
   * Display dependency error messages.
   * This upgrade-step counter should be incremented for each upgrade, not duplicated.
   *
   * @return boolean TRUE on success
   */
  public function upgrade_1402() {
    $unmet = CRM_Volunteer_Upgrader::checkExtensionDependencies();
    self::displayDependencyErrors($unmet);
    return TRUE;
  }

  /**
   * Look up extension dependency error messages and display as Core Session Status
   *
   * @param array $unmet
   */
  public static function displayDependencyErrors(array $unmet){
    foreach ($unmet as $ext) {
      $message = self::getUnmetDependencyErrorMessage($ext);
      CRM_Core_Session::setStatus($message, ts('Prerequisite check failed.', array('domain' => 'org.civicrm.volunteer')), 'no-popup');
    }
  }

  /**
   * Mapping of extensions names to localized dependency error messages
   *
   * @param string $unmet an extension name
   */
  public static function getUnmetDependencyErrorMessage($unmet) {
    switch ($unmet) {
      case 'com.ginkgosreet.multiform':
        return ts('You must install and enable the Multiform extension (https://github.com/ginkgostreet/civicrm_multiform) to use CiviVolunteer.'
          , array('domain' => 'org.civicrm.volunteer'));
        break;
      default:
        break;
    }
  }

  /**
   * Extension Dependency Check
   *
   * @return Array of names of unmet extension dependencies
   */
  public static function checkExtensionDependencies() {
    $ext_manager = CRM_Extension_System::singleton()->getManager();

    $arr_extension_dependencies = array(
      //@TODO move this config out of code
      'com.ginkgostreet.multiform',
    );

    $unmet = array();
    foreach($arr_extension_dependencies as $ext) {
      if($ext_manager->getStatus($ext) != CRM_Extension_Manager::STATUS_INSTALLED) {
          $unmet[] = $ext;
      }
    }
    return $unmet;
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
   * @return int
   * @throws CRM_Core_Exception
   */
  public function findCreateVolunteerActivityType() {
    $optionGroup = civicrm_api('OptionGroup', 'Get', array(
      'version' => 3,
      'name' => 'activity_type',
      'return' => 'id'
    ));

    $optionValue = civicrm_api('OptionValue', 'Get', array(
      'version' => 3,
      'name' => self::customActivityTypeName,
      'option_group_id' => $optionGroup['id'],
      'return' => 'value'
    ));

    if ($optionValue['count']) {
      $opt = current($optionValue['values']);
      return $opt['value'];
    } else {
      $result = civicrm_api('ActivityType', 'create', array(
        'version' => 3,
        'name' => self::customActivityTypeName,
        'label' => ts('Volunteer', array('domain' => 'org.civicrm.volunteer')),
        'weight' => 58,
        'is_active' => '1',
      ));
      if (CRM_Utils_Array::value('is_error', $result, FALSE)) {
        CRM_Core_Error::debug_var('activityTypeResult', $result);
        throw new CRM_Core_Exception('Failed to register activity type');
      }

      return $result['values'][$result['id']]['value'];
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
