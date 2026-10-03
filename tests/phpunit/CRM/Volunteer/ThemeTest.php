<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * Covers volunteer_civicrm_activeTheme() and its setting.
 *
 * @group headless
 */
class CRM_Volunteer_ThemeTest extends VolunteerTestAbstract {

  /**
   * @var array<string, mixed>
   */
  private $savedSettings = array();

  public function setUp(): void {
    parent::setUp();
    foreach (array('theme_backend', 'volunteer_use_backend_theme') as $name) {
      $this->savedSettings[$name] = Civi::settings()->get($name);
    }
    Civi::settings()->set('theme_backend', 'greenwich');
  }

  public function tearDown(): void {
    foreach ($this->savedSettings as $name => $value) {
      Civi::settings()->set($name, $value);
    }
    parent::tearDown();
  }

  /**
   * @param string $page
   * @param string $initial
   * @return string
   */
  private function resolvedTheme(string $page, string $initial = 'classic'): string {
    $theme = $initial;
    volunteer_civicrm_activeTheme($theme, array('page' => $page));
    return $theme;
  }

  public function testPublicVolunteerRoutesUseTheBackendThemeByDefault(): void {
    Civi::settings()->set('volunteer_use_backend_theme', 1);
    $this->assertSame('greenwich', $this->resolvedTheme('civicrm/vol'));
    $this->assertSame('greenwich', $this->resolvedTheme('civicrm/vol/'));
    $this->assertSame('greenwich', $this->resolvedTheme('civicrm/volunteer/signup'));
    $this->assertSame('greenwich', $this->resolvedTheme('civicrm/volunteer/manage'));
  }

  public function testTheOverrideCanBeSwitchedOff(): void {
    Civi::settings()->set('volunteer_use_backend_theme', 0);
    $this->assertSame('classic', $this->resolvedTheme('civicrm/vol'),
      'With the switch off, CiviCRM keeps whatever theme it chose for the route.');
    $this->assertSame('classic', $this->resolvedTheme('civicrm/volunteer/signup'));
  }

  public function testRoutesOutsideCiviVolunteerAreUntouched(): void {
    Civi::settings()->set('volunteer_use_backend_theme', 1);
    $this->assertSame('classic', $this->resolvedTheme('civicrm/contact/view'));
    $this->assertSame('classic', $this->resolvedTheme('civicrm/volunteering'),
      'Only the vol and volunteer path segments are CiviVolunteer routes.');
    $this->assertSame('classic', $this->resolvedTheme(''));
  }

  public function testTheDefaultBackendThemeResolvesToCoreDefault(): void {
    Civi::settings()->set('volunteer_use_backend_theme', 1);
    Civi::settings()->set('theme_backend', 'default');
    $this->assertSame(\Civi\Core\Themes::DEFAULT_THEME, $this->resolvedTheme('civicrm/vol'));
  }

}
