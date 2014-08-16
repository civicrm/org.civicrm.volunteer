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

class CRM_Volunteer_BAO_Need extends CRM_Volunteer_DAO_Need {

  const FLEXIBLE_ROLE_ID = -1;

   /**
   * class constructor
   */
  function __construct() {
      parent::__construct();

  }

  /**
   * Function to create a Volunteer Need
   * takes an associative array and creates a Need object
   *
   * This function is invoked from within the web form layer and also from the api layer
   *
   * @param array   $params      (reference ) an assoc array of name/value pairs
   *
   * @return object CRM_Volunteer_BAO_Need object
   * @access public
   * @static
   */
  static function &create($params) {

    if (empty($params)) {
      return;
    }

    $need = new CRM_Volunteer_DAO_Need();

    $need->copyValues($params);
    $need->save();

    return $need;
  }

  /**
   * Gets label to be used for Flexible Needs.
   *
   * Implemented as a function in case we need to use logic later (e.g., if we
   * allow users to set this on a per-project basis).
   *
   * @return string
   */
  static function getFlexibleRoleLabel() {
    return ts("I'm Flexible", array('domain' => 'org.civicrm.volunteer'));
  }

  /**
   * Returns a string representing the times of a shift. Times will be formatted
   * according to the user's defined time display settings. If no duration is
   * given, only the formatted start time will be returned.
   *
   * @param string $start Should be a parseable time string
   * @param mixed $duration An int or a string, in minutes, or NULL for no end time
   * @return mixed Returns a string on success, boolean FALSE if $start is not
   * a parseable time.
   */
  static function getTimes($start, $duration = NULL) {
    $result = FALSE;

    if (strtotime($start)) {
      $config = CRM_Core_Config::singleton();
      $timeFormat = $config->dateformatDatetime;
      $result = CRM_Utils_Date::customFormat($start, $timeFormat);

      if (
        $duration
        && (is_int($duration) || ctype_digit($duration))
      ) {
        $date = new DateTime($start);
        $startDay = $date->format('Y-m-d');
        $date->add(new DateInterval("PT{$duration}M"));
        $end = $date->format('Y-m-d H:i:s');
        // If days are the same, only show time
        if ($date->format('Y-m-d') == $startDay) {
          $timeFormat = $config->dateformatTime;
        }
        $result .= ' - ' . CRM_Utils_Date::customFormat($end, $timeFormat);
      }
    }

    return $result;
  }

  /**
   * Delete a need, reassign its activities to the project's default flexible need
   * @param $id
   * @return bool
   */
  static function del($id) {
    $need = civicrm_api3('volunteer_need', 'getsingle', array('id' => $id));

    // TODO: What do we do with associated activities when deleting a flexible need?
    if (empty($need['is_flexible'])) {
      // Lookup the flexible need
      $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID($need['project_id']);

      // Reassign any activities back to the flexible need
      $acts = civicrm_api3('volunteer_assignment', 'get', array('volunteer_need_id' => $id));
      $status = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Available');
      foreach ($acts['values'] as $act) {
        civicrm_api3('volunteer_assignment', 'create', array(
          'id' => $act['id'],
          'volunteer_need_id' => $flexibleNeedId,
          'status_id' => $status,
          'time_scheduled_minutes' => 0,
        ));
      }
    }

    $dao = new CRM_Volunteer_DAO_Need();
    $dao->id = $id;
    if ($dao->find()) {
      while ($dao->fetch()) {
        $dao->delete();
      }
    }
    else {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @param int $need_id
   * @return int The number of assignments on the given need
   */
  public static function getAssignmentCount($need_id) {
    CRM_Utils_Type::validate($need_id, 'Integer');
    return civicrm_api3('VolunteerAssignment', 'getcount', array(
      'volunteer_need_id' => $need_id,
    ));
  }
}