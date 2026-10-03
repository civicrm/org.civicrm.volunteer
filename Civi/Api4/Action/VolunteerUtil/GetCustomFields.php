<?php

namespace Civi\Api4\Action\VolunteerUtil;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class GetCustomFields extends AbstractAction {

  public function _run(Result $result) {
    foreach (\CRM_Volunteer_BAO_VolunteerUtil::getCustomFields() as $customField) {
      $result[] = $customField;
    }
  }

}
