'use strict';

// Behavioural checks for the volShiftFilters factory in ang/volunteer/Shifts.js:
// site-local timestamp parsing, range construction from the server presets and
// the interval/attribute matching behind the Shifts & roles filters.

const assert = require('assert');
const {makeAngular, makeUnderscore, makeCRM, loadInNewContext} = require('./harness');

const {angular, registry} = makeAngular();
const underscore = makeUnderscore();
const {CRM} = makeCRM({_: underscore});
loadInNewContext('ang/volunteer/Shifts.js', {angular, CRM, _: underscore});
const filters = registry.factories.volShiftFilters();

const presets = {
  now: '2026-08-19 14:30:00',
  today: {from: '2026-08-19 00:00:00', to: '2026-08-19 23:59:59'},
  this_week: {from: '2026-08-17 00:00:00', to: '2026-08-23 23:59:59'},
  next_week: {from: '2026-08-24 00:00:00', to: '2026-08-30 23:59:59'},
  last_week: {from: '2026-08-10 00:00:00', to: '2026-08-16 23:59:59'},
};
const any = {
  dateScope: 'custom',
  scheduleMode: 'any',
  roleId: null,
  signupStatus: 'any',
};
const need = (overrides = {}) => Object.assign({
  start_time: '2026-08-19 10:00:00',
  duration: 60,
  role_id: 3,
  accepting: true,
}, overrides);

assert.strictEqual(filters.timestamp('2026-08-19', false), Date.UTC(2026, 7, 19, 0, 0, 0));
assert.strictEqual(filters.timestamp('20260819235959', false), Date.UTC(2026, 7, 19, 23, 59, 59));
assert.strictEqual(filters.timestamp('2026-02-30', false), null, 'Calendar rollover must be rejected.');

const oneSided = filters.buildRange('custom', presets, {from: '2026-08-19', to: ''});
assert.strictEqual(oneSided.valid, true);
assert.strictEqual(oneSided.from, Date.UTC(2026, 7, 19, 0, 0, 0));
assert.strictEqual(oneSided.to, null);
assert.strictEqual(
  filters.buildRange('custom', presets, {from: '2026-08-20', to: '2026-08-19'}).valid,
  false,
  'A reversed custom range must be rejected.'
);
assert.strictEqual(
  filters.buildRange('upcoming', presets, {}).from,
  Date.UTC(2026, 7, 19, 14, 30, 0),
  'Upcoming must begin at the server-provided site-local current time.'
);

const today = filters.buildRange('today', presets, {});
assert.strictEqual(
  filters.matches(need({start_time: '2026-08-18 23:30:00', duration: 120}), any, today),
  true,
  'A fixed shift overlapping the start of the range must match.'
);
assert.strictEqual(
  filters.matches(need({start_time: '2026-08-10 09:00:00', end_time: '2026-08-20 17:00:00'}), any, today),
  true,
  'A time window spanning the range must match.'
);
assert.strictEqual(
  filters.matches(need({start_time: '2026-08-01 00:00:00', duration: null}), any, today),
  true,
  'An ongoing shift is an open interval and must match later intersecting ranges.'
);
assert.strictEqual(
  filters.matches(need({start_time: '2026-08-20 00:00:00', duration: null}), any, today),
  false,
  'An ongoing shift must not match a range which ends before it starts.'
);
assert.strictEqual(
  filters.matches(need({start_time: '2026-08-18 10:00:00', duration: 60}), any, today),
  false,
  'A completed fixed shift outside the range must not match.'
);

const allRange = filters.buildRange('all', presets, {});
const combined = {
  dateScope: 'all',
  scheduleMode: 'ongoing',
  roleId: 3,
  signupStatus: 'not_accepting',
};
assert.strictEqual(
  filters.matches(need({start_time: '', duration: null, accepting: false}), combined, allRange),
  true,
  'All dates may include a malformed date, but schedule, role, and status still apply.'
);
assert.strictEqual(filters.matches(need({start_time: '', duration: null, role_id: 4, accepting: false}), combined, allRange), false);
assert.strictEqual(filters.matches(need({start_time: '', duration: null, accepting: true}), combined, allRange), false);
assert.strictEqual(filters.matches(need({start_time: '', duration: 60, accepting: false}), combined, allRange), false);

assert.strictEqual(
  filters.needInterval(need({end_time: '2026-08-19 09:00:00'})),
  null,
  'An inverted time window must not be treated as ongoing.'
);

console.log('Volunteer shift filter behavior checks passed.');
