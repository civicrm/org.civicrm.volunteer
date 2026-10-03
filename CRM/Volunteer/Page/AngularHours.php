<?php

/**
 * Backward-compatible Angular host for the historic hour-log URL.
 */
class CRM_Volunteer_Page_AngularHours extends CRM_Volunteer_Page_Angular {

  public function run() {
    $projectId = CRM_Utils_Request::retrieve('vid', 'Positive', NULL, TRUE);
    CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, (int) $projectId);
    parent::run();
  }

  protected function getDefaultRoute() {
    $projectId = CRM_Utils_Request::retrieve('vid', 'Positive', NULL, TRUE);
    return '/volunteer/standalone/' . (int) $projectId . '/hours';
  }

  protected function getAngularBasePage() {
    return 'civicrm/volunteer/loghours';
  }

}
