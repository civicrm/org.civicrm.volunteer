<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * Static guard against APIv3 creeping back into the extension.
 *
 * CiviVolunteer's production code calls API4 (or a shared domain method where
 * API4 has no equivalent). The public APIv3 actions under api/v3 survive only
 * as deprecated adapters over those API4 actions, and the only file in the
 * suite allowed to invoke the APIv3 entry point is the adapter compatibility test
 * named in ADAPTER_COMPATIBILITY_TEST.
 *
 * These checks read the source tree, so they need no database. They are the
 * regression net for the APIv3-to-API4 conversion.
 *
 * @group headless
 */
class CRM_Volunteer_Api3RegressionTest extends VolunteerTestAbstract {

  /**
   * The one test file permitted to invoke APIv3.
   */
  const ADAPTER_COMPATIBILITY_TEST = 'tests/phpunit/api/v3/DeprecatedApi3AdapterTest.php';

  /**
   * Directories excluded from every scan.
   *
   * mixin/ is vendored civix scaffolding, and node_modules/ or vendor/ may
   * exist in a working copy.
   */
  const EXCLUDED_DIRECTORIES = array('mixin', 'node_modules', 'vendor', 'docs');

  /**
   * Extension root.
   *
   * @return string
   */
  private function extensionRoot(): string {
    return dirname(__DIR__, 4);
  }

  /**
   * The APIv3 call pattern, assembled so this file does not match itself.
   *
   * @return string
   */
  private function api3CallPattern(): string {
    return '/\b' . 'civicrm_' . 'api3\s*\(/';
  }

  /**
   * Every file under the extension root with one of the given extensions.
   *
   * @param array $suffixes
   * @return array
   *   Repo-relative paths.
   */
  private function sourceFiles(array $suffixes): array {
    $root = $this->extensionRoot();
    $iterator = new RecursiveIteratorIterator(
      new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $file) {
          if (!$file->isDir()) {
            return TRUE;
          }
          return !in_array($file->getFilename(), self::EXCLUDED_DIRECTORIES, TRUE);
        }
      )
    );

    $files = array();
    foreach ($iterator as $file) {
      if (!$file->isFile()) {
        continue;
      }
      foreach ($suffixes as $suffix) {
        if (substr($file->getFilename(), -strlen($suffix)) === $suffix) {
          $files[] = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
          break;
        }
      }
    }
    sort($files);
    $this->assertNotEmpty($files, 'The source scan matched no files.');
    return $files;
  }

  /**
   * Production PHP must not call the APIv3 entry point.
   *
   * api/v3 is exempt only for the adapter shim's own dispatch metadata: the
   * adapters themselves call \Civi\Api4, so no APIv3 invocation should remain
   * there either.
   */
  public function testProductionPhpDoesNotCallApi3(): void {
    $offenders = array();
    foreach ($this->sourceFiles(array('.php')) as $file) {
      if (strncmp($file, 'tests/', 6) === 0) {
        continue;
      }
      $contents = file_get_contents($this->extensionRoot() . '/' . $file);
      if (preg_match($this->api3CallPattern(), $contents)) {
        $offenders[] = $file;
      }
    }

    $this->assertSame(
      array(),
      $offenders,
      'These production files still call APIv3. Use \\Civi\\Api4 or a shared domain method instead.'
    );
  }

  /**
   * Only the named adapter compatibility test may invoke APIv3.
   */
  public function testOnlyTheAdapterTestInvokesApi3(): void {
    $offenders = array();
    foreach ($this->sourceFiles(array('.php')) as $file) {
      if (strncmp($file, 'tests/', 6) !== 0 || $file === self::ADAPTER_COMPATIBILITY_TEST) {
        continue;
      }
      $contents = file_get_contents($this->extensionRoot() . '/' . $file);
      if (preg_match($this->api3CallPattern(), $contents)) {
        $offenders[] = $file;
      }
    }

    $this->assertSame(
      array(),
      $offenders,
      'APIv3 belongs only in ' . self::ADAPTER_COMPATIBILITY_TEST . '.'
    );
  }

  /**
   * The adapter compatibility test exists and does exercise APIv3.
   *
   * Without this the guards above would pass trivially once somebody deleted
   * the compatibility coverage.
   */
  public function testTheAdapterCompatibilityTestStillExercisesApi3(): void {
    $path = $this->extensionRoot() . '/' . self::ADAPTER_COMPATIBILITY_TEST;
    $this->assertFileExists($path);
    $this->assertMatchesRegularExpression(
      $this->api3CallPattern(),
      file_get_contents($path),
      self::ADAPTER_COMPATIBILITY_TEST . ' no longer exercises the APIv3 adapters.'
    );
  }

  /**
   * Browser code must use CRM.api4 / crmApi4.
   *
   * crmApi is the Angular APIv3 service, CRM.api3 its jQuery counterpart, and
   * bare CRM.api( the pre-4.3 global. All three are gone.
   */
  public function testJavaScriptDoesNotCallApi3(): void {
    $patterns = array(
      'crmApi(' => '/(?<![A-Za-z0-9_$])crmApi\s*\(/',
      'CRM.api3(' => '/\bCRM\.api3\s*\(/',
      'CRM.api(' => '/\bCRM\.api\s*\(/',
    );

    $offenders = array();
    foreach ($this->sourceFiles(array('.js')) as $file) {
      $contents = file_get_contents($this->extensionRoot() . '/' . $file);
      foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $contents)) {
          $offenders[] = "$file uses $label";
        }
      }
    }

    $this->assertSame(
      array(),
      $offenders,
      'Browser code must call CRM.api4() or inject crmApi4.'
    );
  }

  /**
   * The Angular module declares the api4 dependency it now relies on.
   */
  public function testAngularModuleRequiresApi4(): void {
    $definition = include $this->extensionRoot() . '/ang/volunteer.ang.php';
    $this->assertContains('api4', $definition['requires']);
  }

  /**
   * Angular files that use crmApi4 must not also keep the old injection name.
   */
  public function testAngularInjectsCrmApi4(): void {
    $found = FALSE;
    foreach ($this->sourceFiles(array('.js')) as $file) {
      if (strncmp($file, 'ang/', 4) !== 0) {
        continue;
      }
      if (strpos(file_get_contents($this->extensionRoot() . '/' . $file), 'crmApi4') !== FALSE) {
        $found = TRUE;
      }
    }
    $this->assertTrue($found, 'No Angular file injects crmApi4.');
  }

}
