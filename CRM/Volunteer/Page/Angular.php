<?php

class CRM_Volunteer_Page_Angular extends \CRM_Core_Page {

  public function run() {
    CRM_Core_Region::instance('page-footer')->add(array(
      'template' => 'CRM/common/notifications.tpl',
    ));
    CRM_Volunteer_Angular::load('/volunteer/manage');
    parent::run();
  }

}
