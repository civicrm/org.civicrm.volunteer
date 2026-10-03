<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class Commit extends AbstractAction {

  /**
   * Project aggregate values, including contacts, profiles and location.
   *
   * @required
   * @var array
   */
  protected $values = [];

  public function _run(Result $result) {
    $project = \CRM_Volunteer_Permission::runApi4Write(
      $this->getCheckPermissions(),
      function(array $permissionParams) {
        return \CRM_Volunteer_BAO_Project::create($permissionParams + $this->values);
      }
    );
    $result[] = $project->toArray();
  }

}
