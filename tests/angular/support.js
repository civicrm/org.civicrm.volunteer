'use strict';

/**
 * Per-test helpers for the AngularJS suite. See setup.js for what is real
 * and what is recorded.
 */

const path = require('path');
const fs = require('fs');

const ALL_PERMISSIONS = [
  'access CiviCRM',
  'register to volunteer',
  'create volunteer projects',
  'edit own volunteer projects',
  'edit all volunteer projects',
  'delete own volunteer projects',
  'delete all volunteer projects',
  'edit volunteer project relationships',
  'edit volunteer registration profiles',
  'log own hours',
  'view all contacts',
];

/**
 * Prepares one test: grants permissions, installs the recorders for alerts,
 * confirmations, status messages and popup forms, loads the `volunteer`
 * module with an API4 backend the test controls, and answers crmResource's
 * startup bundle request.
 *
 * Returns the recorders. `api.respond(fn)` sets the backend: fn receives
 * (entity, action, params, index) and returns the rows, a Promise, or throws
 * an object with error_message to reject. Every call is kept in `api.calls`.
 *
 * @param {object} [options]
 * @param {string[]} [options.permissions]  granted permissions; default all.
 * @param {object} [options.vars]           CRM.vars['org.civicrm.volunteer'].
 * @param {boolean} [options.isFrontend]    CRM.config.isFrontend.
 * @param {function} [options.configure]    module config callback, e.g.
 *                                          ($provide) => $provide.value(...).
 */
function boot(options = {}) {
  const recorders = {
    alerts: [],
    confirmations: [],
    statuses: [],
    forms: [],
    api: {calls: [], responder: () => []},
    // Calls core's own widgets make straight to CRM.api3/CRM.api4 (not
    // through crmApi4), e.g. the entity-reference autocomplete.
    coreApi: [],
  };
  recorders.api.respond = (fn) => { recorders.api.responder = fn; };
  recorders.api.callsTo = (entity, action) => recorders.api.calls.filter(
    (call) => call.entity === entity && (!action || call.action === action)
  );
  recorders.api.last = (entity, action) => {
    const matches = recorders.api.callsTo(entity, action);
    return matches[matches.length - 1];
  };

  beforeEach(() => {
    CRM.permissions = {};
    (options.permissions || ALL_PERMISSIONS).forEach((name) => { CRM.permissions[name] = true; });
    CRM.vars['org.civicrm.volunteer'] = Object.assign({}, options.vars || {});
    CRM.config.isFrontend = !!options.isFrontend;
    CRM.volunteerBackboneScripts = undefined;

    recorders.alerts.length = 0;
    recorders.confirmations.length = 0;
    recorders.statuses.length = 0;
    recorders.forms.length = 0;
    recorders.api.calls.length = 0;
    recorders.api.responder = () => [];
    recorders.coreApi.length = 0;
    CRM.api4 = (entity, action, params) => {
      recorders.coreApi.push({version: 4, entity, action, params});
      return CRM.$.Deferred().resolve([]).promise();
    };
    CRM.api3 = (entity, action, params) => {
      recorders.coreApi.push({version: 3, entity, action, params});
      return CRM.$.Deferred().resolve({is_error: 0, values: []}).promise();
    };

    CRM.alert = (text, title, type, alertOptions) => {
      recorders.alerts.push({text, title, type: type || 'alert', options: alertOptions});
      return {close() {}};
    };
    // CRM.confirm returns the dialog element; callers chain .on('crmConfirm:yes').
    // The recorder captures handlers so a test can answer the question.
    CRM.confirm = (confirmOptions) => {
      const handlers = {};
      const entry = {
        options: confirmOptions,
        answer(event) {
          (handlers[event] || []).forEach((fn) => fn());
        },
      };
      recorders.confirmations.push(entry);
      const chain = {
        on(event, fn) { (handlers[event] = handlers[event] || []).push(fn); return chain; },
      };
      return chain;
    };
    CRM.status = (statusOptions, deferred) => {
      recorders.statuses.push(statusOptions);
      return deferred || CRM.$.Deferred().resolve();
    };
    CRM.loadForm = (url, formOptions) => {
      const entry = {url, options: formOptions, handlers: {}};
      recorders.forms.push(entry);
      const chain = {on(event, fn) { entry.handlers[event] = fn; return chain; }};
      return chain;
    };
  });

  beforeEach(angular.mock.module('volunteer', options.configure || angular.noop));

  beforeEach(angular.mock.inject(($httpBackend, $rootScope, crmApi4) => {
    $httpBackend.whenGET(CRM.angular.bundleUrl).respond([]);
    crmApi4.backend = (entity, action, params, index) => {
      recorders.api.calls.push({entity, action, params, index});
      try {
        return Promise.resolve(recorders.api.responder(entity, action, params, index));
      }
      catch (error) {
        return Promise.reject(error);
      }
    };
    // The bootstrap digest. Angular broadcasts $locationChangeStart on a
    // fresh injector's first digest; in a browser that happens before any
    // route template exists, so nothing the tests compile should see it.
    $rootScope.$digest();
  }));

  afterEach(() => {
    CRM.alert = window.__civiVolunteerTest.original.alert;
    CRM.confirm = window.__civiVolunteerTest.original.confirm;
    CRM.status = window.__civiVolunteerTest.original.status;
    CRM.loadForm = window.__civiVolunteerTest.original.loadForm;
    CRM.api3 = window.__civiVolunteerTest.original.api3;
    CRM.api4 = window.__civiVolunteerTest.original.api4;
  });

  return recorders;
}

