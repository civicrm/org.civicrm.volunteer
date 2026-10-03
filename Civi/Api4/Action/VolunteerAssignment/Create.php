<?php

namespace Civi\Api4\Action\VolunteerAssignment;

use Civi\Api4\Generic\BasicCreateAction;

class Create extends BasicCreateAction {

  protected function writeRecord($item) {
    unset($item['id']);
    return $this->saveAssignment($item);
  }

  protected function saveAssignment(array $item): array {
    if (isset($item['contact_id']) && !isset($item['assignee_contact_id'])) {
      $item['assignee_contact_id'] = $item['contact_id'];
    }
    unset($item['contact_id']);
    $id = \CRM_Volunteer_Permission::runApi4Write(
      $this->getCheckPermissions(),
      fn(array $permissionParams) => \CRM_Volunteer_BAO_Assignment::createVolunteerActivity($permissionParams + $item)
    );
    $rows = \CRM_Volunteer_BAO_Assignment::retrieve(['id' => $id]);
    return $rows[$id] ?? ['id' => (int) $id];
  }

}
