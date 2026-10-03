'use strict';

// Behavioural checks for the VolunteerRoster controller (ang/volunteer/Roster.js).
// csvCell is a closure, so the CSV-injection guard is exercised end-to-end
// through exportCsv()'s output; applyFilters() and the grouping sort run
// through $scope.filterRows().
//
// The source executes in this context rather than a new vm context: Roster.js
// builds its group objects inside the vm, and assert.deepStrictEqual refuses
// objects whose prototypes come from another realm.

const assert = require('assert');
const {makeAngular, makeUnderscore, makeCRM, resolved, loadInThisContext} = require('./harness');

const {angular, registry} = makeAngular();
const {CRM} = makeCRM({_: makeUnderscore()});

// exportCsv() news up a Blob and drives an <a download> through $window;
// capture both instead of needing a DOM.
const blobs = [];
class FakeBlob {
  constructor(parts, options) {
    this.parts = parts;
    this.type = options && options.type;
    blobs.push(this);
  }
}

const restoreGlobals = loadInThisContext('ang/volunteer/Roster.js', {angular, CRM, Blob: FakeBlob});
const controller = registry.controllers.VolunteerRoster;
assert.strictEqual(typeof controller, 'function');

const rosterRows = [
  {
    id: 1,
    assignee_display_name: '=SUM(A1:A9)',
    assignee_email: 'jane@example.org',
    assignee_phone: '555-0100',
    role_label: 'Greeter',
    display_time: 'Sat 9:00 AM',
    start_time: '2026-08-22 09:00:00',
    status_name: 'completed',
    status_label: 'Attended',
  },
  {
    id: 2,
    assignee_display_name: 'Doe, Jane',
    assignee_email: 'JANE2@EXAMPLE.ORG',
    assignee_phone: '+1 555-0101',
    role_label: 'Usher',
    display_time: 'Sat 11:00 AM',
    start_time: '2026-08-22 11:00:00',
    status_name: 'available',
    status_label: 'Available',
  },
  {
    id: 3,
    assignee_display_name: 'He said "hi"',
    assignee_email: undefined,
    assignee_phone: null,
    role_label: '-coach',
    display_time: '',
    start_time: '',
    status_name: 'completed',
    status_label: '@import',
  },
];

const apiCalls = [];
const crmApi4 = (entity, action, params) => {
  apiCalls.push({entity, action, params});
  const responses = {
    'VolunteerAssignment.getRoster': [{project_title: 'River cleanup', rows: rosterRows}],
  };
  return resolved(responses[entity + '.' + action] !== undefined ? responses[entity + '.' + action] : []);
};

const volWorkflow = {
  loadContext: () => resolved({
    project: {id: 7, title: 'River cleanup', is_active: true},
    summary: {filled: 2, total: 3},
    beneficiaryNames: ['Friends of the River'],
    supporting: {workflow: {can_send_email: true, email_activity_type_id: 5, sms: {enabled: false, activity_type_id: null}}},
  }),
  confirmDiscard: () => resolved(),
  cancel: () => resolved(),
};

let objectUrlBlob = null;
let createdLink = null;
let linkClicked = false;
let linkRemoved = false;
let linkAppended = false;
let urlRevoked = false;
const windowStub = {
  print() {},
  URL: {
    createObjectURL(blob) { objectUrlBlob = blob; return 'blob:mock-url'; },
    revokeObjectURL() { urlRevoked = true; },
  },
  document: {
    createElement(tag) {
      createdLink = {
        tag: tag,
        click() { linkClicked = true; },
        remove() { linkRemoved = true; },
      };
      return createdLink;
    },
    body: {appendChild() { linkAppended = true; }},
  },
};

const scope = {$on() {}};
controller(
  scope,
  {current: {params: {projectId: '7'}}},
  windowStub,
  crmApi4,
  volWorkflow
);

