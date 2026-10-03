<?php

namespace Civi\Api4\Action\VolunteerAssignment;

use Civi\Api4\Generic\BasicGetAction;

class Get extends BasicGetAction {

  protected function getRecords() {
    $params = $this->simpleFilters();

    if ($this->getCheckPermissions()) {
      $projectIds = \CRM_Volunteer_Permission::extractRequestedIds($params['project_id'] ?? NULL);
      if ($projectIds === []) {
        $needIds = \CRM_Volunteer_Permission::extractRequestedIds($params['volunteer_need_id'] ?? NULL);
        $projectIds = $needIds === NULL
          ? NULL
          : \CRM_Volunteer_Permission::projectIdsForRecords('CRM_Volunteer_DAO_Need', $needIds);
      }
      if ($projectIds === [] && !empty($params['id'])) {
        $assignmentIds = \CRM_Volunteer_Permission::extractRequestedIds($params['id']);
        $projectIds = [];
        foreach ($assignmentIds ?? [] as $assignmentId) {
          $assignment = \CRM_Volunteer_BAO_Assignment::retrieve(['id' => $assignmentId]);
          $assignment = reset($assignment);
          if ($assignment) {
            $projectIds[] = (int) $assignment['project_id'];
          }
        }
      }
      if (empty($projectIds)) {
        throw new \CRM_Core_Exception('A volunteer project scope is required to retrieve assignments.', \CRM_Core_Exception::UNAUTHORIZED);
      }
      foreach (array_unique($projectIds) as $projectId) {
        if (!\CRM_Volunteer_Permission::checkProjectPerms(\CRM_Core_Action::UPDATE, $projectId)
          && !\CRM_Volunteer_Permission::checkProjectPerms(\CRM_Volunteer_Permission::VIEW_ROSTER, $projectId)) {
          throw new \CRM_Core_Exception('You do not have permission to view assignments for this volunteer project.', \CRM_Core_Exception::UNAUTHORIZED);
        }
      }
    }

    return array_values(\CRM_Volunteer_BAO_Assignment::retrieve($params));
  }

  private function simpleFilters(): array {
    $params = [];
    foreach ($this->getWhere() as $clause) {
      if (!is_array($clause) || count($clause) < 3 || !is_string($clause[0])) {
        continue;
      }
      [$field, $operator, $value] = $clause;
      if (!in_array($field, [
        'id', 'project_id', 'volunteer_need_id', 'assignee_contact_id',
        'contact_id', 'target_contact_id', 'status_id', 'activity_date_time',
      ], TRUE)) {
        continue;
      }
      if ($field === 'contact_id') {
        $field = 'assignee_contact_id';
      }
      if ($operator === '=') {
        $params[$field] = $value;
      }
      elseif ($operator === 'IN') {
        $params[$field] = ['IN' => (array) $value];
      }
    }
    return $params;
  }

}
