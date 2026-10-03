<?php

namespace Civi\Api4\Action\VolunteerUtil;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class GetCountries extends AbstractAction {

  public function _run(Result $result) {
    $this->checkAccessPermissions();
    // One row per country, as API4 expects. Callers that want APIv3's
    // ID-keyed map ask for it with the `index` parameter.
    foreach (\CRM_Volunteer_BAO_VolunteerUtil::getCountries() as $country) {
      $result[] = $country;
    }
  }

  private function checkAccessPermissions() {
    if (!$this->getCheckPermissions()) {
      return;
    }
    if (!\CRM_Volunteer_Permission::checkProjectPerms(\CRM_Core_Action::VIEW)
      && !\CRM_Volunteer_Permission::check('create volunteer projects')
      && !\CRM_Volunteer_Permission::check('edit own volunteer projects')
      && !\CRM_Volunteer_Permission::check('edit all volunteer projects')) {
      throw new \CRM_Core_Exception(ts('You do not have permission to access volunteer interfaces.', array('domain' => 'org.civicrm.volunteer')), 403);
    }
  }

}
