'use strict';

// The Shifts & roles step as the router builds it: the VolunteerShifts
// controller driving Shifts.html (the workflow shell with ShiftsBody.html
// included). Rows, filters, autosave and the continue/close gates are
// asserted through the rendered DOM and the API4 calls the page makes.

const {boot, services, settle, render, text, partial, routeFor, ui} = require('./support');

describe('Shifts & roles step', () => {
  const recorders = boot();

  const roles = [
    {id: 3, name: 'greeter', label: 'Greeter', description: ''},
    {id: 4, name: 'usher', label: 'Usher', description: '<p>Shows people to seats.</p>'},
  ];
  const presets = {
    now: '2026-08-19 14:30:00',
    today: {from: '2026-08-19 00:00:00', to: '2026-08-19 23:59:59'},
    this_week: {from: '2026-08-17 00:00:00', to: '2026-08-23 23:59:59'},
    next_week: {from: '2026-08-24 00:00:00', to: '2026-08-30 23:59:59'},
    last_week: {from: '2026-08-10 00:00:00', to: '2026-08-16 23:59:59'},
  };
  const need = (id, overrides) => Object.assign({
    id, project_id: 42, role_id: 3, quantity: 2, is_flexible: 0, is_active: 1, visibility_id: 1,
    start_time: '2026-08-25 09:00:00', end_time: null, duration: 90,
    display_time: 'Tue 25 Aug 9:00 AM', role_label: 'Greeter',
  }, overrides);

  function context(needs, assignments) {
    return [{
      project: {id: 42, title: 'Harvest Festival', is_active: '1'},
      needs: needs,
      assignments: assignments || [],
      capacity: {project_id: 42, filled: 0, total: 0, by_need: {}},
      supporting: {
        workflow: {roles, visibility: {public: '1', admin: '2'}, shift_filter_presets: presets, statuses: []},
        project: {relationship_types: {}, defaults: {}},
      },
      beneficiary_names: [],
    }];
  }

  const defaultNeeds = () => [
    need(30),
    need(31, {start_time: '2026-09-01 18:00:00', display_time: 'Tue 1 Sep 6:00 PM', role_id: 4, role_label: 'Usher'}),
    need(32, {start_time: '2026-01-10 09:00:00', display_time: 'Sat 10 Jan 9:00 AM'}),
    need(33, {is_flexible: 1, quantity: null, duration: null, start_time: '2026-01-01 00:00:00'}),
  ];

  function respondWith(needs, assignments) {
    recorders.api.respond((entity, action) => {
      if (entity === 'VolunteerProject' && action === 'getWorkflowContext') { return context(needs, assignments); }
      if (entity === 'VolunteerUtil' && action === 'getSupportingData') { return context(needs, assignments)[0].supporting.workflow && [context(needs)[0].supporting.workflow]; }
      if (entity === 'VolunteerNeed') { return [{id: 55}]; }
      throw new Error('unexpected ' + entity + '.' + action);
    });
  }

  async function mount(options = {}) {
    const {$compile, $controller, $rootScope} = services('$compile', '$controller', '$rootScope');
    const scope = $rootScope.$new();
    if (options.model) { scope.model = options.model; }
    $controller('VolunteerShifts', {$scope: scope, $route: routeFor({projectId: '42'})});
    const element = render($compile, scope, partial('Shifts.html'));
    await settle($rootScope);
    return {element, scope, $rootScope};
  }

  const rows = (element) => element.find('.crm-vol-shift-row');
  const rowIds = (scope) => scope.visibleNeeds.map((n) => n.id);

  test('renders one row per upcoming scheduled shift, with the role choices and the result count', async () => {
    respondWith(defaultNeeds());
    const {element, scope} = await mount();

    expect(recorders.api.callsTo('VolunteerProject', 'getWorkflowContext')).toHaveLength(1);
    expect(recorders.api.last('VolunteerProject', 'getWorkflowContext').params).toEqual({projectId: 42});
    expect(element.find('.crm-loading-element').length).toBe(0);

    // Need 32 is in the past and 33 is the flexible need: neither is a row.
    expect(rowIds(scope)).toEqual([30, 31]);
    expect(rows(element).length).toBe(2);
    expect(text(element.find('.crm-vol-shift-result-count'))).toBe('Showing 2 of 3 shifts');

    const roleSelect = rows(element).eq(1).find('select').first();
    expect(roleSelect.find('option').map((i, o) => text(o)).get()).toEqual(['Select a role', 'Greeter', 'Usher']);
    expect(roleSelect.val()).toBe('number:4');
    expect(text(rows(element).eq(1).find('.crm-vol-inline-links'))).toContain('Shows people to seats.');

    expect(rows(element).eq(0).find('input[type=number]').first().val()).toBe('2');
    expect(text(rows(element).eq(0).find('.crm-vol-filled-count'))).toBe('0 of 2 filled');
    expect(text(element.find('.crm-vol-flexible-row label'))).toBe('Let people volunteer without picking a shift');
  });

  test('the quick date filters change which rows show; an invalid custom range is refused', async () => {
    respondWith(defaultNeeds());
    const {element, scope} = await mount();
    const quick = (label) => element.find('.crm-vol-shift-quick-filters button').filter((i, b) => text(b) === label);

    quick('All').trigger('click');
    expect(rowIds(scope)).toEqual([30, 31, 32]);
    expect(quick('All').attr('aria-pressed')).toBe('true');
    expect(quick('Upcoming').attr('aria-pressed')).toBe('false');

    quick('Next week').trigger('click');
    expect(rowIds(scope)).toEqual([30]);
    expect(text(element.find('.crm-vol-shift-result-count'))).toBe('Showing 1 of 3 shifts');

    quick('Today').trigger('click');
    expect(rowIds(scope)).toEqual([]);
    expect(text(element.find('.crm-vol-shift-empty strong'))).toBe('No shifts match these filters.');

    scope.ui.shiftFilters.dateFrom = '2026-08-20';
    scope.ui.shiftFilters.dateTo = '2026-08-19';
    scope.applyCustomShiftRange(scope.shiftFilterForm);
    scope.$digest();
    expect(element.find('#crm-vol-shift-filter-error').length).toBe(1);
    expect(rowIds(scope)).toEqual([], 'a rejected range keeps the previous rows');

    element.find('.crm-vol-shift-option-filters button').trigger('click');
    expect(rowIds(scope)).toEqual([30, 31]);
    expect(element.find('#crm-vol-shift-filter-error').length).toBe(0);
  });

  test('the role and sign-up filters narrow the rows', async () => {
    respondWith(defaultNeeds().map((n) => (n.id === 31 ? Object.assign(n, {is_active: 0}) : n)));
    const {element, scope} = await mount();
    const selects = element.find('.crm-vol-shift-option-filters select');

    selects.eq(1).val('number:4').trigger('change');
    expect(rowIds(scope)).toEqual([31]);
    selects.eq(1).val('').trigger('change');
    selects.eq(2).val('accepting').trigger('change');
    expect(rowIds(scope)).toEqual([30]);
    selects.eq(2).val('not_accepting').trigger('change');
    expect(rowIds(scope)).toEqual([31]);
  });

  test('editing the number of spots autosaves the row after the debounce and reports the state', async () => {
    respondWith(defaultNeeds());
    const {element, scope, $rootScope} = await mount();
    const {$timeout} = services('$timeout');
    const spots = rows(element).eq(0).find('input[type=number]').first();

    ui.type(spots, '5');
    expect(recorders.api.callsTo('VolunteerNeed')).toHaveLength(0);
    $timeout.flush(500);
    expect(recorders.api.callsTo('VolunteerNeed', 'update')).toHaveLength(1);
    expect(recorders.api.last('VolunteerNeed', 'update').params).toEqual({
      where: [['id', '=', 30]],
      values: {
        project_id: 42, role_id: 3, quantity: 5, visibility_id: 1, is_active: true, is_flexible: false,
        start_time: '2026-08-25 09:00:00', end_time: null, duration: 90,
      },
    });
    expect(text(rows(element).eq(0).find('.crm-vol-save-state'))).toBe('Saving');
    expect(text(element.find('.crm-vol-autosave-state'))).toBe('Saving');
    expect(element.find('.crm-submit-buttons button').first().prop('disabled')).toBe(true);

    await settle($rootScope);
    expect(text(rows(element).eq(0).find('.crm-vol-save-state'))).toBe('Saved');
    expect(text(element.find('.crm-vol-autosave-state'))).toBe('Changes saved');
    expect(element.find('.crm-submit-buttons button').first().prop('disabled')).toBe(false);
    expect(scope.needs[0].quantity).toBe(5);
  });

  test('a save failure is shown on the row and in the header, and announced', async () => {
    respondWith(defaultNeeds());
    const {element, $rootScope} = await mount();
    recorders.api.respond(() => { throw {error_message: 'Database is read-only'}; });

    ui.check(rows(element).eq(0).find('.crm-vol-shift-visibility input').eq(0), false);
    await settle($rootScope);
    expect(text(rows(element).eq(0).find('.crm-vol-save-state'))).toBe('Not saved');
    expect(text(element.find('.crm-vol-autosave-state'))).toBe('Not saved');
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({text: 'Database is read-only', title: 'Not saved', type: 'error'}));
  });

  test('Add shift creates a draft row that blocks continuing until it has a role', async () => {
    respondWith(defaultNeeds());
    const {element, scope, $rootScope} = await mount();
    const {$location} = services('$location');

    element.find('.crm-vol-section-header button').trigger('click');
    expect(rows(element).length).toBe(3);
    const draft = rows(element).eq(2);
    expect(draft.find('select').first().val()).toBe('');
    expect(text(draft.find('.crm-vol-save-state'))).toBe('Saved');
    expect(recorders.api.callsTo('VolunteerNeed')).toHaveLength(0);

    element.find('.crm-submit-buttons button').first().trigger('click');
    await settle($rootScope);
    expect($location.path()).not.toBe('/volunteer/manage/42/assign');
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({title: 'Shift incomplete'}));

    // Choosing a role creates it on the server and continuing is allowed.
    draft.find('select').first().val('number:3').trigger('change');
    await settle($rootScope);
    expect(recorders.api.last('VolunteerNeed', 'create').params.values).toEqual(expect.objectContaining({
      project_id: 42, role_id: 3, quantity: 1, is_active: true, duration: 60,
    }));
    expect(scope.needs[scope.needs.length - 1].id).toBe(55);

    element.find('.crm-submit-buttons button').first().trigger('click');
    await settle($rootScope);
    expect($location.path()).toBe('/volunteer/manage/42/assign');
  });

  test('Delete asks, mentions existing assignments, and removes the row on Yes', async () => {
    respondWith(defaultNeeds(), [{id: 900, volunteer_need_id: 30}, {id: 901, volunteer_need_id: 30}]);
    const {element, $rootScope} = await mount();

    rows(element).eq(0).find('.crm-vol-destructive-action').trigger('click');
    expect(recorders.confirmations).toHaveLength(1);
    expect(recorders.confirmations[0].options.message).toContain('2 assignment(s)');
    expect(rows(element).length).toBe(2);

    recorders.confirmations[0].answer('crmConfirm:yes');
    await settle($rootScope);
    expect(recorders.api.last('VolunteerNeed', 'delete').params).toEqual({where: [['id', '=', 30]]});
    expect(rows(element).length).toBe(1);
    expect(text(element.find('.crm-vol-shift-result-count'))).toBe('Showing 1 of 2 shifts');
  });

  test('switching a row to Ongoing clears its end time and duration and hides those inputs', async () => {
    respondWith(defaultNeeds());
    const {element, $rootScope} = await mount();
    const row = rows(element).eq(0);
    expect(row.find('.crm-vol-duration').length).toBe(1);

    ui.choose(row.find('input[type=radio][value=ongoing]'));
    await settle($rootScope);
    const values = recorders.api.last('VolunteerNeed', 'update').params.values;
    expect(values.end_time).toBeNull();
    expect(values.duration).toBeNull();
    expect(values.start_time).toBe('2026-08-25 09:00:00');
    expect(row.find('.crm-vol-duration').length).toBe(0);
    expect(text(row.find('.crm-vol-schedule-label'))).toBe('Starts');
  });

  test('the flexible need toggle saves only its visibility', async () => {
    respondWith(defaultNeeds());
    const {element, $rootScope} = await mount();
    ui.check(element.find('.crm-vol-flexible-row input[type=checkbox]'), false);
    await settle($rootScope);
    expect(recorders.api.last('VolunteerNeed', 'update').params).toEqual({
      where: [['id', '=', 33]],
      values: {visibility_id: 2},
    });
  });

  test('in a dialog the submit row is absent and the registry reports the session\'s changes', async () => {
    respondWith(defaultNeeds());
    const registry = {clean: [], created: [], updated: [], deleted: []};
    const {element, scope, $rootScope} = await mount({model: {projectId: 42, dialogMode: true, needRegistry: registry}});
    expect(element.find('.crm-submit-buttons').length).toBe(0);
    expect(registry.clean).toEqual([30, 31, 32]);

    ui.check(rows(element).eq(0).find('.crm-vol-shift-visibility input').eq(0), false);
    await settle($rootScope);
    expect(registry.updated).toEqual([30]);
    expect(registry.clean).toEqual([31, 32]);
    expect(scope.dialogMode).toBe(true);
  });
});
