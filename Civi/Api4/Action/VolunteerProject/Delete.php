<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\DAODeleteAction;

class Delete extends DAODeleteAction {

  protected function deleteObjects($items) {
    $result = array();
    foreach ($items as $item) {
      \CRM_Volunteer_Permission::runApi4Write(
        $this->getCheckPermissions(),
        function(array $permissionParams) use ($item) {
          return \CRM_Volunteer_BAO_Project::deleteProject(
            $item['id'],
            !array_key_exists('check_permissions', $permissionParams)
          );
        }
      );
      $result[] = array('id' => (int) $item['id']);
    }
    return $result;
  }

}
