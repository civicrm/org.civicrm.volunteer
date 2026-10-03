'use strict';

// Module wiring: the things the vm-based checks in tests/js cannot see.
// Every controller and factory is instantiated through Angular's real
// injector with the dependencies it declares, every route is registered with
// the template and resolves the workflow expects, and the two run blocks
// (host redirect, route spinner) behave against a real $rootScope.

const {boot, services, settle, routeFor, fakeWindow} = require('./support');

describe('volunteer module wiring', () => {
  const recorders = boot();

  test('the manifest declares every module the code injects from', angular.mock.inject(($injector) => {
    // A service the code injects that no required module provides fails
    // here with Angular's own "Unknown provider" error.
    for (const name of ['crmApi4', 'crmStatus', 'crmUiAlert', 'crmUiHelp', '$sanitize']) {
      expect(typeof $injector.get(name)).toBe('function');
    }
    expect(typeof $injector.get('$route').routes).toBe('object');
    expect(typeof $injector.get('dialogService').open).toBe('function');
    for (const name of ['volOppSearch', 'volWorkflow', 'volWorkflowDialog', 'volShiftFilters', 'volProjectManagementAccess', 'volHoursReportAccess']) {
      expect($injector.has(name)).toBe(true);
    }
  }));

  test('every route is registered with a template and its own controller', angular.mock.inject(($route) => {
    const routes = $route.routes;
    const expected = {
      '/volunteer/opportunities': 'VolOppsCtrl',
      '/volunteer/manage': 'VolunteerProjects',
      '/volunteer/manage/:projectId': 'VolunteerProject',
      '/volunteer/manage/:projectId/details': 'VolunteerProject',
      '/volunteer/manage/:projectId/shifts': 'VolunteerShifts',
      '/volunteer/manage/:projectId/assign': 'VolunteerAssign',
      '/volunteer/manage/:projectId/roster': 'VolunteerRoster',
      '/volunteer/manage/:projectId/hours': 'VolunteerHours',
      '/volunteer/manage/:projectId/report': 'VolunteerProjectHoursReport',
      '/volunteer/standalone/:projectId/:step': 'VolunteerStandaloneWorkflow',
    };
    for (const [path, controller] of Object.entries(expected)) {
      expect(routes[path]).toBeDefined();
      expect(routes[path].controller).toBe(controller);
      expect(routes[path].templateUrl).toMatch(/^~\/volunteer\/.+\.html$/);
      expect(CRM.angular.templates[routes[path].templateUrl]).toBeDefined();
    }
    // The management routes are guarded; the public one is not.
    expect(routes['/volunteer/opportunities'].resolve.projectManagementAccess).toBeUndefined();
    for (const path of Object.keys(expected).filter((p) => p.startsWith('/volunteer/manage'))) {
      expect(routes[path].resolve.projectManagementAccess).toBeDefined();
    }
    expect(routes['/volunteer/manage/:projectId/report'].resolve.volunteerHoursReportAccess).toBeDefined();
  }));

  test('the details route resolves its data the way the controller consumes it', async () => {
    const {$route, $injector, $rootScope} = services('$route', '$injector', '$rootScope');
    recorders.api.respond((entity, action, params, index) => {
      if (entity === 'VolunteerUtil' && action === 'getCountries') {
        expect(index).toBe('id');
        return {1228: {id: '1228', name: 'United States', is_default: '1'}};
      }
      if (entity === 'VolunteerProject' && action === 'search') {
        expect(params).toEqual({context: 'edit', filters: {id: '42'}});
        return [{id: 42, title: 'Harvest Festival', is_active: '1'}];
      }
      if (entity === 'VolunteerUtil' && action === 'getSupportingData') {
        return [{relationship_types: {}, defaults: {}}];
      }
      if (entity === 'VolunteerProjectContact') {
        return [{contact_id: 7, relationship_type_id: 3}, {contact_id: 9, relationship_type_id: 3}];
      }
      if (entity === 'VolunteerProject' && action === 'getLocationOptions') {
        return [{id: 5, title: 'Town Hall'}, {id: 8, title: 'Park'}];
      }
      throw new Error('unexpected ' + entity + '.' + action);
    });
    const resolve = $route.routes['/volunteer/manage/:projectId/details'].resolve;
    const locals = {$route: routeFor({projectId: '42'})};
    const results = {};
    for (const name of ['countries', 'project', 'supporting_data', 'relationship_data', 'location_blocks']) {
      $injector.invoke(resolve[name], null, locals).then((value) => { results[name] = value; });
    }
    await settle($rootScope);

    expect(results.project.title).toBe('Harvest Festival');
    expect(results.countries['1228'].name).toBe('United States');
    expect(results.relationship_data).toEqual({3: [7, 9]});
    expect(results.location_blocks).toEqual({5: 'Town Hall', 8: 'Park'});
    expect(results.supporting_data.defaults).toEqual({});
  });

  test('a details route for a project that does not exist reports it and resolves nothing', async () => {
    const {$route, $injector, $rootScope} = services('$route', '$injector', '$rootScope');
    recorders.api.respond((entity, action) => (entity === 'VolunteerProject' && action === 'search') ? [] : []);
    let project = 'unset';
    $injector.invoke($route.routes['/volunteer/manage/:projectId'].resolve.project, null, {$route: routeFor({projectId: '999'})})
      .then((value) => { project = value; });
    await settle($rootScope);
    expect(project).toBeUndefined();
    expect(recorders.alerts).toEqual([expect.objectContaining({
      title: 'Not Found',
      text: 'No volunteer project exists with an ID of 999',
      type: 'error',
    })]);
  });

  test('a new project (id 0) resolves without asking the server', async () => {
    const {$route, $injector, $rootScope, $q} = services('$route', '$injector', '$rootScope', '$q');
    let project;
    // ngRoute accepts a plain value from a resolve; this one answers without a promise.
    $q.when($injector.invoke($route.routes['/volunteer/manage/:projectId'].resolve.project, null, {$route: routeFor({projectId: '0'})}))
      .then((value) => { project = value; });
    await settle($rootScope);
    expect(project).toEqual({id: 0});
    expect(recorders.api.callsTo('VolunteerProject')).toEqual([]);
  });

  test('the route spinner blocks the content wrapper while a route loads', angular.mock.inject(($rootScope) => {
    const wrapper = CRM.$('#crm-main-content-wrapper');
    const blocked = () => !!wrapper.data('blockUI.isBlocked');
    $rootScope.$broadcast('$routeChangeStart');
    expect(blocked()).toBe(true);
    $rootScope.$broadcast('$routeChangeError');
    expect(blocked()).toBe(false);
  }));
});

