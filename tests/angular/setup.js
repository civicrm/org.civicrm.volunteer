'use strict';

/**
 * Boots the browser stack the CiviVolunteer Angular module runs on, inside
 * Jest's jsdom document, so each test file starts with the real thing:
 *
 *   jQuery, jQuery UI, select2, jquery-validation, lodash
 *   CiviCRM core's Common.js and crm.ajax.js (ts(), CRM.ts, CRM.checkPerm,
 *     CRM.url, CRM.utils, the crmEntityRef/crmSelect2/crmDatepicker widgets)
 *   AngularJS 1.8 with ngRoute, ngSanitize and angular-mocks
 *   core's crmResource, crmUi, crmUtil, api4, crmDialog and crmApp modules
 *   the extension's ang/volunteer.js and ang/volunteer/*.js
 *
 * Only what needs a server or a real screen is replaced, and each replacement
 * is a recorder a test can assert against: the API4 backend (see support.js),
 * CRM.alert, CRM.confirm, CRM.status and CRM.loadForm. Everything else is the
 * code the browser would run.
 *
 * Core is found by walking up from the extension to a composer-managed site's
 * vendor/civicrm/civicrm-core; set CIVICRM_CORE (and, if it is not the sibling
 * directory, CIVICRM_PACKAGES) to use another checkout.
 */

const fs = require('fs');
const path = require('path');

const extensionRoot = path.resolve(__dirname, '..', '..');

function findCore() {
  if (process.env.CIVICRM_CORE) {
    return path.resolve(process.env.CIVICRM_CORE);
  }
  let dir = extensionRoot;
  while (dir !== path.dirname(dir)) {
    const candidate = path.join(dir, 'vendor', 'civicrm', 'civicrm-core');
    if (fs.existsSync(path.join(candidate, 'js', 'Common.js'))) {
      return candidate;
    }
    dir = path.dirname(dir);
  }
  throw new Error(
    'CiviCRM core was not found above ' + extensionRoot + '. ' +
    'Set CIVICRM_CORE to a civicrm-core checkout, e.g. <site>/vendor/civicrm/civicrm-core.'
  );
}

const core = findCore();
const packages = process.env.CIVICRM_PACKAGES
  ? path.resolve(process.env.CIVICRM_PACKAGES)
  : path.join(path.dirname(core), 'civicrm-packages');

for (const required of [
  path.join(core, 'js', 'Common.js'),
  path.join(core, 'bower_components', 'angular-mocks', 'angular-mocks.js'),
  path.join(packages, 'jquery', 'plugins', 'jquery.blockUI.js'),
]) {
  if (!fs.existsSync(required)) {
    throw new Error('Missing ' + required + '. Check CIVICRM_CORE / CIVICRM_PACKAGES.');
  }
}

// Scripts are evaluated in the global scope of the jsdom window, the way a
// <script> tag would be, so `var CRM`, `window.angular` and friends land
// where the code expects them. The sourceURL keeps stack traces readable.
function loadScript(file) {
  const code = fs.readFileSync(file, 'utf8');
  (0, eval)(code + '\n//# sourceURL=' + path.relative(extensionRoot, file));
}

// The notification container CiviCRM renders on every page. CRM.alert()
// checks for it; without it alerts fall back to window.alert.
document.body.innerHTML = '<div id="crm-notification-container"></div><div id="crm-main-content-wrapper"></div>';

// --- libraries ----------------------------------------------------------------------

loadScript(path.join(core, 'bower_components', 'jquery', 'dist', 'jquery.js'));
loadScript(path.join(core, 'bower_components', 'jquery-ui', 'jquery-ui.js'));
loadScript(path.join(core, 'bower_components', 'select2', 'select2.js'));
loadScript(path.join(core, 'bower_components', 'jquery-validation', 'dist', 'jquery.validate.js'));
loadScript(path.join(core, 'bower_components', 'lodash-compat', 'lodash.js'));
loadScript(path.join(packages, 'jquery', 'plugins', 'jquery.blockUI.js'));
loadScript(path.join(packages, 'jquery', 'plugins', 'jquery.notify.js'));
loadScript(path.join(packages, 'jquery', 'plugins', 'jquery.timeentry.js'));

// --- CiviCRM globals ---------------------------------------------------------------

// What CRM_Core_Resources hands the page before Common.js runs.
window.CRM = {
  config: {
    isFrontend: false,
    allowAlertAutodismissal: true,
    dateInputFormat: 'mm/dd/yy',
    timeInputFormat: 1,
    resourceBase: '/',
    // Read by the crmEntityRef widget; the page supplies these from
    // CRM_Core_Resources.
    entityRef: {filters: {}, links: {}},
  },
  vars: {'org.civicrm.volunteer': {}},
  // Granted permissions; support.js resets this per test.
  permissions: {},
  strings: {},
  angular: {
    // Every module on the page, which crmApp aggregates. Mirrors what
    // CiviCRM's Angular loader emits for the volunteer host pages.
    modules: ['ngRoute', 'ngSanitize', 'crmResource', 'dialogService', 'crmUi', 'crmUtil', 'api4', 'crmDialog', 'volunteer'],
    requires: {
      crmResource: [],
      crmUi: ['crmResource'],
      crmUtil: [],
      api4: [],
      crmDialog: ['dialogService'],
      crmApp: [],
    },
    templates: {},
    // crmResource fetches every module's partials from here at startup; the
    // partials are preloaded into CRM.angular.templates below, and support.js
    // answers this request with an empty bundle.
    bundleUrl: '/civicrm-test/angular-bundle',
  },
  crmApp: {defaultRoute: '/volunteer/manage'},
  volunteer: {isCampaignEnabled: true, isEventEnabled: true, campaignFilter: {}},
};

