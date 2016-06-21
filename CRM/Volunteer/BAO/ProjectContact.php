<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
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
 * @copyright CiviCRM LLC (c) 2004-2015
 * $Id$
 *
 */

class CRM_Volunteer_BAO_ProjectContact extends CRM_Volunteer_DAO_ProjectContact {

  const RELATIONSHIP_OPTION_GROUP = 'volunteer_project_relationship';

  /**
   * Helper function to determine whether or not the current user can read a
   * given contact.
   *
   * @param mixed $contactId
   *   Int or int-like string representing the contact ID.
   * @return boolean
   */
  static function contactIsReadable($contactId) {
    $contactIsReadable = TRUE;
    try {
      // Getlist fails given an IN param for id, so one at a time it is.
      $getList = civicrm_api3("Contact", "getlist", array(
        "id" => $contactId,
        "check_permissions" => 1
      ));
      if ($getList['count'] == 0) {
        $contactIsReadable = FALSE;
      }
    }
    catch (Exception $e) {
      $contactIsReadable = FALSE;
    }
    return $contactIsReadable;
  }

}