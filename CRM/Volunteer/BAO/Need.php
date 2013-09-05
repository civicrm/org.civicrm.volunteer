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

  function delete($params) {
      if (empty($params)) {
          CRM_Core_Error::fatal('No parameters supplied.');
      }

      $custom_group = civicrm_api('CustomGroup', 'getsingle',
              array('name' => 'CiviVolunteer')
              );
      if (isset($custom_group['is_error']) && $custom_group['is_error'] == 1) {
          CRM_Core_Error::fatal('CiviVolunteer Custom Group not defined.');
      } else {
          $group_id = $custom_group['id'];
          $table_name = $custom_group['table_name'];
      }

      $result = civicrm_api('CustomFields', 'getFields', $params);

      $need = new CRM_Volunteer_DAO_Need();
      $need->copyValues($params);

      $need->is_active = 0;
      $result = $need->save();

      return $result;
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
    return ts("I'm Flexible");
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
}
