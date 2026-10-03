<?php

namespace Civi\Api4\Action\VolunteerAssignment;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Roster rows across current and historical assignment statuses.
 */
class GetRoster extends AbstractAction {

  /**
   * @required
   * @var int
   */
  protected $projectId;

  /**
   * @var bool
   */
  protected $includePast = FALSE;

  public function _run(Result $result) {
    $result[] = \CRM_Volunteer_BAO_Assignment::getRosterData(
      (int) $this->projectId,
      (bool) $this->includePast,
      $this->getCheckPermissions()
    );
  }

}
