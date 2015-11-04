<?php

require_once 'Civi/Angular/Page/Main.php';
require_once 'CRM/Volunteer/Angular/Manager.php';

class CRM_Volunteer_Page_Angular extends Civi\Angular\Page\Main {

  public function __construct($title = NULL, $mode = NULL, $res = NULL) {
    parent::__construct($title, $mode);
    //Use the VolunteerManager instead of the core Angular Manger
    $this->angular = new Civi\Angular\VolunteerManager(\CRM_Core_Resources::singleton());
  }
  public function run() {
    parent::run();
  }
  /**
   * Register resources required by Angular.
   */
  public function registerResources() {
    $modules = $this->angular->getModules();
    $page = $this; // PHP 5.3 does not propagate $this to inner functions.

    $this->res->addSettingsFactory(function () use (&$modules, $page) {
      // TODO optimization; client-side caching
      return array_merge($page->angular->getResources(array_keys($modules), 'settings', 'settings'), array(
        'resourceUrls' => \CRM_Extension_System::singleton()->getMapper()->getActiveModuleUrls(),
        'angular' => array(
          'modules' => array_merge(array('ngRoute'), array_keys($modules)),
          'cacheCode' => $page->res->getCacheCode(),
        ),
      ));
    });

    $this->res->addScriptFile('civicrm', 'bower_components/angular/angular.min.js', 100, 'html-header', FALSE);

    // FIXME: crmUi depends on loading ckeditor, but ckeditor doesn't work with this aggregation.
    $this->res->addScriptFile('civicrm', 'packages/ckeditor/ckeditor.js', 100, 'page-header', FALSE);

    $headOffset = 0;
    $config = \CRM_Core_Config::singleton();
    if ($config->debug) {
      foreach ($modules as $moduleName => $module) {
        foreach ($this->angular->getResources($moduleName, 'css', 'cacheUrl') as $url) {
          $this->res->addStyleUrl($url, self::DEFAULT_MODULE_WEIGHT + (++$headOffset), 'html-header');
        }
        foreach ($this->angular->getResources($moduleName, 'js', 'cacheUrl') as $url) {
          $this->res->addScriptUrl($url, self::DEFAULT_MODULE_WEIGHT + (++$headOffset), 'html-header');
          // addScriptUrl() bypasses the normal string-localization of addScriptFile(),
          // but that's OK because all Angular strings (JS+HTML) will load via crmResource.
        }
      }
    }
    else {
      // Note: addScriptUrl() bypasses the normal string-localization of addScriptFile(),
      // but that's OK because all Angular strings (JS+HTML) will load via crmResource.
      $aggScriptUrl = \CRM_Utils_System::url('civicrm/ajax/volunteer-angular-modules', 'format=js&r=' . $page->res->getCacheCode(), FALSE, NULL, FALSE);
      $this->res->addScriptUrl($aggScriptUrl, 120, 'html-header');

      // FIXME: The following CSS aggregator doesn't currently handle path-adjustments - which can break icons.
      //$aggStyleUrl = \CRM_Utils_System::url('civicrm/ajax/angular-modules', 'format=css&r=' . $page->res->getCacheCode(), FALSE, NULL, FALSE);
      //$this->res->addStyleUrl($aggStyleUrl, 120, 'html-header');

      foreach ($this->angular->getResources(array_keys($modules), 'css', 'cacheUrl') as $url) {
        $this->res->addStyleUrl($url, self::DEFAULT_MODULE_WEIGHT + (++$headOffset), 'html-header');
      }
    }
  }

}
