<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class GetLocationOptions extends AbstractAction {

  /**
   * Optional project to scope the location options for.
   *
   * @var int|null
   */
  protected $projectId;

  public function _run(Result $result) {
    foreach (\CRM_Volunteer_BAO_Project::getLocationOptions($this->projectId, $this->getCheckPermissions()) as $row) {
      $result[] = $row;
    }
  }

}