loadScript(path.join(core, 'js', 'Common.js'));
loadScript(path.join(core, 'js', 'crm.ajax.js'));
loadScript(path.join(core, 'js', 'crm.datepicker.js'));
loadScript(path.join(core, 'js', 'wysiwyg', 'crm.wysiwyg.js'));

// CRM.url() is a template filled in by the page. These are the shapes a
// Drupal site renders for the backend and frontend hosts.
CRM.url({
  back: '/civicrm/crmajax-placeholder-url-path?civicrm-placeholder-url-query=1',
  front: '/civicrm/crmajax-placeholder-url-path?civicrm-placeholder-url-query=1',
});

// No test may reach a server. Core's widgets call CRM.api3/CRM.api4 and
// jQuery.ajax directly (the entity-reference autocomplete, for one);
// support.js installs recorders for the two API entry points per test, and
// anything else that tries to open a socket fails at once with a clear
// message instead of a DNS error minutes later.
CRM.$.ajaxSetup({
  xhr: function() {
    throw new Error('Network access is not available in the AngularJS unit tests (' + (this.url || 'unknown URL') + ').');
  },
});
// jQuery effects complete at once, so blockUI overlays and dialogs settle
// within a digest rather than on an animation timer.
CRM.$.fx.off = true;

// --- AngularJS ----------------------------------------------------------------------

loadScript(path.join(core, 'bower_components', 'angular', 'angular.js'));
loadScript(path.join(core, 'bower_components', 'angular-route', 'angular-route.js'));
loadScript(path.join(core, 'bower_components', 'angular-sanitize', 'angular-sanitize.js'));
// angular-mocks installs module()/inject() only when it sees a test runner
// global it recognises; Jest provides the beforeEach/afterEach it then uses.
window.jasmine = window.jasmine || {};
loadScript(path.join(core, 'bower_components', 'angular-mocks', 'angular-mocks.js'));
loadScript(path.join(core, 'bower_components', 'angular-jquery-dialog-service', 'dialog-service.js'));
loadScript(path.join(core, 'js', 'angular-crmResource', 'all.js'));
loadScript(path.join(core, 'ang', 'crmUi.js'));
loadScript(path.join(core, 'ang', 'crmUtil.js'));
loadScript(path.join(core, 'ang', 'api4.js'));
loadScript(path.join(core, 'ang', 'api4', 'crmApi4.js'));
loadScript(path.join(core, 'ang', 'crmDialog.js'));

// --- the extension --------------------------------------------------------------------

// The module's requires come from ang/volunteer.ang.php, the manifest CiviCRM
// itself reads; a dependency missing there is missing in the browser too.
function manifestRequires() {
  const manifest = fs.readFileSync(path.join(extensionRoot, 'ang', 'volunteer.ang.php'), 'utf8');
  const block = manifest.match(/'requires'\s*=>\s*\[([^\]]*)\]/);
  if (!block) {
    throw new Error("ang/volunteer.ang.php: no 'requires' list found.");
  }
  return Array.from(block[1].matchAll(/'([^']+)'/g), (m) => m[1]);
}
CRM.angular.requires.volunteer = manifestRequires();
loadScript(path.join(core, 'ang', 'crmApp.js'));

loadScript(path.join(extensionRoot, 'ang', 'volunteer.js'));
for (const file of fs.readdirSync(path.join(extensionRoot, 'ang', 'volunteer')).filter((f) => f.endsWith('.js')).sort()) {
  loadScript(path.join(extensionRoot, 'ang', 'volunteer', file));
}

// Partials, keyed the way templateUrl refers to them.
function preloadPartials(dir, prefix) {
  for (const file of fs.readdirSync(dir).filter((f) => f.endsWith('.html'))) {
    CRM.angular.templates[prefix + file] = fs.readFileSync(path.join(dir, file), 'utf8');
  }
}
preloadPartials(path.join(core, 'ang', 'crmUi'), '~/crmUi/');
preloadPartials(path.join(extensionRoot, 'ang', 'volunteer'), '~/volunteer/');

// --- recorders for what needs a screen or a server -----------------------------------

// Installed fresh before every test by support.js's boot(); declared here so
// the originals are captured once.
window.__civiVolunteerTest = {
  core,
  packages,
  extensionRoot,
  original: {
    alert: CRM.alert,
    confirm: CRM.confirm,
    status: CRM.status,
    loadForm: CRM.loadForm,
    api3: CRM.api3,
    api4: CRM.api4,
  },
};
