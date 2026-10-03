'use strict';

// Behavioural checks for the VolunteerHours controller (ang/volunteer/Hours.js):
// the minutes/hours conversion in both directions, the payload snapshot behind
// isDirty()/markPristine(), validateRows(), the bulk-hours guards and the
// attended summary.
//
// The source executes in this context rather than a new vm context: Hours.js
// builds its row/payload objects inside the vm, and assert.deepStrictEqual
// refuses objects whose prototypes come from another realm.

const assert = require('assert');
const {makeAngular, makeUnderscore, makeCRM, resolved, loadInThisContext} = require('./harness');

const {angular, registry} = makeAngular();
const {CRM, alerts} = makeCRM({_: makeUnderscore()});
const restoreGlobals = loadInThisContext('ang/volunteer/Hours.js', {angular, CRM});
const controller = registry.controllers.VolunteerHours;
assert.strictEqual(typeof controller, 'function');

// crmApi4 stub: records every call; apiResponses queues results per entity.action.
const apiCalls = [];
const apiResponses = {};
const crmApi4 = (entity, action, params) => {
  apiCalls.push({entity, action, params});
  const key = entity + '.' + action;
  return resolved(apiResponses[key] !== undefined ? apiResponses[key] : []);
};
const logHoursCalls = () => apiCalls.filter(call => call.action === 'logHours');

const volWorkflow = {
  loadContext: () => resolved({
    project: {id: 7, title: 'River cleanup', is_active: true},
    summary: {filled: 1, total: 2},
    beneficiaryNames: ['Friends of the River'],
  }),
  confirmDiscard: () => resolved(),
  cancel: () => resolved(),
};
const q = {
  all: promises => resolved(promises),
  resolve: value => resolved(value),
  reject: reason => ({rejectedWith: reason}),
};
const statusOptions = [];
const crmStatus = (options, promise) => { statusOptions.push(options); return promise; };

const baseContext = {
  needs: [
    {id: 21, is_flexible: 0, role_label: 'Greeter', display_time: 'Sat 9:00 AM', duration: 90},
    {id: 5, is_flexible: 1, role_label: 'General availability', display_time: 'All shifts'},
  ],
  statuses: [{value: '1', label: 'Available'}, {value: '2', label: 'Attended'}],
  completed_status_id: '2',
  no_show_status_id: '3',
  flexible_need_id: '5',
};
const row = (id, contact, minutes, extra = {}) => Object.assign({
  id: id,
  assignee_contact_id: contact,
  volunteer_need_id: 21,
  status_id: '2',
  time_completed_minutes: minutes,
  details: '',
}, extra);

apiResponses['VolunteerAssignment.getHourEntries'] = [Object.assign({}, baseContext, {rows: [
  row(11, 101, 90),
  row(12, 102, 125),
  row(13, 103, null),
  row(14, 104, ''),
  row(15, 105, 60),
]})];

const scope = {$on() {}};
controller(
  scope,
  {current: {params: {projectId: '7'}}},
  {search: () => ({})},
  {addEventListener() {}, removeEventListener() {}},
  q, crmApi4, crmStatus, volWorkflow
);

// --- Construction drove the initial load; prepareData converted minutes. ---
assert.strictEqual(scope.projectId, 7);
assert.strictEqual(scope.loading, false, 'Construction must settle the initial load synchronously here.');
assert.deepStrictEqual(apiCalls[0], {
  entity: 'VolunteerAssignment',
  action: 'getHourEntries',
  params: {projectId: 7, volunteerNeedId: null},
}, "The 'all' scope must request the whole project.");

