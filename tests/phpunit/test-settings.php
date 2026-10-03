<?php

/**
 * Test-only CiviCRM settings guard.
 *
 * cv can populate $GLOBALS['_CV']['TEST_DB_DSN'] from per-site configuration,
 * which may override a shell's CIVICRM_DSN. This wrapper defines the approved
 * disposable DSN first and rejects the site's active CiviCRM database name.
 */

if (PHP_SAPI !== 'cli' || getenv('CIVICRM_UF') !== 'UnitTests') {
  throw new RuntimeException('The CiviVolunteer test settings may only be used by CLI headless tests.');
}

$testDsn = getenv('CIVICRM_DSN');
$expectedTestDatabase = getenv('CIVICRM_TEST_DB');
$parts = is_string($testDsn) ? parse_url($testDsn) : FALSE;
$requestedTestDatabase = is_array($parts) && isset($parts['path'])
  ? ltrim($parts['path'], '/')
  : '';
if ($expectedTestDatabase === FALSE
  || $requestedTestDatabase !== $expectedTestDatabase
  || !preg_match('/^civivolunteer_(?:test|phpunit)(?:_|$)/i', $expectedTestDatabase)
) {
  throw new RuntimeException('The CiviVolunteer test DSN did not pass its database identity check.');
}

if (defined('CIVICRM_DSN') && CIVICRM_DSN !== $testDsn) {
  throw new RuntimeException('CIVICRM_DSN was defined before the CiviVolunteer test guard could validate it.');
}
if (!defined('CIVICRM_DSN')) {
  define('CIVICRM_DSN', $testDsn);
}

// Pin the user framework before the codebase's settings load. Generated
// civicrm.settings.php files for Drupal, WordPress, Joomla, Backdrop, and
// Standalone each hard-code their own CIVICRM_UF inside an if (!defined())
// guard; without this pin a non-Drupal codebase leaks its real UF into the
// headless boot (e.g. "Could not find the bootstrap file for WordPress") and
// the suite never runs. The headless suite requires the UnitTests stub and
// never boots a real CMS.
if (!defined('CIVICRM_UF')) {
  define('CIVICRM_UF', 'UnitTests');
}

if (!function_exists('civivolunteer_find_project_root')) {
  /**
   * See tests/phpunit/bootstrap.php. Duplicated because cv loads this settings
   * wrapper directly, without the PHPUnit bootstrap.
   */
  function civivolunteer_find_project_root($from) {
    for ($dir = $from; $dir !== dirname($dir); $dir = dirname($dir)) {
      if (is_file($dir . '/vendor/autoload.php')) {
        return $dir;
      }
    }
    throw new RuntimeException('Could not locate the repository root above ' . $from);
  }
}
$siteSettings = getenv('CIVIVOLUNTEER_TEST_SITE_SETTINGS');
if ($siteSettings === FALSE || trim($siteSettings) === '') {
  // Fallback for manual invocation when the extension happens to live inside
  // a composer codebase with the standard Drupal layout. The extension
  // directory itself is arbitrary (whatever civicrm.settings.php configures
  // as extensionsDir), so no assumption is made beyond this convenience.
  try {
    $projectRoot = civivolunteer_find_project_root(__DIR__);
    $siteSettings = $projectRoot . '/web/sites/default/civicrm.settings.php';
  }
  catch (RuntimeException $e) {
    throw new RuntimeException('CIVIVOLUNTEER_TEST_SITE_SETTINGS is not set and no composer codebase was found above the extension. Set CIVIVOLUNTEER_TEST_SITE_SETTINGS to the path of the codebase civicrm.settings.php.');
  }
}
if (!is_file($siteSettings)) {
  throw new RuntimeException("The codebase CiviCRM settings file was not found at '$siteSettings'. Set CIVIVOLUNTEER_TEST_SITE_SETTINGS to its path.");
}
require $siteSettings;

if (!empty($civicrm_database_name) && $civicrm_database_name === $expectedTestDatabase) {
  throw new RuntimeException('The disposable test database must not be the site\'s configured CiviCRM database.');
}
if (CIVICRM_DSN !== $testDsn) {
  throw new RuntimeException('The site settings replaced the approved CiviVolunteer test DSN.');
}
