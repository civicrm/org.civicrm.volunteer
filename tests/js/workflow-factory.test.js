'use strict';

// Behavioral checks for the volWorkflow factory in ang/volunteer/Workflow.js:
// summarize() capacity totals, projectPath() normalization, setActive()
// optimistic flip and revert, the consumeAppNavigation() one-shot flag, the
// cancel() branches, and the single-request loadContext() bundle.
//
// summarize() counts a need only when it has a finite positive quantity and
// is neither flexible nor inactive — the same rule as Assign.js isCounted()
// and the server-side VolunteerAssignment.getCapacity action; the three must
// move together.

const assert = require('assert');
const {
  makeAngular, makeUnderscore, makeCRM, makeQ, makeApi, settle, deepEqual, loadInNewContext,
} = require('./harness');

const {angular, registry} = makeAngular();
const underscore = makeUnderscore();
const {CRM, confirmations, bodyTriggers} = makeCRM({_: underscore});
loadInNewContext('ang/volunteer/Workflow.js', {angular, CRM, _: underscore});
const factories = registry.factories;
assert.strictEqual(typeof factories.volWorkflow, 'function');

function buildWorkflow() {
  const paths = [];
  const searches = [];
  const location = {
    path(destination) { paths.push(destination); return location; },
    search(params) { searches.push(params); return location; },
  };
  const state = {reloads: 0, opened: []};
  const route = {reload: () => { state.reloads += 1; }, current: {params: {}}};
  const window = {open: (url) => state.opened.push(url)};
  const api = makeApi();
  const volWorkflow = factories.volWorkflow(
    makeQ(), location, route, window, api.crmApi4, (messages, promise) => promise
  );
  return {volWorkflow, api, paths, searches, state};
}

