<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
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
   * class constructor
   */
  function __construct() {
    parent::__construct();
  }

  /**
   * Function to create a Volunteer Project
   * takes an associative array and creates a Project object
   *
   * This function is invoked from within the web form layer and also from the api layer
   *
   * @param array   $params      (reference ) an assoc array of name/value pairs
   *
   * @return object CRM_Volunteer_BAO_Project object
   * @access public
   * @static
   */
  static function create(array $params) {

    // check required params
    if (!self::dataExists($params)) {
      CRM_Core_Error::fatal('Not enough data to create volunteer project object.');
    }

    // default to active unless explicitly turned off
    $params['is_active'] = CRM_Utils_Array::value('is_active', $params, TRUE);

    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);

    $project->save();

    return $project;
  }

  /**
   * Unsets the Project's is_active flag in the database
   */
  public function disable() {
    $this->is_active = 0;
    $this->save();
  }

  /**
   * Sets the Project's is_active flag in the database
   */
  public function enable() {
    $this->is_active = 1;
    $this->save();
  }

  /**
   * Get a list of Projects matching the params, where params keys are column
   * names of civicrm_volunteer_project.
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  static function retrieve(array $params) {
    $result = array();

    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);
    $project->find();

    while ($project->fetch()) {
      $result[] = $project;
    }

    $project->free();

    return $result;
  }

  /**
   * Check if there is absolute minimum of data to add the object
   *
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return boolean
   * @access public
   */
  public static function dataExists($params) {
    if (
      CRM_Utils_Array::value('id', $params) || (
        CRM_Utils_Array::value('entity_table', $params) &&
        CRM_Utils_Array::value('entity_id', $params)
      )
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns TRUE if value represents an "off" value, FALSE otherwise
   *
   * @param type $value
   * @return boolean
   * @access public
   */
  public static function isOff($value) {
    if (in_array($value, array(FALSE, 0, '0'), TRUE)) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * Given an associative array of name/value pairs, extract all the values
   * that belong to this object and initialize the object with said values. This
   * override adds a little data massaging prior to calling its parent.
   *
   * @param array $params (reference ) associative array of name/value pairs
   *
   * @return boolean      did we copy all null values into the object
   * @access public
   */
  public function copyValues(&$params) {
    if (array_key_exists('is_active', $params)) {
      /*
       * don't force is_active to have a value if none was set, to allow searches
       * where the is_active state of Projects is irrelevant
       */
      $params['is_active'] = CRM_Volunteer_BAO_Project::isOff($params['is_active']) ? 0 : 1;
    }
    return parent::copyValues($params);
  }

  public function getVolunteerCommitment( $projectID ) {
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContacts);

    $activityType = CRM_Activity_BAO_Activity::buildOptions('activity_type_id', 'validate');
    $volunteerID = CRM_Utils_Array::key('Volunteer', $activityType);

    $tableInfo = self::getVolunteerCustomTable();
    $tableName = $tableInfo['table_name'];
    $columnNames = $tableInfo['column_name'];

    foreach ($columnNames as $name => $column) {
      $selectClause[] = "custom.{$column} AS {$name}";
    }
    $customSelect = implode(', ', $selectClause);

    $query = "
SELECT ac.contact_id, a.status_id, custom.entity_id AS activity_id,
       {$customSelect},
       need.start_time, need.is_flexible, need.role_id
FROM civicrm_activity a
LEFT JOIN civicrm_activity_contact ac ON ( ac.activity_id = a.id
AND ac.record_type_id = %1 )
LEFT JOIN {$tableName} custom ON ( custom.entity_id = a.id )
LEFT JOIN civicrm_volunteer_need need ON ( need.id = custom.{$columnNames['volunteer_need_id']} )
LEFT JOIN civicrm_volunteer_project project ON ( project.id = need.project_id )
WHERE activity_type_id = %2 AND project.id = %3
    ";

    $params = array(
      1 => array($targetID, 'Integer'),
      2 => array($volunteerID, 'Integer'),
      3 => array($projectID, 'Integer'),
    );

    $dao = CRM_Core_DAO::executeQuery($query, $params);
    $rows = array();
    while ($dao->fetch()) {
      $row['contact_id'] = $dao->contact_id;
      $row['status_id'] = $dao->status_id;
      $row['role_id'] = $dao->role_id;
      $row['is_flexible'] = $dao->is_flexible;
      $row['time_scheduled'] = $dao->time_scheduled_minutes;
      $row['time_completed'] = $dao->time_completed_minutes;
      $row['start_time'] = $dao->start_time;
      $rows[$dao->activity_id] = $row;
    }
    return $rows;
  }

  public function getVolunteerCustomTable( ) {
    if (!$gid = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_CustomGroup', 'CiviVolunteer', 'id', 'name')) {
      return;
    }
    $tableName = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_CustomGroup', $gid, 'table_name', 'id');

    $details = array();
    CRM_Core_DAO::commonRetrieveAll('CRM_Core_BAO_CustomField', 'custom_group_id', $gid, $details);

    foreach ($details as $value) {
      $columnNames[strtolower($value['name'])] = $value['column_name'];
    }

    $tableInfo = array(
      'table_name' => $tableName,
      'column_name' => $columnNames
    );
    return $tableInfo;
  }
}
