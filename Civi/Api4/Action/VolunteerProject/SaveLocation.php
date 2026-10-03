<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class SaveLocation extends AbstractAction {

  /**
   * Owning project ID, for authorization. NULL when creating a location
   * for a not-yet-saved project.
   *
   * @var int|null
   */
  protected $projectId;

  /**
   * Flattened LocBlock values to write.
   *
   * @required
   * @var array
   */
  protected $values = [];

  public function _run(Result $result) {
    if ($this->getCheckPermissions()) {
      \CRM_Volunteer_Permission::assertProjectPerms(
        $this->projectId ? \CRM_Core_Action::UPDATE : \CRM_Core_Action::ADD,
        $this->projectId ?: NULL
      );
    }
    $result[] = ['id' => \CRM_Volunteer_BAO_Project::saveLocationBlock($this->values)];
  }

}
