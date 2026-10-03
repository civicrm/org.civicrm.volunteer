# CiviVolunteer

The CiviVolunteer extension provides tools for signing up, managing, and tracking volunteers.

## Version 2.5 compatibility and upgrade notes

Version 2.5 targets CiviCRM 6.16, PHP 8.1–8.4, and Smarty 5. Back up the
database and extension directory, then test the upgrade on a clone before
deploying it to production. Upgrade revision 2500 adds indexes and foreign
keys without recreating extension tables; it stops with actionable row IDs if
duplicate or orphaned data would make a constraint unsafe.

Apply extension and core updates and rebuild caches with the codebase's own
`cv`, run from the codebase root. Prefer `./vendor/bin/cv`; fall back to a `cv`
on your `PATH` only if the codebase has none installed:

```console
./vendor/bin/cv updb --no-interaction
./vendor/bin/cv flush
```

Angular Profiles is no longer a CiviVolunteer dependency. Existing profile
assignments remain in `civicrm_uf_join`, and project editors use the built-in
profile picker plus CiviCRM's standard create, field-edit, and preview pages.
Keep Angular Profiles installed during the first staging pass, disable it, and
repeat project editing and public signup tests before removing it. A future
Afform/API4 embedded profile designer remains an option and may be preferable
to the interim picker-and-links workflow.

### Behaviour changes that need action

Three changes can affect an existing site. Full detail is in
[`docs/release/2.5.md`](docs/release/2.5.md).

1. **`civicrm/vol` now requires the `register to volunteer` permission.** It was
   previously reachable by anyone. Sites that already run public signup grant
   this to the anonymous role, because `civicrm/volunteer/signup` has always
   required it -- but a site using only the opportunity browser must grant it or
   the page returns access-denied.
2. **Roster access now follows project authority**: the ability to update the
   project, or being one of its managers. A holder of `edit own volunteer
   projects` can no longer open the roster of a project they do not own.
3. **The APIv3 generic `setvalue` action is refused** for the three
   CiviVolunteer entities. It bypassed all project authorization. Use `create`
   with an `id`, which is guarded and is what core recommends.

## Resources

