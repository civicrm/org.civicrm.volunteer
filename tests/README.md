# Running the CiviVolunteer PHPUnit suite

The extension ships its own runner, guards, and seed location; the seed *dump*
itself is deliberately gitignored (it is a local, machine-generated file — see
"Installing the suite into another codebase" for the one-line regeneration),
and every machine-specific input is a dedicated `CIVIVOLUNTEER_TEST_*`
variable, annotated in the extension's [`.env.example`](../.env.example). This
document explains how the runner resolves credentials and databases, what to
install first, and the hard-won facts about the harness that are not visible
from the code. [`README.md`](../README.md) in the extension root is the
portable summary; this is the practical guide for humans and AI assistants.

Everything referenced here lives inside the extension directory. The extension
is self-contained: nothing outside it — no site repository, no external
document — is required to run or extend the suite.

## TL;DR

```console
path/to/civivolunteer/tools/run-phpunit.sh
path/to/civivolunteer/tools/run-phpunit.sh --filter api_v4_VolunteerNeedSearchTest
```

Works from any directory, in any codebase the extension is installed into.
First run on a machine: creates and seeds the test database (≈60 s extra) and
downloads the pinned PHPUnit 9 PHAR. The suite currently contains about 340
tests and 2,000 assertions with one deliberate environment-dependent skip;
the runner's final line is authoritative as coverage evolves. A full run is
about three minutes on the reference development environment.

The runner never needs a hand-built DSN or a scratchpad script. Do not
resurrect the old approach of writing a throwaway `run-phpunit.sh` to `/tmp`
with an inlined DSN — the committed runner replaced it.

## What the runner does

`tools/run-phpunit.sh` (excluded from release archives by `.distignore`):

1. Locates its own extension directory from the script's path — nothing else
   is assumed about directory layout. The extension may sit inside or outside
   the codebase tree, wherever the codebase's `civicrm.settings.php`
   configures its extensions directory.
2. Sources an `.env` if one can be found (nearest one above the working
   directory, then above the extension; `CIVIVOLUNTEER_TEST_ENV_FILE` pins it
   — exporting the variables directly works too), then reads the test's own
   variables. It does not reuse site variables like `DATABASE_USER`: tests
   read `CIVIVOLUNTEER_TEST_DB_USER` / `CIVIVOLUNTEER_TEST_DB_PASSWORD` /
   `CIVIVOLUNTEER_TEST_DB_HOST` / `CIVIVOLUNTEER_TEST_DB_PORT` only.
   `localhost` is rewritten to `127.0.0.1` so the DSN uses TCP, not a socket.
3. Targets the disposable database `${CIVIVOLUNTEER_TEST_DB:-civivolunteer_phpunit}`.
   If it is missing or has no `civicrm_domain` table, the runner creates it
   and loads `${CIVIVOLUNTEER_TEST_SEED:-<extension>/tests/phpunit/seed/civicrm-seed.sql}`.
4. Builds `CIVICRM_DSN` with the password `rawurlencode`d (it may contain
   characters that corrupt a DSN if pasted raw) and exports the internal
   `CIVICRM_TEST_DB`, `CIVICRM_CV`, `CIVIVOLUNTEER_TEST_SITE_SETTINGS`, and
   `CIVI_SMTP_OUTBOUND_OPTION=2`.
5. Resolves a PHPUnit 9 binary: `$PHPUNIT9_BIN`, then `phpunit9` on `PATH`,
   then a cached PHAR in `tools/.cache/`, downloading the pinned
   PHPUnit 9.6.36 PHAR from phar.phpunit.de and verifying its sha256 on the
   way. `tools/.cache/` is gitignored.
6. Runs the lightweight PHP test-discovery source guard, which catches test
   methods accidentally swallowed by a malformed comment even when `php -l`
   succeeds.
7. `exec`s PHPUnit with `phpunit.xml.dist`, passing through any arguments
   (`--filter`, `--testdox`, …).

