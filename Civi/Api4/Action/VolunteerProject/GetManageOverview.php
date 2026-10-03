<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * One permission-scoped bundle for the project list and dashboard.
 */
class GetManageOverview extends AbstractAction {

  /**
   * Filters accepted by the existing managed-project query.
   *
   * @var array
   */
  protected $filters = [];

  public function _run(Result $result) {
    $result[] = \CRM_Volunteer_BAO_Project::getManageOverview(
      $this->filters,
      $this->getCheckPermissions()
    );
  }

}
