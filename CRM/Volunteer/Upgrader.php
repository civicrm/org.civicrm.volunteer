<?php

/**
 * Collection of upgrade steps
 */
class CRM_Volunteer_Upgrader extends CRM_Volunteer_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed
   */
  public function install() {
      
    $activityTypeId = $this->findCreateVolunteerActivityType();
    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign('volunteer_activity_type_id', $activityTypeId);
    
    $customIDs = $this->findCustomGroupValueIDs();
    $smarty->assign('customIDs', $customIDs);
    $this->executeCustomDataTemplateFile('volunteer-customdata.xml.tpl');

    $this->createVolunteerActivityStatus();
    
    //load the sample data, after the 
    //DB basic structure/custom tables etc are built
    $this->executeSqlFile('sql/volunteer_sample.mysql');
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
    $params = array(
      'version' => 3,
      'sequential' => 1,
    );
    $customGroupID = civicrm_api('CustomGroup', 'getcount', $params);

    $params = array(
      'version' => 3,
      'sequential' => 1,

    );
    $customFieldID = civicrm_api('CustomField', 'getcount', $params);
    $customData = array(
      'customGroupID' => $customGroupID+1,
      'customFieldID' => $customFieldID+1
    );
    return $customData;
  }
  /**
   * @return int
   * @throws CRM_Core_Exception
   */
  public function findCreateVolunteerActivityType() {
    $activityType = civicrm_api('OptionGroup', 'Get', array(
      'version' => 3,
      'name' => 'activity_type',
      'return' => 'id'                                                                                                                                                                             
    ));

    $activityType = civicrm_api('OptionValue', 'Get', array(
      'version' => 3,
      'name' => 'Volunteer',
      'option_group_id' => $activityType['id'],
      'return' => 'value'
    ));

    if ($activityType['count']) {
      foreach($activityType['values'] as $actType) {
        return $actType['value'];
      }   
    }
    else {
      $result = civicrm_api('ActivityType', 'create', array(
        'version' => 3,
        'name' => 'Volunteer',
        'label' => 'Volunteer',
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
      array(
        'name' => 'Available',                                                                                                                                           
        'label' => 'Available'),
      array(
        'name' => 'No_show',                                                                                                                                                
        'label' => 'No-show')
    );

    foreach( $activityStatuses as $eachActivityStatus ) {
      $activityStatus = civicrm_api('OptionValue', 'Get', array(
        'version' => 3,
        'name' => $eachActivityStatus['name'],
        'option_group_id' => $activityStatusID,
        'return' => 'value'
      ));
  
      if (!$activityStatus['count']) {
        $params = array(
          'version' => 3,
          'sequential' => 1,
          'option_group_id'=> $activityStatusID,
          'name' => $eachActivityStatus['name'],
          'label' => $eachActivityStatus['label'],
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
      //x dpm($xmlCode);
      $xml = simplexml_load_string($xmlCode);

      require_once 'CRM/Utils/Migrate/Import.php';
      $import = new CRM_Utils_Migrate_Import();
      $import->runXmlElement($xml);
      return TRUE;
  }

}
