'use strict';

// The public opportunity browser: VolOppsCtrl and the volOppSearch factory
// driving VolOppsCtrl.html. Grouping, the selection rail, quick filters, the
// filter form and the hand-off to signup are asserted through the DOM and
// the URLs the page writes.

const {boot, services, settle, render, text, partial, routeFor, fakeWindow, ui} = require('./support');

describe('Public volunteer opportunities', () => {
  const browser = fakeWindow();
  // volOppSearch reads the bookmarked query from $route at construction, so
  // the route is a stand-in whose params each test sets before mounting.
  const route = routeFor({});
  const recorders = boot({
    isFrontend: true,
    configure: ($provide) => {
      $provide.value('$window', browser);
      $provide.value('$route', route);
    },
  });

  // Rows as VolunteerUtil.getCountries returns them: numeric ids, quoted flags.
  const countries = {
    1228: {id: 1228, name: 'United States', iso_code: 'US', is_default: '1'},
    1012: {id: 1012, name: 'Canada', iso_code: 'CA', is_default: '0'},
  };
  const project = {id: 4, title: 'Spring Fair', description: '<p>Help run the fair.</p>', beneficiaries: [{display_name: 'Friends of the Park'}], campaign_title: 'Spring Drive'};
  const need = (id, overrides) => Object.assign({
    id, is_flexible: 0, role_id: 3, role_label: 'Greeter', role_description: '', project,
    start_time: '2026-08-25 09:00:00', end_time: null, duration: 60, display_time: 'Tue 25 Aug 9:00 AM',
    quantity: 3, quantity_assigned: 1,
  }, overrides);
  const results = () => [
    need(12),
    need(13, {role_label: 'Usher', start_time: '2026-08-25 14:00:00', display_time: 'Tue 25 Aug 2:00 PM', quantity: null, duration: 240}),
    need(14, {role_label: 'Steward', start_time: '2026-08-27 08:00:00', display_time: 'From Thu 27 Aug', duration: null}),
    need(11, {is_flexible: 1, quantity: null, duration: null}),
  ];

  async function mount(params = {}, options = {}) {
    route.current.params = Object.assign({}, params);
    recorders.api.respond((entity, action) => {
      if (entity === 'VolunteerNeed' && action === 'search') { return options.results || results(); }
      if (entity === 'StateProvince') { return [{id: 1001, name: 'Illinois'}]; }
      throw new Error('unexpected ' + entity + '.' + action);
    });
    const {$compile, $controller, $rootScope} = services('$compile', '$controller', '$rootScope');
    const scope = $rootScope.$new();
    $controller('VolOppsCtrl', {
      $scope: scope, $route: route, countries,
      supporting_data: Object.assign({roles: {3: 'Greeter', 4: 'Usher'}, proximity_available: options.proximity !== false}, options.supporting),
    });
    const element = render($compile, scope, partial('VolOppsCtrl.html'));
    await settle($rootScope);
    return {element, scope, $rootScope};
  }

  const cards = (element) => element.find('.crm-vol-opportunity-card');
  const cart = (element) => element.find('.crm-vol-shift-cart');
  const quick = (element, label) => ui.button(element.find('.crm-vol-quick-filters'), label);

  test('searches on arrival and groups the shifts by day, with capacity, duration and organiser on each card', async () => {
    const {element} = await mount();
    expect(recorders.api.last('VolunteerNeed', 'search').params).toEqual({timeFilter: 'all'});
    expect(text(element.find('.crm-vol-results-status'))).toBe('4 opportunities found');
    expect(element.find('.crm-vol-shift-group h2').map((i, h) => text(h)).get()).toEqual(['Tuesday, August 25', 'No fixed time']);
    expect(cards(element).length).toBe(3);

    const greeter = cards(element).eq(0);
    expect(text(greeter.find('h3'))).toBe('Greeter Spring Fair');
    expect(text(greeter.find('time'))).toBe('Tue 25 Aug 9:00 AM');
    expect(text(greeter.find('.crm-vol-opportunity-description'))).toBe('Help run the fair.');
    expect(text(greeter.find('.crm-vol-opportunity-organizer'))).toBe('Organized by Friends of the Park');
    expect(text(greeter.find('.crm-vol-opportunity-campaign'))).toBe('Spring Drive');
    expect(text(greeter.find('.crm-vol-opportunity-capacity'))).toBe('2 spots left 1 hour');
    expect(greeter.find('input').attr('aria-label')).toBe('Select Greeter with Spring Fair');

    expect(text(cards(element).eq(1).find('.crm-vol-opportunity-capacity'))).toBe('Open 4 hours');
    expect(text(cards(element).eq(2).find('.crm-vol-opportunity-capacity'))).toBe('2 spots left flexible');
    expect(text(element.find('.crm-vol-flexible-card span').last())).toBe('I am generally available');
    expect(text(cart(element).find('header span'))).toBe('Nothing picked yet');
    expect(cart(element).find('footer button').prop('disabled')).toBe(true);
  });

  test('picking shifts fills the rail, remembers the selection in the URL, and hands them to signup', async () => {
    const {$location} = services('$location');
    const {element} = await mount();
    ui.click(cards(element).eq(0).find('input'));
    expect(cards(element).eq(0).hasClass('is-selected')).toBe(true);
    expect(text(cart(element).find('header span'))).toBe('1 picked');
    expect(text(cart(element).find('ol li strong'))).toBe('Greeter');
    // The bookmark keeps jQuery.param's bracket form, which parseQueryParams reads back.
    expect($location.search()).toEqual({timeFilter: 'all', 'selected[]': '12'});

    ui.click(element.find('.crm-vol-flexible-card input'));
    const picked = () => cart(element).find('ol li strong').map((i, s) => text(s)).get().sort();
    expect(picked()).toEqual(['Generally available', 'Greeter']);
    expect(text(element.find('.crm-vol-flexible-card span').last())).toBe('Generally available');
    expect(text(element.find('.crm-vol-shift-bar span'))).toBe('2 picked');

    ui.click(cart(element).find('ol li').filter((i, li) => text(CRM.$(li).find('strong')) === 'Greeter').find('.crm-vol-remove-shift'));
    expect(cards(element).eq(0).hasClass('is-selected')).toBe(false);
    expect($location.search()).toEqual({timeFilter: 'all', 'selected[]': '11'});

    ui.click(cart(element).find('footer button'));
    const handoff = new URL(browser.location.href, 'https://example.test');
    expect(handoff.pathname).toBe('/civicrm/volunteer/signup');
    expect(handoff.searchParams.get('reset')).toBe('1');
    expect(handoff.searchParams.getAll('needs[]')).toEqual(['11']);
    expect(handoff.searchParams.get('dest')).toBe('list');
    const back = new URL(handoff.searchParams.get('return'), 'https://example.test');
    expect(back.pathname).toBe('/volunteer/opportunities');
    expect(back.searchParams.getAll('selected[]')).toEqual(['11']);
    expect(back.searchParams.get('timeFilter')).toBe('all');
  });

  test('a bookmarked selection is restored once, and a shift no longer offered is announced and dropped', async () => {
    const {element, $rootScope} = await mount({'selected[]': ['12', '99']});
    expect(cards(element).eq(0).hasClass('is-selected')).toBe(true);
    expect(text(cart(element).find('header span'))).toBe('1 picked');
    expect(recorders.alerts[0]).toEqual(expect.objectContaining({title: 'Selection updated', text: 'A previously selected opportunity is no longer available and was removed.'}));

    ui.click(quick(element, 'Weekends'));
    await settle($rootScope);
    expect(recorders.alerts).toHaveLength(1);
    expect(text(cart(element).find('header span'))).toBe('1 picked');
  });

  test('the quick filters search again with the time filter and mark the active one', async () => {
    const {element, $rootScope} = await mount();
    ui.click(quick(element, 'Evenings'));
    expect(recorders.statuses[0]).toEqual({start: 'Searching...', success: 'Search complete'});
    await settle($rootScope);
    expect(recorders.api.last('VolunteerNeed', 'search').params).toEqual({timeFilter: 'evenings'});
    expect(quick(element, 'Evenings').attr('aria-pressed')).toBe('true');
    expect(quick(element, 'All shifts').attr('aria-pressed')).toBe('false');
  });

  test('the filter form validates a distance search and sends the location and dates as API4 parameters', async () => {
    const {element, $rootScope} = await mount();
    expect(element.find('#crm-vol-more-filters').length).toBe(0);
    ui.click(quick(element, 'More filters'));
    const form = element.find('#crm-vol-more-filters form');
    expect(form.length).toBe(1);
    expect(ui.options(form.find('[ng-model="locationFilters.country"]'))).toEqual(['Select country', 'Canada', 'United States']);
    expect(form.find('[ng-model="locationFilters.country"]').val()).toBe('number:1228');

    // A distance needs somewhere to measure from. The site's default country
    // counts, so clear it to reach the validation branch.
    ui.pick(form.find('[ng-model="locationFilters.country"]'), '');
    ui.type(form.find('[ng-model="locationFilters.radius"]'), '10');
    ui.pick(form.find('[ng-model="locationFilters.unit"]'), 'string:miles');
    // triggerHandler runs ng-submit without asking jsdom to submit the form.
    form.triggerHandler('submit');
    expect(text(form.find('.crm-vol-filter-validation strong'))).toBe('Some filter details need attention.');
    expect(text(form.find('.crm-vol-proximity .crm-vol-filter-field-error'))).toBe('Enter at least one location field to use a distance search.');
    expect(recorders.api.callsTo('VolunteerNeed', 'search')).toHaveLength(1);

    ui.pick(form.find('[ng-model="locationFilters.country"]'), 'number:1228');
    ui.type(form.find('[ng-model="locationFilters.city"]'), 'Springfield');
    form.triggerHandler('submit');
    await settle($rootScope);
    expect(form.find('.crm-vol-filter-validation').length).toBe(0);
    expect(recorders.api.last('VolunteerNeed', 'search').params).toEqual({
      timeFilter: 'all',
      proximity: {country: 1228, radius: 10, unit: 'miles', city: 'Springfield'},
    });

    ui.click(ui.button(form, 'Clear'));
    await settle($rootScope);
    expect(recorders.api.last('VolunteerNeed', 'search').params).toEqual({timeFilter: 'all'});
    expect(form.find('[ng-model="locationFilters.radius"]').val()).toBe('');
  });

  test('without the Geocoder extension the distance fields are disabled and say why', async () => {
    const {element} = await mount({}, {proximity: false});
    ui.click(quick(element, 'More filters'));
    const form = element.find('#crm-vol-more-filters form');
    expect(form.find('[ng-model="locationFilters.radius"]').prop('disabled')).toBe(true);
    expect(text(form.find('#crm-vol-radius-help'))).toBe('Distance filtering requires the Geocoder extension to be enabled and configured.');
  });

  test('a project link with hideSearch keeps the filters out of the way, and hideSearch=1 offers them back', async () => {
    const always = await mount({project: '4', hideSearch: 'always'});
    expect(always.element.find('.crm-vol-quick-filters button').filter((i, b) => /filters/.test(text(b))).length).toBe(0);
    expect(recorders.api.last('VolunteerNeed', 'search').params).toEqual({project: '4', timeFilter: 'all'});

    const optional = await mount({project: '4', hideSearch: '1'});
    const showButton = quick(optional.element, 'More filters');
    expect(showButton.length).toBe(1);
    ui.click(showButton);
    expect(optional.element.find('#crm-vol-more-filters').length).toBe(1);
  });

  test('no results shows the empty state and a zero count', async () => {
    const {element} = await mount({}, {results: []});
    expect(text(element.find('.crm-vol-results-status'))).toBe('No opportunities found');
    expect(text(element.find('.crm-vol-public-empty strong'))).toBe('No opportunities match these filters');
  });
});
