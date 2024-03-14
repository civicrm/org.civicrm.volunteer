<?php

class CRM_Volunteer_Permission {

  const VIEW_ROSTER = 'volunteer_view_roster'; // A number unused by CRM_Core_Action

  /**
   * Returns an array of permissions defined by this extension. Modeled off of
   * CRM_Core_Permission::getCorePermissions().
   *
   * @return array Keyed by machine names with human-readable labels for values
   */
  public static function getVolunteerPermissions() {
    $domain = array('domain' => 'org.civicrm.volunteer');
    $prefix = ts('CiviVolunteer', $domain) . ': ';
    return array(
      'register to volunteer' => array(
        'label' => $prefix . ts('register to volunteer', $domain),
        'description' => ts('Access public-facing volunteer opportunity listings and registration forms', $domain),
      ),
      'log own hours' => array(
        'label' => $prefix . ts('log own hours', $domain),
        'description' => ts('Access forms to self-report performed volunteer hours', $domain),
      ),
      'create volunteer projects' => array(
        'label' => $prefix . ts('create volunteer projects', $domain),
        'description' => ts('Create a new volunteer project record in CiviCRM', $domain),
      ),
      'edit own volunteer projects' => array(
        'label' => $prefix . ts('edit own volunteer projects', $domain),
        'description' => ts('Edit volunteer project records for which the user is specified as the Owner', $domain),
      ),
      'edit all volunteer projects' => array(
        'label' => $prefix . ts('edit all volunteer projects', $domain),
        'description' => ts('Edit all volunteer project records, regardless of ownership', $domain),
      ),
      'delete own volunteer projects' => array(
        'label' => $prefix . ts('delete own volunteer projects', $domain),
        'description' => ts('Delete volunteer project records for which the user is specified as the Owner', $domain),
      ),
      'delete all volunteer projects' => array(
        'label' => $prefix . ts('delete all volunteer projects', $domain),
        'description' => ts('Delete any volunteer project record, regardless of ownership', $domain),
      ),
      'edit volunteer project relationships' => array(
        'label' => $prefix . ts('edit volunteer project relationships', $domain),
        'description' => ts('Override system-wide default project relationships for a particular volunteer project', $domain),
      ),
      'edit volunteer registration profiles' => array(
        'label' => $prefix . ts('edit volunteer registration profiles', $domain),
        'description' => ts('Override system-wide default registration profiles for a particular volunteer project', $domain),
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

    $permClass = CRM_Core_Config::singleton()->userPermissionClass;
    $skipCheck = !$permClass->isModulePermissionSupported() && !is_a($permClass, 'CRM_Core_Permission_UnitTests');

    array_walk_recursive($permissions, function(&$v, $k) use ($skipCheck) {
      // For VOL-71, if this is a permissions-challenged Joomla instance, don't
      // enforce CiviVolunteer-defined permissions.
      if ($skipCheck) {
        if (array_key_exists($v, CRM_Volunteer_Permission::getVolunteerPermissions())) {
          $v = CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION;
        }
      }

      // Ensure that checks for "edit own" pass if user has "edit all."
      if ($v === 'edit own volunteer projects' && self::check('edit all volunteer projects')) {
        $v = CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION;
      }
    });

    return CRM_Core_Permission::check($permissions);
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
