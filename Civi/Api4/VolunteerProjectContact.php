<?php

namespace Civi\Api4;

use Civi\Api4\Generic\DAOEntity;

/**
 * API4 access to volunteer project contacts.
 */
class VolunteerProjectContact extends DAOEntity {

  public static function get($checkPermissions = TRUE) {
    return (new Action\VolunteerProjectContact\Get(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function create($checkPermissions = TRUE) {
    return (new Action\VolunteerProjectContact\Create(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function update($checkPermissions = TRUE) {
    return (new Action\VolunteerProjectContact\Update(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function delete($checkPermissions = TRUE) {
    return (new Action\VolunteerProjectContact\Delete(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function permissions(): array {
    $projectEdit = array(array(
      'create volunteer projects',
      'edit own volunteer projects',
      'edit all volunteer projects',
    ), 'edit volunteer project relationships');
    return array(
      'get' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'create' => $projectEdit,
      'update' => $projectEdit,
      'delete' => $projectEdit,
      // The generic save/replace bypass the aggregate services' own entry
      // points; use the explicit create/update/delete actions instead.
      'save' => \CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
      'replace' => \CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
    );
  }

}
