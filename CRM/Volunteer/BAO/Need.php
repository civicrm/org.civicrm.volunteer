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
  /**
   * Return an ID-Indexed array of Needs
   * 
   * @param array $params
   * 
   * @usage
   */
  static function getNeeds( array $params) {
      if (empty($params)) {
          return;
      }
      
      $daoNeed = new CRM_Volunteer_DAO_Need();
      $daoNeed->copyValues($params);
      $daoNeed->find();
      
      while ($daoNeed->fetch()) {
          foreach ($fields as $id => $field) {
              $needs[$daoNeed->id]  = $daoNeed;
          }
      }
      
      return $needs;
  }
  
  function delete($params) {
      if (empty($params)) {
          return 'ERROR';
      }
      
      $custom_group = civicrm_api('CustomGroup', 'getsingle', 
              array('name' => 'CiviVolunteer')
              );
      if (isset($custom_group['is_error']) && $custom_group['is_error'] == 1) {
          return 'ERROR';
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
}