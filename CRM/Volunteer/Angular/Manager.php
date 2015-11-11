<?php


namespace Civi\Angular;

class VolunteerManager extends Manager {

  public function getModules() {
    if ($this->modules === NULL) {
      $config = \CRM_Core_Config::singleton();

      $angularModules = array();
      //$angularModules['angularFileUpload'] = array(
      //  'ext' => 'civicrm',
      //  'js' => array('bower_components/angular-file-upload/angular-file-upload.min.js'),
      //);
      $angularModules['crmApp'] = array(
        'ext' => 'civicrm',
        'js' => array('ang/crmApp.js'),
      );
      //$angularModules['crmAttachment'] = array(
       // 'ext' => 'civicrm',
      //  'js' => array('ang/crmAttachment.js'),
      //  'css' => array('ang/crmAttachment.css'),
      //  'partials' => array('ang/crmAttachment'),
      //  'settings' => array(
      //    'token' => \CRM_Core_Page_AJAX_Attachment::createToken(),
      //  ),
      //);
      $angularModules['crmAutosave'] = array(
        'ext' => 'civicrm',
        'js' => array('ang/crmAutosave.js'),
      );
      $angularModules['crmCxn'] = array(
        'ext' => 'civicrm',
        'js' => array('ang/crmCxn.js', 'ang/crmCxn/*.js'),
        'css' => array('ang/crmCxn.css'),
        'partials' => array('ang/crmCxn'),
      );
      $angularModules['crmResource'] = array(
        'ext' => 'civicrm',
        // 'js' => array('js/angular-crmResource/byModule.js'), // One HTTP request per module.
        'js' => array('js/angular-crmResource/all.js'), // One HTTP request for all modules.
      );
      $angularModules['crmUi'] = array(
        'ext' => 'civicrm',
        'js' => array('ang/crmUi.js'),
        'partials' => array('ang/crmUi'),
        'settings' => array(
          'browseUrl' => $config->userFrameworkResourceURL . 'packages/kcfinder/browse.php',
          'uploadUrl' => $config->userFrameworkResourceURL . 'packages/kcfinder/upload.php',
        ),
      );
      $angularModules['crmUtil'] = array(
        'ext' => 'civicrm',
        'js' => array('ang/crmUtil.js'),
      );
      // https://github.com/jwstadler/angular-jquery-dialog-service
      $angularModules['dialogService'] = array(
        'ext' => 'civicrm',
        'js' => array('bower_components/angular-jquery-dialog-service/dialog-service.js'),
      );
      $angularModules['ngRoute'] = array(
        'ext' => 'civicrm',
        'js' => array('bower_components/angular-route/angular-route.min.js'),
      );
      $angularModules['ngSanitize'] = array(
        'ext' => 'civicrm',
        'js' => array('bower_components/angular-sanitize/angular-sanitize.min.js'),
      );
      $angularModules['ui.utils'] = array(
        'ext' => 'civicrm',
        'js' => array('bower_components/angular-ui-utils/ui-utils.min.js'),
      );
      $angularModules['ui.sortable'] = array(
        'ext' => 'civicrm',
        'js' => array('bower_components/angular-ui-sortable/sortable.min.js'),
      );
      $angularModules['unsavedChanges'] = array(
        'ext' => 'civicrm',
        'js' => array('bower_components/angular-unsavedChanges/dist/unsavedChanges.min.js'),
      );

      \CRM_Utils_Hook::angularModules($angularModules);

      //Lets filter out unneeded modules
      foreach ($angularModules as $name => $module) {
        //If the module doesn't request to be part of our page, and isn't a core module
        // that we have included, remove it
        if ((!array_key_exists("volunteer", $module) || !$module['volunteer']) && $module['ext'] != 'civicrm' && $module['ext'] != 'org.civicrm.angularprofiles') {
          unset($angularModules[$name]);
        }
      }

      $this->modules = $this->resolvePatterns($angularModules);
    }

    return $this->modules;
  }

}