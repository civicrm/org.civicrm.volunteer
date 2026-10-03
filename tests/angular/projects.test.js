'use strict';

// Manage Volunteer Projects: VolunteerProjects driving Projects.html with
// the list and dashboard partials. The list filters, bulk actions, dashboard
// queues and the dialogs they open are asserted through the DOM.

const {boot, services, settle, render, text, partial, ui} = require('./support');

describe('Manage volunteer projects', () => {
  const dialogs = {opened: []};
  const recorders = boot({configure: ($provide) => {
    // The workflow dialogs are covered in dialog.test.js; here only the
    // request to open one and the refresh afterwards matter.
    $provide.decorator('dialogService', ($delegate, $q) => {
      $delegate.open = (name, template, model, options) => {
        dialogs.opened.push({name, template, model, options});
        return $q.resolve('closed');
      };
      return $delegate;
    });
  }});

  function overview() {
    return {
      summary: {active_projects: 2, filled_spots_14_days: 3, total_spots_14_days: 5, projects_short: 1, shifts_awaiting_hours: 2},
      projects: [
        {
          id: 1, title: 'Spring Fair', is_active: 1, campaign_id: 11, campaign_label: 'Spring Drive',
          beneficiaries: [101, 102], beneficiary_names: ['Ada Lovelace', 'Grace Hopper'], beneficiary_options: {101: 'Ada Lovelace', 102: 'Grace Hopper'},
          upcoming_roles: ['Greeter', 'Usher'], next_shift: {start_time: '2026-08-25 14:30:00'},
          staffing: {filled: 2, total: 5, open: 3}, needs_volunteers: true,
          associated_entity: {entity_table: 'civicrm_event', entity_id: 5, title: 'City Marathon'},
        },
        {
          id: 2, title: 'River Cleanup', is_active: 0, campaign_id: '', beneficiaries: [], beneficiary_names: [], beneficiary_options: {},
          upcoming_roles: [], next_shift: null, staffing: {filled: 0, total: 0, open: 0},
        },
        {
          id: 3, title: 'Library Night', is_active: 1, campaign_id: 12, campaign_label: 'Autumn Drive',
          beneficiaries: [102], beneficiary_names: ['Grace Hopper'], beneficiary_options: {102: 'Grace Hopper'},
          upcoming_roles: ['Shelver'], next_shift: {start_time: '2026-08-27 18:00:00'}, staffing: {filled: 2, total: 2, open: 0},
        },
      ],
      attention: [
        {action_type: 'staffing', project_id: 1, need_id: 9, project_title: 'Spring Fair', role_label: 'Greeter', open: 3, total: 5, display_time: 'Tue 25 Aug 2:30 PM'},
        {action_type: 'hours', project_id: 3, need_id: 12, project_title: 'Library Night', role_label: 'Shelver', missing_hours: 2},
      ],
      up_next: [{project_id: 1, project_title: 'Spring Fair', start_time: '2026-08-25 14:30:00', filled: 2, total: 5, open: 3}],
      this_week: [{need_id: 9, start_time: '2026-08-25 14:30:00', role_label: 'Greeter', project_title: 'Spring Fair', filled: 2, total: 5, open: 3}],
    };
  }

  function mount(options = {}) {
    dialogs.opened.length = 0;
    recorders.api.respond((entity, action) => {
      if (entity === 'VolunteerProject' && action === 'getManageOverview') { return [options.refreshed || overview()]; }
      if (entity === 'VolunteerProject') { return [{}]; }
      throw new Error('unexpected ' + entity + '.' + action);
    });
    const {$compile, $controller, $rootScope, $location} = services('$compile', '$controller', '$rootScope', '$location');
    if (options.view) { $location.search({view: options.view}); }
    const scope = $rootScope.$new();
    $controller('VolunteerProjects', {$scope: scope, projectOverview: options.overview || overview()});
    const element = render($compile, scope, partial('Projects.html'));
    return {element, scope, $rootScope, $location};
  }

  const listRows = (element) => element.find('#crm-vol-project-list tbody tr');
  const titles = (element) => listRows(element).map((i, tr) => text(CRM.$(tr).find('.crm-vol-manage-project-title'))).get();

  test('renders the summary tiles and the active projects with staffing, context and links', () => {
    const {element} = mount();
    expect(element.find('.crm-vol-manage-metric strong').map((i, s) => text(s)).get()).toEqual(['2', '3 / 5', '1', '2']);
    expect(element.find('a[href="#/volunteer/manage/0/details"]').length).toBe(1);
    expect(element.find('a.button').filter((i, a) => text(a) === 'Hours report').attr('href')).toBe(CRM.url('civicrm/volunteer/hours-report'));
    expect(ui.shown(element.find('.help'))).toBe(false);

    expect(titles(element)).toEqual(['Spring Fair', 'Library Night']);
    const spring = listRows(element).eq(0);
    expect(spring.find('.crm-vol-needs-badge').length).toBe(1);
    expect(text(spring.find('.crm-vol-manage-project-subtitle'))).toBe('Greeter, Usher');
    expect(spring.find('.crm-vol-manage-project-event').attr('href')).toBe(CRM.url('civicrm/event/manage/settings', 'reset=1&action=update&id=5'));
    expect(text(spring.find('.crm-vol-manage-project-event'))).toBe('City Marathon');
    expect(text(spring.find('.crm-vol-manage-project-campaign'))).toBe('Spring Drive');
    expect(text(spring.find('td').eq(2))).toBe('Ada Lovelace, Grace Hopper');
    expect(text(spring.find('td').eq(3))).toBe('Tue Aug 25, 2:30 PM');
    expect(text(spring.find('.crm-vol-staffing'))).toBe('2 of 5 filled 3 spots open');
    expect(spring.find('.crm-vol-staffing strong').hasClass('is-short')).toBe(true);
    expect(spring.find('a[target=_blank]').attr('href')).toBe(CRM.url('civicrm/vol/', '', 'front') + '#/volunteer/opportunities?project=1&hideSearch=1');

    const library = listRows(element).eq(1);
    expect(text(library.find('.crm-vol-staffing'))).toBe('2 of 2 filled fully staffed');
    expect(library.find('.crm-vol-staffing strong').hasClass('is-full')).toBe(true);
    expect(text(element.find('.crm-vol-list-footer'))).toBe('Showing 2 of 3 projects show archived');
  });

  test('the filters narrow the list: text, status, beneficiary, campaign', () => {
    const {element} = mount();
    ui.click(ui.button(element.find('.crm-vol-list-footer'), 'show archived'));
    expect(titles(element)).toEqual(['Spring Fair', 'River Cleanup', 'Library Night']);
    expect(listRows(element).eq(1).hasClass('is-archived')).toBe(true);
    expect(text(listRows(element).eq(1).find('.crm-vol-archived-badge'))).toBe('Archived');

    ui.choose(element.find('.crm-vol-status-toggle input[value=archived]'));
    expect(titles(element)).toEqual(['River Cleanup']);

    ui.choose(element.find('.crm-vol-status-toggle input[value=active]'));
    ui.type(element.find('#crm-vol-project-search'), 'marathon');
    expect(titles(element)).toEqual(['Spring Fair']);
    ui.type(element.find('#crm-vol-project-search'), 'shelver');
    expect(titles(element)).toEqual(['Library Night']);
    ui.type(element.find('#crm-vol-project-search'), 'zzz');
    expect(titles(element)).toEqual([]);
    expect(text(element.find('.crm-vol-empty-state').filter((i, el) => ui.shown(el)).find('strong'))).toBe('No projects match these filters');

    ui.click(ui.button(element, 'Reset filters'));
    expect(titles(element)).toEqual(['Spring Fair', 'Library Night']);

    expect(ui.options(element.find('.crm-vol-manage-filters select'))).toEqual(['Any beneficiary', 'Ada Lovelace', 'Grace Hopper']);
    ui.pick(element.find('.crm-vol-manage-filters select'), '101');
    expect(titles(element)).toEqual(['Spring Fair']);
  });

  test('bulk actions need a selection, confirm, act on each project and refresh the list', async () => {
    const refreshed = overview();
    refreshed.projects[0].is_active = 0;
    refreshed.summary.active_projects = 1;
    const {element, $rootScope} = mount({refreshed});
    const apply = ui.button(element.find('.crm-vol-bulk-bar'), 'Apply');
    const action = element.find('#batchAction');
    expect(apply.prop('disabled')).toBe(true);
    expect(action.prop('disabled')).toBe(true);
    expect(ui.options(action)).toEqual(['Bulk actions…', 'Activate', 'Archive', 'Delete']);

    ui.check(listRows(element).eq(0).find('input[type=checkbox]'), true);
    expect(text(element.find('.crm-vol-bulk-count'))).toBe('1 selected');
    ui.pick(action, 'archive');
    expect(apply.prop('disabled')).toBe(false);

    ui.click(apply);
    expect(recorders.confirmations[0].options.message).toBe('Archive the selected projects?');
    expect(recorders.api.callsTo('VolunteerProject', 'update')).toHaveLength(0);

    recorders.confirmations[0].answer('crmConfirm:yes');
    await settle($rootScope);
    expect(recorders.api.last('VolunteerProject', 'update').params).toEqual({where: [['id', '=', 1]], values: {is_active: false}});
    expect(recorders.statuses[0]).toEqual({start: 'Archiving projects...', success: 'Projects archived'});
    expect(recorders.api.callsTo('VolunteerProject', 'getManageOverview')).toHaveLength(1);
    expect(titles(element)).toEqual(['Library Night']);
    expect(element.find('.crm-vol-manage-metric strong').first().text()).toBe('1');
    expect(action.val()).toBe('');
  });

  test('selecting every visible project and then changing a filter clears the selection', () => {
    const {element, scope} = mount();
    ui.check(element.find('#crm-vol-project-list thead input[type=checkbox]'), true);
    expect(scope.selectedProjectCount()).toBe(2);
    expect(listRows(element).find('input[type=checkbox]:checked').length).toBe(2);
    ui.type(element.find('#crm-vol-project-search'), 'spring');
    expect(scope.selectedProjectCount()).toBe(0);
    expect(element.find('#crm-vol-project-list thead input[type=checkbox]').prop('checked')).toBe(false);
  });

  test('the dashboard shows the attention queue, up next and this week, and its actions open the right dialog', async () => {
    const {element, $rootScope, $location} = mount();
    ui.click(element.find('.crm-vol-view-toggle button').eq(1));
    expect($location.search()).toEqual({view: 'dashboard'});
    expect(element.find('#crm-vol-project-list').length).toBe(0);

    const cards = element.find('.crm-vol-attention-card');
    expect(cards.length).toBe(2);
    expect(text(cards.eq(0).find('h3'))).toBe('Spring Fair · Greeter');
    expect(text(cards.eq(0).find('p'))).toBe('3 of 5 spots still open for Tue 25 Aug 2:30 PM.');
    expect(text(cards.eq(1).find('p'))).toBe('Hours have not been logged for 2 volunteer(s).');
    expect(text(element.find('.crm-vol-up-next-grid article a'))).toBe('Spring Fair');
    expect(text(element.find('.crm-vol-up-next-grid article time'))).toBe('Tue Aug 25, 2:30 PM');
    expect(text(element.find('.crm-vol-this-week li time'))).toBe('Tue 25');
    expect(text(element.find('.crm-vol-this-week li span').last())).toBe('2 of 5');

    ui.click(ui.button(cards.eq(0), 'Fill spots'));
    expect(dialogs.opened[0]).toEqual(expect.objectContaining({
      name: 'volunteerWorkflowDialog',
      template: '~/volunteer/WorkflowDialog.html',
      model: expect.objectContaining({step: 'assign', projectId: 1, scopeNeedId: 9, dialogMode: true}),
    }));
    expect(dialogs.opened[0].options.title).toBe('Assign volunteers');
    await settle($rootScope);
    expect(recorders.api.callsTo('VolunteerProject', 'getManageOverview')).toHaveLength(1);

    ui.click(ui.button(cards.eq(1), 'Log hours'));
    expect(dialogs.opened[1].model).toEqual(expect.objectContaining({step: 'hours', projectId: 3, scopeNeedId: 12}));
  });

  test('the row actions menu opens each workflow dialog for that project', () => {
    const {element} = mount();
    const menu = listRows(element).eq(0).find('.crm-vol-actions-menu');
    ui.click(ui.button(menu, 'Shifts & roles'));
    ui.click(ui.button(menu, 'View roster'));
    expect(dialogs.opened.map((d) => d.model.step)).toEqual(['shifts', 'roster']);
    expect(dialogs.opened[0].model.projectId).toBe(1);
    expect(dialogs.opened[0].model.needRegistry).toEqual({clean: [], created: [], updated: [], deleted: []});
  });

  test('an empty site shows the getting-started message', () => {
    const {element} = mount({overview: {summary: {}, projects: [], attention: [], up_next: [], this_week: []}});
    expect(text(element.find('.crm-vol-empty-state').filter((i, el) => ui.shown(el)).find('strong'))).toBe('No volunteer projects yet');
    expect(ui.shown(element.find('.crm-vol-bulk-bar'))).toBe(false);
  });

  test('with CiviCampaign off there is no campaign filter and no campaign label', () => {
    CRM.volunteer.isCampaignEnabled = false;
    try {
      const {element} = mount();
      expect(element.find('.crm-vol-manage-filters .crm-form-entityref').length).toBe(0);
      expect(element.find('.crm-vol-manage-project-campaign').length).toBe(0);
    }
    finally {
      CRM.volunteer.isCampaignEnabled = true;
    }
  });
});

describe('Manage volunteer projects for an editor of their own projects only', () => {
  boot({permissions: ['access CiviCRM', 'edit own volunteer projects']});

  test('the page says it shows only their projects and hides the hours report', () => {
    const {$compile, $controller, $rootScope} = services('$compile', '$controller', '$rootScope');
    const scope = $rootScope.$new();
    $controller('VolunteerProjects', {$scope: scope, projectOverview: {summary: {}, projects: [], attention: [], up_next: [], this_week: []}});
    const element = render($compile, scope, partial('Projects.html'));
    expect(ui.shown(element.find('.help'))).toBe(true);
    expect(element.find('a.button').filter((i, a) => text(a) === 'Hours report').length).toBe(0);
  });
});
