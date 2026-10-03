'use strict';

// Behavioral checks for the VolunteerProjects controller in
// ang/volunteer/Projects.js: the visibleProjects() filter chain, the keyed
// beneficiary_options merge in applyOverview() (a positional zip would
// mislabel beneficiaries whenever one name fails to resolve), the
// associatedEntityTitle() fallback, and localDate() site-value parsing.

const assert = require('assert');
const {
  makeAngular, makeUnderscore, makeCRM, makeQ, makeApi, settle, deepEqual, makeDateFilter, loadInNewContext,
} = require('./harness');

const {angular, registry} = makeAngular();
const underscore = makeUnderscore();
const {CRM} = makeCRM({_: underscore});
loadInNewContext('ang/volunteer/Projects.js', {angular, CRM, _: underscore});
const controllers = registry.controllers;
assert.strictEqual(typeof controllers.VolunteerProjects, 'function');

const overview = () => ({
  summary: {active_projects: 2},
  projects: [
    {
      id: 1, title: 'Spring Fair', is_active: 1, campaign_id: 11, campaign_label: 'Spring Drive',
      beneficiaries: [101, 102], beneficiary_names: ['Ada Lovelace', 'Grace Hopper'],
      beneficiary_options: {101: 'Ada Lovelace', 102: 'Grace Hopper'},
      upcoming_roles: ['Greeter'],
      associated_entity: {entity_id: 5, entity_table: 'civicrm_event', title: 'City Marathon'},
    },
    {
      id: 2, title: 'River Cleanup', is_active: 0, campaign_id: '',
      beneficiaries: [], beneficiary_names: [], beneficiary_options: {},
      upcoming_roles: [],
    },
    {
      id: 3, title: 'Library Night', is_active: 1, campaign_id: 12, campaign_label: 'Autumn Drive',
      beneficiaries: [102], beneficiary_names: ['Grace Hopper'],
      beneficiary_options: {102: 'Grace Hopper'},
      upcoming_roles: ['Shelver'],
      associated_entity: {entity_id: 9, entity_table: 'civicrm_event', title: null},
    },
  ],
  attention: [], up_next: [], this_week: [],
});

function buildProjects(options = {}) {
  const scope = {$watch: () => {}, $applyAsync: (fn) => fn()};
  const api = makeApi();
  const filterService = makeDateFilter();
  const viewParams = options.viewParam ? {view: options.viewParam} : {};
  const location = {
    search(query, value) {
      if (arguments.length === 0) { return viewParams; }
      viewParams[query] = value;
      return location;
    },
  };
  const opens = [];
  const dialog = {
    open: (step, project, settings) => {
      opens.push({step, projectId: project && project.id, settings});
      return Promise.resolve('closed');
    },
  };
  controllers.VolunteerProjects(
    scope, filterService.filter, makeQ(), api.crmApi4, (messages, promise) => promise,
    options.overview || overview(), location, dialog
  );
  return {scope, api, location, opens, filterCalls: filterService.calls};
}

const visibleIds = (scope) => scope.visibleProjects().map((project) => project.id);

