<?php

namespace Civi\Api4\Action\VolunteerCommendation;

use Civi\Api4\Generic\BasicCreateAction;

class Create extends BasicCreateAction {

  protected function writeRecord($item) {
    unset($item['id']);
    return $this->saveCommendation($item);
  }

  protected function saveCommendation(array $item): array {
    $params = [
      'cid' => $item['volunteer_contact_id'] ?? NULL,
      'vid' => $item['volunteer_project_id'] ?? NULL,
      'details' => $item['details'] ?? NULL,
    ];
    $activity = \CRM_Volunteer_Permission::runApi4Write(
      $this->getCheckPermissions(),
      fn(array $permissionParams) => \CRM_Volunteer_BAO_Commendation::create($permissionParams + $params)
    );
    $id = (int) ($activity['id'] ?? 0);
    $rows = \CRM_Volunteer_BAO_Commendation::retrieve(['id' => $id]);
    return $rows[$id] ?? ['id' => $id];
  }

}
