<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;

/**
 * Relationship-aware project reads.
 */
class Get extends DAOGetAction {

  public function _run(Result $result) {
    $publicRead = FALSE;
    if ($this->getCheckPermissions()) {
      $scope = \CRM_Volunteer_Permission::getApi4ProjectReadScope();
      if (!$scope['all']) {
        if ($scope['public']) {
          $publicRead = TRUE;
          $this->addWhere('is_active', '=', TRUE);
        }
        else {
          $this->addWhere('id', 'IN', $scope['project_ids']);
        }
      }
    }

    parent::_run($result);
    if ($publicRead) {
      $allowedFields = array_flip(array(
        'id',
        'title',
        'description',
        'is_active',
        'loc_block_id',
        'campaign_id',
        'row_count',
      ));
      $rows = $result->getArrayCopy();
      foreach ($rows as &$row) {
        if (isset($row['description'])) {
          $row['description'] = \CRM_Utils_String::purifyHTML($row['description']);
        }
        $row = array_intersect_key($row, $allowedFields);
      }
      unset($row);
      $result->exchangeArray($rows);
    }
  }

}
