<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class GetLocation extends AbstractAction {

  /**
   * LocBlock ID to load.
   *
   * @required
   * @var int
   */
  protected $id;

  /**
   * Optional owning project ID, used for authorization.
   *
   * @var int|null
   */
  protected $projectId;

  public function _run(Result $result) {
    foreach (\CRM_Volunteer_BAO_Project::getLocationData($this->id, $this->projectId, $this->getCheckPermissions()) as $row) {
      $result[] = $row;
    }
  }

}
