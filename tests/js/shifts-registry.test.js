'use strict';

// Behavioral checks for the VolunteerShifts controller in ang/volunteer/Shifts.js:
// the API values written per schedule mode, the session need registry the
// close-dialog consumers read, the per-row save queue, duplication, the custom
// date range and the continue/close gates. The volShiftFilters factory half of
// the file is covered by shift-filter.test.js.

const assert = require('assert');
const {
  makeAngular, makeUnderscore, makeCRM, makeQ, makeApi, settle, deepEqual, loadInNewContext,
} = require('./harness');

const {angular, registry} = makeAngular();
const underscore = makeUnderscore();
const {CRM, alerts, confirmations} = makeCRM({_: underscore});
loadInNewContext('ang/volunteer/Shifts.js', {angular, CRM, _: underscore});
const controllers = registry.controllers;

const shiftFilters = registry.factories.volShiftFilters();
assert.strictEqual(typeof controllers.VolunteerShifts, 'function');

function makeWorkflowStub(context) {
  const navigations = [];
  return {
    navigations,
    loadContext: () => Promise.resolve(Object.assign({
      project: {id: 42, title: 'Harvest Festival', is_active: 1},
      needs: [], assignments: [], beneficiaryNames: [], filledByNeed: {},
      summary: {filled: 0, total: 0}, supporting: {workflow: {}},
    }, context)),
    getSupportingData: () => Promise.resolve({
      workflow: {
        roles: [{id: '3', label: 'Greeter'}],
        visibility: {public: '1', admin: '2'},
        shift_filter_presets: {
          now: '2026-08-19 14:30:00',
          today: {from: '2026-08-19 00:00:00', to: '2026-08-19 23:59:59'},
        },
      },
      project: {},
    }),
    projectPath: (projectId, step) => '/volunteer/manage/' + parseInt(projectId, 10) + '/' + step,
    navigate: (destination) => navigations.push(destination),
    cancel: () => {},
  };
}

function buildRegistry(options = {}) {
  const scope = {
    model: options.model,
    $applyAsync: (fn) => fn(),
    $watch: () => {},
  };
  const api = makeApi();
  const workflow = makeWorkflowStub(options.context);
  controllers.VolunteerShifts(
    scope, {current: {params: {projectId: '42'}}}, {}, makeQ(), api.crmApi4, workflow, shiftFilters
  );
  return {scope, api, workflow};
}

const need = (overrides = {}) => Object.assign({
  id: 7, role_id: '3', quantity: '2', public: true, accepting: true,
  schedule_mode: 'fixed', start_time: '2026-08-25 09:00:00',
  end_time: '2026-08-25 11:00:00', duration: '90',
}, overrides);

const todayAtMidnightString = () => {
  const date = new Date();
  const pad = (value) => (value < 10 ? '0' + value : String(value));
  return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' 00:00:00';
};

const visibleIds = (scope) => scope.visibleNeeds.map((row) => row.id);

