<?php

namespace Civi\Api4;

use Civi\Api4\Generic\BasicGetFieldsAction;

/**
 * API4 access to volunteer assignment activities.
 *
 * @searchable secondary
 * @primaryKey id
 */
class VolunteerAssignment extends Generic\BasicEntity {

  public static function get($checkPermissions = TRUE) {
    return (new Action\VolunteerAssignment\Get(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function create($checkPermissions = TRUE) {
    return (new Action\VolunteerAssignment\Create(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function update($checkPermissions = TRUE) {
    return (new Action\VolunteerAssignment\Update(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function delete($checkPermissions = TRUE) {
    return (new Action\VolunteerAssignment\Delete(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getRoster($checkPermissions = TRUE) {
    return (new Action\VolunteerAssignment\GetRoster(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getCapacity($checkPermissions = TRUE) {
    return (new Action\VolunteerAssignment\GetCapacity(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getHourEntries($checkPermissions = TRUE) {
    return (new Action\VolunteerAssignment\GetHourEntries(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function logHours($checkPermissions = TRUE) {
    return (new Action\VolunteerAssignment\LogHours(self::getEntityName(), __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  public static function getFields($checkPermissions = TRUE) {
    return (new BasicGetFieldsAction(self::getEntityName(), __FUNCTION__, [self::class, '_fields']))
      ->setCheckPermissions($checkPermissions);
  }

  public static function _fields(): array {
    // Names match what CRM_Volunteer_BAO_Assignment::retrieve() selects: the
    // joined contact columns are prefixed by their activity role, so an
    // assignee's display name is `assignee_display_name`, not `display_name`.
    $definitions = [
      'id' => 'Integer',
      'project_id' => 'Integer',
      'volunteer_need_id' => 'Integer',
      'assignee_contact_id' => 'Integer',
      // Legacy alias accepted by create and update, so it is not read-only.
      'contact_id' => 'Integer',
      'target_contact_id' => 'Integer',
      'source_contact_id' => 'Integer',
      'activity_type_id' => 'Integer',
      'status_id' => 'Integer',
      'campaign_id' => 'Integer',
      'subject' => 'String',
      'details' => 'Text',
      'activity_date_time' => 'Timestamp',
      'duration' => 'Integer',
      'time_scheduled_minutes' => 'Integer',
      'time_completed_minutes' => 'Integer',
      'volunteer_role_id' => 'Integer',
      'start_time' => 'Timestamp',
      'end_time' => 'Timestamp',
      'is_flexible' => 'Boolean',
      'role_id' => 'Integer',
      'assignee_sort_name' => 'String',
      'assignee_display_name' => 'String',
      'assignee_email' => 'String',
      'assignee_phone' => 'String',
      'assignee_phone_ext' => 'String',
      'target_sort_name' => 'String',
      'target_display_name' => 'String',
      'target_email' => 'String',
      'target_phone' => 'String',
      'target_phone_ext' => 'String',
    ];
    // Values the assignment service derives from the owning need, project or
    // contact records; a caller cannot set them directly.
    $readonly = [
      'project_id', 'activity_type_id', 'start_time', 'end_time',
      'is_flexible', 'role_id', 'target_contact_id', 'campaign_id',
      'assignee_sort_name', 'assignee_display_name', 'assignee_email',
      'assignee_phone', 'assignee_phone_ext', 'target_sort_name',
      'target_display_name', 'target_email', 'target_phone',
      'target_phone_ext',
    ];
    $fields = [];
    foreach ($definitions as $name => $dataType) {
      $fields[] = [
        'name' => $name,
        'data_type' => $dataType,
        'readonly' => in_array($name, $readonly, TRUE),
      ];
    }
    return $fields;
  }

  public static function permissions(): array {
    $projectEdit = [[
      'create volunteer projects',
      'edit own volunteer projects',
      'edit all volunteer projects',
    ]];
    return [
      'get' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'create' => $projectEdit,
      'update' => $projectEdit,
      'delete' => $projectEdit,
      // These actions perform row-level project authorization because roster
      // access also includes project managers who may not hold an edit-own or
      // edit-all permission.
      'getRoster' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'getCapacity' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'getHourEntries' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'logHours' => \CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION,
      'save' => \CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
      'replace' => \CRM_Core_Permission::ALWAYS_DENY_PERMISSION,
    ];
  }

}
