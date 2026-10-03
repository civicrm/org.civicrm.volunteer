'use strict';

// The CiviVolunteer settings form on the real site: the public-theme switch
// is shown, saves, and reads back through the settings API.

const {test, expect, env} = require('./support');

test.describe('CiviVolunteer settings', () => {
  const read = () => env.api4('Setting', 'get', {select: ['volunteer_use_backend_theme']})[0].value;

  test.afterAll(() => {
    env.api4('Setting', 'set', {values: {volunteer_use_backend_theme: 1}});
  });

  test('the public-theme switch round-trips through the form', async ({admin}) => {
    await admin.goto('/civicrm/admin/volunteer/settings?reset=1');
    const box = admin.locator('input[name="volunteer_use_backend_theme"]');
    await expect(box).toBeVisible();
    await expect(box).toBeChecked();
    await expect(admin.locator('.crm-section.volunteer_use_backend_theme .description')).toContainText('frontend theme');

    await box.uncheck();
    await admin.getByRole('button', {name: 'Save Volunteer Settings'}).first().click();
    await admin.waitForLoadState('networkidle');
    expect(Number(read())).toBe(0);

    await admin.goto('/civicrm/admin/volunteer/settings?reset=1');
    await expect(admin.locator('input[name="volunteer_use_backend_theme"]')).not.toBeChecked();

    await admin.locator('input[name="volunteer_use_backend_theme"]').check();
    await admin.getByRole('button', {name: 'Save Volunteer Settings'}).first().click();
    await admin.waitForLoadState('networkidle');
    expect(Number(read())).toBe(1);
  });
});
