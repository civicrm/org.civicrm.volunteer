<?php

require_once 'Civi/Angular/Page/Main.php';
require_once 'CRM/Volunteer/Angular/Manager.php';

class CRM_Volunteer_Page_Angular extends Civi\Angular\Page\Main {

  public function __construct($title = NULL, $mode = NULL, $res = NULL) {
    parent::__construct($title, $mode);
    //Use the VolunteerManager instead of the core Angular Manger
    $this->angular = new Civi\Angular\VolunteerManager(\CRM_Core_Resources::singleton());

    //If we are given a resource context, use it.
    if($res) {
      $this->res = $res;
    }
  }

  /**
   * Register resources required by Angular.
   */
  public function registerResources($region = 'html-header', $includeExtras = true) {
    $modules = $this->angular->getModules();
    $page = $this; // PHP 5.3 does not propagate $this to inner functions.

    $page->res->addSettingsFactory(function () use (&$modules, $page) {
      // TODO optimization; client-side caching
      return array_merge($page->angular->getResources(array_keys($modules), 'settings', 'settings'), array(
        'resourceUrls' => \CRM_Extension_System::singleton()->getMapper()->getActiveModuleUrls(),
        'angular' => array(
          'modules' => array_merge(array('ngRoute'), array_keys($modules)),
          'cacheCode' => $page->res->getCacheCode(),
        ),
      ));
    });

    $page->res->addScriptFile('civicrm', 'bower_components/angular/angular.min.js', -100, $region, FALSE);


    if($includeExtras) {

      //Civi vs 4.7 and above has reworked how wysiwyg works and we don't
      //have to side load ckeditor anymore
      $version = substr(CRM_Utils_System::version(), 0, 3);
      if($version <= 4.6) {
        //crmUi depends on loading ckeditor, but ckeditor doesn't work properly with aggregation.

        //Add a basepath so that CKEditor works when Drupal (or other extension/cms) does the aggregation
        $basePath = $page->res->getUrl("civicrm") . "packages/ckeditor/";
        $page->res->addScript("window.CKEDITOR_BASEPATH = '{$basePath}';", 119, $region, FALSE);
        $page->res->addScriptFile('civicrm', 'packages/ckeditor/ckeditor.js', 120, $region, FALSE);
      }

      //Add jquery Notify
      $page->res->addScriptFile('civicrm', 'packages/jquery/plugins/jquery.notify.min.js', 10, $region, FALSE);
      $page->assign("includeNotificationTemplate", true);
    }


    $headOffset = 1;
    $config = \CRM_Core_Config::singleton();
    if ($config->debug) {
      foreach ($modules as $moduleName => $module) {
        foreach ($page->angular->getResources($moduleName, 'css', 'cacheUrl') as $url) {
          $page->res->addStyleUrl($url, self::DEFAULT_MODULE_WEIGHT + (++$headOffset), $region);
        }
        foreach ($page->angular->getResources($moduleName, 'js', 'cacheUrl') as $url) {
          $page->res->addScriptUrl($url, self::DEFAULT_MODULE_WEIGHT + (++$headOffset), $region);
          // addScriptUrl() bypasses the normal string-localization of addScriptFile(),
          // but that's OK because all Angular strings (JS+HTML) will load via crmResource.
        }
      }
    }
    else {
      // Note: addScriptUrl() bypasses the normal string-localization of addScriptFile(),
      // but that's OK because all Angular strings (JS+HTML) will load via crmResource.
      $aggScriptUrl = \CRM_Utils_System::url('civicrm/ajax/volunteer-angular-modules', 'format=js&r=' . $page->res->getCacheCode(), FALSE, NULL, FALSE);
      $page->res->addScriptUrl($aggScriptUrl, 1, $region);

      // FIXME: The following CSS aggregator doesn't currently handle path-adjustments - which can break icons.
      //$aggStyleUrl = \CRM_Utils_System::url('civicrm/ajax/angular-modules', 'format=css&r=' . $page->res->getCacheCode(), FALSE, NULL, FALSE);
      //$page->res->addStyleUrl($aggStyleUrl, 1, $region);

      foreach ($page->angular->getResources(array_keys($modules), 'css', 'cacheUrl') as $url) {
        $page->res->addStyleUrl($url, self::DEFAULT_MODULE_WEIGHT + (++$headOffset), $region);
      }
    }
  }

}
