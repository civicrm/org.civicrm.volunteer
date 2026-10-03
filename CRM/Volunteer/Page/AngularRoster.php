<?php

/**
 * Backward-compatible Angular host for the historic roster URL.
 */
class CRM_Volunteer_Page_AngularRoster extends CRM_Volunteer_Page_Angular {

  protected function getDefaultRoute() {
    $projectId = CRM_Utils_Request::retrieve('project_id', 'Positive', NULL, TRUE);
    return '/volunteer/standalone/' . (int) $projectId . '/roster';
  }

  protected function getAngularBasePage() {
    return 'civicrm/volunteer/roster';
  }

}