Every guard in the runner exits non-zero with a `run-phpunit: …` message on
stderr, so a misconfiguration stops before PHPUnit starts rather than
surfacing later as a confusing database error.

### Configuration knobs

Environment beats `.env` for these; `.env` may define them as defaults:

| Variable | Default | Purpose |
| --- | --- | --- |
| `CIVIVOLUNTEER_TEST_DB_USER` | — (required) | MySQL account for the disposable test database. Needs `CREATE` on it. |
| `CIVIVOLUNTEER_TEST_DB_PASSWORD` | — (required) | Password for that account. |
| `CIVIVOLUNTEER_TEST_DB_HOST` | `127.0.0.1` | Database host (TCP). |
| `CIVIVOLUNTEER_TEST_DB_PORT` | `3306` | Database port. |
| `CIVIVOLUNTEER_TEST_DB` | `civivolunteer_phpunit` | Disposable database name. Must begin with `civivolunteer_test` or `civivolunteer_phpunit` (the bootstrap enforces this too). |
| `CIVIVOLUNTEER_TEST_SEED` | `<extension>/tests/phpunit/seed/civicrm-seed.sql` | mysqldump used when the test DB must be created. |
| `CIVIVOLUNTEER_TEST_SITE_SETTINGS` | discovered: upward search from the working directory, then the extension, over the standard Drupal/WordPress/Joomla/Backdrop/Standalone layouts | The codebase's real CiviCRM settings file, which the test guard loads and then overrides with the test DSN. Set it when discovery cannot find it. |
| `CIVICRM_CV` | `vendor/bin/cv` above the working directory, then above the extension, then `cv` on `PATH` | The cv binary the bootstrap shells out to. The codebase's own copy is preferred: it is installed against this CiviCRM, while a global `cv` may target a different core release. |
| `CIVIVOLUNTEER_TEST_ENV_FILE` | nearest `.env` above the working directory, then the extension | Env file the runner sources. Not required if the variables are exported. |
| `PHPUNIT9_BIN` | — | Explicit PHPUnit 9.6 binary/PHAR when you do not want the auto-download. |

The disposable database is safe to drop at any time; the next run reseeds it.
The runner refuses a name that collides with `CIVICRM_DATABASE` from `.env`.

## Supported CMS platforms

The suite is CMS-agnostic: it never boots Drupal, WordPress, Joomla, Backdrop,
or anything else. The bootstrap pins `CIVICRM_UF=UnitTests` in the
environment, and the test-settings wrapper additionally defines the
`CIVICRM_UF` constant *before* the codebase's own `civicrm.settings.php`
loads. That second pin matters: generated settings files for each CMS
hard-code their own UF inside an `if (!defined())` guard, and without the pin
a non-Drupal codebase leaks its real UF into the headless boot and dies with
e.g. `Could not find the bootstrap file for WordPress` before any test runs.

The only CMS-dependent inputs are where `civicrm.settings.php` lives, a
reachable `cv`, and (optionally) where the codebase keeps its `.env`. The
runner searches these layouts, upward from the working directory and then
from the extension:

| CMS | `civicrm.settings.php` locations searched |
| --- | --- |
| Drupal 10+ (composer) | `web/sites/default/`, `web/sites/*/` |
| Drupal 7 / Backdrop | `sites/default/`, `sites/*/` |
| WordPress | `wp-content/uploads/civicrm/` (modern), `wp-content/plugins/civicrm/` (legacy) |
| Joomla 5+ | `administrator/components/com_civicrm/` |
| Standalone | project root (`civicrm.settings.php`) |

Anything outside these layouts: set `CIVIVOLUNTEER_TEST_SITE_SETTINGS` to the
file's absolute path. The `.env` file itself is optional on every platform —
exporting the variables works, and WordPress/Joomla codebases without a root
`.env` convention can simply export them (or create one; the runner searches
upward for it).

