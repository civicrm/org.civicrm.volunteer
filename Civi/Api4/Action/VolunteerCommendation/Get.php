<?php

namespace Civi\Api4\Action\VolunteerCommendation;

use Civi\Api4\Generic\BasicGetAction;

class Get extends BasicGetAction {

  protected function getRecords() {
    $params = [];
    foreach ($this->getWhere() as $clause) {
      if (is_array($clause) && count($clause) >= 3 && $clause[1] === '=') {
        $params[$clause[0]] = $clause[2];
      }
    }

    if ($this->getCheckPermissions()) {
      $projectId = (int) ($params['volunteer_project_id'] ?? 0);
      if (!$projectId && !empty($params['id'])) {
        $rows = \CRM_Volunteer_BAO_Commendation::retrieve(['id' => $params['id']]);
        $row = reset($rows);
        $projectId = (int) ($row['volunteer_project_id'] ?? 0);
      }
      if (!$projectId) {
        throw new \CRM_Core_Exception('A volunteer project ID is required to retrieve commendations.');
      }
      \CRM_Volunteer_Permission::assertProjectPerms(\CRM_Core_Action::UPDATE, $projectId);
    }

    return array_values(\CRM_Volunteer_BAO_Commendation::retrieve($params));
  }

}
