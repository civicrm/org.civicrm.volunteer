<?php

namespace Civi\Api4\Action\VolunteerProjectContact;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;

/**
 * Project-contact reads are never exposed through the public opportunity API.
 */
class Get extends DAOGetAction {

  public function _run(Result $result) {
    if ($this->getCheckPermissions()) {
      $scope = \CRM_Volunteer_Permission::getApi4ProjectReadScope();
      if (!$scope['all']) {
        // A public-only scope has no access to ownership, management, or
        // beneficiary relationships through API4.
        $this->addWhere('project_id', 'IN', $scope['public'] ? array() : $scope['project_ids']);
      }
    }
    parent::_run($result);
  }

}
