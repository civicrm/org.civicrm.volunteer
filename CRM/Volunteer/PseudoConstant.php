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

/**
 * This class holds all the Pseudo constants that are specific to Volunteer. This avoids
 * polluting the core class and isolates the Volunteer
 */
class CRM_Volunteer_PseudoConstant extends CRM_Core_PseudoConstant {

  /**
   * Volunteer Role
   *
   * @var array
   * @static
   */
  private static $volunteerRole;

  /**
   * Get all the volunteer roles
   *
   * @access public
   *
   * @return array - array reference of all volunteer roles if any
   * @static
   */
  public static function &volunteerRole($id = NULL, $cond = NULL) {
    $index = $cond ? $cond : 'No Condition';
    if (!CRM_Utils_Array::value($index, self::$volunteerRole)) {
      self::$volunteerRole[$index] = array();

      $condition = NULL;

      if ($cond) {
        $condition = "AND $cond";
      }

      self::$volunteerRole[$index] = CRM_Core_OptionGroup::values('volunteer_role', FALSE, FALSE,
        FALSE, $condition
      );
    }

    if ($id) {
      return self::$volunteerRole[$index][$id];
    }
    return self::$volunteerRole[$index];
  }

 }

