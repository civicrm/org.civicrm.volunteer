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
    
    $need = new CRM_Volunteer_BAO_Need();
    
    $need->copyValues($params);
    $need->save();
    
    return $need;
  }
  
  function get($params) {
      if (empty($params)) {
          return;
      }
      
      $tbl_activities = $this->tableName();
      $fields = CRM_Volunteer_BAO_Need::fields();
      $fld_entity_id = $fields['civicrm_volunteer_need_id']['name'];
      $fld_entity_type = $fields['entity_table']['name'];
      
      $query = "SELECT 
          {$tbl_activities}.*
      FROM {$tbl_activities}
      WHERE {$tbl_activities}.{$fld_entity_id} = '{$params['id']}'
          AND {$tbl_activities}.{$fld_entity_type} = '{$params['type']}'
          ";
      
      $result = CRM_Core_DAO::executeQuery($query);
      
      while ($result->fetch()) {
          foreach ($fields as $id => $field) {
              $needs[$result[$fld_entity_id]]  = $result[$field['name']];
          }
      }
      
      return $needs;
  }
  
  function delete($params) {
      if (empty($params)) {
          return;
      }
      $need = new CRM_Volunteer_DAO_Need();
      $need->copyValues($params);

      $need->is_deleted = 1;
      $result = $need->save();
      
      return $result;
  }
}