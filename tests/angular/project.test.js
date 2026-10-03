'use strict';

// The Details step: VolunteerProject driving Project.html with the resolves
// the route supplies. This is the largest controller and the one whose
// behaviour depends most on Angular's own machinery -- $watch-driven dirty
// tracking, ng-options, the form controller -- so it is exercised here with
// the real template and digest rather than a hand-built scope.

const {boot, services, settle, render, text, partial, routeFor, ui} = require('./support');

// Rows as VolunteerUtil.getCountries returns them: numeric ids, quoted flags.
const countries = {
  1228: {id: 1228, name: 'United States', iso_code: 'US', is_default: '1'},
  1012: {id: 1012, name: 'Canada', iso_code: 'CA', is_default: '0'},
};
const relationshipTypes = {
  1: {id: '1', value: '1', name: 'volunteer_beneficiary', label: 'Beneficiary', description: 'Who benefits'},
  2: {id: '2', value: '2', name: 'volunteer_manager', label: 'Manager', description: ''},
  3: {id: '3', value: '3', name: 'volunteer_owner', label: 'Owner', description: ''},
};
function supporting() {
  return {
    relationship_types: relationshipTypes,
    phone_types: {1: 'Phone', 2: 'Mobile'},
    volunteer_general_project_settings_help_text: '<p>Describe your project.</p>',
    profile_audience_types: {
      primary: {type: 'primary', label: 'Individual registrations'},
      additional: {type: 'additional', label: 'Group registrations'},
      both: {type: 'both', label: 'Both'},
    },
    defaults: {
      title: '', description: '', is_active: '1', campaign_id: '', loc_block_id: '',
      relationships: {1: [], 2: [5], 3: [5]},
      profiles: [{uf_group_id: 7, module_data: {audience: 'primary'}, is_active: '1', module: 'CiviVolunteer', weight: 1}],
    },
  };
}
const existingProject = () => ({
  id: 42, title: 'Harvest Festival', description: '<p>Bring gloves.</p>', is_active: '1', campaign_id: '', loc_block_id: '5',
  profiles: [{id: 3, uf_group_id: 7, module_data: '{"audience":"primary"}', weight: 1, is_active: '1', module: 'CiviVolunteer'}],
});
const relationshipData = () => ({1: [9], 2: [5], 3: [5]});
const locationBlocks = () => ({5: 'Town Hall', 8: 'Park'});

function respondFor(_recorders, overrides = {}) {
  _recorders.api.respond((entity, action, params) => {
    const key = entity + '.' + action;
    if (overrides[key]) { return overrides[key](params); }
    if (key === 'VolunteerUtil.getProfiles') {
      return [{
        profiles: [{id: 7, display_title: 'Volunteer Signup', fields: [{id: 1, label: 'First Name', is_required: true}, {id: 2, label: 'Email', is_required: false}], edit_url: '/edit/7', preview_url: '/preview/7'},
          {id: 8, display_title: 'Group Signup', fields: [], edit_url: '/edit/8', preview_url: '/preview/8'}],
        can_manage: true, create_url: '/create-profile',
      }];
    }
    if (key === 'VolunteerUtil.getPermissions') { return [{name: 'edit all volunteer projects', safe_name: 'edit_all_volunteer_projects'}]; }
    if (key === 'VolunteerProject.getLocation') {
      return [{id: 5, count_loc_used: 2, address: {name: 'Town Hall', street_address: '1 Main St', city: 'Springfield', country_id: 1228, state_province_id: 1001, postal_code: '62701'}, phone: {phone: '555-0100', phone_type_id: '1'}, email: {email: 'hall@example.org'}}];
    }
    if (key === 'StateProvince.get') { return params.where[0][2] === 1228 ? [{id: 1001, name: 'Illinois'}, {id: 1002, name: 'Indiana'}] : [{id: 1100, name: 'Ontario'}]; }
    if (key === 'VolunteerProject.commit') { return [{id: 42}]; }
    if (key === 'VolunteerProject.getWorkflowContext') {
      return [{project: {id: 42, title: 'Harvest Festival', is_active: '1'}, needs: [], assignments: [], capacity: {filled: 1, total: 4, by_need: {}}, supporting: {workflow: {}, project: {}}, beneficiary_names: ['Friends of the Park']}];
    }
    throw new Error('unexpected ' + key);
  });
}


const field = (element, model) => element.find('[ng-model="' + model + '"]');
const save = (element) => ui.click(element.find('.crm-vol-project-save-done'));
const saveAndNext = (element) => ui.click(element.find('.crm-vol-project-save-next'));

