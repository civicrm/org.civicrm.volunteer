'use strict';

// Behavioral checks for the VolOppsCtrl controller in ang/volunteer/VolOppsCtrl.js:
// result grouping, the one-time selection restore, and the location-filter
// normalization that runs at construction.

const assert = require('assert');
const {
  makeAngular, makeUnderscore, makeCRM, settle, deepEqual, makeDateFilter, loadInNewContext,
} = require('./harness');

const {angular, registry} = makeAngular();
const underscore = makeUnderscore();
const {CRM, alerts} = makeCRM({_: underscore});
loadInNewContext('ang/volunteer/VolOppsCtrl.js', {angular, CRM, _: underscore});
const controllers = registry.controllers;
assert.strictEqual(typeof controllers.VolOppsCtrl, 'function');

const countries = {
  1228: {id: '1228', name: 'United States', iso_code: 'us', is_default: 1},
  1012: {id: '1012', name: 'Canada', iso_code: 'ca', is_default: 0},
};

function buildOpps(options = {}) {
  const results = [];
  const selectedCalls = [];
  const searchCounts = {run: 0};
  const apiCalls = [];
  const filterService = makeDateFilter();
  const volOppSearch = {
    params: options.params || {proximity: {}},
    results: () => results,
    search: () => {
      searchCounts.run += 1;
      return Promise.resolve();
    },
    setSelected: (ids) => selectedCalls.push(ids.slice()),
    returnContext: (ids) => 'return:' + ids.join(','),
  };
  const scope = {$watch: () => {}};
  const crmApi4 = (entity, action, params) => {
    apiCalls.push({entity, action, params});
    return Promise.resolve([]);
  };
  controllers.VolOppsCtrl(
    {current: {params: options.routeParams || {}}},
    scope,
    {location: {}},
    filterService.filter,
    (messages, promise) => promise,
    crmApi4,
    volOppSearch,
    options.countries || countries,
    Object.assign({roles: {}, proximity_available: 1}, options.supporting)
  );
  return {scope, results, selectedCalls, searchCounts, filterCalls: filterService.calls, apiCalls};
}

const scheduledNeed = (id, overrides = {}) => Object.assign({
  id: id, is_flexible: 0, start_time: '2026-08-25 09:00:00', duration: 60, end_time: null,
}, overrides);

