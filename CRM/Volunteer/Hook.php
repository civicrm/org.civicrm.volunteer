<?php

class CRM_Volunteer_Hook {

  /**
   * This hook is invoked when retrieving the default values for a volunteer
   * project.
   *
   * @param array $defaults
   *   Reference to the array of default values.
   *
   * @return null
   *   The return value is ignored.
   */
  static function projectDefaultSettings (array &$defaults) {
    return CRM_Utils_Hook::singleton()->invoke(1, $defaults, CRM_Utils_Hook::$_nullObject,
      CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject,
      'civicrm_volunteer_projectDefaultSettings'
    );
  }

}
