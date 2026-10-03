<?php

namespace Civi\Api4\Action\VolunteerProjectContact;

use Civi\Api4\Generic\DAOCreateAction;
class Create extends DAOCreateAction {

  protected function write(array $items) {
    $this->filterUnpermittedFields($items);
    $saved = array();
    foreach ($items as $item) {
      $saved[] = \CRM_Volunteer_Permission::runApi4Write(
        $this->getCheckPermissions(),
        function(array $permissionParams) use ($item) {
          return \CRM_Volunteer_BAO_ProjectContact::create($permissionParams + $item);
        }
      );
    }
    return $saved;
  }

}
