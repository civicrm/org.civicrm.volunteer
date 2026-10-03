'use strict';

/**
 * Shared fixtures for the behavioural JavaScript checks.
 *
 * Each check loads one real source file from ang/ under Node's vm module with
 * a fake `angular`, a fake `CRM` and the lodash/underscore surface the
 * extension reaches through CRM._, then drives the captured controller or
 * factory directly and asserts on what it returns and what it sends to the
 * API. Everything the checks used to declare inline lives here so a new
 * lodash method or CRM helper is added once.
 *
 * The fakes are deliberately small: they implement only what the shipped code
 * calls. A missing method fails loudly as a TypeError, which is the right
 * outcome -- it means the source started depending on something the fixture
 * does not yet model.
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const extensionRoot = path.resolve(__dirname, '..', '..');

function read(relativePath) {
  return fs.readFileSync(path.join(extensionRoot, relativePath), 'utf8');
}

// --- angular -------------------------------------------------------------------

/**
 * A fake angular.module() that records every registration by name so a check
 * can pick out the controller or factory it wants. Array-annotated
 * definitions are unwrapped to the function.
 */
function makeAngular(extra) {
  const registry = {factories: {}, controllers: {}, directives: {}, components: {}};
  const unwrap = (definition) => (Array.isArray(definition) ? definition[definition.length - 1] : definition);
  const moduleApi = {
    factory(name, definition) { registry.factories[name] = unwrap(definition); return moduleApi; },
    service(name, definition) { registry.factories[name] = unwrap(definition); return moduleApi; },
    controller(name, definition) { registry.controllers[name] = unwrap(definition); return moduleApi; },
    directive(name, definition) { registry.directives[name] = unwrap(definition); return moduleApi; },
    component(name, definition) { registry.components[name] = definition; return moduleApi; },
    config() { return moduleApi; },
    run() { return moduleApi; },
  };
  const angular = Object.assign({
    module() { return moduleApi; },
    copy(value) { return value === undefined ? value : JSON.parse(JSON.stringify(value)); },
    extend: Object.assign,
    forEach(collection, fn) {
      if (collection === null || collection === undefined) { return; }
      if (Array.isArray(collection)) { collection.forEach((value, index) => fn(value, index)); }
      else { Object.keys(collection).forEach((key) => fn(collection[key], key)); }
    },
    isArray: Array.isArray,
    isObject: (value) => value !== null && typeof value === 'object',
    isString: (value) => typeof value === 'string',
    isFunction: (value) => typeof value === 'function',
    toJson: (value) => JSON.stringify(value),
    noop() {},
  }, extra);
  return {angular, registry};
}

// --- lodash / underscore -----------------------------------------------------------

const asList = (collection) => (Array.isArray(collection) ? collection : Object.values(collection || {}));
const matching = (props) => (item) => Object.keys(props).every((key) => item[key] === props[key]);

/**
 * The lodash surface the extension uses through CRM._ (and the bare `_`
 * closure argument). CiviCRM ships lodash 3 (lodash-compat), so the semantics
 * here are lodash 3's: first() is the head element and takes no count;
 * take(n) is the prefix. Collection helpers accept arrays or plain objects
 * the way lodash does, since several controllers hand over API maps keyed by
 * id.
 */