(async function() {
  // -- construction: keyed beneficiary merge and view default -------------------

  {
    const {scope, opens} = buildProjects();
    // beneficiary_options is keyed by contact ID by the API; entries from all
    // projects merge into one keyed map, never a positional zip.
    deepEqual(scope.beneficiaries, {101: 'Ada Lovelace', 102: 'Grace Hopper'},
      'beneficiary labels are merged by contact id across projects');
    assert.strictEqual(scope.view, 'list', 'the default view is the list');

    // The associated entity title falls back to the event id when the title
    // is gone (a deleted event), and reads null without an entity at all.
    assert.strictEqual(scope.associatedEntityTitle(scope.projects[0]), 'City Marathon');
    assert.strictEqual(scope.associatedEntityTitle(scope.projects[2]), 'Event #9',
      'a deleted event still shows a stable label with its id');
    assert.strictEqual(scope.associatedEntityTitle(scope.projects[1]), null);
    assert.strictEqual(scope.associatedEntityTitle(null), null);
    assert.deepStrictEqual(opens, [], 'nothing opens a dialog at construction');
  }

  // -- visibleProjects(): the filter chain ----------------------------------------

  {
    const {scope} = buildProjects();

    // Status: active by default, archived flips it.
    deepEqual(visibleIds(scope), [1, 3]);
    scope.filters.status = 'archived';
    deepEqual(visibleIds(scope), [2]);
    scope.filters.status = 'active';

    // Campaign match.
    scope.filters.campaign_id = '11';
    deepEqual(visibleIds(scope), [1]);
    scope.filters.campaign_id = '12';
    deepEqual(visibleIds(scope), [3]);
    scope.filters.campaign_id = '';

    // Beneficiary membership (numeric id against the filter's string value).
    scope.filters.beneficiary = '102';
    deepEqual(visibleIds(scope), [1, 3]);
    scope.filters.beneficiary = '101';
    deepEqual(visibleIds(scope), [1]);
    scope.filters.beneficiary = '';

    // Search over title, campaign label, associated entity title,
    // beneficiary names and upcoming roles; case-insensitive.
    scope.filters.search = 'marathon';
    deepEqual(visibleIds(scope), [1], 'the associated entity title is searched');
    scope.filters.search = 'SPRING';
    deepEqual(visibleIds(scope), [1], 'the title is searched case-insensitively');
    scope.filters.search = 'spring drive';
    deepEqual(visibleIds(scope), [1], 'the campaign label is searched');
    scope.filters.search = 'hopper';
    deepEqual(visibleIds(scope), [1, 3], 'beneficiary names are searched');
    scope.filters.search = 'shelver';
    deepEqual(visibleIds(scope), [3], 'upcoming roles are searched');
    scope.filters.search = 'zzz';
    deepEqual(visibleIds(scope), [], 'a miss hides everything');

    // Combined: archived + text.
    scope.filters.search = 'river';
    scope.filters.status = 'archived';
    deepEqual(visibleIds(scope), [2]);
  }

  // -- applyOverview() re-runs through refreshProjects -----------------------------

  {
    const {scope, api, opens} = buildProjects();
    scope.ui.batchAction = 'archive';
    scope.ui.allSelected = true;

    const second = {
      summary: {active_projects: 1},
      projects: [{
        id: 9, title: 'New Project', is_active: 1, campaign_id: '',
        beneficiaries: [201], beneficiary_names: ['New Beneficiary'],
        beneficiary_options: {201: 'New Beneficiary'},
      }],
      attention: [], up_next: [], this_week: [],
    };
    api.responses.push({rows: [second]});
    await scope.openProjectDialog('assign', scope.projectById(1), 7);

    deepEqual(opens, [{step: 'assign', projectId: 1, settings: {scopeNeedId: 7}}],
      'the dialog opens for the requested step and project');
    deepEqual(scope.projects.map((project) => project.id), [9],
      'a closed dialog refreshes the overview');
    deepEqual(scope.beneficiaries, {201: 'New Beneficiary'},
      'the beneficiary map is rebuilt from the new overview');
    assert.strictEqual(scope.ui.batchAction, '', 'batch state resets on refresh');
    assert.strictEqual(scope.ui.allSelected, false);
    deepEqual(visibleIds(scope), [9]);
  }

  // -- localDate(): site-local wall time, empty values ------------------------------

  {
    const {scope, filterCalls} = buildProjects();
    assert.strictEqual(scope.weekDay('2026-08-25T14:30:00'), 'Tue 25');
    assert.strictEqual(scope.shortTime('2026-08-25 14:30:00'), '2:30 PM');
    assert.strictEqual(scope.shortDateTime('2026-08-25 14:30:00'), 'Tue Aug 25, 2:30 PM');

    // The parsed date uses the site-local components, not UTC reinterpretation.
    const parsed = filterCalls[filterCalls.length - 1];
    assert.strictEqual(parsed.date.getTime(), new Date(2026, 7, 25, 14, 30).getTime());

    // A value outside the site format falls back to Date parsing.
    assert.strictEqual(scope.shortTime('2026/08/25 14:30'), '2:30 PM');

    // Empty values render the placeholders, never an invalid date.
    assert.strictEqual(scope.shortDateTime(''), 'No upcoming shift');
    assert.strictEqual(scope.shortDateTime(null), 'No upcoming shift');
    assert.strictEqual(scope.weekDay(''), '');
  }

  console.log('Volunteer projects filter behavior checks passed.');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