describe('Project details step', () => {
  const recorders = boot();

  function respond(overrides = {}) {
    respondFor(recorders, overrides);
  }

  async function mount(options = {}) {
    respond(options.api);
    const {$compile, $controller, $rootScope} = services('$compile', '$controller', '$rootScope');
    const scope = $rootScope.$new();
    const projectId = options.projectId === undefined ? '42' : options.projectId;
    $controller('VolunteerProject', {
      $scope: scope,
      $route: routeFor({projectId}),
      countries: options.countries || countries,
      project: options.project === undefined ? existingProject() : options.project,
      relationship_data: options.relationships || relationshipData(),
      supporting_data: options.supporting || supporting(),
      location_blocks: locationBlocks(),
    });
    const element = render($compile, scope, partial('Project.html'));
    await settle($rootScope);
    return {element, scope, $rootScope};
  }


  test('an existing project renders its fields, loads its location and starts clean', async () => {
    const {element, scope} = await mount();
    expect(field(element, 'project.title').val()).toBe('Harvest Festival');
    expect(text(element.find('.help').first())).toBe('Describe your project.');
    // The partial's root carries crm-vol-perm-to-class.
    expect(element.hasClass('crm-vol-project')).toBe(true);
    expect(element.hasClass('crm-vol-perm-edit_all_volunteer_projects')).toBe(true);
    expect(text(element.find('.crm-vol-capacity-meta'))).toBe('1 of 4 spots filled');
    expect(text(element.find('.crm-vol-project-meta'))).toContain('Friends of the Park');

    const location = field(element, 'project.loc_block_id');
    expect(ui.options(location)).toEqual(['', 'Create a new Location', 'Town Hall', 'Park']);
    expect(location.val()).toBe('string:5');
    expect(recorders.api.last('VolunteerProject', 'getLocation').params).toEqual({id: '5', projectId: 42});
    expect(field(element, 'locBlock.address.city').val()).toBe('Springfield');
    expect(field(element, 'locBlock.address.name').val()).toBe('Town Hall');
    expect(ui.shown(element.find('#crm-vol-location-block .status'))).toBe(true);
    expect(recorders.api.last('StateProvince', 'get').params.where).toEqual([['country_id', '=', 1228]]);
    expect(ui.options(field(element, 'locBlock.address.state_province_id'))).toEqual(['Select State/Province', 'Illinois', 'Indiana']);
    expect(field(element, 'locBlock.address.state_province_id').val()).toBe('number:1001');

    expect(scope.workflow.isDirty()).toBe(false);
    expect(element.find('.crm-profile-selector-container').length).toBe(1);
    expect(field(element, 'profile.uf_group_id').val()).toBe('number:7');
    expect(text(element.find('.crm-vol-profile-fields'))).toBe('Fields: First Name *, Email');
    expect(element.find('.crm-vol-profile-tools a').first().attr('href')).toBe('/edit/7');
    expect(element.find('.crm-button-create-profile').attr('href')).toBe('/create-profile');
  });

  test('editing the address makes the step dirty; saving sends the location with the project and cleans it', async () => {
    const {element, scope, $rootScope} = await mount();
    ui.type(field(element, 'locBlock.address.city'), 'Shelbyville');
    expect(scope.workflow.isDirty()).toBe(true);

    save(element);
    await settle($rootScope);
    const values = recorders.api.last('VolunteerProject', 'commit').params.values;
    expect(values.id).toBe(42);
    expect(values.title).toBe('Harvest Festival');
    expect(values.location.address.city).toBe('Shelbyville');
    expect(values.location.address.country_id).toBe(1228);
    expect(values.profiles[0]).toEqual(expect.objectContaining({uf_group_id: 7, module_data: {audience: 'primary'}}));
    expect(values.profiles[0]._client_id).toBeUndefined();
    expect(values.project_contacts).toEqual({1: [9], 2: [5], 3: [5]});
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({text: 'Changes saved successfully', type: 'success'}));
    expect(scope.workflow.isDirty()).toBe(false);
  });

  test('a save without touching the address does not resend the location', async () => {
    const {element, $rootScope} = await mount();
    ui.type(field(element, 'project.title'), 'Harvest Festival 2026');
    save(element);
    await settle($rootScope);
    const values = recorders.api.last('VolunteerProject', 'commit').params.values;
    expect(values.title).toBe('Harvest Festival 2026');
    expect(values.location).toBeUndefined();
  });

  test('changing the country clears the state and reloads the choices', async () => {
    const {element, $rootScope} = await mount();
    ui.pick(field(element, 'locBlock.address.country_id'), 'number:1012');
    await settle($rootScope);
    expect(recorders.api.last('StateProvince', 'get').params.where).toEqual([['country_id', '=', 1012]]);
    expect(ui.options(field(element, 'locBlock.address.state_province_id'))).toEqual(['Select State/Province', 'Ontario']);
    expect(field(element, 'locBlock.address.state_province_id').val()).toBe('');
  });

  test('choosing "Create a new Location" starts a fresh address in the default country', async () => {
    const {element, scope, $rootScope} = await mount();
    ui.pick(field(element, 'project.loc_block_id'), 'string:0');
    await settle($rootScope);
    expect(scope.locBlock).toEqual({address: {country_id: 1228}});
    expect(field(element, 'locBlock.address.city').val()).toBe('');
    expect(field(element, 'locBlock.address.country_id').val()).toBe('number:1228');

    ui.type(field(element, 'locBlock.address.city'), 'Capital City');
    save(element);
    await settle($rootScope);
    const values = recorders.api.last('VolunteerProject', 'commit').params.values;
    expect(values.loc_block_id).toBeUndefined();
    expect(values.location.address.city).toBe('Capital City');
  });

  test('profiles: adding an empty row blocks the save until it is filled or removed; one primary is required', async () => {
    const {element, $rootScope} = await mount();
    ui.click(element.find('.crm-button-add-profile'));
    expect(element.find('.crm-profile-selector-container').length).toBe(2);
    save(element);
    await settle($rootScope);
    expect(recorders.alerts.map((a) => a.text)).toContain('Please select at least one profile, and remove empty selections');
    expect(recorders.api.callsTo('VolunteerProject', 'commit')).toHaveLength(0);

    ui.pick(element.find('.crm-profile-selector-container').eq(1).find('[ng-model="profile.uf_group_id"]'), 'number:8');
    ui.pick(element.find('.crm-profile-selector-container').eq(0).find('[ng-model="profile.module_data.audience"]'), 'additional');
    ui.pick(element.find('.crm-profile-selector-container').eq(1).find('[ng-model="profile.module_data.audience"]'), 'additional');
    recorders.alerts.length = 0;
    save(element);
    await settle($rootScope);
    expect(recorders.alerts.map((a) => a.text)).toEqual(expect.arrayContaining([
      'You may only have one profile that is used for group registrations',
      'Please select at least one profile that is used for individual registrations',
    ]));
    expect(recorders.api.callsTo('VolunteerProject', 'commit')).toHaveLength(0);

    ui.click(element.find('.crm-profile-selector-container').eq(1).find('.crm-button-remove-profile'));
    expect(element.find('.crm-profile-selector-container').length).toBe(1);
  });

  test('the profile list refreshes when the window regains focus, keeping the selection', async () => {
    const {element, $rootScope} = await mount();
    expect(recorders.api.callsTo('VolunteerUtil', 'getProfiles')).toHaveLength(1);
    // triggerHandler runs the jQuery handlers without asking jsdom to focus the window.
    angular.element(window).triggerHandler('focus');
    await settle($rootScope);
    expect(recorders.api.callsTo('VolunteerUtil', 'getProfiles')).toHaveLength(2);
    expect(recorders.api.last('VolunteerUtil', 'getProfiles').params).toEqual({profileIds: [7]});
    expect(field(element, 'profile.uf_group_id').val()).toBe('number:7');
  });

  test('every required relationship must have someone', async () => {
    const {element, scope, $rootScope} = await mount();
    expect(element.find('.crm-vol-relationships input[crm-entityref]').length).toBe(3);
    scope.relationships[1] = [];
    save(element);
    await settle($rootScope);
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({text: 'The Beneficiary relationship must not be blank.'}));
    expect(recorders.api.callsTo('VolunteerProject', 'commit')).toHaveLength(0);
  });

  test('a new project starts from the site defaults, needs a title, and lands on its own details page once saved', async () => {
    const {$location} = services('$location');
    const {element, scope, $rootScope} = await mount({projectId: '0', project: {id: 0}, relationships: {}});
    expect(text(element.find('h1'))).toBe('New volunteer project');
    expect(field(element, 'project.title').val()).toBe('');
    expect(element.find('.crm-profile-selector-container').length).toBe(1);
    expect(scope.relationships).toEqual({1: [], 2: [5], 3: [5]});
    expect(scope.workflow.isDirty()).toBe(false);

    save(element);
    await settle($rootScope);
    expect(recorders.alerts.map((a) => a.text)).toContain('Title is a required field');
    expect(recorders.api.callsTo('VolunteerProject', 'commit')).toHaveLength(0);

    scope.relationships[1] = [9];
    ui.type(field(element, 'project.title'), 'Beach Cleanup');
    expect(scope.workflow.isDirty()).toBe(true);
    recorders.alerts.length = 0;
    save(element);
    await settle($rootScope);
    const values = recorders.api.last('VolunteerProject', 'commit').params.values;
    expect(values.title).toBe('Beach Cleanup');
    expect(values.is_active).toBe(true);
    // A new project carries the placeholder id 0, which the API treats as "create".
    expect(values.id).toBe(0);
    expect(values.project_contacts).toEqual({1: [9], 2: [5], 3: [5]});
    expect($location.path()).toBe('/volunteer/manage/42/details');
    expect(scope.workflow.projectId).toBe(42);
  });

  test('Save and set up shifts continues to the Shifts step', async () => {
    const {$location} = services('$location');
    const {element, $rootScope} = await mount();
    expect(text(element.find('.crm-vol-project-save-next .crm-button-text'))).toBe('Save and set up shifts');
    saveAndNext(element);
    await settle($rootScope);
    expect(recorders.api.callsTo('VolunteerProject', 'commit')).toHaveLength(1);
    expect($location.path()).toBe('/volunteer/manage/42/shifts');
  });

  test('a failed save explains itself and keeps the edits', async () => {
    const {element, scope, $rootScope} = await mount({api: {'VolunteerProject.commit': () => { throw {error_message: 'Deadlock'}; }}});
    ui.type(field(element, 'project.title'), 'Renamed');
    save(element);
    await settle($rootScope);
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({title: 'A technical problem has occurred', type: 'error'}));
    expect(scope.workflow.isDirty()).toBe(true);
    expect(field(element, 'project.title').val()).toBe('Renamed');
  });

  test('Cancel leaves for the project list, asking first when the form is dirty', async () => {
    const {$location} = services('$location');
    const {element, $rootScope} = await mount();
    ui.type(field(element, 'project.title'), 'Changed');
    ui.click(element.find('.crm-vol-project-cancel'));
    expect(recorders.confirmations[0].options.title).toBe('Discard unsaved changes?');
    recorders.confirmations[0].answer('crmConfirm:yes');
    await settle($rootScope, 2);
    expect($location.path()).toBe('/volunteer/manage');
  });

  test('Preview Description shows the sanitized description under the title', async () => {
    const {element} = await mount({project: Object.assign(existingProject(), {description: '<p>Bring gloves.</p><script>alert(1)</script>'})});
    ui.click(element.find('.crm-vol-project-preview-description'));
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({text: '<p>Bring gloves.</p>', title: 'Harvest Festival', type: 'info'}));
  });
});

