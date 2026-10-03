<?php

namespace Civi\Api4\Action\VolunteerUtil;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class GetPermissions extends AbstractAction {

  public function _run(Result $result) {
    foreach (\CRM_Volunteer_BAO_VolunteerUtil::getPermissions() as $permission) {
      $result[] = $permission;
    }
  }

}
