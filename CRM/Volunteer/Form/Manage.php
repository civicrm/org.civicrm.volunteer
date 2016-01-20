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

class CRM_Volunteer_Form_Manage {

  /**
   * Load needed JS, CSS and settings for the backend Volunteer Management UI
   */
  public static function addResources($entity_id, $entity_table) {
    static $loaded = FALSE;
    $ccr = CRM_Core_Resources::singleton();
    if ($loaded || $ccr->isAjaxMode()) {
      return;
    }
    $loaded = TRUE;
    $config = CRM_Core_Config::singleton();

    // Vendor libraries
    $ccr->addScriptFile('civicrm', 'packages/backbone/json2.js', 100, 'html-header', FALSE);
    $ccr->addScriptFile('civicrm', 'packages/backbone/backbone-min.js', 120, 'html-header', FALSE);
    $ccr->addScriptFile('civicrm', 'packages/backbone/backbone.marionette.min.js', 125, 'html-header', FALSE);

    // Our stylesheet
    $ccr->addStyleFile('org.civicrm.volunteer', 'css/volunteer_app.css');

    // Add all scripts for our js app
    $weight = 0;
    $baseDir = CRM_Extension_System::singleton()->getMapper()->keyToBasePath('org.civicrm.volunteer') . '/';
    // This glob pattern will recurse the js directory up to 4 levels deep
    foreach (glob($baseDir . 'js/backbone/{*,*/*,*/*/*,*/*/*/*}.js', GLOB_BRACE) as $file) {
      $fileName = substr($file, strlen($baseDir));
      $ccr->addScriptFile('org.civicrm.volunteer', $fileName, $weight++);
    }

    // Add our template
    CRM_Core_Smarty::singleton()->assign('isModulePermissionSupported',
      CRM_Core_Config::singleton()->userPermissionClass->isModulePermissionSupported());
    CRM_Core_Region::instance('page-header')->add(array(
      'template' => 'CRM/Volunteer/Form/Manage.tpl',
    ));

    // Fetch event so we can set the default start time for needs
    // FIXME: Not the greatest for supporting non-events
    $entity = civicrm_api3(str_replace('civicrm_', '', $entity_table), 'getsingle', array('id' => $entity_id));

    // Static variables
    $ccr->addSetting(array(
      'pseudoConstant' => array(
        'volunteer_need_visibility' => array_flip(CRM_Volunteer_BAO_Need::buildOptions('visibility_id', 'validate')),
        'volunteer_role' => CRM_Volunteer_BAO_Need::buildOptions('role_id', 'get'),
        'volunteer_status' => CRM_Activity_BAO_Activity::buildOptions('status_id', 'validate'),
      ),
      'volunteer' => array(
        'default_date' => CRM_Utils_Array::value('start_date', $entity),
      ),
      'config' => array(
        'timeInputFormat' => $config->timeInputFormat,
      ),
      'constants' => array(
        'CRM_Core_Action' => array(
          'NONE' => 0,
          'ADD' => 1,
          'UPDATE' => 2,
          'VIEW' => 4,
          'DELETE' => 8,
          'BROWSE' => 16,
          'ENABLE' => 32,
          'DISABLE' => 64,
          'EXPORT' => 128,
          'BASIC' => 256,
          'ADVANCED' => 512,
          'PREVIEW' => 1024,
          'FOLLOWUP' => 2048,
          'MAP' => 4096,
          'PROFILE' => 8192,
          'COPY' => 16384,
          'RENEW' => 32768,
          'DETACH' => 65536,
          'REVERT' => 131072,
          'CLOSE' =>  262144,
          'REOPEN' =>  524288,
          'MAX_ACTION' => 1048575,
        ),
      ),
    ));

    // Check for problems
    _volunteer_checkResourceUrl();
  }
}
