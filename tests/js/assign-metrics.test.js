'use strict';

// Behavioral checks for the VolunteerAssign controller in ang/volunteer/Assign.js:
// capacity math (openSpots/spotsLabel/capacityLabel), the finite-quantity rule
// behind isCounted, initials(), the need values written for scheduled vs
// available shifts, and the optimistic remove/move with revert on failure.
//
// isCounted() is kept in step, by comment, with volWorkflow.summarize() and the
// server-side VolunteerAssignment.getCapacity action: only needs with a finite
// positive quantity contribute to capacity on every layer.

const assert = require('assert');
const {
  makeAngular, makeUnderscore, makeCRM, makeQ, makeApi, settle, deepEqual, loadInNewContext,
} = require('./harness');

const {angular, registry} = makeAngular();
const underscore = makeUnderscore();
const {CRM, alerts, confirmations} = makeCRM({_: underscore});
loadInNewContext('ang/volunteer/Assign.js', {angular, CRM, _: underscore});
const controllers = registry.controllers;
assert.strictEqual(typeof controllers.VolunteerAssign, 'function');

const need = (id, overrides = {}) => Object.assign({
  id: id, role_id: 3, quantity: '3', is_flexible: 0, is_active: 1, visibility_id: 1,
  start_time: '2026-08-25 09:00:00', duration: 90, end_time: null,
}, overrides);

function baseState() {
  return {
    context: {
      project: {id: 42, title: 'Harvest Festival', is_active: 1},
      beneficiaryNames: [],
      supporting: {workflow: {
        statuses: [{name: 'Available', id: '1'}, {name: 'Scheduled', id: '2'}],
        roles: [{id: 3, label: 'Greeter'}],
      }},
      // Need 35 is oversubscribed (3 filled of 1 spot) to pin the Math.max
      // clamp; need 34 has no quantity and never counts toward capacity.
      filledByNeed: {30: 2, 31: 1, 33: 0, 35: 3},
      needs: [
        need(30),
        need(31, {quantity: '1', start_time: '2026-08-25 13:00:00', duration: 60}),
        need(32, {quantity: '2', is_active: 0}),
        need(33, {quantity: null, is_flexible: 1, duration: null, start_time: '2026-08-25 08:00:00'}),
        need(34, {quantity: null, duration: null}),
        need(35, {quantity: '1', start_time: '2026-08-25 17:00:00', duration: 60}),
      ],
      assignments: [
        {id: 900, volunteer_need_id: 30, assignee_display_name: 'Ada Lovelace', assignee_contact_id: 202, details: 'morning'},
        {id: 901, volunteer_need_id: 30, assignee_display_name: 'Grace Hopper', assignee_contact_id: 203, details: ''},
        {id: 902, volunteer_need_id: 31, assignee_display_name: 'Cher', assignee_contact_id: 204, details: ''},
        {id: 903, volunteer_need_id: 33, assignee_display_name: 'Madonna', assignee_contact_id: 205, details: ''},
        {id: 904, volunteer_need_id: 33, assignee_display_name: 'Jean van der Meulen', assignee_contact_id: 206, details: ''},
      ],
    },
  };
}

function buildAssign(state) {
  const scope = {
    $applyAsync: (fn) => fn(),
    $watch: () => {},
    $on: () => {},
  };
  const api = makeApi();
  const navigations = [];
  const workflow = {
    loadContext: () => Promise.resolve(state.context),
    getAssignments: () => Promise.resolve(state.context.assignments),
    getCapacity: () => Promise.resolve({by_need: Object.assign({}, state.context.filledByNeed)}),
    projectPath: (projectId, step) => '/volunteer/manage/' + parseInt(projectId, 10) + '/' + step,
    navigate: (destination) => navigations.push(destination),
    cancel: () => {},
  };
  controllers.VolunteerAssign(
    scope, {current: {params: {projectId: '42'}}}, {}, makeQ(), api.crmApi4,
    (messages, promise) => promise, workflow
  );
  return {scope, api, workflow};
}

const shiftById = (scope, id) => scope.shifts.find((row) => row.id === id);

