'use strict';

// The <volunteer-workflow-shell> component: the header, step tabs and
// navigation guard every workflow step renders inside. Compiled from
// WorkflowShell.html against a real scope, so what is asserted is the DOM the
// user sees and the navigation Angular actually performs.

const {boot, services, settle, render, text, fakeWindow} = require('./support');

describe('volunteer workflow shell', () => {
  const browser = fakeWindow();
  const recorders = boot({configure: ($provide) => { $provide.value('$window', browser); }});

  function workflowState(overrides) {
    return Object.assign({
      step: 'shifts',
      projectId: 42,
      project: {id: 42, title: 'Harvest Festival', is_active: true},
      summary: {filled: 2, total: 5},
      beneficiaryNames: ['Friends of the Park'],
      manualSave: false,
      autoSave: false,
      formContext: 'standAlone',
      isDirty: () => false,
    }, overrides);
  }

  function mount(workflow) {
    const {$compile, $rootScope} = services('$compile', '$rootScope');
    const scope = $rootScope.$new();
    scope.workflow = workflow;
    const element = render($compile, scope,
      '<volunteer-workflow-shell workflow="workflow"><p class="body">step body</p></volunteer-workflow-shell>');
    return {element, scope, $rootScope};
  }

  test('owning the page, it renders breadcrumb, heading, project meta and the tab bar around the step body', () => {
    const {element} = mount(workflowState());
    expect(element.find('.crm-vol-workflow').hasClass('crm-vol-workflow--embedded')).toBe(false);
    expect(text(element.find('.crm-vol-breadcrumb'))).toBe('Volunteer projects / Harvest Festival');
    expect(element.find('.crm-vol-breadcrumb a').attr('href')).toBe('#/volunteer/manage');
    expect(text(element.find('h1'))).toBe('Harvest Festival');
    expect(text(element.find('.crm-vol-project-meta'))).toContain('Beneficiary: Friends of the Park');
    expect(text(element.find('.crm-vol-capacity-meta'))).toBe('2 of 5 spots filled');
    expect(element.find('nav ul').hasClass('nav-tabs')).toBe(true);
    expect(text(element.find('.crm-vol-workflow-content p.body'))).toBe('step body');
  });

  test('embedded in the event tab, the host already supplies breadcrumb and heading, so the shell drops them', () => {
    const {element} = mount(workflowState({formContext: 'eventTab'}));
    expect(element.find('.crm-vol-workflow').hasClass('crm-vol-workflow--embedded')).toBe(true);
    expect(element.find('.crm-vol-breadcrumb').length).toBe(0);
    expect(element.find('h1').length).toBe(0);
    expect(element.find('nav ul').hasClass('crm-vol-workflow-steps')).toBe(true);
    expect(element.find('nav ul').hasClass('nav-tabs')).toBe(false);
  });

  test('the hours report tab appears only for a user who may see contacts across projects', () => {
    const labels = (element) => element.find('nav li a').map((i, a) => text(a)).get();
    const withReport = mount(workflowState());
    expect(labels(withReport.element)).toEqual(['Details', 'Shifts & roles', 'Assign volunteers', 'Roster', 'Hours', 'Hours report']);
    expect(withReport.element.find('nav li.active a').text().trim()).toBe('Shifts & roles');
    expect(withReport.element.find('nav li.active a').attr('aria-current')).toBe('page');

    CRM.permissions['view all contacts'] = false;
    const withoutReport = mount(workflowState());
    expect(labels(withoutReport.element)).toEqual(['Details', 'Shifts & roles', 'Assign volunteers', 'Roster', 'Hours']);
  });

  test('before the project exists the other tabs are disabled and preview is unavailable', () => {
    const {element} = mount(workflowState({step: 'details', projectId: 0, project: {id: 0, title: '', is_active: null}}));
    expect(text(element.find('h1'))).toBe('New volunteer project');
    const disabled = element.find('nav li.disabled a').map((i, a) => text(a)).get();
    expect(disabled).toEqual(['Shifts & roles', 'Assign volunteers', 'Roster', 'Hours', 'Hours report']);
    expect(element.find('nav li.disabled a').first().attr('aria-disabled')).toBe('true');
    expect(element.find('.crm-vol-workflow-actions button').first().prop('disabled')).toBe(true);
  });

  test('the status select is withheld until the real status is known, then saves a change immediately', async () => {
    const unknown = mount(workflowState({project: {id: 42, title: 'Harvest Festival', is_active: null}}));
    expect(unknown.element.find('#crm-vol-project-status').length).toBe(0);

    const {element, scope, $rootScope} = mount(workflowState());
    const select = element.find('#crm-vol-project-status');
    expect(select.length).toBe(1);
    expect(select.find('option').map((i, o) => text(o)).get()).toEqual(['Active', 'Inactive']);
    expect(select.val()).toBe('boolean:true');

    recorders.api.respond(() => [{id: 42, is_active: false}]);
    select.val('boolean:false').trigger('change');
    expect(scope.workflow.project.is_active).toBe(false);
    expect(select.prop('disabled')).toBe(true);
    await settle($rootScope);

    expect(recorders.api.last('VolunteerProject', 'update').params).toEqual({
      where: [['id', '=', 42]],
      values: {is_active: false},
    });
    expect(recorders.statuses[0]).toEqual({start: 'Saving status...', success: 'Status saved'});
    expect(select.prop('disabled')).toBe(false);
  });

  test('a failed status save restores the previous value', async () => {
    const {element, scope, $rootScope} = mount(workflowState());
    recorders.api.respond(() => { throw {error_message: 'Not allowed'}; });
    element.find('#crm-vol-project-status').val('boolean:false').trigger('change');
    await settle($rootScope);
    expect(scope.workflow.project.is_active).toBe(true);
    expect(element.find('#crm-vol-project-status').val()).toBe('boolean:true');
  });

  test('clicking a tab navigates; on a dirty step it asks first and only navigates after Yes', async () => {
    const {$location, volWorkflow} = services('$location', 'volWorkflow');
    const clean = mount(workflowState());
    clean.element.find('nav li a').eq(2).trigger('click');
    expect($location.path()).toBe('/volunteer/manage/42/assign');
    expect(recorders.confirmations).toHaveLength(0);
    // The shell's own guard saw that navigation and consumed the one-shot flag.
    expect(volWorkflow.consumeAppNavigation()).toBe(false);

    const dirty = mount(workflowState({step: 'details', isDirty: () => true}));
    dirty.element.find('nav li a').eq(3).trigger('click');
    expect($location.path()).toBe('/volunteer/manage/42/assign');
    expect(recorders.confirmations).toHaveLength(1);
    expect(recorders.confirmations[0].options.title).toBe('Discard unsaved changes?');

    recorders.confirmations[0].answer('crmConfirm:yes');
    await settle(dirty.$rootScope, 2);
    expect($location.path()).toBe('/volunteer/manage/42/roster');
  });

  test('browser navigation away from a dirty step is held until the user confirms, then let through', async () => {
    const markPristine = jest.fn();
    const {element, $rootScope} = mount(workflowState({isDirty: () => true, markPristine}));
    const next = 'https://example.test/civicrm/volunteer/manage#/volunteer/manage/42/roster';

    const event = $rootScope.$broadcast('$locationChangeStart', next, 'https://example.test/civicrm/volunteer/manage#/volunteer/manage/42/shifts');
    expect(event.defaultPrevented).toBe(true);
    expect(recorders.confirmations).toHaveLength(1);
    expect(browser.location.href).toBe('');

    recorders.confirmations[0].answer('crmConfirm:yes');
    await settle($rootScope, 2);
    expect(markPristine).toHaveBeenCalledTimes(1);
    expect(browser.location.href).toBe(next);
    // The prevented route change left the page blocked; the guard lifts it so
    // the confirmation is clickable.
    expect(!!CRM.$('#crm-main-content-wrapper').data('blockUI.isBlocked')).toBe(false);
    expect(element.find('.crm-vol-workflow').length).toBe(1);
  });

  test('navigation the app itself performs is never challenged', () => {
    const {$rootScope, volWorkflow} = services('$rootScope', 'volWorkflow');
    mount(workflowState({isDirty: () => true}));
    volWorkflow.navigate('/volunteer/manage/42/assign');
    const event = $rootScope.$broadcast('$locationChangeStart', 'https://example.test/#/volunteer/manage/42/assign', 'https://example.test/#/volunteer/manage/42/shifts');
    expect(event.defaultPrevented).toBe(false);
    expect(recorders.confirmations).toHaveLength(0);
  });

  test('a clean step is not challenged by browser navigation', () => {
    const {$rootScope} = mount(workflowState());
    const event = $rootScope.$broadcast('$locationChangeStart', 'https://example.test/#/x', 'https://example.test/#/y');
    expect(event.defaultPrevented).toBe(false);
  });

  test('the action row reflects the step: manual save button, autosave indicator, preview', () => {
    const save = jest.fn();
    const manual = mount(workflowState({manualSave: true, save}));
    const saveButton = manual.element.find('.crm-vol-workflow-actions button.button');
    expect(text(saveButton)).toBe('Save changes');
    saveButton.trigger('click');
    expect(save).toHaveBeenCalledTimes(1);
    expect(manual.element.find('.crm-vol-autosave-state').length).toBe(0);

    const auto = mount(workflowState({autoSave: true, pending: true}));
    expect(text(auto.element.find('.crm-vol-autosave-state'))).toBe('Saving');
    expect(auto.element.find('.crm-vol-autosave-state i').hasClass('fa-spinner')).toBe(true);
    auto.scope.workflow.pending = false;
    auto.scope.workflow.saveError = true;
    auto.scope.$digest();
    expect(text(auto.element.find('.crm-vol-autosave-state'))).toBe('Not saved');
    auto.scope.workflow.saveError = false;
    auto.scope.$digest();
    expect(text(auto.element.find('.crm-vol-autosave-state'))).toBe('Changes saved');

    auto.element.find('.crm-vol-workflow-actions button').first().trigger('click');
    expect(browser.open).toHaveBeenCalledWith(
      CRM.url('civicrm/vol/', '', 'front') + '#/volunteer/opportunities?project=42&hideSearch=1', '_blank', 'noopener'
    );
  });

  test('Cancel on a step other than Details returns to the project list, asking first when dirty', async () => {
    const {$location} = services('$location');
    const {element, $rootScope} = mount(workflowState({isDirty: () => true}));
    const cancel = element.find('.crm-vol-workflow-actions button').filter((i, b) => text(b) === 'Cancel');
    expect(cancel.length).toBe(1);
    cancel.trigger('click');
    expect(recorders.confirmations).toHaveLength(1);
    recorders.confirmations[0].answer('crmConfirm:yes');
    await settle($rootScope, 2);
    expect($location.path()).toBe('/volunteer/manage');

    const details = mount(workflowState({step: 'details'}));
    expect(details.element.find('.crm-vol-workflow-actions button').filter((i, b) => text(b) === 'Cancel').length).toBe(0);
  });
});
