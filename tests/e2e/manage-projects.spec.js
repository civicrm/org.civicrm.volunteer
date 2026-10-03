'use strict';

// Manage Volunteer Projects on the real site: the seeded project appears
// with its staffing, the dashboard queues it, a row action opens the
// workflow dialog, and the Search Kit hours report loads.

const {test, expect, angularReady} = require('./support');

test.describe('Manage volunteer projects', () => {
  test('lists the seeded project with its staffing and role', async ({admin, state}) => {
    await admin.goto('/civicrm/volunteer/manage');
    await angularReady(admin, '#crm-vol-project-list');
    const row = admin.locator('#crm-vol-project-list tbody tr', {hasText: state.title});
    await expect(row).toHaveCount(1);
    await expect(row.locator('.crm-vol-manage-project-subtitle')).toHaveText(state.roleLabel);
    await expect(row.locator('.crm-vol-staffing')).toContainText('0 of 3 filled');
    await expect(row.locator('.crm-vol-needs-badge')).toHaveText('Needs volunteers');
    await expect(row.locator('td').nth(2)).toContainText('E2E Friends of the Park');

    await admin.locator('#crm-vol-project-search').fill('no such project ' + state.stamp);
    await expect(admin.locator('#crm-vol-project-list tbody tr')).toHaveCount(0);
    await expect(admin.getByText('No projects match these filters')).toBeVisible();
  });

  test('the dashboard queues the unstaffed shift and its action opens the Assign dialog', async ({admin, state}) => {
    await admin.goto('/civicrm/volunteer/manage');
    await angularReady(admin, '#crm-vol-project-list');
    await admin.locator('.crm-vol-view-toggle').getByRole('button', {name: 'Dashboard'}).click();
    await admin.waitForSelector('.crm-vol-dashboard-grid', {state: 'visible'});
    await expect(admin).toHaveURL(/view=dashboard/);
    const card = admin.locator('.crm-vol-attention-card', {hasText: state.title});
    await expect(card).toHaveCount(1);
    await expect(card).toContainText('3 of 3 spots still open');

    await card.getByRole('button', {name: 'Fill spots'}).click();
    const dialog = admin.locator('.ui-dialog', {has: admin.locator('.crm-vol-workflow-dialog')});
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('.ui-dialog-title')).toHaveText('Assign volunteers');
    await expect(dialog.locator('.crm-vol-rail-shift')).toHaveCount(1);
    await expect(dialog.locator('.crm-vol-rail-shift .crm-vol-capacity-summary')).toHaveText('0 of 3 filled');
    await dialog.getByRole('button', {name: 'Close'}).click();
    await expect(dialog).toHaveCount(0);
  });

  test('the row actions open the Shifts & roles dialog on the project', async ({admin, state}) => {
    await admin.goto('/civicrm/volunteer/manage');
    await angularReady(admin, '#crm-vol-project-list');
    const row = admin.locator('#crm-vol-project-list tbody tr', {hasText: state.title});
    await row.locator('.crm-vol-actions-menu summary').click();
    await row.getByRole('button', {name: 'Shifts & roles'}).click();
    const dialog = admin.locator('.ui-dialog', {has: admin.locator('.crm-vol-workflow-dialog')});
    await expect(dialog.locator('.ui-dialog-title')).toHaveText('Shifts & roles');
    await expect(dialog.locator('.crm-vol-project-context')).toContainText(state.title);
    await expect(dialog.locator('.crm-vol-shift-row')).toHaveCount(1);
    await expect(dialog.locator('.crm-vol-shift-row select').first()).toHaveValue(/:?\d+$/);
    await dialog.getByRole('button', {name: 'Close'}).click();
    await expect(dialog).toHaveCount(0);
  });

  test('the hours report renders its Search Kit displays', async ({admin}) => {
    await admin.goto('/civicrm/volunteer/hours-report');
    await admin.waitForSelector('afsearch-volunteer-hours-report');
    await expect(admin.locator('afsearch-volunteer-hours-report')).toContainText(/Volunteer Hours|hours/i);
    await expect(admin.locator('afsearch-volunteer-hours-report crm-search-display-table, afsearch-volunteer-hours-report .crm-search-display').first()).toBeVisible();
  });
});
