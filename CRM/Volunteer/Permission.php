<?php

class CRM_Volunteer_Permission extends CRM_Core_Permission {

  /**
   * Returns an array of permissions defined by this extension. Modeled off of
   * CRM_Core_Permission::getCorePermissions().
   *
   * @return array Keyed by machine names with human-readable labels for values
   */
  public static function getVolunteerPermissions() {
    $prefix = ts('CiviVolunteer', array('domain' => 'org.civicrm.volunteer')) . ': ';
    return array(
      'register to volunteer' => $prefix . ts('register to volunteer', array('domain' => 'org.civicrm.volunteer')),
    );
  }

  /**
   * Given a permission string or array, check for access requirements. For
   * VOL-71, if this is a permissions-challenged Joomla instance, don't enforce
   * CiviVolunteer-defined permissions.
   *
   * @param mixed $permissions The permission(s) to check as an array or string.
   *        See parent class for examples.
   * @return boolean
   */
  public static function check($permissions) {
    $permissions = (array) $permissions;

    if (!CRM_Core_Config::singleton()->userPermissionClass->isModulePermissionSupported()) {
      array_walk_recursive($permissions, function(&$v, $k) {
        if (array_key_exists($v, self::getVolunteerPermissions())) {
          $v = CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION;
        }
      });
    }

    return parent::check($permissions);
  }
}