// --- Construction drove the initial load; the status list is deduped and
// --- sorted by label.
assert.deepStrictEqual(apiCalls[0], {
  entity: 'VolunteerAssignment',
  action: 'getRoster',
  params: {projectId: 7, includePast: false},
});
assert.strictEqual(scope.projectId, 7);
assert.strictEqual(scope.loading, false);
assert.strictEqual(scope.workflow.project.title, 'River cleanup');
assert.deepStrictEqual(scope.statuses, [
  {name: 'completed', label: 'Attended'},
  {name: 'available', label: 'Available'},
]);
assert.strictEqual(scope.canSendEmail(), true, 'Email actions follow the workflow context capabilities.');
assert.strictEqual(scope.canEmail(scope.rows[0]), true);
assert.strictEqual(scope.canEmail(scope.rows[2]), false, 'A row without an email address cannot be emailed.');
assert.strictEqual(scope.canSms(scope.rows[1]), false, 'SMS is gated on an enabled provider.');

// --- applyFilters: case-insensitive search across name, email, phone, role,
// --- shift and status; status narrowing; groups sorted by start time.
const visibleIds = () => scope.visibleRows.map(row => row.id);
scope.ui.search = 'JANE';
scope.filterRows();
assert.deepStrictEqual(visibleIds(), [1, 2],
  'Search is case-insensitive and spans fields: an email hit and a name hit.');
scope.ui.search = 'greet';
scope.filterRows();
assert.deepStrictEqual(visibleIds(), [1], 'Search also matches the role label.');
scope.ui.search = '555-0101';
scope.filterRows();
assert.deepStrictEqual(visibleIds(), [2], 'Search also matches the phone number.');
scope.ui.search = '';
scope.ui.statusFilter = 'completed';
scope.filterRows();
assert.deepStrictEqual(visibleIds(), [1, 3], 'The status filter narrows by machine name.');
scope.ui.search = '=sum';
scope.filterRows();
assert.deepStrictEqual(visibleIds(), [1], 'Search and status filters combine.');
scope.ui.search = '';
scope.ui.statusFilter = '';
scope.filterRows();

assert.deepStrictEqual(scope.groups.map(group => group.label),
  ['Unscheduled', 'Sat 9:00 AM', 'Sat 11:00 AM'],
  'Rows group by display time; unscheduled rows fall last in label but sort first by empty start.');
assert.strictEqual(scope.groups[0].start, '');
assert.strictEqual(scope.groups[1].start, '2026-08-22 09:00:00');
assert.strictEqual(scope.groups[1].rows.length, 1);
assert.strictEqual(scope.groups[1].rows[0].id, 1);
assert.strictEqual(scope.groups[2].rows[0].id, 2);

// applyFilters keeps the select-all checkbox in sync with the visible rows.
scope.ui.search = 'greet';
scope.filterRows();
assert.strictEqual(scope.ui.allSelected, false);
scope.selected[1] = true;
scope.filterRows();
assert.strictEqual(scope.ui.allSelected, true, 'Selecting every visible row checks the select-all box.');
scope.ui.search = '';
scope.ui.statusFilter = '';
scope.filterRows();

// --- csvCell, exercised through exportCsv: spreadsheet-formula injection
// --- is neutralised; quotes and commas are escaped; ordinary text is intact.
scope.exportCsv();
assert.strictEqual(blobs.length, 1);
assert.strictEqual(blobs[0].type, 'text/csv;charset=utf-8');
assert.strictEqual(blobs[0].parts.length, 1);
const expectedCsv = '\ufeff' + [
  '"Volunteer","Role","Shift","Status","Email","Phone"',
  '"\'=SUM(A1:A9)","Greeter","Sat 9:00 AM","Attended","jane@example.org","555-0100"',
  '"Doe, Jane","Usher","Sat 11:00 AM","Available","JANE2@EXAMPLE.ORG","\'+1 555-0101"',
  '"He said ""hi""","\'-coach","","\'@import","",""',
].join('\r\n');
assert.strictEqual(blobs[0].parts[0], expectedCsv,
  'Formula-leading cells are single-quote prefixed; embedded quotes are doubled; commas are quoted; null/undefined become empty cells.');
assert.strictEqual(objectUrlBlob, blobs[0], 'The download URL is created from the generated CSV blob.');
assert.strictEqual(createdLink.tag, 'a');
assert.strictEqual(createdLink.download, 'volunteer-roster-7.csv');
assert.strictEqual(linkAppended, true);
assert.strictEqual(linkClicked, true);
assert.strictEqual(linkRemoved, true);
assert.strictEqual(urlRevoked, true);

restoreGlobals();

console.log('Volunteer roster CSV and filter behavior checks passed.');