describe('Project details step inside the CiviEvent Volunteers tab', () => {
  const recorders = boot({vars: {context: 'eventTab', entityTable: 'civicrm_event', entityId: 5, entityTitle: 'City Marathon', entityCampaignId: 11}});

  test('a new project is seeded from the event and saving announces it to the tab', async () => {
    respondFor(recorders);
    const {$compile, $controller, $rootScope, $location} = services('$compile', '$controller', '$rootScope', '$location');
    const announced = [];
    CRM.$('body').on('volunteerProjectSaveComplete.test', (event, projectId) => announced.push(projectId));
    try {
      const scope = $rootScope.$new();
      $controller('VolunteerProject', {
        $scope: scope, $route: routeFor({projectId: '0'}), countries, project: {id: 0},
        relationship_data: {}, supporting_data: supporting(), location_blocks: locationBlocks(),
      });
      const element = render($compile, scope, partial('Project.html'));
      await settle($rootScope);
      expect(element.find('.crm-vol-workflow').hasClass('crm-vol-workflow--embedded')).toBe(true);
      expect(field(element, 'project.title').val()).toBe('City Marathon');
      expect(scope.project.entity_table).toBe('civicrm_event');
      expect(scope.project.entity_id).toBe(5);
      expect(scope.project.campaign_id).toBe(11);

      scope.relationships[1] = [9];
      saveAndNext(element);
      await settle($rootScope);
      expect(announced).toEqual([42]);
      expect($location.path()).toBe('/volunteer/manage/42/shifts');
    }
    finally {
      CRM.$('body').off('volunteerProjectSaveComplete.test');
    }
  });
});
