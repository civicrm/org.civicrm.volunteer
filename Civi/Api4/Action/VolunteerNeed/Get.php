<?php

namespace Civi\Api4\Action\VolunteerNeed;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;

/**
 * Relationship-aware need reads.
 */
class Get extends DAOGetAction {

  public function _run(Result $result) {
    $publicRead = FALSE;
    if ($this->getCheckPermissions()) {
      $scope = \CRM_Volunteer_Permission::getApi4ProjectReadScope();
      if (!$scope['all']) {
        if ($scope['public']) {
          $publicRead = TRUE;
          $publicVisibility = \CRM_Core_PseudoConstant::getKey(
            'CRM_Volunteer_BAO_Need',
            'visibility_id',
            'public'
          );
          $activeProjectIds = array();
          $dao = \CRM_Core_DAO::executeQuery('SELECT id FROM civicrm_volunteer_project WHERE is_active = 1');
          while ($dao->fetch()) {
            $activeProjectIds[] = (int) $dao->id;
          }
          $this->addWhere('project_id', 'IN', $activeProjectIds);
          $this->addWhere('is_active', '=', TRUE);
          $this->addWhere('visibility_id', '=', $publicVisibility);
        }
        else {
          $this->addWhere('project_id', 'IN', $scope['project_ids']);
        }
      }
    }

    parent::_run($result);

    $rows = $result->getArrayCopy();
    $rows = \CRM_Volunteer_BAO_Need::addDisplayFields($rows);
    if ($publicRead) {
      $allowedFields = array_flip(array(
        'id',
        'project_id',
        'start_time',
        'end_time',
        'duration',
        'is_flexible',
        'quantity',
        'visibility_id',
        'role_id',
        'is_active',
        'row_count',
        'display_time',
        'role_label',
        'role_description',
      ));
      foreach ($rows as &$row) {
        $row = array_intersect_key($row, $allowedFields);
      }
      unset($row);
    }
    $result->exchangeArray($rows);
  }

}
