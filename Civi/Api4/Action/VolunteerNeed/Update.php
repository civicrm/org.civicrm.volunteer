<?php

namespace Civi\Api4\Action\VolunteerNeed;

use Civi\Api4\Generic\DAOUpdateAction;

class Update extends DAOUpdateAction {

  protected function write(array $items) {
    $this->filterUnpermittedFields($items);
    $saved = array();
    foreach ($items as $item) {
      $saved[] = \CRM_Volunteer_Permission::runApi4Write(
        $this->getCheckPermissions(),
        function(array $permissionParams) use ($item) {
          return \CRM_Volunteer_BAO_Need::create($permissionParams + $item);
        }
      );
    }
    return $saved;
  }

}
