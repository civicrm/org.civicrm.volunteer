<?php
/**
 * PHPStan bootstrap for static analysis of CiviVolunteer.
 *
 * PHPStan's autoload-function source locator calls CiviCRM's classloader when
 * it cannot resolve a symbol. Resolving CRM_Core_Smarty then include-loads
 * CRM/Core/SmartyCompatibility.php, whose top-level code calls
 * \Civi::paths()->getPath(...). Without a booted CiviCRM container Civi::paths()
 * returns NULL, so PHPStan aborts with "Call to a member function getPath() on
 * null" and reports whichever file it was analysing as unanalyzable -- which is
 * misleading, because the file itself is fine.
 *
 * Declaring a stub Smarty first turns that shim's `if (!class_exists('Smarty'))`
 * branch into a no-op so analysis proceeds. Nothing here is used at runtime.
 */

$autoload = NULL;
for ($dir = __DIR__; $dir !== dirname($dir); $dir = dirname($dir)) {
  if (is_file($dir . '/vendor/autoload.php')) {
    $autoload = $dir . '/vendor/autoload.php';
    break;
  }
}
if ($autoload === NULL) {
  fwrite(STDERR, "Could not locate vendor/autoload.php above " . __DIR__ . "\n");
  exit(1);
}
require $autoload;

if (!class_exists('Smarty', FALSE)) {
  eval('class Smarty { public $left_delimiter; public $right_delimiter; }');
}
