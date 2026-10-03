'use strict';

// The workflow dialogs opened from Manage Projects and by third parties
// through CRM.volunteerPopup(): volWorkflowDialog through core's real
// dialogService and jQuery UI, with WorkflowDialog.html and the step inside
// it compiled for real.

const {boot, services, settle, text} = require('./support');

describe('Workflow dialogs', () => {
  const recorders = boot();

  function respond() {
    recorders.api.respond((entity, action) => {
      if (entity === 'VolunteerProject' && action === 'getWorkflowContext') {
        return [{
          project: {id: 42, title: 'Harvest Festival', is_active: '1'},
          needs: [{id: 30, project_id: 42, role_id: 3, role_label: 'Greeter', quantity: 2, is_flexible: 0, is_active: 1, visibility_id: 1, start_time: '2026-08-25 09:00:00', end_time: null, duration: 90, display_time: 'Tue 25 Aug 9:00 AM'}],
          assignments: [], capacity: {filled: 0, total: 2, by_need: {30: 0}},
          supporting: {workflow: {roles: [{id: 3, label: 'Greeter'}], visibility: {public: '1', admin: '2'}, shift_filter_presets: {now: '2026-08-19 00:00:00'}, statuses: []}, project: {}},
          beneficiary_names: [],
        }];
      }
      if (entity === 'VolunteerUtil' && action === 'getSupportingData') {
        return [{roles: [{id: 3, label: 'Greeter'}], visibility: {public: '1', admin: '2'}, shift_filter_presets: {now: '2026-08-19 00:00:00'}}];
      }
      if (entity === 'VolunteerAssignment' && (action === 'getHourEntries' || action === 'logHours')) { return [{needs: [], statuses: [], completed_status_id: '2', no_show_status_id: '3', flexible_need_id: '33', rows: []}]; }
      if (entity === 'VolunteerCommendation') { return []; }
      if (entity === 'VolunteerNeed') { return [{id: 30}]; }
      throw new Error('unexpected ' + entity + '.' + action);
    });
  }

  afterEach(() => {
    CRM.$('.ui-dialog-content').each(function() {
      if (CRM.$(this).dialog('instance')) { CRM.$(this).dialog('destroy'); }
    });
    CRM.$('.ui-dialog, .ui-widget-overlay').remove();
  });

  test('the legacy CRM.volunteerPopup entry point opens the Shifts step in a dialog and reports the session on close', async () => {
    respond();
    const {$rootScope, dialogService} = services('$rootScope', 'dialogService');
    const closed = [];
    CRM.$('body').on('volunteer:close:define.test', (event, projectId, registry) => closed.push({projectId, registry}));
    try {
      const promise = CRM.volunteerPopup('Define', 'Define', 42, 'Harvest Festival');
      await settle($rootScope);

      const dialog = CRM.$('.ui-dialog');
      expect(dialog.length).toBe(1);
      expect(text(dialog.find('.ui-dialog-title'))).toBe('Shifts & roles');
      expect(dialog.hasClass('crm-volunteer-workflow-dialog')).toBe(true);
      expect(text(dialog.find('.crm-vol-project-context'))).toBe('Volunteer project Harvest Festival');
      expect(dialog.find('.crm-vol-shifts .crm-vol-shift-row').length).toBe(1);
      expect(dialog.find('.crm-vol-shifts .crm-submit-buttons').length).toBe(0);
      expect(recorders.api.last('VolunteerProject', 'getWorkflowContext').params).toEqual({projectId: 42});

      let settled = false;
      promise.then(() => { settled = true; });
      dialogService.close('volunteerWorkflowDialog', {});
      await settle($rootScope);
      expect(settled).toBe(true);
      expect(closed).toEqual([{projectId: 42, registry: {clean: [30], created: [], updated: [], deleted: []}}]);
      expect(CRM.$('.ui-dialog').length).toBe(0);
    }
    finally {
      CRM.$('body').off('volunteer:close:define.test');
    }
  });

  test('the Hours dialog carries a Save hours button that runs the step\'s save', async () => {
    respond();
    const {$rootScope, dialogService, volWorkflowDialog} = services('$rootScope', 'dialogService', 'volWorkflowDialog');
    const promise = volWorkflowDialog.open('hours', {id: 42, title: 'Harvest Festival'});
    await settle($rootScope);

    const dialog = CRM.$('.ui-dialog');
    expect(text(dialog.find('.ui-dialog-title'))).toBe('Log hours');
    expect(dialog.find('.crm-vol-hours').length).toBe(1);
    const buttons = dialog.find('.ui-dialog-buttonpane button').map((i, b) => text(b)).get();
    expect(buttons).toEqual(['Save hours', 'Close']);

    dialog.find('.ui-dialog-buttonpane button').eq(0).trigger('click');
    await settle($rootScope);
    expect(recorders.api.callsTo('VolunteerAssignment', 'logHours')).toHaveLength(1);

    // Dismissing is a normal outcome; the promise resolves rather than rejects.
    let outcome = 'pending';
    promise.then((value) => { outcome = value; }, (error) => { outcome = {rejected: error}; });
    dialogService.cancel('volunteerWorkflowDialog');
    await settle($rootScope);
    expect(outcome).toBeUndefined();
  });

  test('step names from the old callers are normalised', () => {
    const {volWorkflowDialog} = services('volWorkflowDialog');
    expect(volWorkflowDialog.normalizeStep('Define')).toBe('shifts');
    expect(volWorkflowDialog.normalizeStep('Assign')).toBe('assign');
    expect(volWorkflowDialog.normalizeStep('Roster')).toBe('roster');
    expect(volWorkflowDialog.normalizeStep('Hours')).toBe('hours');
    expect(volWorkflowDialog.normalizeStep('roster')).toBe('roster');
    expect(volWorkflowDialog.normalizeStep(undefined)).toBe('');
  });
});
