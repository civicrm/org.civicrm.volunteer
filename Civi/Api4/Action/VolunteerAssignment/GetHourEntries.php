<?php

namespace Civi\Api4\Action\VolunteerAssignment;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Editable hour-log rows and their shift/status metadata.
 */
class GetHourEntries extends AbstractAction {

  /**
   * @required
   * @var int
   */
  protected $projectId;

  /**
   * NULL means all shifts.
   *
   * @var int|null
   */
  protected $volunteerNeedId;

  public function _run(Result $result) {
    $needId = $this->volunteerNeedId === NULL ? NULL : (int) $this->volunteerNeedId;
    $result[] = \CRM_Volunteer_BAO_Assignment::getHourEntriesData(
      (int) $this->projectId,
      $needId,
      $this->getCheckPermissions()
    );
  }

}