assert.strictEqual(scope.rows[0].hours, 1.5, '90 minutes must render as 1.5 hours.');
assert.strictEqual(scope.rows[1].hours, 2.08, '125 minutes must round to two decimals.');
assert.strictEqual(scope.rows[2].hours, null, 'NULL minutes must stay NULL, not become 0.');
assert.strictEqual(scope.rows[3].hours, null, 'Empty-string minutes must normalise to NULL.');
assert.strictEqual(scope.rows[4].hours, 1);
assert.strictEqual(scope.rows[0].status_id, 2, 'Row statuses must be parsed to integers.');
assert.strictEqual(scope.completedStatusId, 2);
assert.strictEqual(scope.noShowStatusId, 3);
assert.strictEqual(scope.flexibleNeedId, 5);
assert.strictEqual(scope.needs.length, 1, 'The flexible need must be excluded from the per-shift scope options.');
assert.strictEqual(scope.needs[0].id, 21);
assert.deepStrictEqual(scope.statuses, [{value: '1', label: 'Available'}, {value: '2', label: 'Attended'}]);

// --- isDirty / markPristine snapshot the payload, not the scope. ---
assert.strictEqual(scope.workflow.isDirty(), false, 'A freshly loaded sheet is pristine.');
scope.rows[0].hours = 2;
assert.strictEqual(scope.workflow.isDirty(), true, 'Editing hours must mark the sheet dirty.');
scope.rows[0].hours = 1.5;
assert.strictEqual(scope.workflow.isDirty(), false, 'Restoring the loaded value must be pristine again.');
scope.rows[0].hours = 2;
scope.workflow.markPristine();
assert.strictEqual(scope.workflow.isDirty(), false, 'markPristine must snapshot the current rows.');
scope.rows[0].hours = 1.5;
assert.strictEqual(scope.workflow.isDirty(), true, 'Changing away from the new snapshot must be dirty.');
scope.workflow.markPristine();
scope.rows[0].details = 'late arrival';
assert.strictEqual(scope.workflow.isDirty(), true, 'Editing details must mark the sheet dirty too.');
scope.rows[0].details = '';
assert.strictEqual(scope.workflow.isDirty(), false);

// --- saveHours converts hours back to whole minutes and normalises rows. ---
scope.rows = [
  {id: 11, assignee_contact_id: 101, volunteer_need_id: 21, status_id: 2, hours: 1.75, details: 'brisk pace'},
  {_new: true, assignee_contact_id: 202, volunteer_need_id: 5, status_id: '2', hours: '0.5'},
];
apiResponses['VolunteerAssignment.logHours'] = [Object.assign({}, baseContext, {rows: [
  row(11, 101, 105),
]})];
const saved = scope.saveHours();
assert.strictEqual(typeof saved.then, 'function');
const entries = logHoursCalls()[0].params.entries;
assert.strictEqual(entries.length, 2);
assert.deepStrictEqual(entries[0], {
  id: 11,
  assignee_contact_id: 101,
  volunteer_need_id: 21,
  status_id: 2,
  time_completed_minutes: 105,
  details: 'brisk pace',
});
assert.deepStrictEqual(entries[1], {
  id: null,
  assignee_contact_id: 202,
  volunteer_need_id: 5,
  status_id: 2,
  time_completed_minutes: 30,
  details: '',
}, 'A new row normalises: no id, parsed status, string hours converted, details "".');
assert.strictEqual(scope.workflow.saving, false, 'saving must reset once the request settles.');
assert.strictEqual(scope.rows[0].hours, 1.75, '105 minutes from the server must render back as 1.75 hours (round trip).');
assert.strictEqual(scope.workflow.isDirty(), false, 'A successful save must snapshot the server state as pristine.');
assert.strictEqual(statusOptions[statusOptions.length - 1].success, 'Volunteer hours saved');

// --- validateRows gates: new row needs a contact, every row needs a status
// --- and non-negative numeric hours.
const logHoursBefore = logHoursCalls().length;
const alertsBeforeInvalid = alerts.length;
scope.rows = [{_new: true, assignee_contact_id: null, volunteer_need_id: 5, status_id: 2, hours: 1}];
scope.saveHours();
scope.rows = [{_new: true, assignee_contact_id: 202, volunteer_need_id: 5, status_id: null, hours: 1}];
scope.saveHours();
scope.rows = [{id: 11, assignee_contact_id: 101, volunteer_need_id: 21, status_id: 2, hours: -0.5}];
scope.saveHours();
scope.rows = [{id: 11, assignee_contact_id: 101, volunteer_need_id: 21, status_id: 2, hours: 'lots'}];
scope.saveHours();
assert.strictEqual(logHoursCalls().length, logHoursBefore, 'Invalid sheets must not reach the API.');
assert.strictEqual(alerts.length, alertsBeforeInvalid + 4, 'Each invalid sheet is explained: no volunteer, no status, negative hours, non-numeric hours.');
assert.strictEqual(alerts[alerts.length - 1].title, 'Check hour entries');

