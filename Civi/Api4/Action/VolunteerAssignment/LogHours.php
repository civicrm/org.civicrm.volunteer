<?php

namespace Civi\Api4\Action\VolunteerAssignment;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Transactional attendance and hour logging for one volunteer project.
 */
class LogHours extends AbstractAction {

  /**
   * @required
   * @var int
   */
  protected $projectId;

  /**
   * NULL means all shifts; new rows then use the flexible need.
   *
   * @var int|null
   */
  protected $volunteerNeedId;

  /**
   * @required
   * @var array
   */
  protected $entries = [];

  public function _run(Result $result) {
    $needId = $this->volunteerNeedId === NULL ? NULL : (int) $this->volunteerNeedId;
    $result[] = \CRM_Volunteer_BAO_Assignment::logHours(
      (int) $this->projectId,
      $needId,
      $this->entries,
      $this->getCheckPermissions()
    );
  }

}