(async function() {
  // -- construction-time location normalization ---------------------------------

  {
    // A bookmarked country id is matched and normalized to a number.
    const {scope} = buildOpps({params: {
      proximity: {country: '1012', state_province_id: '1030', unit: 'mile'},
    }});
    assert.strictEqual(scope.searchParams.proximity.country, 1012);
    assert.strictEqual(scope.searchParams.proximity.state_province_id, 1030);
    assert.strictEqual(scope.searchParams.proximity.unit, 'miles', 'the singular unit is normalized');
    assert.strictEqual(scope.locationFilters.unit, 'miles');
    assert.ok(!('country_id' in scope.searchParams.proximity), 'the legacy country_id key is dropped');

    // Matching by name is case-insensitive.
    const byName = buildOpps({params: {proximity: {country: 'united states'}}});
    assert.strictEqual(byName.scope.searchParams.proximity.country, 1228);

    // Matching by ISO code is case-insensitive on both sides.
    const byIso = buildOpps({params: {proximity: {country: 'US'}}});
    assert.strictEqual(byIso.scope.searchParams.proximity.country, 1228);

    // An unknown bookmark is dropped; the site default only fills the draft
    // location model, so the first result list is not silently filtered.
    const unknown = buildOpps({params: {proximity: {country: 'Atlantis'}}});
    assert.strictEqual(unknown.scope.defaultCountryId, 1228, 'the default country is read from is_default');
    assert.ok(!('country' in unknown.scope.searchParams.proximity), 'an unknown country is not applied');
    assert.strictEqual(unknown.scope.locationFilters.country, 1228, 'the default fills the draft model');

    // No bookmark at all behaves the same way.
    const empty = buildOpps({params: {proximity: {}}});
    assert.ok(!('country' in empty.scope.searchParams.proximity));
    assert.strictEqual(empty.scope.locationFilters.country, 1228);

    // Non-positive state ids are dropped rather than coerced to NaN.
    const zeroState = buildOpps({params: {proximity: {state_province_id: '0'}}});
    assert.ok(!('state_province_id' in zeroState.scope.searchParams.proximity));
    const junkState = buildOpps({params: {proximity: {state_province_id: 'abc'}}});
    assert.ok(!('state_province_id' in junkState.scope.searchParams.proximity));

    // Without proximity support, radius/unit never reach either model.
    const noProximity = buildOpps({
      params: {proximity: {country: '1012', radius: '5', unit: 'km'}},
      supporting: {proximity_available: 0},
    });
    assert.ok(!('radius' in noProximity.scope.searchParams.proximity));
    assert.ok(!('unit' in noProximity.scope.searchParams.proximity));
    assert.ok(!('radius' in noProximity.scope.locationFilters));
    assert.ok(!('unit' in noProximity.scope.locationFilters));
    assert.strictEqual(noProximity.scope.proximityAvailable, false);
  }

  // -- groupResults(): flexible / ongoing / by-date buckets ---------------------

  {
    const {scope, results, filterCalls} = buildOpps({params: {proximity: {}}});
    await settle(); // the construction-time search runs with no results
    assert.strictEqual(scope.hasSearched, true);

    results.push(
      {id: 11, is_flexible: 1, quantity: 5},
      scheduledNeed(12, {start_time: '2026-08-25T09:00:00', duration: 90}),
      scheduledNeed(13, {start_time: '2026-08-25 14:00:00'}),
      scheduledNeed(14, {start_time: '2026-08-27 08:00:00', duration: null}),
      scheduledNeed(15, {start_time: '2026-08-26 08:00:00', duration: null, end_time: '2026-08-26 12:00:00'}),
      scheduledNeed(16, {start_time: '', duration: 60})
    );
    await scope.search();

    deepEqual(scope.groups.map((group) => group.key),
      ['2026-08-25', '2026-08-26', '', 'no-fixed-time'],
      'groups are keyed by start date, with the ongoing bucket last');
    deepEqual(scope.groups[0].needs.map((need) => need.id), [12, 13]);
    assert.strictEqual(scope.groups[0].label, 'Tuesday, August 25');
    deepEqual(scope.groups[1].needs.map((need) => need.id), [15]);
    assert.strictEqual(scope.groups[2].label, 'Upcoming shifts',
      'a need whose start_time does not parse falls back to the generic label');
    assert.strictEqual(scope.groups[3].label, 'No fixed time');
    deepEqual(scope.groups[3].needs.map((need) => need.id), [14],
      'a need with no duration and no end_time has no fixed time');
    deepEqual(scope.flexibleNeeds.map((need) => need.id), [11],
      'flexible needs are listed separately, never inside a date group');
    assert.strictEqual(scope.resultCount(), 6);

    // The group label date is parsed from the site-local wall time components
    // (both the space and the ISO "T" separator), not reinterpreted as UTC.
    const labelCall = filterCalls.find((call) => call.pattern === 'EEEE, MMMM d');
    assert.strictEqual(labelCall.date.getTime(), new Date(2026, 7, 25, 9, 0).getTime());
  }

  // -- reconcileSelections(): one-time cart restore ------------------------------

  {
    const {scope, results, selectedCalls} = buildOpps({params: {
      proximity: {},
      selected: ['12', '12', 'bad', '14'],
    }});
    // Only 12 is available: 14 is unavailable at restore time, so it is
    // dropped from the restore (it never enters the cart) and announced.
    results.push(scheduledNeed(12));
    const alertsBefore = alerts.length;
    await settle(); // the construction-time search performs the restore

    deepEqual(Object.keys(scope.shoppingCart), ['12'],
      'only requested selections still present in the results are restored');
    assert.strictEqual(scope.shoppingCart[12].inCart, true);
    deepEqual(selectedCalls[selectedCalls.length - 1], ['12']);
    assert.strictEqual(alerts.length, alertsBefore + 1);
    assert.strictEqual(alerts[alerts.length - 1].title, 'Selection updated');
    assert.ok(
      alerts[alerts.length - 1].message.indexOf('no longer available') >= 0,
      'the unavailable selection is announced'
    );

    // The restore is one-time: 14 reappearing in a later search must not
    // re-seed the cart, and no further restore alerts fire.
    results.push(scheduledNeed(14));
    await scope.search();
    deepEqual(Object.keys(scope.shoppingCart), ['12'],
      'a selection that was unavailable at restore time must not resurrect');
    assert.strictEqual(alerts.length, alertsBefore + 1,
      'the restore alert fires exactly once');

    // A selection the user removed does not come back either.
    scope.toggleSelection(scope.shoppingCart[12]);
    assert.strictEqual(scope.shoppingCart[12], undefined);
    await scope.search();
    deepEqual(Object.keys(scope.shoppingCart), [],
      'a user-removed selection must not come back on the next search');
    deepEqual(selectedCalls[selectedCalls.length - 1], []);
    assert.strictEqual(alerts.length, alertsBefore + 1);
  }

  console.log('Volunteer opportunities grouping behavior checks passed.');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