(async function() {
  // -- summarize(): capacity totals over counted needs -----------------------------

  {
    const {volWorkflow} = buildWorkflow();
    const needs = [
      {id: 1, quantity: 5, is_flexible: 1, is_active: 1}, // flexible: excluded
      {id: 2, quantity: 4, is_flexible: 0, is_active: 0}, // inactive: excluded
      {id: 3, quantity: 0, is_flexible: 0, is_active: 1}, // zero quantity: excluded
      {id: 4, quantity: null, is_flexible: 0, is_active: 1}, // no quantity: excluded
      {id: 5, quantity: '2', is_flexible: 0, is_active: 1},
      {id: 6, quantity: 3, is_flexible: '0', is_active: '1'},
    ];
    const assignments = [
      {volunteer_need_id: 5}, {volunteer_need_id: 5},
      {volunteer_need_id: 1}, // belongs to an uncounted need
      {volunteer_need_id: 2}, // belongs to an uncounted need
      {volunteer_need_id: 99}, // unknown need
      {volunteer_need_id: 6},
    ];
    deepEqual(volWorkflow.summarize(needs, assignments), {filled: 3, total: 5},
      'only finite-quantity active scheduled needs count toward totals');
    deepEqual(volWorkflow.summarize([], []), {filled: 0, total: 0});
  }

  // -- projectPath(): the project id is normalized ---------------------------------

  {
    const {volWorkflow} = buildWorkflow();
    assert.strictEqual(volWorkflow.projectPath('42', 'assign'), '/volunteer/manage/42/assign');
    assert.strictEqual(volWorkflow.projectPath(42), '/volunteer/manage/42/details',
      'a missing step defaults to details');
    assert.strictEqual(volWorkflow.projectPath('042', 'report'), '/volunteer/manage/42/report',
      'a padded id is parsed to a number');
  }

  // -- setActive(): non-boolean no-op, optimistic flip, revert on failure -----------

  {
    const {volWorkflow, api} = buildWorkflow();

    // Angular's <select> commits undefined before the project loads; only a
    // real boolean is treated as a choice.
    const untouched = {project: {id: 42, is_active: true}};
    await volWorkflow.setActive(untouched, undefined);
    await volWorkflow.setActive(untouched, null);
    assert.strictEqual(untouched.project.is_active, true, 'non-boolean values change nothing');
    assert.strictEqual(api.calls.length, 0);

    // Before the project has an id, the flip is local only.
    const local = {project: {id: null, is_active: false}};
    await volWorkflow.setActive(local, true);
    assert.strictEqual(local.project.is_active, true);
    assert.strictEqual(api.calls.length, 0, 'no project id means no write');

    // With a project id the flip is optimistic, then saved.
    const saved = {project: {id: 42, is_active: false}};
    const saving = volWorkflow.setActive(saved, true);
    assert.strictEqual(saved.project.is_active, true, 'the flip is immediate');
    assert.strictEqual(saved.statusSaving, true);
    await saving;
    assert.strictEqual(saved.project.is_active, true);
    assert.strictEqual(saved.statusSaving, false);
    assert.strictEqual(api.calls.length, 1);
    assert.strictEqual(api.calls[0].entity, 'VolunteerProject');
    assert.strictEqual(api.calls[0].action, 'update');
    deepEqual(api.calls[0].params.where, [['id', '=', 42]]);
    deepEqual(api.calls[0].params.values, {is_active: true});

    // A failed save restores the previous value.
    api.responses.push({error: {error_message: 'nope'}});
    const failed = {project: {id: 43, is_active: true}};
    await volWorkflow.setActive(failed, false).catch((error) => error);
    assert.strictEqual(failed.project.is_active, true, 'failure restores the previous value');
    assert.strictEqual(failed.statusSaving, false);
  }

  // -- navigate() / consumeAppNavigation(): the one-shot flag ------------------------

  {
    const {volWorkflow, paths, searches} = buildWorkflow();
    assert.strictEqual(volWorkflow.consumeAppNavigation(), false,
      'no navigation has happened yet');

    volWorkflow.navigate('/volunteer/manage/42/assign', {needId: 30});
    deepEqual(paths, ['/volunteer/manage/42/assign']);
    deepEqual(searches, [{needId: 30}]);
    assert.strictEqual(volWorkflow.consumeAppNavigation(), true,
      'a navigation the app initiated sets the flag');
    assert.strictEqual(volWorkflow.consumeAppNavigation(), false, 'the flag is one-shot');

    volWorkflow.navigate('/volunteer/manage');
    deepEqual(searches[1], {}, 'navigate without a search clears the query');
  }

  // -- cancel(): the three branches --------------------------------------------------

  {
    // A clean standalone context navigates back to the project list.
    const clean = buildWorkflow();
    clean.volWorkflow.cancel({isDirty: () => false, formContext: 'standAlone'});
    deepEqual(clean.paths, ['/volunteer/manage']);

    // An undefined context is tolerated.
    const bare = buildWorkflow();
    bare.volWorkflow.cancel(undefined);
    deepEqual(bare.paths, ['/volunteer/manage']);

    // A dirty context waits for the discard confirmation before navigating.
    const dirty = buildWorkflow();
    dirty.volWorkflow.cancel({isDirty: () => true, formContext: 'standAlone'});
    deepEqual(dirty.paths, [], 'a dirty context must not navigate before confirmation');
    assert.strictEqual(confirmations.length, 1);
    assert.strictEqual(confirmations[0].options.title, 'Discard unsaved changes?');
    confirmations.shift().callback();
    await settle();
    deepEqual(dirty.paths, ['/volunteer/manage']);

    // An event-tab context has nowhere to navigate: it refreshes in place.
    const eventTab = buildWorkflow();
    const pristines = [];
    eventTab.volWorkflow.cancel({
      formContext: 'eventTab',
      isDirty: () => false,
      markPristine: () => pristines.push(true),
    });
    deepEqual(eventTab.paths, [], 'the event tab never navigates away');
    deepEqual(pristines, [true], 'the event tab is marked pristine');
    assert.strictEqual(eventTab.state.reloads, 1, 'the route is reloaded to re-read the event');
    deepEqual(bodyTriggers[bodyTriggers.length - 1], {selector: 'body', event: 'volunteerProjectCancel'},
      'the cancel event is still announced for listeners');
  }

  // -- loadContext(): one bundled request, unpacked for the steps ---------------------

  {
    const {volWorkflow, api} = buildWorkflow();
    api.responses.push({rows: [{
      project: {id: 42, title: 'Harvest Festival', is_active: '1'},
      needs: [{id: 5, quantity: '2', is_flexible: 0, is_active: 1}],
      assignments: [{id: 900, volunteer_need_id: 5}],
      capacity: {project_id: 42, filled: 1, total: 2, by_need: {5: 1}},
      supporting: {workflow: {roles: [{id: 3, label: 'Greeter'}]}, project: {phone_types: {}}},
      beneficiary_names: ['Friends of the Festival'],
    }]});
    const context = await volWorkflow.loadContext('42');

    assert.strictEqual(api.calls.length, 1, 'a step change costs one request');
    assert.strictEqual(api.calls[0].entity, 'VolunteerProject');
    assert.strictEqual(api.calls[0].action, 'getWorkflowContext');
    deepEqual(api.calls[0].params, {projectId: 42}, 'the project id is sent as a number');

    assert.strictEqual(context.project.is_active, true, 'the project status is a real boolean');
    deepEqual(context.summary, {filled: 1, total: 2}, 'the header summary comes from the capacity summary');
    deepEqual(context.filledByNeed, {5: 1});
    deepEqual(context.beneficiaryNames, ['Friends of the Festival']);
    assert.ok(!('beneficiary_names' in context), 'the wire name is not left on the context');
    deepEqual(context.supporting.workflow.roles, [{id: 3, label: 'Greeter'}]);

    // The bundle seeds the shared supporting-data cache, so a step that also
    // asks for it does not issue a second request.
    const supporting = await volWorkflow.getSupportingData();
    assert.strictEqual(api.calls.length, 1, 'supporting data is served from the bundle');
    deepEqual(supporting.project, {phone_types: {}});

    // Without a capacity summary the header falls back to counting rows.
    api.responses.push({rows: [{
      project: {id: 42, title: 'Harvest Festival', is_active: 0},
      needs: [{id: 5, quantity: '2', is_flexible: 0, is_active: 1}],
      assignments: [{id: 900, volunteer_need_id: 5}, {id: 901, volunteer_need_id: 5}],
      capacity: null,
      supporting: {workflow: {}, project: {}},
      beneficiary_names: [],
    }]});
    const counted = await volWorkflow.loadContext(42);
    assert.strictEqual(counted.project.is_active, false);
    deepEqual(counted.summary, {filled: 2, total: 2}, 'summarize() stands in for a missing capacity summary');
    deepEqual(counted.filledByNeed, {});

    // An empty result is the not-found case the steps already handle.
    api.responses.push({rows: []});
    const missing = await volWorkflow.loadContext(99).then(() => null, (error) => error);
    assert.strictEqual(missing.error_message, 'The volunteer project does not exist.');
  }

  console.log('Volunteer workflow factory behavior checks passed.');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
