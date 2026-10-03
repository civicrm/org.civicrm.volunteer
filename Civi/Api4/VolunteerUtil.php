<?php

namespace Civi\Api4;

use Civi\Api4\Generic\BasicGetFieldsAction;

/**
 * API4 access to CiviVolunteer's shared UI-support helpers.
 *
 * The implementation lives in CRM_Volunteer_BAO_VolunteerUtil; the former
 * api/v3/VolunteerUtil.php file is a thin compatibility layer over these
 * actions.
 */
class VolunteerUtil extends Generic\AbstractEntity {

  public static function getPermissions($checkPermissions = TRUE) {
    return (new Action\VolunteerUtil\GetPermissions(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getProfiles($checkPermissions = TRUE) {
    return (new Action\VolunteerUtil\GetProfiles(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getSupportingData($checkPermissions = TRUE) {
    return (new Action\VolunteerUtil\GetSupportingData(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getCountries($checkPermissions = TRUE) {
    return (new Action\VolunteerUtil\GetCountries(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getCustomFields($checkPermissions = TRUE) {
    return (new Action\VolunteerUtil\GetCustomFields(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getFields($checkPermissions = TRUE) {
    return (new BasicGetFieldsAction(self::getEntityName(), __FUNCTION__, static function() {
      return [];
    }))->setCheckPermissions($checkPermissions);
  }

  public static function permissions(): array {
    $projectManagement = [[
      'create volunteer projects',
      'edit own volunteer projects',
      'edit all volunteer projects',
    ]];
    return [
      'getPermissions' => ['access CiviCRM'],
      'getProfiles' => 'edit volunteer registration profiles',
      // getSupportingData and getCountries enforce controller/contextual
      // project permissions in the action itself.
      'getSupportingData' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'getCountries' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'getCustomFields' => $projectManagement,
      'default' => ['administer CiviCRM'],
    ];
  }

}