Status: exercised end-to-end on Drupal. The other platforms are expected to
work because the CMS is never booted, but they have not been run end-to-end.
Note that current CiviCRM ships a dedicated Joomla 5 build (the `joomla5bc`
download); the suite's requirements are otherwise unchanged — a CiviCRM
6.16-era codebase with `cv`.

## Installing the suite into another codebase

Requirements: a CiviCRM **6.16** codebase with a reachable `cv` — preferably
the codebase's own `vendor/bin/cv` (`composer require civicrm/cv`), otherwise
one on `PATH` — plus PHP 8.1–8.4 CLI, the `mysql` client, and `curl` or
`wget` (or a pre-installed `phpunit9` / `PHPUNIT9_BIN`).

1. Copy or clone the extension into whatever directory the codebase's
   `civicrm.settings.php` configures for extensions — inside or outside the
   codebase tree; no location is assumed.
2. Add the test credentials to the codebase's `.env` (the extension's
   [`.env.example`](../.env.example) lists every variable with defaults):
   ```ini
   CIVIVOLUNTEER_TEST_DB_USER="mysql_user"
   CIVIVOLUNTEER_TEST_DB_PASSWORD="mysql_password"
   ```
   Optionally set `CIVIVOLUNTEER_TEST_SITE_SETTINGS` if the codebase's
   `civicrm.settings.php` is not discovered automatically (see
   "Supported CMS platforms" for the layouts searched).
3. Generate the seed dump (gitignored by design; the runner refuses to guess
   for you and prints these same instructions when it is missing):

   ```console
   mkdir -p path/to/civivolunteer/tests/phpunit/seed
   mysqldump -h 127.0.0.1 -P 3306 -u root -p <some_civicrm_db> \
     > path/to/civivolunteer/tests/phpunit/seed/civicrm-seed.sql
   ```

4. Run the TL;DR command. First run creates + seeds the disposable database
   and fetches the PHAR; later runs reuse both.

The seed only needs to be a *bootable* CiviCRM 6.16 schema — the headless
installer replaces all contents on every run, so any dump of a working 6.16
CiviCRM database is acceptable.

There is no requirement for a dedicated `test_user`; any account that can
`CREATE` the disposable database works.

Note that a `civicrm.settings.php` which reads `DATABASE_*` from the
environment at load time still needs those site variables, which is why the
runner sources the whole `.env` rather than only the test keys. The test DSN,
however, is built exclusively from the `CIVIVOLUNTEER_TEST_DB_*` variables.

## Safety rails (why the bootstrap refuses things)

`tests/phpunit/bootstrap.php` and `tests/phpunit/test-settings.php` only run
when `CIVICRM_DSN` and `CIVICRM_TEST_DB` name the *same* database, the name
begins with `civivolunteer_test` or `civivolunteer_phpunit`, and CiviCRM's
preflight resolves exactly that name through the test-only settings guard. The
codebase's configured CiviCRM database is rejected, and the resolved database
is re-checked immediately before headless initialization. CiviCRM's headless
setup drops, recreates, and modifies the approved database — it must never
contain production or shared data. The runner mirrors the name and
site-database checks so misconfiguration fails with a clear message before
PHPUnit starts.

## The harness builds triggers, and that matters

`setUpHeadless()` calls `CRM_Core_DAO::triggerRebuild()`. `Civi\Test`'s installer
does not, and their absence hid a real defect for a long time: core keeps an
`AFTER UPDATE` trigger on every Activity custom-value table which writes
`civicrm_activity.modified_date`, so
`UPDATE custom_table ... JOIN civicrm_activity` is MySQL error 1442 on every
production site but succeeded in a trigger-less test database. Two tests
"covering" `VolunteerNeed.delete` passed for exactly that reason. Do not remove
the rebuild.

## Never issue DDL inside a test

MySQL commits implicitly on DDL, which tears down the transaction
`TransactionalInterface` depends on and leaves every row the test created behind
in the database. In practice this means:

