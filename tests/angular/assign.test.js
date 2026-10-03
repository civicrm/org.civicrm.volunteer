'use strict';

// The Assign volunteers step as the router builds it: VolunteerAssign driving
// Assign.html (shell + AssignBody.html). Metrics, the shift rail, the
// selected-shift panel, the available pool and every mutation are asserted
// through the rendered DOM and the API4 calls they produce.

const {boot, services, settle, render, text, partial, routeFor, ui} = require('./support');

describe('Assign volunteers step', () => {
  const recorders = boot();

  const roles = [{id: 3, label: 'Greeter'}, {id: 4, label: 'Usher'}];
  const statuses = [{name: 'Available', id: '1', label: 'Available'}, {name: 'Scheduled', id: '2', label: 'Scheduled'}];
  const need = (id, overrides) => Object.assign({
    id, project_id: 42, role_id: 3, role_label: 'Greeter', quantity: 3, is_flexible: 0, is_active: 1,
    start_time: '2026-08-25 09:00:00', end_time: null, duration: 90, display_time: 'Tue 25 Aug 9:00 AM',
  }, overrides);
  const assignment = (id, needId, contactId, name, extra) => Object.assign({
    id, volunteer_need_id: needId, assignee_contact_id: contactId, assignee_display_name: name,
    assignee_email: name.split(' ')[0].toLowerCase() + '@example.org', assignee_phone: '', details: '',
  }, extra);

  function baseNeeds() {
    return [
      need(30),
      need(31, {role_id: 4, role_label: 'Usher', quantity: 1, start_time: '2026-08-25 13:00:00', duration: 60, display_time: 'Tue 25 Aug 1:00 PM'}),
      need(32, {is_active: 0}),
      need(33, {is_flexible: 1, quantity: null, duration: null, start_time: '2026-08-01 00:00:00', display_time: ''}),
    ];
  }
  function baseAssignments() {
    return [
      assignment(900, 30, 202, 'Ada Lovelace', {details: 'morning'}),
      assignment(901, 30, 203, 'Grace Hopper'),
      assignment(902, 31, 204, 'Cher'),
      assignment(903, 33, 205, 'Madonna'),
      assignment(904, 33, 206, 'Jean van der Meulen'),
    ];
  }

  function respond(state) {
    recorders.api.respond((entity, action, params) => {
      if (entity === 'VolunteerProject' && action === 'getWorkflowContext') {
        return [{
          project: {id: 42, title: 'Harvest Festival', is_active: '1'},
          needs: state.needs, assignments: state.assignments,
          capacity: {project_id: 42, filled: state.filled, total: state.total, by_need: state.byNeed},
          supporting: {workflow: {roles, statuses, visibility: {public: '1', admin: '2'}}, project: {}},
          beneficiary_names: [],
        }];
      }
      if (entity === 'VolunteerAssignment' && action === 'get') { return state.assignments; }
      if (entity === 'VolunteerAssignment' && action === 'getCapacity') {
        return [{project_id: 42, filled: state.filled, total: state.total, by_need: state.byNeed}];
      }
      if (entity === 'VolunteerAssignment') { return [{id: 950}]; }
      if (entity === 'VolunteerNeed' && action === 'create') { return [{id: 55}]; }
      throw new Error('unexpected ' + entity + '.' + action);
    });
  }

  function defaultState() {
    return {needs: baseNeeds(), assignments: baseAssignments(), filled: 3, total: 4, byNeed: {30: 2, 31: 1}};
  }

  async function mount(state = defaultState(), model) {
    respond(state);
    const {$compile, $controller, $rootScope} = services('$compile', '$controller', '$rootScope');
    const scope = $rootScope.$new();
    if (model) { scope.model = model; }
    $controller('VolunteerAssign', {$scope: scope, $route: routeFor({projectId: '42'})});
    const element = render($compile, scope, partial('Assign.html'));
    await settle($rootScope);
    return {element, scope, $rootScope, state};
  }

  const rail = (element) => element.find('.crm-vol-rail-shift');
  const assignedRows = (element) => element.find('.crm-vol-assigned-row');
  const openSpots = (element) => element.find('.crm-vol-open-spot');
  const pool = (element) => element.find('.crm-vol-available-card-item');
  const metrics = (element) => element.find('.crm-vol-assign-metrics strong').map((i, el) => text(el)).get();

  test('renders the metrics, the shift rail with the first shift selected, its people and open spots, and the pool', async () => {
    const {element} = await mount();

    // assigned 2 + 1 across counted shifts; 4 spots in total, so 1 open; 2 waiting in the pool.
    expect(metrics(element)).toEqual(['3', '1', '2']);

    expect(rail(element).length).toBe(2);
    expect(rail(element).eq(0).hasClass('is-selected')).toBe(true);
    expect(rail(element).eq(0).find('button').attr('aria-current')).toBe('true');
    expect(text(rail(element).eq(0).find('.crm-vol-capacity-summary'))).toBe('2 of 3 filled');
    expect(rail(element).eq(1).hasClass('is-full')).toBe(true);
    expect(text(rail(element).eq(1).find('.crm-vol-rail-role'))).toBe('Usher');

    const panel = element.find('.crm-vol-selected-shift');
    expect(text(panel.find('h3').first())).toBe('Greeter');
    expect(text(panel.find('header .crm-vol-capacity-summary'))).toBe('2 of 3 spots filled');
    expect(assignedRows(element).map((i, r) => text(CRM.$(r).find('td').eq(0))).get()).toEqual(['AL Ada Lovelace', 'GH Grace Hopper']);
    expect(assignedRows(element).eq(0).find('a').attr('href')).toBe(CRM.url('civicrm/contact/view', {reset: 1, cid: 202}));
    expect(assignedRows(element).eq(0).attr('data-assignment-id')).toBe('900');
    expect(openSpots(element).length).toBe(1);

    expect(pool(element).length).toBe(2);
    expect(text(pool(element).eq(1).find('a'))).toBe('Jean van der Meulen');
    expect(text(pool(element).eq(1).find('.crm-vol-avatar'))).toBe('JV');
    expect(ui.button(pool(element).eq(0), 'Assign to Greeter — Tue 25 Aug 9:00 AM').length).toBe(1);
    expect(ui.button(pool(element).eq(0), 'Assign to Usher — Tue 25 Aug 1:00 PM').length).toBe(1);
  });

  test('picking another shift in the rail changes the selected panel', async () => {
    const {element} = await mount();
    ui.click(rail(element).eq(1).find('button'));
    expect(rail(element).eq(1).hasClass('is-selected')).toBe(true);
    expect(rail(element).eq(0).hasClass('is-selected')).toBe(false);
    expect(text(element.find('.crm-vol-selected-shift h3').first())).toBe('Usher');
    expect(assignedRows(element).length).toBe(1);
    expect(openSpots(element).length).toBe(0);
    expect(text(element.find('.crm-vol-selected-shift header .crm-vol-capacity-summary'))).toBe('1 of 1 spots filled');
  });

  test('a shift with nobody and no spots left says so, and a project without shifts points to the Shifts step', async () => {
    const {$location} = services('$location');
    const empty = await mount({needs: [need(30, {quantity: null})], assignments: [], filled: 0, total: 0, byNeed: {}});
    expect(text(empty.element.find('.crm-vol-empty-row'))).toBe('Nobody is assigned to this shift yet.');
    expect(text(empty.element.find('.crm-vol-selected-shift header .crm-vol-capacity-summary'))).toBe('No capacity limit');

    const none = await mount({needs: [need(33, {is_flexible: 1})], assignments: [], filled: 0, total: 0, byNeed: {}});
    expect(text(none.element.find('.messages.status'))).toContain('This project has no shifts yet.');
    ui.click(ui.button(none.element, 'Set up shifts and roles'));
    expect($location.path()).toBe('/volunteer/manage/42/shifts');
  });

  test('moving a volunteer updates the lists and counts at once and writes the target shift values', async () => {
    const {element, $rootScope} = await mount();
    const adaMenu = assignedRows(element).eq(0).find('.crm-vol-actions-menu');

    // The Usher shift is full: refused before any request.
    ui.click(ui.button(adaMenu, 'Move to Usher — Tue 25 Aug 1:00 PM'));
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({title: 'No open spots'}));
    expect(recorders.api.callsTo('VolunteerAssignment')).toHaveLength(0);

    ui.click(ui.button(adaMenu, 'Move to Available volunteers'));
    // Optimistic: the DOM and metrics change before the server answers.
    expect(assignedRows(element).length).toBe(1);
    expect(pool(element).length).toBe(3);
    expect(metrics(element)).toEqual(['2', '2', '3']);
    expect(text(rail(element).eq(0).find('.crm-vol-capacity-summary'))).toBe('1 of 3 filled');
    expect(recorders.api.last('VolunteerAssignment', 'update').params).toEqual({
      where: [['id', '=', 900]],
      values: {volunteer_need_id: 33, status_id: 1, activity_date_time: '2026-08-01 00:00:00', time_scheduled_minutes: null, volunteer_role_id: 3},
    });
    expect(recorders.statuses[0]).toEqual({start: 'Saving assignment…', success: 'Assignment saved'});
    await settle($rootScope);
    expect(pool(element).length).toBe(3);
  });

  test('a failed move puts the volunteer back', async () => {
    const {element, $rootScope} = await mount();
    recorders.api.respond(() => { throw {error_message: 'Shift is closed'}; });
    ui.click(ui.button(assignedRows(element).eq(0).find('.crm-vol-actions-menu'), 'Move to Available volunteers'));
    expect(assignedRows(element).length).toBe(1);
    await settle($rootScope);
    expect(assignedRows(element).length).toBe(2);
    expect(text(assignedRows(element).eq(0).find('a'))).toBe('Ada Lovelace');
    expect(metrics(element)).toEqual(['3', '1', '2']);
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({text: 'Shift is closed', title: 'Not saved'}));
  });

  test('removing a volunteer asks by name and deletes on Yes', async () => {
    const {element, $rootScope} = await mount();
    ui.click(ui.button(assignedRows(element).eq(1).find('.crm-vol-actions-menu'), 'Remove from this shift'));
    expect(recorders.confirmations[0].options.message).toBe('Remove Grace Hopper from this volunteer opportunity?');
    expect(assignedRows(element).length).toBe(2);

    recorders.confirmations[0].answer('crmConfirm:yes');
    $rootScope.$digest();
    expect(assignedRows(element).length).toBe(1);
    expect(openSpots(element).length).toBe(2);
    expect(recorders.api.last('VolunteerAssignment', 'delete').params).toEqual({where: [['id', '=', 901]]});
    await settle($rootScope);
    expect(metrics(element)).toEqual(['2', '2', '2']);
  });

  test('adding someone to a shift through the search box creates the assignment and reloads the people', async () => {
    const {element, $rootScope, state} = await mount();
    const search = element.find('#crm-vol-assign-search');
    expect(search.length).toBe(1);

    state.assignments.push(assignment(950, 30, 207, 'Bo Peep'));
    state.byNeed[30] = 3;
    // The entity-reference widget hands its selection to ngModel; this is
    // that hand-off without driving select2's own DOM.
    search.controller('ngModel').$setViewValue('207');
    await settle($rootScope);

    expect(recorders.api.last('VolunteerAssignment', 'create').params.values).toEqual({
      volunteer_need_id: 30, status_id: 2, activity_date_time: '2026-08-25 09:00:00',
      time_scheduled_minutes: 90, volunteer_role_id: 3, contact_id: 207,
    });
    expect(assignedRows(element).length).toBe(3);
    expect(text(assignedRows(element).eq(2).find('a'))).toBe('Bo Peep');
    expect(openSpots(element).length).toBe(0);
    expect(rail(element).eq(0).hasClass('is-full')).toBe(true);
  });

  test('a pick the widget reports twice still creates one assignment', async () => {
    const {element, $rootScope, state} = await mount();
    const search = element.find('#crm-vol-assign-search');
    state.assignments.push(assignment(950, 30, 207, 'Bo Peep'));
    state.byNeed[30] = 3;
    // Both of the widget's change listeners commit the same contact before
    // the first request has answered.
    search.controller('ngModel').$setViewValue('207');
    search.controller('ngModel').$setViewValue('207');
    await settle($rootScope);
    expect(recorders.api.callsTo('VolunteerAssignment', 'create')).toHaveLength(1);
    expect(assignedRows(element).length).toBe(3);

    // Once the first has landed, the same person can be added again on purpose.
    search.controller('ngModel').$setViewValue('207');
    await settle($rootScope);
    expect(recorders.api.callsTo('VolunteerAssignment', 'create')).toHaveLength(2);
  });

  test('"Also add to" copies the assignment, keeping the note', async () => {
    const {element, $rootScope} = await mount();
    ui.click(ui.button(assignedRows(element).eq(0).find('.crm-vol-actions-menu'), 'Also add to Available volunteers'));
    await settle($rootScope);
    expect(recorders.api.last('VolunteerAssignment', 'create').params.values).toEqual(expect.objectContaining({
      volunteer_need_id: 33, status_id: 1, contact_id: 202, details: 'morning',
    }));
  });

  test('dropping a card on a shift goes through the same move as the menu', async () => {
    const {element, $rootScope} = await mount();
    const railTarget = rail(element).eq(1);
    const drop = railTarget.droppable('option', 'drop');
    expect(typeof drop).toBe('function');

    // Onto the full Usher shift: refused.
    drop.call(railTarget[0], {}, {draggable: pool(element).eq(0)});
    $rootScope.$digest();
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({title: 'No open spots'}));

    // Onto the selected shift's open spot: moved.
    const spot = openSpots(element).first();
    spot.droppable('option', 'drop').call(spot[0], {}, {draggable: pool(element).eq(0)});
    $rootScope.$digest();
    expect(recorders.api.last('VolunteerAssignment', 'update').params.where).toEqual([['id', '=', 903]]);
    expect(assignedRows(element).length).toBe(3);
    expect(pool(element).length).toBe(1);
  });

  test('"Add a shift" creates one with the first role and selects it after reloading', async () => {
    const {element, $rootScope, state} = await mount();
    state.needs.push(need(55, {quantity: 1, start_time: '2026-08-19 00:00:00', display_time: 'Wed 19 Aug 12:00 AM'}));
    ui.click(ui.button(element, 'Add a shift'));
    await settle($rootScope);
    const values = recorders.api.last('VolunteerNeed', 'create').params.values;
    expect(values).toEqual(expect.objectContaining({project_id: 42, role_id: 3, quantity: 1, is_active: true, is_flexible: false, duration: 60}));
    expect(values.start_time).toMatch(/^\d{4}-\d{2}-\d{2} 00:00:00$/);
    expect(rail(element).length).toBe(3);
    expect(rail(element).eq(2).hasClass('is-selected')).toBe(true);
  });

  test('"Log hours for this shift" goes to the Hours step scoped to the shift', async () => {
    const {$location} = services('$location');
    const {element} = await mount();
    ui.click(element.find('.crm-vol-selected-shift-footer button'));
    expect($location.path()).toBe('/volunteer/manage/42/hours');
    expect($location.search()).toEqual({needId: 30});
  });
});
