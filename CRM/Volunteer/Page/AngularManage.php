<?php

/**
 * Permission-protected host page for the administrative AngularJS routes.
 */
class CRM_Volunteer_Page_AngularManage extends CRM_Volunteer_Page_Angular {

  /**
   * {@inheritdoc}
   */
  protected function getDefaultRoute() {
    return '/volunteer/manage';
  }

  /**
   * {@inheritdoc}
   */
  protected function getAngularBasePage() {
    return 'civicrm/volunteer/manage';
  }

}
