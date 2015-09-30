<?php

class CRM_Volunteer_Page_Backbone extends CRM_Core_Page {
  function run() {
    // Add our template
    CRM_Core_Smarty::singleton()->assign('isModulePermissionSupported',
      CRM_Core_Config::singleton()->userPermissionClass->isModulePermissionSupported());
    CRM_Core_Region::instance('page-header')->add(array(
      // TODO: consider renaming this TPL
      'template' => 'CRM/Volunteer/Form/Manage.tpl',
    ));

    parent::run();
  }
}