describe('volunteer module on the public host', () => {
  const browser = fakeWindow();
  boot({isFrontend: true, configure: ($provide) => { $provide.value('$window', browser); }});

  test('a management route requested on the public host is redirected to the backend host', angular.mock.inject(($rootScope, $location) => {
    $location.url('/volunteer/manage/42/shifts?needId=3');
    const event = $rootScope.$broadcast('$routeChangeStart');
    expect(event.defaultPrevented).toBe(true);
    expect(browser.location.replace).toHaveBeenCalledTimes(1);
    expect(browser.location.replace.mock.calls[0][0])
      .toBe(CRM.url('civicrm/volunteer/manage', null, 'back') + '#/volunteer/manage/42/shifts?needId=3');
  }));

  test('the public opportunities route stays on the public host', angular.mock.inject(($rootScope, $location) => {
    $location.url('/volunteer/opportunities?project=4');
    const event = $rootScope.$broadcast('$routeChangeStart');
    expect(event.defaultPrevented).toBe(false);
  }));
});

describe('access guards', () => {
  describe('with no project permissions', () => {
    const recorders = boot({permissions: ['access CiviCRM', 'register to volunteer']});

    test('management routes are refused with an explanation', async () => {
      const {volProjectManagementAccess, $rootScope} = services('volProjectManagementAccess', '$rootScope');
      let outcome;
      Promise.resolve(volProjectManagementAccess()).then(() => { outcome = 'allowed'; }, (error) => { outcome = error; });
      await settle($rootScope, 2);
      expect(outcome).toEqual({is_error: 1, error_message: 'You do not have permission to manage volunteer projects.'});
      expect(recorders.alerts[0]).toEqual(expect.objectContaining({title: 'Access denied', type: 'error'}));
    });
  });

  describe('with edit-own only', () => {
    const recorders = boot({permissions: ['access CiviCRM', 'edit own volunteer projects']});

    test('management is allowed but the cross-project hours report is not', async () => {
      const {volProjectManagementAccess, volHoursReportAccess, $rootScope} = services('volProjectManagementAccess', 'volHoursReportAccess', '$rootScope');
      expect(volProjectManagementAccess()).toBe(true);
      let outcome;
      Promise.resolve(volHoursReportAccess()).then(() => { outcome = 'allowed'; }, (error) => { outcome = error; });
      await settle($rootScope, 2);
      expect(outcome.error_message).toBe('You do not have permission to view the volunteer hours report.');
      expect(recorders.alerts).toHaveLength(1);
    });
  });

  describe('with edit-all and view-all-contacts', () => {
    boot({permissions: ['access CiviCRM', 'edit all volunteer projects', 'view all contacts']});

    test('the hours report is allowed', angular.mock.inject((volHoursReportAccess) => {
      expect(volHoursReportAccess()).toBe(true);
    }));
  });
});