function makeUnderscore() {
  const _ = {
    isArray: Array.isArray,
    isEmpty(value) {
      if (Array.isArray(value)) { return value.length === 0; }
      return value === null || value === undefined || Object.keys(value).length === 0;
    },
    keys: Object.keys,
    values: (object) => Object.values(object || {}),
    size: (object) => Object.keys(object || {}).length,
    each(collection, fn) {
      if (Array.isArray(collection)) { collection.forEach((value, index) => fn(value, index)); }
      else { Object.keys(collection || {}).forEach((key) => fn(collection[key], key)); }
      return collection;
    },
    map(collection, fn) {
      if (Array.isArray(collection)) { return collection.map(fn); }
      return Object.keys(collection || {}).map((key) => fn(collection[key], key));
    },
    filter: (collection, fn) => asList(collection).filter(fn),
    find: (collection, fn) => asList(collection).find(fn),
    findWhere: (collection, props) => asList(collection).find(matching(props)),
    where: (collection, props) => asList(collection).filter(matching(props)),
    some: (collection, fn) => asList(collection).some(fn),
    every: (collection, fn) => asList(collection).every(fn),
    reduce(collection, fn, initial) {
      if (Array.isArray(collection)) { return collection.reduce((acc, value, index) => fn(acc, value, index), initial); }
      return Object.keys(collection || {}).reduce((acc, key) => fn(acc, collection[key], key), initial);
    },
    without: (list, ...removed) => (list || []).filter((item) => removed.indexOf(item) < 0),
    pluck: (collection, key) => asList(collection).map((item) => item[key]),
    countBy: (collection, key) => asList(collection).reduce((acc, item) => {
      const group = item[key];
      acc[group] = (acc[group] || 0) + 1;
      return acc;
    }, {}),
    groupBy(collection, fn) {
      const grouped = {};
      asList(collection).forEach((item) => {
        const key = fn(item);
        (grouped[key] = grouped[key] || []).push(item);
      });
      return grouped;
    },
    uniq(collection, isSorted, iteratee) {
      if (typeof isSorted === 'function') { iteratee = isSorted; }
      const seen = [];
      return asList(collection).filter((item) => {
        const key = iteratee ? iteratee(item) : item;
        if (seen.indexOf(key) >= 0) { return false; }
        seen.push(key);
        return true;
      });
    },
    sortBy: (collection, key) => asList(collection).slice().sort(
      (a, b) => String(a[key]).localeCompare(String(b[key]))
    ),
    first: (collection) => asList(collection)[0],
    take: (collection, count) => asList(collection).slice(0, count === undefined ? 1 : count),
    flatten: (list) => Array.prototype.concat.apply([], list),
    transform(object, fn, accumulator) {
      const result = accumulator !== undefined ? accumulator : (Array.isArray(object) ? [] : {});
      Object.keys(object || {}).forEach((key) => fn(result, object[key], key));
      return result;
    },
  };
  const wrap = (value) => ({
    map: (fn) => wrap(_.map(value, fn)),
    filter: (fn) => wrap(_.filter(value, fn)),
    first: () => wrap(_.first(value)),
    take: (count) => wrap(_.take(value, count)),
    uniq: (isSorted, iteratee) => wrap(_.uniq(value, isSorted, iteratee)),
    pluck: (key) => wrap(_.pluck(value, key)),
    sortBy: (key) => wrap(_.sortBy(value, key)),
    value: () => value,
  });
  _.chain = wrap;
  return _;
}

// --- CRM -----------------------------------------------------------------------

/** CiviCRM's ts(): substitutes %1, %2, ... from the params object. */
function translate(text, params) {
  if (!params) { return String(text); }
  return Object.keys(params).reduce(
    (out, key) => out.split('%' + key).join(String(params[key])),
    String(text)
  );
}

/**
 * jQuery.param-style serialisation: nested objects use bracket keys, arrays
 * repeat a []= key.
 */
function paramString(object) {
  const pairs = [];
  const add = (key, value) => pairs.push(
    encodeURIComponent(key) + '=' + encodeURIComponent(value === null || value === undefined ? '' : value)
  );
  (function walk(node, prefix) {
    Object.keys(node || {}).forEach((key) => {
      const value = node[key];
      const name = prefix ? prefix + '[' + key + ']' : key;
      if (Array.isArray(value)) {
        value.forEach((item) => add(name + '[]', item));
      } else if (value !== null && typeof value === 'object') {
        walk(value, name);
      } else {
        add(name, value);
      }
    });
  })(object);
  return pairs.join('&');
}

/**
 * The CRM global. Alerts, confirmations, body triggers and CRM.$.param inputs
 * are recorded so checks can assert on what the source announced or asked.
 * Confirmations are held rather than answered: call `.callback()` on one to
 * simulate the user choosing Yes.
 */
function makeCRM(overrides) {
  const alerts = [];
  const confirmations = [];
  const bodyTriggers = [];
  const paramInputs = [];
  const $ = (selector) => ({trigger(event) { bodyTriggers.push({selector, event}); }});
  $.param = (object) => { paramInputs.push(object); return paramString(object); };
  const CRM = Object.assign({
    $,
    _: makeUnderscore(),
    ts: () => translate,
    alert: (message, title, type) => alerts.push({message, title, type}),
    confirm: (options) => ({
      on(event, callback) {
        if (event === 'crmConfirm:yes') { confirmations.push({options, callback}); }
        return this;
      },
    }),
    url: (route) => 'url:' + route,
    checkPerm: () => true,
    angRequires: () => [],
    utils: {},
    vars: {},
    volunteer: {isCampaignEnabled: true, isEventEnabled: true, campaignFilter: {}},
  }, overrides);
  return {CRM, alerts, confirmations, bodyTriggers, paramInputs};
}

// --- promises and service stubs ---------------------------------------------------

