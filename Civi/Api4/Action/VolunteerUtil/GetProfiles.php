<?php

namespace Civi\Api4\Action\VolunteerUtil;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class GetProfiles extends AbstractAction {

  /**
   * Profile IDs to include even when inactive or missing.
   *
   * @var array
   */
  protected $profileIds = [];

  public function _run(Result $result) {
    $result[] = \CRM_Volunteer_BAO_VolunteerUtil::getProfiles($this->profileIds);
  }

}
