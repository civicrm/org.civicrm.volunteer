'use strict';

// The whole project workflow through the real UI, then the database:
// create a project, define a shift, assign a volunteer, log their hours,
// read the roster -- and confirm through cv that what the screens showed is
// what was stored.

const {test, expect, env, remember, angularReady, pickEntityRef, notifications, statusBubble, expectUrl, scopeOf} = require('./support');

test.describe('Project workflow end to end', () => {
  test('create, staff, log hours and read back', async ({admin, state}) => {
    test.setTimeout(240 * 1000);
    const title = 'E2E Beach Cleanup ' + state.stamp;

    // --- Details: a new project -----------------------------------------------
    await admin.goto('/civicrm/volunteer/manage#/volunteer/manage/0/details');
    await angularReady(admin, '.crm-vol-project');
    await expect(admin.locator('.crm-vol-workflow h1')).toContainText('New volunteer project');
    await admin.locator('[ng-model="project.title"]').fill(title);
    await pickEntityRef(admin, '.crm-vol-rel-volunteer_beneficiary', 'E2E Friends', 'E2E Friends of the Park ' + state.stamp);
    await admin.locator('.crm-vol-project-save-next').click();

    await expectUrl(admin, /#\/volunteer\/manage\/(\d+)\/shifts/, 20000, () => scopeOf(admin, '.crm-vol-project', '{title: project.title, relationships: relationships, profiles: profiles, contacts: project.project_contacts}'));
    const projectId = parseInt(admin.url().match(/manage\/(\d+)\/shifts/)[1], 10);
    remember('project', projectId);
    await expect(admin.locator('.crm-vol-workflow h1')).toHaveText(title);

    // --- Shifts & roles: one shift, autosaved ------------------------------------
    await angularReady(admin, '.crm-vol-shifts');
    await admin.getByRole('button', {name: 'Add shift'}).click();
    const row = admin.locator('.crm-vol-shift-row').last();
    await row.locator('select').first().selectOption({label: state.roleLabel});
    await expect(row.locator('.crm-vol-save-state')).toHaveText('Saved');
    const spots = row.locator('input[type=number]').first();
    await spots.fill('2');
    await spots.dispatchEvent('input');
    await expect(admin.locator('.crm-vol-autosave-state')).toHaveText('Changes saved');
    await expect(row.locator('.crm-vol-filled-count')).toHaveText('0 of 2 filled');

    const needs = env.api4('VolunteerNeed', 'get', {where: [['project_id', '=', projectId], ['is_flexible', '=', false]]});
    expect(needs).toHaveLength(1);
    expect(needs[0].quantity).toBe(2);
    expect(String(needs[0].role_id)).toBe(String(state.roleValue));

    await admin.getByRole('button', {name: 'Continue to assign volunteers'}).click();
    await expectUrl(admin, /\/assign$/);

    // --- Assign: search a volunteer into the shift ------------------------------
    await angularReady(admin, '.crm-vol-assign-board');
    await expect(admin.locator('.crm-vol-rail-shift')).toHaveCount(1);
    await expect(admin.locator('.crm-vol-open-spot')).toHaveCount(2);
    // The contact autocomplete matches the start of the sort name (last name first).
    await pickEntityRef(admin, '.crm-vol-add-person', 'E2E-Olsen-' + state.stamp, 'E2E-Olsen-' + state.stamp, {clearsAfterPick: true});
    const assigned = admin.locator('.crm-vol-assigned-row');
    await expect(assigned).toHaveCount(1);
    await expect(assigned.first()).toContainText(state.volunteerName);
    await expect(admin.locator('.crm-vol-open-spot')).toHaveCount(1);
    await expect(admin.locator('.crm-vol-assign-metrics strong').first()).toHaveText('1');
    await expect(admin.locator('.crm-vol-capacity-meta')).toHaveText('1 of 2 spots filled');

    const assignments = env.api4('VolunteerAssignment', 'get', {where: [['project_id', '=', projectId]]});
    expect(assignments).toHaveLength(1);
    expect(assignments[0].assignee_contact_id).toBe(state.volunteerContactId);

    // --- Hours: attended, two hours ---------------------------------------------
    await admin.getByRole('button', {name: 'Log hours for this shift'}).click();
    await expectUrl(admin, /\/hours\?needId=\d+$/);
    await angularReady(admin, '.crm-vol-hours-table');
    const sheetRow = admin.locator('.crm-vol-hours-table tbody tr', {hasText: state.volunteerName});
    await expect(sheetRow).toHaveCount(1);
    await admin.getByRole('button', {name: 'Mark everyone Attended'}).click();
    await sheetRow.locator('.crm-vol-hours-input input').fill('2');
    await admin.locator('.crm-vol-hours .crm-submit-buttons').getByRole('button', {name: 'Save hours'}).click();
    // Earlier bubbles may still be fading out; look for this one by its text.
    await expect(statusBubble(admin).filter({hasText: /Volunteer hours saved/})).toHaveCount(1);

    let entry;
    await expect.poll(() => {
      const logged = env.api4('VolunteerAssignment', 'getHourEntries', {projectId});
      entry = logged[0].rows.find((r) => String(r.assignee_contact_id) === String(state.volunteerContactId));
      return entry && Number(entry.time_completed_minutes);
    }, {timeout: 20000}).toBe(120);
    const logged = env.api4('VolunteerAssignment', 'getHourEntries', {projectId});
    expect(String(entry.status_id)).toBe(String(logged[0].completed_status_id));

    // --- Roster ---------------------------------------------------------------
    await admin.locator('nav li a', {hasText: 'Roster'}).click();
    await expectUrl(admin, /\/roster$/);
    await angularReady(admin, '.crm-vol-roster-groups');
    const rosterRow = admin.locator('.crm-vol-roster-table tbody tr', {hasText: state.volunteerName});
    await expect(rosterRow).toHaveCount(1);
    await expect(rosterRow).toContainText(state.roleLabel);
    await expect(rosterRow.locator('.crm-vol-status-badge')).toBeVisible();

    // --- Back on the list --------------------------------------------------------
    // "Add shift" dates a new shift today at midnight, so by now it has
    // ended; the list's staffing column counts upcoming shifts only.
    await admin.goto('/civicrm/volunteer/manage');
    await angularReady(admin, '#crm-vol-project-list');
    const listRow = admin.locator('#crm-vol-project-list tbody tr', {hasText: title});
    await expect(listRow).toHaveCount(1);
    await expect(listRow.locator('.crm-vol-manage-project-subtitle')).toHaveText('No upcoming roles');
    await expect(listRow.locator('.crm-vol-staffing')).toContainText('No finite shifts');
    await expect(listRow.locator('td').nth(2)).toContainText('E2E Friends of the Park');
  });

  test('Details refuses to save without a title and keeps the edits', async ({admin}) => {
    await admin.goto('/civicrm/volunteer/manage#/volunteer/manage/0/details');
    await angularReady(admin, '.crm-vol-project');
    await admin.locator('.crm-vol-project-save-done').click();
    await expect.poll(async () => (await notifications(admin)).join('\n')).toContain('Title is a required field');
    await expect(admin).toHaveURL(/\/manage\/0\/details$/);
  });
});