/**
 * Fetches services from the current test's injector, synchronously.
 *
 * angular.mock.inject() runs its function at once and ignores a returned
 * promise, so an async test body must not be wrapped in it: the test would
 * end before its awaits. Take the services out first, then await.
 *
 *   const {$rootScope, crmApi4} = services('$rootScope', 'crmApi4');
 */
function services(...names) {
  let out;
  angular.mock.inject(names.concat([function() { out = {}; names.forEach((name, i) => { out[name] = arguments[i]; }); }]));
  return out;
}

/**
 * Lets the API backend's promises resolve and runs a digest, repeatedly, so
 * chains of "call the API, then call it again" settle. Each round handles
 * one hop.
 */
async function settle($rootScope, rounds = 8) {
  for (let i = 0; i < rounds; i++) {
    await Promise.resolve();
    await new Promise((resolve) => setTimeout(resolve, 0));
    $rootScope.$digest();
  }
}

/**
 * Compiles markup against a scope and digests. Returns the jQuery-wrapped
 * root element.
 */
function render($compile, scope, markup) {
  const element = $compile(markup)(scope);
  scope.$digest();
  return CRM.$(element);
}

/**
 * Drive form controls the way Angular's bindings listen for them. Angular
 * binds checkboxes, radios and selects to `change` and text inputs to
 * `input`; jsdom's synthetic click() does not run a checkbox's activation
 * behaviour, so the property is set first and the event fired explicitly.
 */
const ui = {
  click: (element) => CRM.$(element).trigger('click'),
  check: (element, checked) => CRM.$(element).prop('checked', checked).trigger('change'),
  choose: (element) => CRM.$(element).prop('checked', true).trigger('change'),
  pick: (element, value) => CRM.$(element).val(value).trigger('change'),
  type: (element, value) => CRM.$(element).val(value).trigger('input'),
  /** Buttons/links whose visible text matches exactly. */
  button: (root, label) => CRM.$(root).find('button, a').filter((i, el) => text(el) === label),
  /** The options of a select as visible text. */
  options: (select) => CRM.$(select).find('option').map((i, o) => text(o)).get(),
  /**
   * Whether ng-show/ng-hide leaves an element shown. jsdom has no layout, so
   * jQuery's :visible cannot answer this; the ng-hide class can.
   */
  shown: (element) => CRM.$(element).length > 0 && !CRM.$(element).hasClass('ng-hide'),
};

/** Text content with whitespace collapsed, for assertions on rendered copy. */
function text(element) {
  return CRM.$(element).text().replace(/\s+/g, ' ').trim();
}

/** Reads a partial from disk, for compiling a route template directly. */
function partial(name) {
  return fs.readFileSync(path.join(window.__civiVolunteerTest.extensionRoot, 'ang', 'volunteer', name), 'utf8');
}

/**
 * A stand-in for Angular's $window whose `location` and `open` are inert
 * recorders, for code that navigates the browser. Everything else falls
 * through to the real window so Angular's own services keep working.
 */
function fakeWindow() {
  const stub = Object.create(window);
  Object.defineProperty(stub, 'location', {value: {href: '', replace: jest.fn(), assign: jest.fn()}, writable: true});
  Object.defineProperty(stub, 'open', {value: jest.fn(), writable: true});
  Object.defineProperty(stub, 'print', {value: jest.fn(), writable: true});
  return stub;
}

/** A route object the controllers read their projectId from. */
function routeFor(params) {
  return {current: {params: Object.assign({}, params)}, reload() { routeFor.reloads++; }};
}
routeFor.reloads = 0;

module.exports = {ALL_PERMISSIONS, boot, services, settle, render, text, partial, routeFor, fakeWindow, ui};
