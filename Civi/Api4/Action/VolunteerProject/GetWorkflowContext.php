<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Everything the project workflow header and steps read for one project.
 *
 * One request replaces the separate project, need, assignment, capacity,
 * supporting-data and beneficiary reads the Angular workflow used to issue on
 * every step change. Each piece is still produced by the same guarded read it
 * came from, so a caller sees exactly what the individual actions would have
 * shown them.
 */
class GetWorkflowContext extends AbstractAction {

  /**
   * @required
   * @var int
   */
  protected $projectId;

  public function _run(Result $result) {
    $result[] = \CRM_Volunteer_BAO_Project::getWorkflowContext(
      (int) $this->projectId,
      $this->getCheckPermissions()
    );
  }

}
