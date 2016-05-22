<?php
class CRM_Volunteer_Hook
{

  static function projectDefaultSettings(&$defaults) {
    return CRM_Utils_Hook::singleton()->invoke(1, $defaults, CRM_Utils_Hook::$_nullObject,
      CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject,
      'civicrm_volunteer_projectDefaultSettings'
    );
  }
}