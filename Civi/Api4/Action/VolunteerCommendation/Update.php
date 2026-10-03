<?php

namespace Civi\Api4\Action\VolunteerCommendation;

use Civi\Api4\Generic\BasicUpdateAction;

class Update extends BasicUpdateAction {

  protected function writeRecord($item) {
    $params = [
      'aid' => $item['id'],
      'cid' => $item['volunteer_contact_id'] ?? NULL,
      'vid' => $item['volunteer_project_id'] ?? NULL,
    ];
    if (array_key_exists('details', $item)) {
      $params['details'] = $item['details'];
    }
    $activity = \CRM_Volunteer_Permission::runApi4Write(
      $this->getCheckPermissions(),
      fn(array $permissionParams) => \CRM_Volunteer_BAO_Commendation::create($permissionParams + $params)
    );
    $id = (int) ($activity['id'] ?? $item['id']);
    $rows = \CRM_Volunteer_BAO_Commendation::retrieve(['id' => $id]);
    return $rows[$id] ?? ['id' => $id];
  }

}
