<?php

namespace Civi\Api4\Action\VolunteerAssignment;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Filled/total shift capacity for one volunteer project.
 *
 * VolunteerAssignment::get() deliberately returns only Scheduled and Available
 * activities, so a project whose volunteers have all been marked Attended would
 * otherwise report zero filled spots in the workflow header. This action counts
 * every non-deleted volunteer activity instead.
 */
class GetCapacity extends AbstractAction {

  /**
   * @required
   * @var int
   */
  protected $projectId;

  public function _run(Result $result) {
    $result[] = \CRM_Volunteer_BAO_Assignment::getCapacitySummary(
      (int) $this->projectId,
      $this->getCheckPermissions()
    );
  }

}
