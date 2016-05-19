<?php
class CRM_Volunteer_Hook
{
  static $_nullObject = NULL;

  static function runDefaultSettingsHook(&$defaults) {
    return CRM_Utils_Hook::singleton()->invoke(1, $defaults, self::$_nullObject,
      self::$_nullObject, self::$_nullObject, self::$_nullObject, self::$_nullObject,
      'volunteer_ProjectDefaultSettings'
    );
  }
}
?>