- `\Civi::rebuild(['metadata' => TRUE])` is fine; adding `'triggers' => TRUE` is
  not. `enableCampaignComponent()` / `disableCampaignComponent()` on
  `VolunteerTestAbstract` deliberately rebuild metadata only.
- Rows *do* accumulate in this database over time. `civicrm_campaign` had 322
  leaked rows from `CRM_Core_DAO::createTestObject()`, at which point its
  generated names started colliding and unrelated tests began failing with
  `DB Error: already exists`. `DELETE FROM civicrm_campaign;` clears it — the
  column is `ON DELETE SET NULL` everywhere it is referenced. Prefer
  `VolunteerTestAbstract::createCampaign()`, which uses API4 and a `uniqid()`
  name, wherever CiviCampaign is enabled.

## Toggling a component

`CRM_Core_BAO_ConfigSetting::enableComponent('CiviCampaign')` works here, and it
matters: with CiviCampaign off — which is the harness default — API4 omits
`campaign_id` from both `Activity` and `Event` entirely (the field is tagged
`'component' => 'CiviCampaign'`), and `\Civi\Api4\Campaign` is not loadable at
all. Any campaign assertion written without enabling it is either reading through
the DAO or quietly testing nothing. Pair every `enableCampaignComponent()` with
`disableCampaignComponent()` in a `finally` or `tearDown()`.

## When a run fails before any test executes

Output ends at `Initializing "Headless System" ...` followed by one line. That is
the *install* failing, not a test. Three of these were real bugs in the
extension, all invisible on a site that reached 2.5 by upgrading, and all fixed:

| Symptom | Cause |
| --- | --- |
| `Unable to build the volunteer hours report: missing custom_table.` | `Civi\Api4\VolunteerHoursReport` is a `SqlView`, and `SqlView` drops and recreates its database view on every API4 entityTypes rebuild -- including the ones during install, before the `CiviVolunteer` custom group, the volunteer tables, or the custom *columns* exist. It threw instead of standing aside. It now reports itself unavailable and leaves the view uncreated until a rebuild can build it. |
| `Failed to create customField ...: One of the parameters (value: volunteer_commendation) is not of the type Int` | `installCommendationActivityType()` passed a custom group *name* in `custom_group_id`. APIv3 accepted that; API4 requires the ID. |
| `The volunteer_sign_up profile should exist after installation.` | `xml/auto_install.xml` holds the shipped signup profile. Old civix loaded that filename by convention; civix 25.10 does not, and nothing replaced it, so `install()` never created the profile. It is imported explicitly now. |

If the install fails with something new, the underlying SQL or API error is in
the site log, which the summary line swallows. `cv` locates the log directory
on any CMS:

```console
LOGDIR=$(cv path -d '[civicrm.log]')
grep -n "nativecode" "$LOGDIR/$(ls -t "$LOGDIR" | head -1)" | tail
```

## When a run fails with `DB Error: already exists`

Rows have accumulated in the test database. Most tests roll back, but any test
that issues DDL commits its transaction (see above), and an aborted run rolls
back nothing at all -- so fixtures with fixed names eventually collide with
their own debris. Fixture names are unique per call now wherever that was
practical, but `CRM_Core_DAO::createTestObject('CRM_Volunteer_BAO_Project')`
follows the `campaign_id` FK and mints a campaign every time, which is not
worth rewriting. Around 160 leak per full run; clear them when the count gets
large:

```sql
DELETE FROM civicrm_campaign;
```

`civicrm_volunteer_project.campaign_id` and `civicrm_activity.campaign_id` are
both `ON DELETE SET NULL`, so this is safe. Dropping and reseeding the whole
disposable database also works and is the nuclear option:

```console
mysql -h 127.0.0.1 -u root -p -e 'DROP DATABASE civivolunteer_phpunit;'
```

## Gotchas

