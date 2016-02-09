<?php

class CRM_Volunteer_Permission extends CRM_Core_Permission {

  const VIEW_ROSTER = 'volunteer_view_roster'; // A number unused by CRM_Core_Action

  /**
   * Returns an array of permissions defined by this extension. Modeled off of
   * CRM_Core_Permission::getCorePermissions().
   *
   * @return array Keyed by machine names with human-readable labels for values
   */
  public static function getVolunteerPermissions() {
    $prefix = ts('CiviVolunteer', array('domain' => 'org.civicrm.volunteer')) . ': ';
    return array(
      'register to volunteer' => array(
        $prefix . ts('register to volunteer', array('domain' => 'org.civicrm.volunteer')),
        ts('Access public-facing volunteer opportunity listings and registration forms', array('domain' => 'org.civicrm.volunteer')),
      ),
      'log own hours' => array(
        $prefix . ts('log own hours', array('domain' => 'org.civicrm.volunteer')),
        ts('Access forms to self-report performed volunteer hours', array('domain' => 'org.civicrm.volunteer')),
      ),
      'create volunteer projects' => array(
        $prefix . ts('create volunteer projects', array('domain' => 'org.civicrm.volunteer')),
        ts('Create a new volunteer project record in CiviCRM', array('domain' => 'org.civicrm.volunteer')),
      ),
      'edit own volunteer projects' => array(
        $prefix . ts('edit own volunteer projects', array('domain' => 'org.civicrm.volunteer')),
        ts('Edit volunteer project records for which the user is specified as the Owner', array('domain' => 'org.civicrm.volunteer')),
      ),
      'edit all volunteer projects' => array(
        $prefix . ts('edit all volunteer projects', array('domain' => 'org.civicrm.volunteer')),
        ts('Edit all volunteer project records, regardless of ownership', array('domain' => 'org.civicrm.volunteer')),
      ),
      'delete own volunteer projects' => array(
        $prefix . ts('delete own volunteer projects', array('domain' => 'org.civicrm.volunteer')),
        ts('Delete volunteer project records for which the user is specified as the Owner', array('domain' => 'org.civicrm.volunteer')),
      ),
      'delete all volunteer projects' => array(
        $prefix . ts('delete all volunteer projects', array('domain' => 'org.civicrm.volunteer')),
        ts('Delete any volunteer project record, regardless of ownership', array('domain' => 'org.civicrm.volunteer')),
      ),
    );
  }

  /**
   * Given a permission string or array, check for access requirements.
   *
   * @param mixed $permissions
   *   The permission(s) to check as an array or string. See parent class for examples.
   * @return boolean
   */
  public static function check($permissions) {
    $permissions = (array) $permissions;
    $isModulePermissionSupported = CRM_Core_Config::singleton()->userPermissionClass->isModulePermissionSupported();

    array_walk_recursive($permissions, function(&$v, $k) use ($isModulePermissionSupported) {
      // For VOL-71, if this is a permissions-challenged Joomla instance, don't
      // enforce CiviVolunteer-defined permissions.
      if (!$isModulePermissionSupported) {
        if (array_key_exists($v, CRM_Volunteer_Permission::getVolunteerPermissions())) {
          $v = CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION;
        }
      }

      // Ensure that checks for "edit own" pass if user has "edit all."
      if ($v === 'edit own volunteer projects' && self::check('edit all volunteer projects')) {
        $v = CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION;
      }
    });

    return parent::check($permissions);
  }

  /**
   * Checks whether the logged in user has permission to perform an action
   * against a specified project.
   *
   * @param int $op
   *   See the constants in CRM_Core_Action and CRM_Volunteer_Page_Roster.
   * @param int $projectId
   *   Required for some but not all operations.
   * @return boolean
   *   TRUE is the action is allowed; else FALSE.
   */
  public static function checkProjectPerms($op, $projectId = NULL) {
    $opsRequiringProjectId = array(CRM_Core_Action::UPDATE, CRM_Core_Action::DELETE, self::VIEW_ROSTER,);
    if (in_array($op, $opsRequiringProjectId) && empty($projectId)) {
      CRM_Core_Error::fatal('Missing required parameter Project ID');
    }

    $contactId = CRM_Core_Session::getLoggedInContactID();

    switch ($op) {
      case CRM_Core_Action::ADD:
        return self::check('create volunteer projects');

      case CRM_Core_Action::UPDATE:
        if (self::check('edit all volunteer projects')) {
          return TRUE;
        }

        $projectOwners = CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, 'volunteer_owner');
        if (self::check('edit own volunteer projects')
          && in_array($contactId, $projectOwners)) {
          return TRUE;
        }
        break;
      case CRM_Core_Action::DELETE:
        if (self::check('delete all volunteer projects')) {
          return TRUE;
        }

        $projectOwners = CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, 'volunteer_owner');
        if (self::check('delete own volunteer projects')
          && in_array($contactId, $projectOwners)) {
          return TRUE;
        }
        break;
      case CRM_Core_Action::VIEW:
        if (self::check('register to volunteer') || self::check('edit all volunteer projects')) {
          return TRUE;
        }
        break;
      case self::VIEW_ROSTER:
        if (self::check('edit all volunteer projects')) {
          return TRUE;
        }

        $projectManagers = CRM_Volunteer_BAO_Project::getContactsByRelationship($projectId, 'volunteer_manager');
        if (in_array($contactId, $projectManagers)) {
          return TRUE;
        }
        break;
    }

    return FALSE;
  }

}
