'use strict';

// The Log hours step: VolunteerHours driving Hours.html (shell +
// HoursBody.html). Scope selection, the sheet's conversions, bulk actions,
// commendations and the save are asserted through the DOM and the API.

const {boot, services, settle, render, text, partial, routeFor, ui} = require('./support');

describe('Log hours step', () => {
  const recorders = boot();

  const needs = [
    {id: 30, is_flexible: 0, role_label: 'Greeter', display_time: 'Tue 25 Aug 9:00 AM', duration: 90},
    {id: 33, is_flexible: 1, role_label: 'General availability', display_time: 'All shifts', duration: null},
  ];
  const statuses = [{id: 1, label: 'Available'}, {id: 2, label: 'Attended'}, {id: 3, label: 'No-show'}];
  const row = (id, contactId, name, needId, statusId, minutes, extra) => Object.assign({
    id, assignee_contact_id: contactId, assignee_display_name: name, assignee_email: name.split(' ')[0].toLowerCase() + '@example.org',
    volunteer_need_id: needId, role_label: needId === 30 ? 'Greeter' : 'General availability',
    display_time: needId === 30 ? 'Tue 25 Aug 9:00 AM' : 'All shifts', time_scheduled_minutes: needId === 30 ? 90 : null,
    status_id: String(statusId), time_completed_minutes: minutes, details: '',
  }, extra);

  function sheet(rows) {
    return [{needs, statuses, completed_status_id: '2', no_show_status_id: '3', flexible_need_id: '33', rows}];
  }
  const defaultRows = () => [
    row(11, 202, 'Ada Lovelace', 30, 2, 90),
    row(12, 203, 'Grace Hopper', 30, 1, null),
    row(13, 205, 'Madonna', 33, 2, 125),
  ];

  function respond(rows, commendations) {
    recorders.api.respond((entity, action, params) => {
      if (entity === 'VolunteerProject' && action === 'getWorkflowContext') {
        return [{
          project: {id: 42, title: 'Harvest Festival', is_active: '1'}, needs: [], assignments: [],
          capacity: {filled: 2, total: 3, by_need: {}}, supporting: {workflow: {}, project: {}}, beneficiary_names: ['Friends'],
        }];
      }
      if (entity === 'VolunteerAssignment' && action === 'getHourEntries') {
        return sheet(params.volunteerNeedId ? rows.filter((r) => r.volunteer_need_id === params.volunteerNeedId) : rows);
      }
      if (entity === 'VolunteerAssignment' && action === 'logHours') {
        return sheet(params.entries.map((entry, i) => Object.assign({}, rows[i] || row(90 + i, entry.assignee_contact_id, 'New Person', entry.volunteer_need_id, 2, null), {
          status_id: String(entry.status_id), time_completed_minutes: entry.time_completed_minutes, details: entry.details,
        })));
      }
      if (entity === 'VolunteerCommendation') { return commendations || []; }
      throw new Error('unexpected ' + entity + '.' + action);
    });
  }

  async function mount(options = {}) {
    respond(options.rows || defaultRows(), options.commendations);
    const {$compile, $controller, $rootScope, $location} = services('$compile', '$controller', '$rootScope', '$location');
    if (options.needId) { $location.search({needId: options.needId}); }
    const scope = $rootScope.$new();
    if (options.model) { scope.model = options.model; }
    $controller('VolunteerHours', {$scope: scope, $route: routeFor({projectId: '42'})});
    const element = render($compile, scope, partial('Hours.html'));
    await settle($rootScope);
    return {element, scope, $rootScope};
  }

  const rows = (element) => element.find('.crm-vol-hours-table tbody tr').filter((i, tr) => !CRM.$(tr).find('.crm-vol-empty-row').length);
  const statusOf = (tr) => CRM.$(tr).find('select').val();
  const hoursOf = (tr) => CRM.$(tr).find('.crm-vol-hours-input input').val();

  test('renders the sheet: scope choices, one row per assignment with status, converted hours and scheduled time', async () => {
    const {element} = await mount();
    expect(recorders.api.last('VolunteerAssignment', 'getHourEntries').params).toEqual({projectId: 42, volunteerNeedId: null});

    expect(ui.options(element.find('#crm-vol-hours-shift'))).toEqual(['All shifts', 'Greeter — Tue 25 Aug 9:00 AM']);
    expect(rows(element).length).toBe(3);

    const ada = rows(element).eq(0);
    expect(ui.options(ada.find('select'))).toEqual(['Available', 'Attended', 'No-show']);
    expect(statusOf(ada)).toBe('number:2');
    expect(hoursOf(ada)).toBe('1.5');
    expect(text(ada.find('td').eq(3))).toBe('1.5 hrs');
    expect(text(ada.find('td').eq(1))).toContain('Ada Lovelace');

    const grace = rows(element).eq(1);
    expect(statusOf(grace)).toBe('number:1');
    expect(hoursOf(grace)).toBe('');

    expect(hoursOf(rows(element).eq(2))).toBe('2.08');
    expect(text(rows(element).eq(2).find('td').eq(3))).toBe('—');
    expect(text(element.find('.crm-vol-hours-total'))).toBe('3.58 hours across 2 volunteers marked Attended');
    expect(text(element.find('.crm-vol-capacity-meta'))).toBe('2 of 3 spots filled');
  });

  test('arriving with a shift in the URL loads that shift only', async () => {
    const {element} = await mount({needId: 30});
    expect(recorders.api.last('VolunteerAssignment', 'getHourEntries').params).toEqual({projectId: 42, volunteerNeedId: 30});
    expect(element.find('#crm-vol-hours-shift').val()).toBe('30');
    expect(rows(element).length).toBe(2);
  });

  test('Mark everyone Attended and the bulk hours box change every attended row', async () => {
    const {element} = await mount();
    ui.click(ui.button(element, 'Mark everyone Attended'));
    expect(rows(element).map((i, tr) => statusOf(tr)).get()).toEqual(['number:2', 'number:2', 'number:2']);

    ui.type(element.find('.crm-vol-hours-bulk input'), '2');
    ui.click(ui.button(element.find('.crm-vol-hours-bulk'), 'Apply'));
    expect(rows(element).map((i, tr) => hoursOf(tr)).get()).toEqual(['2', '2', '2']);
    expect(text(element.find('.crm-vol-hours-total'))).toBe('6 hours across 3 volunteers marked Attended');

    ui.type(element.find('.crm-vol-hours-bulk input'), '');
    ui.click(ui.button(element.find('.crm-vol-hours-bulk'), 'Apply'));
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({title: 'No hours entered'}));
    expect(rows(element).map((i, tr) => hoursOf(tr)).get()).toEqual(['2', '2', '2']);
  });

  test('Save hours sends whole minutes, reports through the status bubble and marks the sheet clean', async () => {
    const {element, scope, $rootScope} = await mount();
    ui.type(rows(element).eq(0).find('.crm-vol-hours-input input'), '1.75');
    ui.type(rows(element).eq(0).find('input[type=text]'), 'brisk pace');
    expect(scope.workflow.isDirty()).toBe(true);

    ui.click(ui.button(element, 'Save hours'));
    await settle($rootScope);
    const request = recorders.api.last('VolunteerAssignment', 'logHours').params;
    expect(request.projectId).toBe(42);
    expect(request.volunteerNeedId).toBeNull();
    expect(request.entries[0]).toEqual({
      id: 11, assignee_contact_id: 202, volunteer_need_id: 30, status_id: 2, time_completed_minutes: 105, details: 'brisk pace',
    });
    expect(request.entries[1].time_completed_minutes).toBeNull();
    expect(recorders.statuses[0]).toEqual({start: 'Saving hours…', success: 'Volunteer hours saved'});
    expect(scope.workflow.isDirty()).toBe(false);
    expect(hoursOf(rows(element).eq(0))).toBe('1.75');
  });

  test('an invalid sheet is refused before reaching the server', async () => {
    const {element, $rootScope} = await mount();
    ui.type(rows(element).eq(0).find('.crm-vol-hours-input input'), '-1');
    ui.click(ui.button(element, 'Save hours'));
    await settle($rootScope);
    expect(recorders.api.callsTo('VolunteerAssignment', 'logHours')).toHaveLength(0);
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({title: 'Check hour entries'}));
  });

  test('changing the shift scope on a dirty sheet asks first; No keeps the scope, Yes reloads the shift', async () => {
    const {element, $rootScope} = await mount();
    const scopeSelect = element.find('#crm-vol-hours-shift');
    ui.type(rows(element).eq(0).find('.crm-vol-hours-input input'), '3');

    ui.pick(scopeSelect, '30');
    expect(recorders.confirmations[0].options.title).toBe('Discard unsaved changes?');
    $rootScope.$digest();
    expect(scopeSelect.val()).toBe('all', 'the selection is put back until the user answers');
    expect(recorders.api.callsTo('VolunteerAssignment', 'getHourEntries')).toHaveLength(1);

    recorders.confirmations[0].answer('crmConfirm:yes');
    await settle($rootScope);
    expect(recorders.api.last('VolunteerAssignment', 'getHourEntries').params.volunteerNeedId).toBe(30);
    expect(scopeSelect.val()).toBe('30');
    expect(rows(element).length).toBe(2);
  });

  test('a walk-in row is added under the current scope and can be removed', async () => {
    const {element} = await mount();
    ui.click(ui.button(element, 'Add someone who wasn’t signed up'));
    expect(rows(element).length).toBe(4);
    const added = rows(element).eq(3);
    expect(added.find('input.crm-form-entityref').length).toBe(1);
    expect(statusOf(added)).toBe('number:2');
    expect(text(added.find('td').eq(2).contents().first())).toBe('General availability');
    expect(text(added.find('td').eq(2).find('span'))).toBe('All shifts');
    ui.click(ui.button(added, 'Remove'));
    expect(rows(element).length).toBe(3);
  });

  test('the commendation star reflects existing commendations and opens the form with the right ids', async () => {
    const {element} = await mount({commendations: [{id: 77, volunteer_contact_id: 202}]});
    const stars = element.find('.crm-vol-commendation-button');
    expect(stars.eq(0).hasClass('is-commended')).toBe(true);
    expect(stars.eq(1).hasClass('is-commended')).toBe(false);

    ui.click(stars.eq(0));
    expect(recorders.forms[0].url).toBe(CRM.url('civicrm/volunteer/commendation', {vid: 42, cid: 202, aid: 77}));
    ui.click(stars.eq(1));
    expect(recorders.forms[1].url).toBe(CRM.url('civicrm/volunteer/commendation', {vid: 42, cid: 203}));
  });

  test('inside a dialog the page-level submit row is gone and the dialog close honours a dirty sheet', async () => {
    const {element, scope, $rootScope} = await mount({model: {projectId: 42, dialogMode: true}});
    expect(element.find('.crm-submit-buttons').length).toBe(0);
    const close = jest.fn();
    scope.volunteerWorkflowDialog = {close};

    scope.closeWorkflowDialog();
    expect(close).toHaveBeenCalledTimes(1);

    ui.type(rows(element).eq(0).find('.crm-vol-hours-input input'), '4');
    scope.closeWorkflowDialog();
    expect(close).toHaveBeenCalledTimes(1);
    expect(recorders.confirmations).toHaveLength(1);
    recorders.confirmations[0].answer('crmConfirm:yes');
    await settle($rootScope, 2);
    expect(close).toHaveBeenCalledTimes(2);
  });
});