- **Never `vendor/bin/phpunit`.** In a Drupal codebase that is PHPUnit 11;
  CiviCRM 6.16's `CiviTestListener` implements the `TestListener` interface
  PHPUnit dropped in 10.0. The runner only ever uses PHPUnit 9.6.
- **Do not name a test helper `at()`.** `PHPUnit\Framework\TestCase::at()` is a
  static method; a non-static override is a hard fatal at class-load time that
  aborts the *whole run*, not just that class:
  `Cannot make static method PHPUnit\Framework\TestCase::at() non static`.
  Other names to avoid: `any`, `never`, `once`, `exactly`, `atLeast`.
- **Deprecation noise is normal.** Filter it: `| grep -v "^PHP Deprecat"`.
- **Failures scroll off the top.** `| tail -40` shows the summary; use
  `--filter 'Class::method'` to isolate one and see its full trace.
- Getting the real SQL out of a `DBQueryException`: `getDebugInfo()` and
  `getUserInfo()` carry the query plus `[nativecode=...]`. `getSQL()` is empty.
  Do not `var_export($e->getErrorData())` — it contains circular references.
- A `civicrm.settings.php` that pins `mailing_backend` from `CIVI_SMTP_*`
  environment variables makes it a *mandatory* setting no test can override at
  runtime; `CRM_Utils_Mail::validOutBoundMail()` — which
  VolunteerUtil.getSupportingData calls for can_send_email — reads that key
  without a guard, and the resulting "Undefined array key" is promoted to a
  test error. That is why the runner exports `CIVI_SMTP_OUTBOUND_OPTION=2`
  (mail disabled); it is a no-op in codebases whose settings do not read that
  variable.

## Fixture facts worth remembering

Learned the hard way while writing `VolunteerProjectOverviewTest`:

- **A project's flexible need is created `visibility_id => 'admin'`**
  (`CRM_Volunteer_BAO_Project`, in the VOL-269 block). It is therefore absent
  from public search until something publishes it. Any test asserting that
  general availability is offered must set `visibility_id` to `public` first.
- **API4 `Contact::get()` returns trashed contacts.** Setting `is_deleted` does
  not hide a contact from it, so it cannot be used to simulate an unresolvable
  contact.
- **`civicrm_volunteer_project_contact.contact_id` has a real FK**, so an
  orphan row pointing at a nonexistent contact cannot be inserted. To simulate
  a name that will not resolve, blank a real contact's `display_name` with
  direct SQL instead.
- **Projects created in the test harness get a non-NULL `campaign_id`** they
  were never given (e.g. `3272`). A live site typically has all-NULL
  campaign_ids, so this is a harness artifact — but it does mean any code path
  touching campaigns *will* execute in tests. It comes from
  `CRM_Core_DAO::createTestObject()` following the FK; it disappears after
  clearing `civicrm_campaign` and reappears as tests repopulate it. Never assert
  that a project has *no* campaign — assert that it agrees with its project.
- **`Activity.campaign_id` is invisible to API4 here.** It is component-tagged,
  and the harness ships without CiviCampaign, so `Activity::get()` returns no
  such key. Read it through the DAO (`CRM_Volunteer_BAO_Assignment::findById()`,
  `CRM_Core_DAO::getFieldValue()`) or enable the component first.
- `getManageOverview()` takes an injectable `$now` — use it, so rolling
  seven/fourteen-day window assertions do not drift with the calendar.
- Useful helpers on `VolunteerTestAbstract`: `createProject()`, `createNeed()`,
  `createAssignment()`, `individualCreate()`, `getOptionValue($group, $name)`,
  `getMockedContactId()`.
- Swap the permission set mid-test with
  `CRM_Core_Config::singleton()->userPermissionClass->permissions = array(...)`;
  `setUp()` resets it between tests.

## JavaScript tests: three layers

The JavaScript is tested at three levels. Each one catches what the level
below cannot, and each costs more to set up.