// Null hours are legitimate (attended, no duration recorded) and must save.
scope.rows = [{id: 11, assignee_contact_id: 101, volunteer_need_id: 21, status_id: 2, hours: null}];
scope.saveHours();
assert.strictEqual(
  logHoursCalls()[logHoursCalls().length - 1].params.entries[0].time_completed_minutes,
  null,
  'NULL hours must round-trip as NULL minutes.'
);

// --- applyBulkHours guards: empty or invalid input never touches the rows. ---
const sheet = () => (scope.rows = [
  {id: 11, assignee_contact_id: 101, volunteer_need_id: 21, status_id: 2, hours: 1},
  {id: 12, assignee_contact_id: 102, volunteer_need_id: 21, status_id: 3, hours: 2},
]);
for (const empty of [null, '', undefined]) {
  sheet();
  scope.ui.bulkHours = empty;
  scope.applyBulkHours();
  assert.deepStrictEqual(
    scope.rows.map(item => item.hours),
    [1, 2],
    'An empty bulk-hours box must not zero everyone (Number(null) === 0).'
  );
}
sheet();
scope.ui.bulkHours = 'four';
scope.applyBulkHours();
assert.deepStrictEqual(scope.rows.map(item => item.hours), [1, 2], 'Non-numeric bulk hours must be refused.');
sheet();
scope.ui.bulkHours = -1;
scope.applyBulkHours();
assert.deepStrictEqual(scope.rows.map(item => item.hours), [1, 2], 'Negative bulk hours must be refused.');
assert.deepStrictEqual(
  alerts.slice(-5).map(alert => alert.title),
  ['No hours entered', 'No hours entered', 'No hours entered', 'Invalid hours', 'Invalid hours']
);
sheet();
scope.ui.bulkHours = '2.25';
scope.applyBulkHours();
assert.strictEqual(scope.rows[0].hours, 2.25, 'Bulk hours must apply to Attended rows.');
assert.strictEqual(scope.rows[1].hours, 2, 'Rows in another status must keep their hours.');

// --- attendedSummary sums Attended rows, ignoring non-numeric hours. ---
scope.rows = [
  {id: 11, assignee_contact_id: 101, volunteer_need_id: 21, status_id: 2, hours: 1.5},
  {id: 12, assignee_contact_id: 102, volunteer_need_id: 21, status_id: 2, hours: 'garbage'},
  {id: 13, assignee_contact_id: 103, volunteer_need_id: 21, status_id: 2, hours: null},
  {id: 14, assignee_contact_id: 104, volunteer_need_id: 21, status_id: 3, hours: 99},
];
assert.strictEqual(
  scope.attendedSummary(),
  '1.5 hours across 3 volunteers marked Attended',
  'Non-numeric hours are excluded by isFinite; a non-attended row is not counted at all.'
);

// --- addVolunteer / removeNewRow / markAllAttended. ---
const rowsBefore = scope.rows.length;
scope.ui.scopeNeedId = 'all';
scope.addVolunteer();
const added = scope.rows[scope.rows.length - 1];
assert.strictEqual(added._new, true);
assert.strictEqual(added.volunteer_need_id, 5, "The 'all' scope files new rows under the flexible need.");
assert.strictEqual(added.role_label, 'General availability');
assert.strictEqual(added.status_id, 2, 'New rows start out marked Attended.');
assert.strictEqual(added.hours, null);
scope.removeNewRow(added);
assert.strictEqual(scope.rows.length, rowsBefore);
scope.rows[0].status_id = 3;
scope.markAllAttended();
assert.strictEqual(scope.rows[0].status_id, 2);

restoreGlobals();

console.log('Volunteer hours entry behavior checks passed.');
