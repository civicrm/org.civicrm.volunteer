'use strict';

// Behavioural checks for the volOppSearch factory (ang/volunteer.js): hash-URL
// parsing, API4 parameter mapping and the bookmarkable query-string contract
// the public opportunity browser depends on.
//
// The source executes in this context rather than a new vm context:
// volOppSearch builds its parameter objects inside the vm, and
// assert.deepStrictEqual refuses objects whose prototypes come from another
// realm.

const assert = require('assert');
const {makeAngular, makeUnderscore, makeCRM, resolved, loadInThisContext} = require('./harness');

const {angular, registry} = makeAngular();
const {CRM, paramInputs} = makeCRM({_: makeUnderscore()});
const restoreGlobals = loadInThisContext('ang/volunteer.js', {angular, CRM});
const factories = registry.factories;

assert.ok(factories.volOppSearch, 'volunteer.js must register the volOppSearch factory.');

const apiCalls = [];
let apiResult = [];
const crmApi4 = (entity, action, params) => {
  apiCalls.push({entity, action, params});
  return resolved(apiResult);
};

function locationStub() {
  const calls = [];
  const replaceCalls = [];
  return {
    calls: calls,
    replaceCalls: replaceCalls,
    search(query) {
      calls.push(query);
      return {replace() { replaceCalls.push(query); }};
    },
  };
}

// The factory is DI-annotated; invoke it with per-scenario route params.
function buildSearch(routeParams) {
  const location = locationStub();
  const service = factories.volOppSearch(crmApi4, location, {current: {params: routeParams}});
  return {search: service, location: location};
}

// --- parseQueryParams: nested bracket keys, radius as a number, array
// --- normalisation.
const nested = buildSearch({
  'proximity[street_address]': 'Main St',
  'proximity[radius]': '5',
  'role_id[]': '4',
  'selected[]': '12',
  date_start: '2026-08-01',
  beneficiary: '3,9',
}).search;
assert.deepStrictEqual(nested.params.proximity, {street_address: 'Main St', radius: 5},
  'Bracket keys nest; the number-typed radius is parsed with parseFloat.');
assert.deepStrictEqual(nested.params.role_id, ['4'],
  "The {'': '4'} bookmark form of role_id[] flattens to a plain array.");
assert.deepStrictEqual(nested.params.selected, ['12']);
assert.strictEqual(nested.params.date_start, '2026-08-01');
assert.strictEqual(nested.params.beneficiary, '3,9');

const deep = buildSearch({'a[b][c]': 'v', 'proximity[radius]': '2.5'}).search;
assert.deepStrictEqual(deep.params.a, {b: {c: 'v'}}, 'Multi-level bracket keys nest recursively.');
assert.strictEqual(deep.params.proximity.radius, 2.5);

assert.deepStrictEqual(buildSearch({role_id: '4'}).search.params.role_id, ['4'],
  'A scalar role_id normalises to a one-element array.');
assert.deepStrictEqual(buildSearch({'role_id[]': ['4', '7']}).search.params.role_id, ['4', '7'],
  'Repeated role_id[] values stay one flat array.');
assert.deepStrictEqual(buildSearch({'selected[]': ['2', '9']}).search.params.selected, ['2', '9']);

// --- toApi4SearchParams: snake_case -> camelCase, empties skipped; the
// --- bookmark URL is pruned and keeps beneficiary as CSV (VOL-187).
apiResult = [{id: 1, title: 'Planting'}];
const s4 = buildSearch({
  'role_id[]': ['4', '7'],
  date_start: '',
  date_end: '2026-08-20',
  campaign_id: '',
  beneficiary: '3,9',
});
const searchPromise = s4.search.search();
assert.strictEqual(typeof searchPromise.then, 'function');
assert.strictEqual(apiCalls[apiCalls.length - 1].entity, 'VolunteerNeed');
assert.strictEqual(apiCalls[apiCalls.length - 1].action, 'search');
assert.deepStrictEqual(apiCalls[apiCalls.length - 1].params, {
  beneficiary: ['3', '9'],
  roleId: ['4', '7'],
  dateEnd: '2026-08-20',
}, 'snake_case filters map to API4 camelCase names; empty strings never reach API4.');
assert.deepStrictEqual(s4.search.results(), [{id: 1, title: 'Planting'}],
  'API4 rows are bound directly, not unwrapped from an APIv3 envelope.');
assert.ok(s4.location.calls[0].indexOf('date_end=2026-08-20') >= 0);
assert.ok(s4.location.calls[0].indexOf('date_start') < 0, 'Falsy values are pruned from the bookmark URL.');
assert.ok(s4.location.calls[0].indexOf('campaign_id') < 0);
assert.deepStrictEqual(paramInputs[0], {beneficiary: '3,9', date_end: '2026-08-20', role_id: ['4', '7']},
  'The entityRef beneficiary serialises as CSV; arrays keep their bracket keys.');
assert.deepStrictEqual(s4.search.params.beneficiary, ['3', '9'],
  'search() splits the CSV back into an array for the form widget.');

s4.search.params.proximity = {};
s4.search.params.role_id = [];
s4.search.params.campaign_id = null;
s4.search.params.timeFilter = 'morning';
s4.search.search();
assert.deepStrictEqual(apiCalls[apiCalls.length - 1].params, {
  beneficiary: ['3', '9'],
  roleId: [],
  dateEnd: '2026-08-20',
  timeFilter: 'morning',
}, 'Empty objects and nulls are skipped entirely; an empty array passes through (the isEmpty guard excludes arrays).');

// --- setSelected replaces (never appends) and rewrites, not pushes. ---
const s5 = buildSearch({});
s5.search.setSelected([2, 9]);
assert.deepStrictEqual(s5.search.params.selected, ['2', '9']);
s5.search.setSelected([4]);
assert.deepStrictEqual(s5.search.params.selected, ['4'], 'setSelected must replace, not append.');
s5.search.setSelected([]);
assert.ok(!('selected' in s5.search.params), 'An empty selection removes the parameter.');
assert.strictEqual(s5.location.replaceCalls.length, 3,
  'Selection updates replace the URL entry instead of pushing browser history.');

// --- returnContext: the hash-relative URL other pages hand back. ---
const s6 = buildSearch({});
assert.strictEqual(s6.search.returnContext([]), '/volunteer/opportunities',
  'With nothing to restore, the context is the bare path without a dangling "?".');
const contextUrl = s6.search.returnContext([4, 7]);
assert.ok(contextUrl.indexOf('/volunteer/opportunities?') === 0,
  'A selection yields a bookmarkable query URL.');
assert.ok(contextUrl.indexOf('selected') >= 0 && contextUrl.indexOf('=4') >= 0 && contextUrl.indexOf('=7') >= 0);
assert.deepStrictEqual(s6.search.params.selected, ['4', '7']);

// --- Beneficiary array -> CSV on the URL side (VOL-187), falsy pruning. ---
const s7 = buildSearch({});
s7.search.params.beneficiary = [3, 9];
s7.search.params.date_start = '';
s7.search.setSelected([]);
assert.strictEqual(s7.search.params.beneficiary, '3,9',
  'The bookmark URL keeps beneficiary as CSV for the entityRef widget.');
const cleaned = paramInputs[paramInputs.length - 1];
assert.strictEqual(cleaned.beneficiary, '3,9');
assert.ok(!('date_start' in cleaned), 'Falsy values never reach the serialised URL.');

restoreGlobals();

console.log('Volunteer opportunity search parameter checks passed.');