| Layer | Directory | Runs | Needs |
|---|---|---|---|
| Behavioural checks | `tests/js` | `tools/run-js-tests.sh` | Node only |
| AngularJS on the browser stack | `tests/angular` | `npm run test:angular` | `npm install`, a CiviCRM core checkout |
| End to end | `tests/e2e` | `npm run test:e2e` | `npm install`, a running site with the extension installed |

`npm install` from the extension root installs Jest and Playwright into
`node_modules/` (gitignored, never in a release archive). Run
`npx playwright install chromium` once for the browser.

### Behavioural checks (`tests/js`)

Plain `node`, no database, no dependencies. `tools/run-js-tests.sh` runs
every `tests/js/*.test.js`, prints `PASS`/`FAIL` per file and exits non-zero
if any check failed; `node tests/js/shift-filter.test.js` runs one.

Each check executes the real controller or factory source under Node's `vm`
with a fake `angular`, a fake `CRM` and stubbed services, then asserts on
return values and on what the code sends to the API. The fakes live in
`tests/js/harness.js`: `makeAngular()` records module registrations,
`makeCRM()` records alerts, confirmations and body triggers,
`makeUnderscore()` is the lodash surface the extension reaches through
`CRM._` (with lodash 3 semantics, which is what CiviCRM ships: `first()`
takes no count, `take(n)` does), and `makeApi()` is a recording `crmApi4`
with a per-call response queue.

This layer is fast and dependency-free, and that is also its limit: it sees
no templates, no dependency injection, no digest. A controller that asks for
a service the module does not provide, a template binding that never
renders, or a lodash call whose real semantics differ from the fake all pass
here. Do not add checks that grep the source text for class names or call
sites; they fail on any rename and pass on broken code, and the set that
used to exist was removed for that reason. `hours-report-config.test.js` is
the one structural check left: it validates the managed Search Kit
configuration, which is data.

### AngularJS on the browser stack (`tests/angular`)

