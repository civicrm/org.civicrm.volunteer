'use strict';

// The Roster step: VolunteerRoster driving Roster.html (shell +
// RosterBody.html). Grouping, filters, selection-led email, the per-row
// contact actions and their gating are asserted through the DOM.

const {boot, services, settle, render, text, partial, routeFor, fakeWindow, ui} = require('./support');

describe('Roster step', () => {
  const browser = fakeWindow();
  const recorders = boot({configure: ($provide) => { $provide.value('$window', browser); }});

  const rows = () => [
    {id: 1, assignee_contact_id: 202, assignee_display_name: 'Ada Lovelace', assignee_email: 'ada@example.org', assignee_phone: '555-0100',
      role_label: 'Greeter', display_time: 'Sat 9:00 AM', start_time: '2026-08-22 09:00:00', status_name: 'Scheduled', status_label: 'Scheduled', can_view_contact: true},
    {id: 2, assignee_contact_id: 204, assignee_display_name: 'Jane Doe', assignee_email: '', assignee_phone: '555-0101', assignee_phone_ext: '12',
      role_label: 'Usher', display_time: 'Sat 11:00 AM', start_time: '2026-08-22 11:00:00', status_name: 'Completed', status_label: 'Attended', can_view_contact: true},
    {id: 3, assignee_contact_id: 206, assignee_display_name: 'Bo Peep', assignee_email: 'bo@example.org', assignee_phone: '',
      role_label: 'Greeter', display_time: '', start_time: '', status_name: 'Scheduled', status_label: 'Scheduled', can_view_contact: false},
  ];

  function respond(options = {}) {
    recorders.api.respond((entity, action, params) => {
      if (entity === 'VolunteerProject' && action === 'getWorkflowContext') {
        return [{
          project: {id: 42, title: 'Harvest Festival', is_active: '1'}, needs: [], assignments: [], capacity: null,
          supporting: {workflow: Object.assign({can_send_email: true, email_activity_type_id: 3, sms: {enabled: true, activity_type_id: 4}}, options.workflow), project: {}},
          beneficiary_names: [],
        }];
      }
      if (entity === 'VolunteerAssignment' && action === 'getRoster') {
        return [{project_title: 'Harvest Festival', rows: params.includePast ? rows().concat([{
          id: 4, assignee_contact_id: 208, assignee_display_name: 'Old Timer', assignee_email: 'old@example.org', assignee_phone: '',
          role_label: 'Usher', display_time: 'Sat 1 Jan 9:00 AM', start_time: '2026-01-01 09:00:00', status_name: 'Completed', status_label: 'Attended', can_view_contact: true,
        }]) : rows()}];
      }
      throw new Error('unexpected ' + entity + '.' + action);
    });
  }

  async function mount(options) {
    respond(options);
    const {$compile, $controller, $rootScope} = services('$compile', '$controller', '$rootScope');
    const scope = $rootScope.$new();
    $controller('VolunteerRoster', {$scope: scope, $route: routeFor({projectId: '42'})});
    const element = render($compile, scope, partial('Roster.html'));
    await settle($rootScope);
    return {element, scope, $rootScope};
  }

  const groups = (element) => element.find('.crm-vol-roster-group');
  const visibleRows = (element) => element.find('.crm-vol-roster-table tbody tr');

  test('groups everyone by shift, unscheduled first, with contact links and status badges', async () => {
    const {element} = await mount();
    expect(recorders.api.last('VolunteerAssignment', 'getRoster').params).toEqual({projectId: 42, includePast: false});
    expect(groups(element).map((i, g) => text(CRM.$(g).find('h3'))).get()).toEqual([
      'Unscheduled 1 volunteers', 'Sat 9:00 AM 1 volunteers', 'Sat 11:00 AM 1 volunteers',
    ]);

    const ada = groups(element).eq(1).find('tbody tr');
    expect(ada.find('td').eq(1).find('a').attr('href')).toBe(CRM.url('civicrm/contact/view', {reset: 1, cid: 202}));
    expect(ada.find('.crm-vol-status-badge').length).toBe(0);
    expect(ada.find('a[href="mailto:ada@example.org"]').length).toBe(1);
    expect(ada.find('td').eq(3).find('a[href="tel:555-0100"]').length).toBe(1);
    expect(text(ada.find('.crm-vol-roster-row-actions'))).toBe('Email Call SMS');

    const jane = groups(element).eq(2).find('tbody tr');
    expect(text(jane.find('.crm-vol-status-badge'))).toBe('Attended');
    expect(text(jane.find('td').eq(3))).toBe('555-0101 ext. 12');
    expect(text(jane.find('.crm-vol-roster-row-actions'))).toBe('Call SMS');

    const bo = groups(element).eq(0).find('tbody tr');
    expect(bo.find('td').eq(1).find('a').length).toBe(0);
    expect(text(bo.find('td').eq(1))).toBe('Bo Peep');
    expect(text(bo.find('.crm-vol-roster-row-actions'))).toBe('Email');
    expect(text(element.find('.crm-vol-roster-footnote'))).toBe('Assignments that ended before today are hidden.');
  });

  test('search and status narrow the rows as you type', async () => {
    const {element} = await mount();
    ui.type(element.find('input[type=search]'), 'jane');
    expect(visibleRows(element).length).toBe(1);
    expect(text(visibleRows(element).find('td').eq(1))).toContain('Jane Doe');

    ui.type(element.find('input[type=search]'), '');
    expect(ui.options(element.find('.crm-vol-roster-filters select'))).toEqual(['All statuses', 'Attended', 'Scheduled']);
    ui.pick(element.find('.crm-vol-roster-filters select'), 'Scheduled');
    expect(visibleRows(element).length).toBe(2);

    ui.type(element.find('input[type=search]'), 'nobody');
    expect(visibleRows(element).length).toBe(0);
    expect(text(element.find('.messages.status'))).toBe('No roster entries match these filters.');
  });

  test('selecting volunteers enables Email selected, which opens the email form for those with an address', async () => {
    const {element} = await mount();
    const emailSelected = ui.button(element.find('.crm-vol-roster-actions'), 'Email selected');
    expect(emailSelected.prop('disabled')).toBe(true);
    expect(text(element.find('.crm-vol-selection-label'))).toBe('Nobody selected');

    ui.check(groups(element).eq(1).find('tbody input[type=checkbox]'), true);
    expect(text(element.find('.crm-vol-selection-label'))).toBe('1 selected');
    expect(emailSelected.prop('disabled')).toBe(false);
    // Each group's header box is the same "select all visible" control.
    ui.check(groups(element).eq(2).find('thead input[type=checkbox]'), true);
    expect(text(element.find('.crm-vol-selection-label'))).toBe('3 selected');
    expect(groups(element).eq(0).find('thead input[type=checkbox]').prop('checked')).toBe(true);

    ui.click(emailSelected);
    // Jane has no email, so only Ada and Bo are addressed.
    expect(recorders.forms[0].url).toBe(CRM.url('civicrm/activity/email/add', {reset: 1, action: 'add', atype: 3, cid: '202,206'}));
  });

  test('the per-row actions open the right core forms', async () => {
    const {element} = await mount();
    ui.click(ui.button(groups(element).eq(1), 'Email'));
    expect(recorders.forms[0].url).toBe(CRM.url('civicrm/activity/email/add', {reset: 1, action: 'add', cid: 202, atype: 3}));
    ui.click(ui.button(groups(element).eq(2), 'SMS'));
    expect(recorders.forms[1].url).toBe(CRM.url('civicrm/activity/sms/add', {reset: 1, action: 'add', cid: 204, atype: 4}));
  });

  test('without outbound mail the email actions are withheld and say why', async () => {
    const {element} = await mount({workflow: {can_send_email: false}});
    const emailSelected = ui.button(element.find('.crm-vol-roster-actions'), 'Email selected');
    ui.check(groups(element).eq(1).find('tbody input[type=checkbox]'), true);
    expect(emailSelected.prop('disabled')).toBe(true);
    expect(emailSelected.attr('title')).toBe('Outbound email is not configured for this site.');
    expect(ui.button(groups(element).eq(1), 'Email').length).toBe(0);
  });

  test('Include past assignments reloads with history and drops the footnote', async () => {
    const {element, $rootScope} = await mount();
    ui.check(element.find('.crm-vol-include-past input'), true);
    await settle($rootScope);
    expect(recorders.api.last('VolunteerAssignment', 'getRoster').params).toEqual({projectId: 42, includePast: true});
    expect(groups(element).length).toBe(4);
    expect(element.find('.crm-vol-roster-footnote').length).toBe(0);
  });

  test('Print roster prints the page', async () => {
    const {element} = await mount();
    ui.click(ui.button(element.find('.crm-vol-roster-actions'), 'Print roster'));
    expect(browser.print).toHaveBeenCalledTimes(1);
  });
});
