<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class RemoveProfile extends AbstractAction {

  /**
   * UFJoin ID of the profile to remove.
   *
   * @required
   * @var int
   */
  protected $id;

  /**
   * Project the profile is attached to.
   *
   * @required
   * @var int
   */
  protected $projectId;

  public function _run(Result $result) {
    \CRM_Volunteer_BAO_Project::removeProfile($this->id, $this->projectId, $this->getCheckPermissions());
    $result[] = ['id' => (int) $this->id];
  }

}
