<?php

class CRM_Volunteer_Page_Angular extends \CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()->addScriptFile('civicrm', 'packages/jquery/plugins/jquery.notify.min.js', 10, 'html-header');

    $loader = new \Civi\Angular\AngularLoader();
    $loader->setModules(array('volunteer'));
    $loader->setPageName('civicrm/vol');
    $loader->load();
    \Civi::resources()->addSetting(array(
      'crmApp' => array(
        'defaultRoute' => '/volunteer/manage',
      ),
    ));

    parent::run();
  }

}
