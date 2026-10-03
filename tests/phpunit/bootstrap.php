<?php

ini_set('memory_limit', '2G');

/**
 * Locate the repository root by searching upward for composer's autoloader.
 *
 * A hard-coded dirname(__DIR__, 9) bakes in one codebase's exact directory
 * nesting, so moving the extension -- or installing it standalone -- silently
 * resolves to the wrong place.
 *
 * @param string $from
 * @return string
 */
function civivolunteer_find_project_root($from) {
  for ($dir = $from; $dir !== dirname($dir); $dir = dirname($dir)) {
    if (is_file($dir . '/vendor/autoload.php')) {
      return $dir;
    }
  }
  throw new RuntimeException('Could not locate the repository root above ' . $from);
}

// cv location comes from the environment (the runner always exports it). The
// upward search is only a convenience for manual invocation when the extension
// happens to live inside a composer codebase — the extension directory itself
// is arbitrary, because it is whatever the codebase's civicrm.settings.php
// configures as its extensions directory.
$cvBinary = getenv('CIVICRM_CV');
if ($cvBinary === FALSE || trim($cvBinary) === '') {
  $cvBinary = civivolunteer_find_project_root(__DIR__) . '/vendor/bin/cv';
}
$testDsn = getenv('CIVICRM_DSN');
$expectedTestDatabase = getenv('CIVICRM_TEST_DB');
if ($testDsn === FALSE || trim($testDsn) === '') {
  throw new RuntimeException('CIVICRM_DSN must explicitly name a disposable test database. The CiviVolunteer suite will not fall back to the site database.');
}
if ($expectedTestDatabase === FALSE || trim($expectedTestDatabase) === '') {
  throw new RuntimeException('CIVICRM_TEST_DB must explicitly repeat the disposable test database name.');
}
$requestedTestDatabase = civivolunteer_dsn_database($testDsn);
if ($requestedTestDatabase !== $expectedTestDatabase) {
  throw new RuntimeException("CIVICRM_DSN names '$requestedTestDatabase', but CIVICRM_TEST_DB names '$expectedTestDatabase'.");
}
if (!preg_match('/^civivolunteer_(?:test|phpunit)(?:_|$)/i', $expectedTestDatabase)) {
  throw new RuntimeException("The disposable database name '$expectedTestDatabase' must begin with 'civivolunteer_test' or 'civivolunteer_phpunit'.");
}

// CiviTestListener invokes `cv` by name. Prefer the resolved executable
// consistently, including for the listener's later full headless bootstrap.
$path = getenv('PATH');
putenv('PATH=' . dirname($cvBinary) . PATH_SEPARATOR . ($path === FALSE ? '' : $path));
putenv('CIVICRM_UF=UnitTests');
// cv normally injects TEST_DB_DSN from its per-site configuration. Force it
// through a test-only settings wrapper so a stale cv setting cannot silently
// substitute the live site's CiviCRM database.
putenv('CIVICRM_SETTINGS=' . __DIR__ . '/test-settings.php');
$resolvedPath = trim(civivolunteer_cv(
  $cvBinary,
  array('php:eval', '--level=settings', 'echo parse_url(CIVICRM_DSN, PHP_URL_PATH);'),
  'raw'
));
$preflightDatabase = ltrim($resolvedPath, '/');
if ($preflightDatabase !== $expectedTestDatabase) {
  throw new RuntimeException("CiviCRM preflight resolved '$preflightDatabase' instead of the approved disposable database '$expectedTestDatabase'.");
}
eval(civivolunteer_cv($cvBinary, array('php:boot', '--level=classloader'), 'phpcode'));

/**
 * Return the database name from a CiviCRM URL-style DSN.
 */
function civivolunteer_dsn_database($dsn) {
  $parts = parse_url($dsn);
  $database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
  if ($database === '' || !preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    throw new RuntimeException('CIVICRM_DSN must contain an explicit, simply named database.');
  }
  return $database;
}

/**
 * Call the "cv" command.
 *
 * @param string $cmd
 *   The rest of the command to send.
 * @param string $decode
 *   Ex: 'json' or 'phpcode'.
 * @return string
 *   Response output (if the command executed normally).
 * @throws \RuntimeException
 *   If the command terminates abnormally.
 */
function civivolunteer_cv($cvBinary, array $args, $decode = 'json') {
  if (!is_executable($cvBinary)) {
    throw new RuntimeException("CiviCRM cv executable was not found at $cvBinary. Set CIVICRM_CV explicitly.");
  }
  $cmd = array_merge(array($cvBinary), $args);
  $descriptorSpec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => STDERR);
  $oldOutput = getenv('CV_OUTPUT');
  putenv('CV_OUTPUT=json');
  $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__);
  $oldOutput === FALSE ? putenv('CV_OUTPUT') : putenv("CV_OUTPUT=$oldOutput");
  fclose($pipes[0]);
  $result = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException("Command failed (" . implode(' ', $cmd) . "):\n$result");
  }
  switch ($decode) {
    case 'raw':
      return $result;

    case 'phpcode':
      // If the last output is /*PHPCODE*/, then we managed to complete execution.
      if (substr(trim($result), 0, 12) !== "/*BEGINPHP*/" || substr(trim($result), -10) !== "/*ENDPHP*/") {
        throw new \RuntimeException("Command failed (" . implode(' ', $cmd) . "):\n$result");
      }
      return $result;

    case 'json':
      return json_decode($result, 1);

    default:
      throw new RuntimeException("Bad decoder format ($decode)");
  }
}