(async function() {
  // -- board partition and capacity math ----------------------------------------

  {
    const {scope} = buildAssign(baseState());
    await settle();
    assert.strictEqual(scope.loading, false);

    deepEqual(scope.shifts.map((row) => row.id), [30, 31, 34, 35],
      'the board lists active scheduled shifts; inactive and flexible needs are excluded');
    assert.strictEqual(scope.availableNeed.id, 33);
    deepEqual(scope.availableAssignments.map((row) => row.id), [903, 904]);
    assert.strictEqual(scope.selectedNeed.id, 30, 'the first shift is selected by default');

    // assigned = 2 + 1 + 3 = 6 across counted needs; total = 3 + 1 + 1 = 5;
    // open never goes negative.
    deepEqual(scope.metrics, {assigned: 6, open: 0, available: 2, total: 5},
      'metrics count only needs with a finite quantity and clamp open at zero');
    deepEqual(scope.workflow.summary, {filled: 6, total: 5});

    const need30 = shiftById(scope, 30);
    const need31 = shiftById(scope, 31);
    const need34 = shiftById(scope, 34);
    const need35 = shiftById(scope, 35);

    assert.strictEqual(scope.filledFor(need30), 2);
    assert.strictEqual(scope.openSpots(need30).length, 1, 'one placeholder per unfilled spot');
    assert.strictEqual(scope.openSpots(need31).length, 0, 'a full shift has no open spots');
    assert.strictEqual(scope.openSpots(need35).length, 0,
      'an oversubscribed shift is clamped, never negative');
    assert.strictEqual(scope.openSpots(need34).length, 0,
      'a need without quantity has no spot placeholders');
    assert.strictEqual(scope.openSpots(null).length, 0);

    assert.strictEqual(scope.spotsLabel(need30), '2 of 3 spots filled');
    assert.strictEqual(scope.capacityLabel(need30), '2 of 3 filled');
    assert.strictEqual(scope.spotsLabel(scope.availableNeed), 'No capacity limit');
    assert.strictEqual(scope.spotsLabel(need34), 'No capacity limit',
      'a need without quantity reads as unlimited');
    assert.strictEqual(scope.capacityLabel(null), '');

    assert.strictEqual(scope.isFull(need31), true);
    assert.strictEqual(scope.isFull(need30), false);
    assert.strictEqual(scope.isFull(need34), false, 'an unlimited need is never full');
  }

  // -- initials() ----------------------------------------------------------------

  {
    const {scope} = buildAssign(baseState());
    await settle();
    assert.strictEqual(scope.initials('Ada Lovelace'), 'AL');
    assert.strictEqual(scope.initials('jean van der Meulen'), 'JV',
      'only the first two name parts count');
    assert.strictEqual(scope.initials('cher'), 'C');
    assert.strictEqual(scope.initials('  spaced   out  names '), 'SO',
      'blank parts are skipped, not counted');
    assert.strictEqual(scope.initials(''), '');
    assert.strictEqual(scope.initials(null), '');
  }

  // -- needValues(): scheduled vs available, NULL duration passthrough -----------

  {
    const {scope, api} = buildAssign(baseState());
    await settle();

    // Adding to a scheduled shift writes the Scheduled status and the duration.
    scope.newContacts[30] = '202';
    await scope.addVolunteer(shiftById(scope, 30));
    let call = api.calls[api.calls.length - 1];
    assert.strictEqual(call.entity, 'VolunteerAssignment');
    assert.strictEqual(call.action, 'create');
    deepEqual(call.params.values, {
      volunteer_need_id: 30,
      status_id: 2,
      activity_date_time: '2026-08-25 09:00:00',
      time_scheduled_minutes: 90,
      volunteer_role_id: 3,
      contact_id: 202,
    }, 'a scheduled shift gets the Scheduled status and its duration in minutes');
    assert.strictEqual(scope.newContacts[30], null, 'the contact picker is cleared after adding');

    // Adding to the flexible need writes the Available status and passes a NULL
    // duration through as NULL rather than coercing it to 0.
    scope.newContacts[33] = 205;
    await scope.addVolunteer(scope.availableNeed);
    call = api.calls[api.calls.length - 1];
    deepEqual(call.params.values, {
      volunteer_need_id: 33,
      status_id: 1,
      activity_date_time: '2026-08-25 08:00:00',
      time_scheduled_minutes: null,
      volunteer_role_id: 3,
      contact_id: 205,
    }, 'the flexible need gets the Available status and a NULL duration');

    // Without a contact picked, nothing is sent.
    const callsBefore = api.calls.length;
    assert.strictEqual(scope.addVolunteer(shiftById(scope, 30)), undefined);
    assert.strictEqual(api.calls.length, callsBefore);
  }

  // -- removeVolunteer(): optimistic splice, revert on failure --------------------

  {
    const state = baseState();
    const {scope, api} = buildAssign(state);
    await settle();
    const need30 = shiftById(scope, 30);
    const ada = need30.assignments[0];
    const alertsBefore = alerts.length;

    api.responses.push({error: {error_message: 'denied'}});
    scope.removeVolunteer(ada);
    assert.strictEqual(confirmations.length, 1, 'removal asks for confirmation');
    assert.ok(confirmations[0].options.message.indexOf('Ada Lovelace') >= 0,
      'the confirmation names the volunteer');
    confirmations.shift().callback();

    // Optimistic state, visible before the round trip completes.
    assert.strictEqual(need30.assignments.length, 1);
    assert.strictEqual(scope.metrics.assigned, 5);
    assert.strictEqual(scope.spotsLabel(need30), '1 of 3 spots filled');

    await settle();
    assert.strictEqual(need30.assignments.length, 2, 'a failed delete restores the row');
    assert.strictEqual(need30.assignments[0], ada, 'the row returns at its original index');
    assert.strictEqual(scope.metrics.assigned, 6);
    assert.strictEqual(scope.workflow.saveError, true);
    assert.ok(alerts.length > alertsBefore, 'the failure is announced');

    // A successful delete keeps the row out.
    const grace = need30.assignments[1];
    scope.removeVolunteer(grace);
    confirmations.shift().callback();
    await settle();
    assert.strictEqual(need30.assignments.length, 1);
    assert.deepStrictEqual(need30.assignments.map((row) => row.id), [900]);
    assert.strictEqual(scope.metrics.assigned, 5);
    assert.strictEqual(scope.workflow.saveError, false);
  }

  // -- moveTo(): guards, optimistic move, revert on failure ------------------------

  {
    const {scope, api} = buildAssign(baseState());
    await settle();
    const need30 = shiftById(scope, 30);
    const need31 = shiftById(scope, 31);
    const need34 = shiftById(scope, 34);
    const ada = need30.assignments[0];
    const alertsBefore = alerts.length;

    // A full target is refused before any API call.
    await scope.moveTo(ada, need31).catch(() => 'blocked');
    assert.ok(alerts.some((alert, index) => index >= alertsBefore && alert.title === 'No open spots'),
      'moving into a full shift warns');
    assert.strictEqual(api.calls.length, 0);
    assert.strictEqual(need30.assignments.length, 2, 'nothing moved');

    // Same-need and missing targets are quiet no-ops.
    await scope.moveTo(ada, need30);
    await scope.moveTo(ada, null);
    assert.strictEqual(api.calls.length, 0);

    // Moving to the unlimited need: optimistic list moves first.
    const moved = scope.moveTo(ada, need34);
    assert.strictEqual(ada.volunteer_need_id, 34);
    assert.strictEqual(need30.assignments.indexOf(ada), -1);
    assert.strictEqual(need34.assignments.indexOf(ada), 0);
    assert.strictEqual(scope.metrics.assigned, 5,
      'an assignment moved to an uncounted need leaves the counted totals');
    let call = api.calls[api.calls.length - 1];
    assert.strictEqual(call.action, 'update');
    deepEqual(call.params.where, [['id', '=', 900]]);
    deepEqual(call.params.values, {
      volunteer_need_id: 34,
      status_id: 2,
      activity_date_time: '2026-08-25 09:00:00',
      time_scheduled_minutes: null,
      volunteer_role_id: 3,
    }, 'the move writes the target need values');
    await moved;
    assert.strictEqual(need34.assignments.indexOf(ada), 0, 'a successful move stays');

    // A failed move puts everything back.
    const assignedBeforeFailedMove = scope.metrics.assigned;
    const grace = need30.assignments[0];
    api.responses.push({error: {error_message: 'nope'}});
    await scope.moveTo(grace, need34);
    assert.strictEqual(grace.volunteer_need_id, 30, 'a failed move restores the source need');
    deepEqual(need30.assignments.map((row) => row.id), [901],
      'the reverted row returns to its source list');

    // need34 still holds ada from the successful move; grace was reverted out.
    assert.strictEqual(scope.metrics.assigned, assignedBeforeFailedMove,
      'the metrics are restored to the pre-move values after the revert');
    assert.strictEqual(scope.workflow.saveError, true);
  }

  console.log('Volunteer assignment metrics behavior checks passed.');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