(async function() {
  // -- apiValues(): the schedule mode decides which time columns are written ----

  {
    const {scope, api} = buildRegistry();
    await settle();
    assert.strictEqual(scope.projectId, 42);
    deepEqual(scope.visibility, {public: '1', admin: '2'});

    // fixed: a duration shift must not carry an end_time.
    api.responses.push({rows: [{id: 7}]});
    scope.saveNeed(need());
    await settle();
    let call = api.calls[api.calls.length - 1];
    assert.strictEqual(call.entity, 'VolunteerNeed');
    assert.strictEqual(call.action, 'update');
    deepEqual(call.params.where, [['id', '=', 7]]);
    deepEqual(call.params.values, {
      project_id: 42,
      role_id: 3,
      quantity: 2,
      visibility_id: 1,
      is_active: true,
      is_flexible: false,
      start_time: '2026-08-25 09:00:00',
      end_time: null,
      duration: 90,
    }, 'a fixed shift clears end_time and parses its numeric columns');

    // ongoing: no duration, no end_time; a missing start becomes today.
    const before = todayAtMidnightString();
    api.responses.push({rows: [{id: 8}]});
    scope.saveNeed(need({
      id: 8, schedule_mode: 'ongoing', start_time: null, end_time: '2026-12-01 10:00:00',
      duration: 45, public: false, accepting: false,
    }));
    const after = todayAtMidnightString();
    await settle();
    let values = api.calls[api.calls.length - 1].params.values;
    assert.ok(
      values.start_time === before || values.start_time === after,
      'an ongoing shift without a start time starts today at midnight'
    );
    assert.ok(/^\d{4}-\d{2}-\d{2} 00:00:00$/.test(values.start_time));
    assert.strictEqual(values.end_time, null, 'an ongoing shift has no end_time');
    assert.strictEqual(values.duration, null, 'an ongoing shift has no duration');
    assert.strictEqual(values.visibility_id, 2, 'a private shift is stored with the admin visibility');
    assert.strictEqual(values.is_active, false);

    // ongoing with an explicit start keeps it.
    api.responses.push({rows: [{id: 8}]});
    scope.saveNeed(need({id: 8, schedule_mode: 'ongoing', start_time: '2026-09-01 08:30:00'}));
    await settle();
    values = api.calls[api.calls.length - 1].params.values;
    assert.strictEqual(values.start_time, '2026-09-01 08:30:00');

    // window: the end_time is written, not cleared.
    api.responses.push({rows: [{id: 9}]});
    scope.saveNeed(need({id: 9, schedule_mode: 'window', duration: '240'}));
    await settle();
    values = api.calls[api.calls.length - 1].params.values;
    assert.strictEqual(values.end_time, '2026-08-25 11:00:00', 'a window shift keeps its end_time');
    assert.strictEqual(values.duration, 240);

    // empty-string quantity and duration are stored as NULL, not 0.
    api.responses.push({rows: [{id: 10}]});
    scope.saveNeed(need({id: 10, quantity: '', duration: ''}));
    await settle();
    values = api.calls[api.calls.length - 1].params.values;
    assert.strictEqual(values.quantity, null);
    assert.strictEqual(values.duration, null);

    // a need without an id goes through create.
    api.responses.push({rows: [{id: '55'}]});
    scope.saveNeed(need({id: null}));
    await settle();
    call = api.calls[api.calls.length - 1];
    assert.strictEqual(call.action, 'create', 'a need without an id must be created');
    assert.strictEqual(call.params.where, undefined);
  }

  // -- markRegistry(): the session registry the close-dialog consumers read ----

  {
    const registryState = {clean: [], created: [], updated: [], deleted: []};
    const {scope, api} = buildRegistry({model: {projectId: 42, needRegistry: registryState}});
    await settle();

    // created stays created, even after further edits.
    api.responses.push({rows: [{id: '55'}]});
    const fresh = need({id: null});
    scope.saveNeed(fresh);
    await settle();
    assert.strictEqual(fresh.id, 55);
    deepEqual(registryState.created, [55]);
    api.responses.push({rows: [{id: 55}]});
    scope.saveNeed(fresh);
    await settle();
    deepEqual(registryState.created, [55], 'a session-created need stays reported as created');
    deepEqual(registryState.updated, [], 're-editing a created need must not move it to updated');

    // clean moves to updated.
    const edited = {clean: [7], created: [], updated: [], deleted: []};
    const model = {projectId: 42, needRegistry: edited};
    const second = buildRegistry({model});
    await settle();
    second.api.responses.push({rows: [{id: 7}]});
    second.scope.saveNeed(need({id: 7}));
    await settle();
    deepEqual(edited.clean, [], 'an edited pre-existing need leaves the clean list');
    deepEqual(edited.updated, [7]);

    // deleted: membership moves to deleted after a confirmed delete.
    const context = {
      needs: [
        {id: 7, role_id: '3', quantity: '2', is_active: 1, is_flexible: 0, visibility_id: 1,
          start_time: '2026-01-10 09:00:00', duration: 60, end_time: null},
      ],
      assignments: [{volunteer_need_id: 7}, {volunteer_need_id: 7}],
    };
    const third = buildRegistry({model: {projectId: 42, needRegistry: registryState}, context});
    await settle();
    const doomed = third.scope.needs[0];
    assert.strictEqual(doomed.assignment_count, 2);
    third.scope.deleteNeed(doomed);
    assert.strictEqual(confirmations.length, 1, 'deleting a stored need asks for confirmation');
    assert.ok(
      confirmations[0].options.message.indexOf('2 assignment') >= 0,
      'the confirm message counts the shift assignments'
    );
    third.api.responses.push({rows: []});
    confirmations.shift().callback();
    await settle();
    deepEqual(third.scope.needs, [], 'the deleted need leaves the list');
    deepEqual(visibleIds(third.scope), []);
    deepEqual(registryState.deleted, [7]);
    assert.strictEqual(
      [registryState.clean, registryState.created, registryState.updated]
        .filter((list) => list.indexOf(7) >= 0).length,
      0,
      'a deleted id belongs to the deleted list only'
    );

    // an unsaved need is dropped locally without a confirmation or an API call.
    const apiCallsBefore = third.api.calls.length;
    const draft = need({id: null, _clientId: 'new-9'});
    third.scope.needs.push(draft);
    third.scope.deleteNeed(draft);
    assert.strictEqual(confirmations.length, 0);
    assert.strictEqual(third.api.calls.length, apiCallsBefore);
    deepEqual(third.scope.needs, []);
  }

  // -- _saveQueue: a slower earlier autosave must not overwrite a newer edit ----

  {
    const {scope, api} = buildRegistry();
    await settle();
    const row = need({id: 12, quantity: '1', duration: '60', end_time: null});

    const first = {};
    api.responses.push({deferred: first});
    scope.saveNeed(row);
    await settle();
    assert.strictEqual(api.calls.length, 1);
    assert.strictEqual(api.calls[0].params.values.duration, 60);
    assert.strictEqual(row.saveState, 'saving');
    assert.strictEqual(scope.workflow.pending, true);

    row.duration = '120';
    scope.saveNeed(row);
    assert.strictEqual(
      api.calls.length, 1, 'a queued save must wait for the in-flight request'
    );

    first.resolve([{id: 12}]);
    await settle();
    assert.strictEqual(api.calls.length, 2);
    assert.strictEqual(
      api.calls[1].params.values.duration, 120,
      'once the slower earlier save lands, the newer edit is what gets written'
    );
    assert.strictEqual(row.saveState, 'saved');
    assert.strictEqual(scope.workflow.pending, false);

    // A failed save does not strand the queue: the next edit still goes out.
    const failing = {};
    api.responses.push({deferred: failing});
    scope.saveNeed(row);
    await settle();
    row.duration = '180';
    scope.saveNeed(row);
    const alertsBefore = alerts.length;
    failing.reject({error_message: 'offline'});
    await settle();
    assert.strictEqual(api.calls[api.calls.length - 1].params.values.duration, 180);
    assert.strictEqual(row.saveState, 'saved');
    // The error flag is only reset when a new save *starts*, so a save that
    // was queued before the failure (and succeeds afterwards) does not clear
    // it; starting one more save does.
    assert.strictEqual(scope.workflow.saveError, true, 'the earlier failure keeps the error flag set');
    scope.saveNeed(row);
    await settle();
    assert.strictEqual(scope.workflow.saveError, false, 'starting a new save resets the error flag');
    assert.strictEqual(row.saveState, 'saved');
    assert.ok(alerts.length > alertsBefore, 'the failed save is announced');
  }

  // -- duplicateNeed(): the copy starts a life of its own -----------------------

  {
    const {scope, api} = buildRegistry();
    await settle();
    const source = need({id: 20, assignment_count: 3, saveState: 'saved'});

    // Block the source's own save queue; the duplicate must not queue behind it.
    const blocker = {};
    api.responses.push({deferred: blocker});
    scope.saveNeed(source);
    await settle();
    const callsAfterSourceSave = api.calls.length;

    api.responses.push({rows: [{id: '77'}]});
    scope.duplicateNeed(source);
    await settle();
    assert.strictEqual(
      api.calls.length, callsAfterSourceSave + 1,
      'the duplicate must not inherit the source save queue'
    );
    const createCall = api.calls[api.calls.length - 1];
    assert.strictEqual(createCall.action, 'create', 'dropping the id routes the duplicate through create');
    assert.strictEqual(createCall.params.values.role_id, 3);
    await settle();

    const duplicate = scope.needs[scope.needs.length - 1];
    assert.notStrictEqual(duplicate, source);
    assert.strictEqual(duplicate.id, 77);
    assert.strictEqual(duplicate.assignment_count, 0, 'the duplicate starts with its counts reset');
    assert.strictEqual(duplicate.saveState, 'saved');
    assert.ok(duplicate._clientId.indexOf('copy-') === 0, 'the duplicate gets its own client id');
    assert.strictEqual(source.assignment_count, 3, 'the source keeps its counts');
    assert.strictEqual(source.id, 20);

    blocker.resolve([{id: 20}]);
    await settle();
  }

  // -- applyCustomShiftRange(): empty inputs mean "all dates" -------------------

  {
    const context = {
      needs: [
        {id: 40, role_id: '3', quantity: '1', is_active: 1, is_flexible: 0, visibility_id: 1,
          start_time: '2026-01-10 09:00:00', duration: 60, end_time: null},
        {id: 41, role_id: '3', quantity: '1', is_active: 1, is_flexible: 0, visibility_id: 1,
          start_time: '2026-08-25 09:00:00', duration: 60, end_time: null},
      ],
      assignments: [],
    };
    const {scope} = buildRegistry({context});
    await settle();

    // Default filter is "upcoming", seeded from the server preset.
    assert.strictEqual(scope.ui.appliedShiftFilters.dateScope, 'upcoming');
    deepEqual(visibleIds(scope), [41], 'a past shift is hidden by the upcoming preset');

    // An invalid custom form shows the error and changes nothing.
    scope.applyCustomShiftRange({$invalid: true});
    assert.strictEqual(scope.ui.shiftFilterError, true);
    assert.strictEqual(scope.ui.appliedShiftFilters.dateScope, 'upcoming');

    // Both dates empty: the scope becomes "all".
    scope.applyCustomShiftRange({$invalid: false});
    assert.strictEqual(scope.ui.shiftFilterError, false);
    assert.strictEqual(scope.ui.shiftFilters.dateScope, 'all');
    assert.strictEqual(scope.ui.appliedShiftFilters.dateScope, 'all');
    deepEqual(visibleIds(scope), [40, 41], 'the all scope shows every shift');

    // A one-sided custom range keeps the custom scope.
    scope.ui.shiftFilters.dateFrom = '2026-08-19';
    scope.ui.shiftFilters.dateTo = '';
    scope.applyCustomShiftRange({});
    assert.strictEqual(scope.ui.shiftFilters.dateScope, 'custom');
    deepEqual(visibleIds(scope), [41]);

    // A reversed range is rejected and keeps the previous applied filters.
    scope.ui.shiftFilters.dateFrom = '2026-08-20';
    scope.ui.shiftFilters.dateTo = '2026-08-19';
    scope.applyCustomShiftRange({});
    assert.strictEqual(scope.ui.shiftFilterError, true);
    deepEqual(visibleIds(scope), [41], 'a rejected range keeps the visible list');
  }

  // -- waitForSaves(): the continue/close gates ---------------------------------

  {
    const context = {
      needs: [
        {id: 30, role_id: '3', quantity: '1', is_active: 1, is_flexible: 0, visibility_id: 1,
          start_time: '2026-08-25 09:00:00', duration: 60, end_time: null},
      ],
      assignments: [],
    };
    const {scope, api, workflow} = buildRegistry({context});
    await settle();
    let closed = 0;
    scope.volunteerWorkflowDialog = {close: () => { closed += 1; }};

    // A save error blocks navigation.
    scope.workflow.saveError = true;
    scope.continueToAssign();
    await settle();
    deepEqual(workflow.navigations, [], 'a save error blocks continuing');

    // A shift without a role blocks navigation and warns.
    scope.workflow.saveError = false;
    scope.addShift();
    assert.strictEqual(scope.needs.length, 2);
    const alertsBefore = alerts.length;
    scope.continueToAssign();
    await settle();
    deepEqual(workflow.navigations, [], 'an incomplete shift blocks continuing');
    assert.ok(alerts.some(
      (alert) => alert.title === 'Shift incomplete' && alerts.indexOf(alert) >= alertsBefore
    ));
    scope.closeWorkflowDialog();
    await settle();
    assert.strictEqual(closed, 0, 'an incomplete shift also blocks closing the dialog');
    scope.deleteNeed(scope.needs[1]);
    assert.strictEqual(scope.needs.length, 1);

    // Navigation waits for the in-flight save, then goes to the assign step.
    const inFlight = {};
    api.responses.push({deferred: inFlight});
    scope.saveNeed(scope.needs[0]);
    scope.continueToAssign();
    await settle();
    deepEqual(workflow.navigations, [], 'navigation waits for pending saves');
    inFlight.resolve([{id: 30}]);
    await settle();
    deepEqual(workflow.navigations, ['/volunteer/manage/42/assign']);

    scope.closeWorkflowDialog();
    await settle();
    assert.strictEqual(closed, 1);
  }

  console.log('Volunteer shifts registry behavior checks passed.');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