/** A native-promise $q with resolve, reject, defer and an all() that takes arrays or objects. */
function makeQ() {
  const all = (work) => {
    if (Array.isArray(work)) { return Promise.all(work); }
    const keys = Object.keys(work);
    return Promise.all(keys.map((key) => work[key])).then((values) => {
      const result = {};
      keys.forEach((key, index) => { result[key] = values[index]; });
      return result;
    });
  };
  const defer = () => {
    const deferred = {};
    deferred.promise = new Promise((resolve, reject) => {
      deferred.resolve = resolve;
      deferred.reject = reject;
    });
    return deferred;
  };
  return {resolve: (value) => Promise.resolve(value), reject: (error) => Promise.reject(error), defer, all};
}

/**
 * A thenable whose callbacks run at once, for checks that want construction
 * and a save to complete synchronously without awaiting.
 */
function resolved(value) {
  const promise = {
    then(onFulfilled) { return resolved(onFulfilled ? onFulfilled(value) : value); },
    catch() { return promise; },
    finally(onSettled) { if (onSettled) { onSettled(); } return promise; },
  };
  return promise;
}

/**
 * A recording crmApi4. Every call is pushed to `calls`; `responses` is a
 * queue consumed one entry per call, each `{rows}`, `{error}` or
 * `{deferred: {}}` -- the last leaves the request hanging until the check
 * resolves or rejects the deferred itself. An empty queue answers `[{}]`.
 */
function makeApi() {
  const calls = [];
  const responses = [];
  const crmApi4 = (entity, action, params) => {
    calls.push({entity, action, params});
    const response = responses.length ? responses.shift() : {rows: [{}]};
    if (response.deferred) {
      Object.assign(response.deferred, makeQ().defer());
      return response.deferred.promise;
    }
    return response.error ? Promise.reject(response.error) : Promise.resolve(response.rows);
  };
  return {crmApi4, calls, responses};
}

/** Lets queued microtasks and a zero-delay timer run. */
const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

/**
 * Values produced inside a new vm context carry that realm's prototypes, which
 * assert.deepStrictEqual rejects; compare through a host-realm round trip.
 */
const deepEqual = (actual, expected, message) => assert.deepStrictEqual(
  JSON.parse(JSON.stringify(actual)), expected, message
);

/**
 * angular's $filter('date') for the handful of patterns the templates use.
 * Every call is recorded so a check can inspect the Date the source built.
 */
function makeDateFilter() {
  const calls = [];
  const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const weekdaysShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
    'August', 'September', 'October', 'November', 'December'];
  const monthsShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const pad = (value) => (value < 10 ? '0' + value : String(value));
  // Placeholder tokens first, so weekday and month names containing "d" or
  // "M" are never re-replaced while the pattern is expanded.
  const format = (date, pattern) => pattern
    .replace('EEEE', '\u0000')
    .replace('EEE', '\u0001')
    .replace('MMMM', '\u0002')
    .replace('MMM', '\u0003')
    .replace('h:mm a', '\u0004')
    .replace('d', '\u0005')
    .replace('\u0000', weekdays[date.getDay()])
    .replace('\u0001', weekdaysShort[date.getDay()])
    .replace('\u0002', months[date.getMonth()])
    .replace('\u0003', monthsShort[date.getMonth()])
    .replace('\u0004', ((date.getHours() % 12) || 12) + ':' + pad(date.getMinutes())
      + ' ' + (date.getHours() < 12 ? 'AM' : 'PM'))
    .replace('\u0005', String(date.getDate()));
  const filter = (name) => (date, pattern) => {
    calls.push({name, date, pattern});
    return format(date, pattern);
  };
  return {filter, calls};
}

// --- loading the source -------------------------------------------------------------

/**
 * Runs one source file in a fresh vm context. Objects the source creates
 * belong to that realm, so compare them with deepEqual() above.
 */
function loadInNewContext(relativePath, sandbox) {
  vm.runInNewContext(read(relativePath), Object.assign({Date, jQuery: {}}, sandbox));
}

/**
 * Runs one source file in this context with the given globals installed,
 * for checks that need assert.deepStrictEqual on objects the source builds.
 * Returns a function that restores the previous globals; a failed assertion
 * exits the process anyway, so leakage is only a concern for a passing run.
 */
function loadInThisContext(relativePath, globals) {
  const saved = {};
  Object.keys(globals).forEach((name) => {
    saved[name] = global[name];
    global[name] = globals[name];
  });
  vm.runInThisContext(read(relativePath));
  return () => {
    Object.keys(saved).forEach((name) => { global[name] = saved[name]; });
  };
}

module.exports = {
  extensionRoot,
  read,
  makeAngular,
  makeUnderscore,
  makeCRM,
  translate,
  paramString,
  makeQ,
  resolved,
  makeApi,
  settle,
  deepEqual,
  makeDateFilter,
  loadInNewContext,
  loadInThisContext,
};
