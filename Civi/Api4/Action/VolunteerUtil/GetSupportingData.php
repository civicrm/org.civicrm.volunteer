<?php

namespace Civi\Api4\Action\VolunteerUtil;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class GetSupportingData extends AbstractAction {

  /**
   * Name of the interface needing supporting data.
   *
   * @required
   * @var string
   */
  protected $controller;

  public function _run(Result $result) {
    // The permission to enforce depends on the controller, so the domain
    // method -- not the static entity metadata -- performs the check.
    $result[] = \CRM_Volunteer_BAO_VolunteerUtil::getSupportingData(
      $this->controller,
      $this->getCheckPermissions()
    );
  }

}
