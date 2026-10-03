<?php

namespace Civi\Api4;

use Civi\Api4\Generic\BasicGetFieldsAction;

/**
 * API4 access to volunteer commendation activities.
 *
 * @searchable secondary
 * @primaryKey id
 */
class VolunteerCommendation extends Generic\BasicEntity {

  public static function get($checkPermissions = TRUE) {
    return (new Action\VolunteerCommendation\Get(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function create($checkPermissions = TRUE) {
    return (new Action\VolunteerCommendation\Create(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function update($checkPermissions = TRUE) {
    return (new Action\VolunteerCommendation\Update(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function delete($checkPermissions = TRUE) {
    return (new Action\VolunteerCommendation\Delete(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getFields($checkPermissions = TRUE) {
    return (new BasicGetFieldsAction(self::getEntityName(), __FUNCTION__, [self::class, '_fields']))
      ->setCheckPermissions($checkPermissions);
  }

  public static function _fields(): array {
    $definitions = [
      'id' => 'Integer',
      'volunteer_project_id' => 'Integer',
      'volunteer_contact_id' => 'Integer',
      'activity_type_id' => 'Integer',
      'status_id' => 'Integer',
      'subject' => 'String',
      'details' => 'Text',
      'activity_date_time' => 'Timestamp',
      'volunteer_sort_name' => 'String',
      'volunteer_display_name' => 'String',
    ];
    $fields = [];
    foreach ($definitions as $name => $dataType) {
      $fields[] = ['name' => $name, 'data_type' => $dataType];
    }
    return $fields;
  }

  public static function permissions(): array {
    $edit = [['edit own volunteer projects', 'edit all volunteer projects']];
    return [
      'get' => $edit,
      'create' => $edit,
      'update' => $edit,
      'delete' => $edit,
      'save' => \CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
      'replace' => \CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
    ];
  }

}