Jest with a jsdom document, booting what a CiviVolunteer page actually runs:
jQuery, jQuery UI, select2, jquery-validation, lodash, CiviCRM core's
`Common.js` and `crm.ajax.js` (so `ts()`, `CRM.ts`, `CRM.checkPerm`,
`CRM.url` and the `crmEntityRef`/`crmSelect2`/`crmDatepicker` widgets are
core's own), AngularJS 1.8 with ngRoute, ngSanitize and angular-mocks,
core's `crmResource`, `crmUi`, `crmUtil`, `api4`, `crmDialog` and `crmApp`
modules, and then the extension's module with every partial preloaded.
Each test compiles the real route template against a real scope and drives
it through Angular's digest.

Only what needs a server or a screen is replaced, and each replacement is a
recorder a test asserts against: the API4 backend (`recorders.api`, set per
test with `respond()`), `CRM.alert`, `CRM.confirm` (held, answered with
`.answer('crmConfirm:yes')`), `CRM.status`, `CRM.loadForm`, and direct
`CRM.api3`/`CRM.api4` calls from core's widgets. Any other attempt to open a
socket fails at once. See `tests/angular/setup.js` for the boot and
`support.js` for the helpers: `boot()` prepares a test, `services()` takes
services out of the injector (do not wrap an `async` test body in
`angular.mock.inject`, which discards the returned promise), `settle()`
lets API promises resolve across digest rounds, and `ui.*` drives inputs the
way Angular listens for them (`change` for checkboxes, radios and selects,
`input` for text).

Core is found by walking up from the extension to a composer site's
`vendor/civicrm/civicrm-core`; set `CIVICRM_CORE` (and `CIVICRM_PACKAGES`
if it is not the sibling directory) for any other checkout:

```console
CIVICRM_CORE=/path/to/site/vendor/civicrm/civicrm-core npm run test:angular
```

This layer found the defects the vm layer had masked: `initials()` on
lodash 3, a `NaN` role on a new shift, unhandled `$q` rejections. What it
still cannot see is the server, the real select2 widget behaviour under a
mouse, and the CMS around the page.

### End to end (`tests/e2e`)

Playwright against a running CiviCRM site with the extension installed, as
the administrator and as an anonymous visitor. Three settings say where the
site is and how to reach its command line, from the environment or from
`tests/e2e/.env` (gitignored; the keys are documented in `.env.example`):

```ini
CIVIVOLUNTEER_E2E_URL=https://clean-drupal.ddev.site:8443
CIVIVOLUNTEER_E2E_SITE=/path/to/that/site
CIVIVOLUNTEER_E2E_SHELL=ddev exec
```

`CIVIVOLUNTEER_E2E_SHELL` is the prefix that runs a command inside the site
(empty for a local install). The suite needs `vendor/bin/cv` and
`vendor/bin/drush` there: `global-setup.js` signs the administrator in
through `drush uli` and keeps the session in `tests/e2e/.auth/`, grants the
anonymous role *register to volunteer* if it lacks it, and seeds a project,
a shift and a volunteer through `cv api4`; `global-teardown.js` removes
everything the suite created and revokes the permission again if it granted
it. The specs read the seeded state through the `state` fixture and check
outcomes in the database through `cv` after acting in the browser.

```console
npm run test:e2e
npx playwright test --config tests/e2e/playwright.config.js --headed
npx playwright test --config tests/e2e/playwright.config.js --grep "signs up"
```

A failed step saves a screenshot and a trace under `test-results/`. The
helpers in `tests/e2e/support.js` are worth knowing: `expectUrl()` waits for
a navigation and, if it does not happen, fails with whatever CiviCRM
announced instead (a validation message beats a timeout), `pickEntityRef()`
drives a select2 entity-reference widget, and `statusBubble()` reads
`CRM.status`, which is not a notification.

This layer found the defects the other two could not: one pick in the
Assign search box creating two assignments (two `change` listeners on the
widget), the settings form printing `Array` for every description, and the
default-profile fallback failing after the settings form had been saved
once. It is the slowest layer and the one that tells you the extension
works.

## Deliberate headless form-test boundaries

The primary anonymous-signup happy path runs through the real
`preProcess()`, `buildQuickForm()`, validation, and `postProcess()` lifecycle.
The companion fan-out, write-time capacity failure, and flexible-signup cases
construct the post-build form state directly so they can isolate transactional
write behavior without rebuilding several Profiles each time. `LogTest` runs
the real pre-process and QuickForm build, then uses a small controller double
for submitted rows. These are form/service integration tests, not browser
tests: JavaScript visibility, DOM binding, redirects emitted as HTTP headers,
and final Smarty rendering remain outside the headless suite.

## Notes for AI coding assistants

- The one command is `path/to/civivolunteer/tools/run-phpunit.sh` (add
  `--filter Class::method` for one test). Do not construct `CIVICRM_DSN`
  by hand or write a throwaway runner to `/tmp`; the committed script is the
  supported path and prints the values it resolved.
- Credentials are the `CIVIVOLUNTEER_TEST_DB_*` variables in the codebase's
  `.env` — never the site's `DATABASE_*` variables. If a run fails to connect,
  check those first.
- Do not use `vendor/bin/phpunit` (see Gotchas). Do not install PHPUnit 9 into
  the codebase's `composer.json` — it conflicts with `drupal/core-dev`.
- After a full-suite failure, read saved runner output bottom-up:
  `tail -40`, filter `^PHP Deprecat`. If output stops right after
  `Initializing "Headless System"`, that is an install failure — see the
  triage table above and the ConfigAndLog grep.
- Never point the suite at a database whose name does not start with
  `civivolunteer_test`/`civivolunteer_phpunit`; the guard will (correctly)
  refuse, and weakening the guard to accept another name is never the fix.
- Before adding a new test, skim "Fixture facts worth remembering" — most
  surprises (campaign artifacts, component-tagged fields, flexible-need
  visibility) are already documented there.