* [Documentation](https://docs.civicrm.org/volunteer/en/latest/) (in a dedicated guide)
* [Release downloads](https://civicrm.org/extensions/civivolunteer) (within CiviCRM.org's extensions directory)
* [Issue tracking (current)](https://github.com/civicrm/org.civicrm.volunteer/issues)
* [Issue tracking (archived)](https://issues.civicrm.org/jira/browse/VOL) (in a Jira project)
* [Q&A on StackExchange](http://civicrm.stackexchange.com/questions/tagged/civivolunteer) (with the `civivolunteer` tag)

## Development tests

The development suite and static-analysis configuration are included in the
source repository but intentionally excluded from release archives by
`.distignore`. This section is the portable summary;
[`tests/README.md`](tests/README.md) is the full guide — runner internals,
supported CMS layouts, safety rails, triage for failed runs, and the harness
and fixture behaviour worth knowing before adding a test.

The headless test suite uses the pinned **PHPUnit 9.6.36** PHAR and an isolated
CiviCRM test database, and needs no configuration files beyond a handful of
dedicated `CIVIVOLUNTEER_TEST_*` variables — see [`.env.example`](.env.example)
for the full annotated list. The extension directory is whatever the codebase's
`civicrm.settings.php` configures (its extensions directory) — inside or
outside the codebase tree; nothing assumes a particular location. The suite
runs on any CMS CiviCRM 6.16 supports — Drupal, WordPress, Joomla 5+,
Backdrop, Standalone — because it pins `CIVICRM_UF=UnitTests` and never boots
the CMS; the CMS only decides where `civicrm.settings.php` lives. The only
requirements are a CiviCRM 6.16 codebase, a reachable `cv`, and the
credentials. Run the committed runner from any directory:

```console
path/to/civivolunteer/tools/run-phpunit.sh
path/to/civivolunteer/tools/run-phpunit.sh --filter api_v4_VolunteerNeedSearchTest
path/to/civivolunteer/tools/run-js-tests.sh
cd path/to/civivolunteer && npm install && npm run test:angular && npm run test:e2e
```

Required `.env` variables:

```ini
CIVIVOLUNTEER_TEST_DB_USER="mysql_user"
CIVIVOLUNTEER_TEST_DB_PASSWORD="mysql_password"
```

Optional (defaults in parentheses): `CIVIVOLUNTEER_TEST_DB_HOST` (127.0.0.1),
`CIVIVOLUNTEER_TEST_DB_PORT` (3306), `CIVIVOLUNTEER_TEST_DB`
(`civivolunteer_phpunit`), `CIVIVOLUNTEER_TEST_SEED`
(`<extension>/tests/phpunit/seed/civicrm-seed.sql`),
`CIVIVOLUNTEER_TEST_SITE_SETTINGS` (discovered: upward search from the working
directory, then the extension, over the standard
Drupal/WordPress/Joomla/Backdrop/Standalone layouts — set it when your
settings file lives elsewhere),
`CIVICRM_CV` (the codebase's own `vendor/bin/cv`, found above the working
directory or the extension, falling back to `cv` on `PATH` only when none
exists), `CIVIVOLUNTEER_TEST_ENV_FILE` (nearest `.env`
above the working directory, then the extension), and `PHPUNIT9_BIN`.
The PHPUnit runner creates and seeds the disposable database when missing, fetches the
pinned PHAR when no `phpunit9` is on `PATH`, and exports the environment the
bootstrap requires. The account only needs `CREATE` on the disposable database;
its name must begin with `civivolunteer_test` or `civivolunteer_phpunit`. The
seed dump itself is deliberately gitignored (`.env.example` shows how to
regenerate one); any bootable CiviCRM 6.16 schema works.

The JavaScript is tested at three levels, described in
[`tests/README.md`](tests/README.md): `tools/run-js-tests.sh` runs the
dependency-free behavioural checks under `tests/js` with Node alone;
`npm run test:angular` runs the AngularJS module on the real browser stack
(jQuery, core's `Common.js`, Angular 1.8 with angular-mocks and core's own
modules) under Jest and jsdom, locating CiviCRM core from a composer site or
`CIVICRM_CORE`; and `npm run test:e2e` runs Playwright against a running site
with the extension installed, pointed at by `CIVIVOLUNTEER_E2E_*` (see
`.env.example`). `npm install` fetches the tooling into `node_modules/`, which
is never part of a release. The database-backed PHPUnit suite remains the
authoritative pre-merge check; its local seed is intentionally not committed
or uploaded to CI.

CiviCRM 6.16's legacy listener first performs a full bootstrap, so a new
disposable database must initially be seeded with a bootable CiviCRM 6.16
schema; the headless builder will then replace all of its contents. Do not point
it at a production or shared site database. To invoke PHPUnit manually instead
of through the runner, provide a dedicated disposable database DSN yourself:

```console
CIVICRM_DSN='mysql://test_user:test_password@127.0.0.1/civivolunteer_test?new_link=true' \
CIVICRM_TEST_DB=civivolunteer_test \
CIVICRM_CV=/path/to/codebase/vendor/bin/cv \
CIVIVOLUNTEER_TEST_SITE_SETTINGS=/path/to/codebase/web/sites/default/civicrm.settings.php \
phpunit9 -c /path/to/civivolunteer/phpunit.xml.dist
```

The bootstrap refuses to run unless `CIVICRM_DSN` and `CIVICRM_TEST_DB`
explicitly name the same database, the name begins with `civivolunteer_test`
or `civivolunteer_phpunit`, and CiviCRM resolves that exact name through the
test-only settings guard. It also rejects the site's configured CiviCRM
database and checks the resolved database again immediately before headless
initialization. CiviCRM's headless setup
drops, recreates, and modifies the approved database, so it must never contain
production or shared data.

### Static analysis

```console
vendor/bin/phpstan analyse -c web/sites/default/civicrm/extensions/custom/civivolunteer/tools/phpstan.neon
```

`tools/phpstan.neon` runs at level 5 over `Civi/`, `CRM/`, `api/` and `schema/`,
with the CodeGen DAOs excluded from analysis but kept on the symbol path.
`tools/phpstan-baseline.neon` records the pre-existing debt in legacy files, so a
clean run means "no *new* errors". Regenerate the baseline deliberately, never to
silence a newly introduced error.

`tools/phpstan-bootstrap.php` declares a stub `Smarty` class before analysis.
Without it, PHPStan's autoload source locator pulls in CiviCRM's classloader,
which include-loads `CRM/Core/SmartyCompatibility.php`; that file calls
`\Civi::paths()->getPath()` at the top level and fails with
"Call to a member function getPath() on null" because no CiviCRM container is
booted. The symptom is a misleading "cannot analyse <file>" internal error whose
target varies with analysis order.

### Why a separate PHPUnit binary

The repository's own `vendor/bin/phpunit` is PHPUnit 11 and **cannot** run this
suite. CiviCRM 6.16 ships `Civi\Test\CiviTestListener`, which resolves to
`CiviTestListenerPHPUnit7`, and that class implements
`\PHPUnit\Framework\TestListener` — an interface PHPUnit removed in 10.0.
`phpunit.xml.dist` therefore deliberately targets the PHPUnit 9.3 schema and
uses `<listeners>`, `backupStaticAttributes`, and
`<coverage processUncoveredFiles>`, all of which PHPUnit 10 dropped.

PHPUnit 9 also cannot be added to the root `composer.json`: `drupal/core-dev`
requires `phpunit/phpunit ^11.5.50`, and Composer cannot install two versions of
one package. `tools/run-phpunit.sh` handles this automatically — it uses
`PHPUNIT9_BIN` or `phpunit9` on `PATH` when present and otherwise downloads the
pinned PHAR into `tools/.cache/` after verifying its sha256. To install the
binary by hand instead:

```console
wget -O ~/bin/phpunit9 https://phar.phpunit.de/phpunit-9.6.36.phar
echo 'd9552a130747f02f9d7fc2427b143189c638e273c502c8faa88ab6b04c5f2662  '"$HOME"'/bin/phpunit9' | sha256sum --check
chmod +x ~/bin/phpunit9
phpunit9 --version   # must report 9.6.36
```

CI needs none of that: calling `tools/run-phpunit.sh` self-suffices. The test
bootstrap does not rely on the root Composer autoloader — it boots CiviCRM's
classloader itself via `cv php:boot` — so the PHPUnit 9 binary may live
anywhere on `PATH`.

This constraint disappears if CiviCRM core ships a PHPUnit 10+ runner extension
to replace the listener; until then the two toolchains coexist by design.
