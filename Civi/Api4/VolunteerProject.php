<?php

namespace Civi\Api4;

use Civi\Api4\Generic\DAOEntity;

/**
 * API4 access to volunteer projects.
 *
 * Writes continue through the guarded aggregate API while row-level project
 * authorization is moved into a shared service.
 */
class VolunteerProject extends DAOEntity {

  public static function get($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\Get(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function create($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\Create(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function update($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\Update(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function delete($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\Delete(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function commit($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\Commit(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function search($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\Search(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getWorkflowContext($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\GetWorkflowContext(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getLocationOptions($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\GetLocationOptions(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getLocation($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\GetLocation(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function saveLocation($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\SaveLocation(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function removeProfile($checkPermissions = TRUE) {
    return (new Action\VolunteerProject\RemoveProfile(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function permissions(): array {
    return array(
      // The custom Get action performs relationship-aware row authorization.
      'get' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'create' => 'create volunteer projects',
      'update' => array(array('edit own volunteer projects', 'edit all volunteer projects')),
      'delete' => array(array('delete own volunteer projects', 'delete all volunteer projects')),
      'commit' => array(array('create volunteer projects', 'edit own volunteer projects', 'edit all volunteer projects')),
      // The action performs its own context-dependent authorization: an edit
      // context requires project edit rights, a public read only the viewer
      // permission asserted by assertProjectPerms().
      'search' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'getManageOverview' => array(array('edit own volunteer projects', 'edit all volunteer projects')),
      // The bundled reads each apply their own row-level project
      // authorization; this coarse gate only refuses callers who could not
      // pass any of them.
      'getWorkflowContext' => array(array('create volunteer projects', 'edit own volunteer projects', 'edit all volunteer projects')),
      'getLocationOptions' => array(array('create volunteer projects', 'edit own volunteer projects', 'edit all volunteer projects')),
      'getLocation' => array(array('create volunteer projects', 'edit own volunteer projects', 'edit all volunteer projects')),
      'saveLocation' => array(array('create volunteer projects', 'edit own volunteer projects', 'edit all volunteer projects')),
      'removeProfile' => 'edit volunteer registration profiles',
      // The generic save/replace bypass the aggregate services' own entry
      // points; use the explicit create/update/delete actions instead.
      'save' => \CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
      'replace' => \CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
    );
  }

}
