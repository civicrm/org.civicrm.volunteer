<?php

class CRM_Volunteer_Angular {

  private static $loaded = FALSE;

  /**
   * @return boolean
   */
  public static function isLoaded() {
    return self::$loaded;
  }

  /**
   * Loads dependencies for CiviVolunteer Angular app.
   *
   * @param string $defaultRoute
   *   If the base page is loaded with no route, show this one.
   * @param string $basePage
   *   Angular host page used to resolve modules for this request.
   */
  public static function load($defaultRoute, $basePage = 'civicrm/vol') {
    if (self::isLoaded()) {
      return;
    }

    CRM_Core_Resources::singleton()->addScriptFile('civicrm.packages', 'jquery/plugins/jquery.notify.min.js', 10, 'html-header');

    $loader = Civi::service('angularjs.loader');
    $modules = ['volunteer'];
    // The public opportunity browser uses the same base Angular module as
    // project management. Load the reusable Search Kit report only for a
    // management workflow and only when the current user may open it; this
    // keeps its back-office dependencies off the anonymous opportunities page.
    if (str_starts_with($defaultRoute, '/volunteer/manage')
      && CRM_Core_Permission::check('edit all volunteer projects')
      && CRM_Core_Permission::check('view all contacts')) {
      $modules[] = 'afsearchVolunteerHoursReport';
    }
    $loader->addModules($modules);
    $loader->setPageName($basePage);
    \Civi::resources()->addSetting([
      'crmApp' => [
        'defaultRoute' => $defaultRoute,
      ],
    ]);

    self::$loaded = TRUE;
  }

}
