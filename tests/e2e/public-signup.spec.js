'use strict';

// A visitor with no account finds the seeded shift on the public page,
// picks it, fills in the signup form and is scheduled -- verified in the
// database, not just on the screen.

const {test, expect, env, notifications} = require('./support');

test.describe('Public opportunity browser and signup', () => {
  test('a visitor picks a shift and signs up', async ({page, state}) => {
    test.setTimeout(180 * 1000);
    await page.goto('/civicrm/vol');
    await page.waitForSelector('.crm-vol-opportunities-page');
    const card = page.locator('.crm-vol-opportunity-card', {hasText: state.title});
    await expect(card).toHaveCount(1);
    await expect(card).toContainText(state.roleLabel);
    await expect(card).toContainText('3 spots left');
    await expect(card).toContainText('2 hours');
    await expect(card.locator('.crm-vol-opportunity-organizer')).toContainText('E2E Friends of the Park');

    await card.locator('input[type=checkbox]').click();
    await expect(card).toHaveClass(/is-selected/);
    const cart = page.locator('.crm-vol-shift-cart');
    await expect(cart.locator('header span')).toHaveText('1 picked');
    await expect(cart.locator('ol li strong')).toHaveText(state.roleLabel);
    await expect(page).toHaveURL(/selected/);

    await cart.getByRole('button', {name: 'Continue to your details'}).click();
    await page.waitForURL(/civicrm\/volunteer\/signup/);
    await expect(page.locator('.crm-vol-signup-page h1')).toContainText('tell us who you are');
    const commitments = page.locator('.crm-vol-signup-commitments');
    await expect(commitments).toContainText(state.roleLabel);
    await expect(commitments).toContainText(state.title);
    await expect(commitments.locator('header span')).toHaveText('1 picked');
    await expect(page.locator('.crm-vol-back-to-shifts')).toHaveAttribute('href', /volunteer\/opportunities/);

    const email = 'e2e-signup-' + state.stamp + '@example.org';
    const form = page.locator('.crm-volunteer-signup-profiles');
    await form.getByLabel(/First Name/).fill('Sam');
    await form.getByLabel(/Last Name/).fill('E2E-Signup');
    await form.getByLabel(/Email/).first().fill(email);
    await form.getByLabel(/Phone/).first().fill('555-0199');
    await page.getByRole('button', {name: 'Confirm my sign-up'}).click();

    await page.waitForURL(/civicrm\/vol/);
    await expect.poll(async () => (await notifications(page)).join('\n')).toContain('You are scheduled to volunteer');

    const contacts = env.api4('Contact', 'get', {select: ['id', 'display_name'], where: [['email_primary.email', '=', email]]});
    expect(contacts).toHaveLength(1);
    expect(contacts[0].display_name).toBe('Sam E2E-Signup');
    const assignments = env.api4('VolunteerAssignment', 'get', {
      where: [['project_id', '=', state.projectId], ['assignee_contact_id', '=', contacts[0].id]],
    });
    expect(assignments).toHaveLength(1);
    expect(assignments[0].volunteer_need_id).toBe(state.needId);

    // The shift now has one fewer spot for the next visitor.
    await page.goto('/civicrm/vol');
    await expect(page.locator('.crm-vol-opportunity-card', {hasText: state.title})).toContainText('2 spots left');
  });

  test('the signup form refuses an incomplete submission', async ({page, state}) => {
    await page.goto('/civicrm/volunteer/signup?reset=1&needs[]=' + state.needId + '&dest=list');
    await page.waitForSelector('.crm-vol-signup-page');
    await page.locator('.crm-volunteer-signup-profiles').getByLabel(/First Name/).fill('Only');
    await page.getByRole('button', {name: 'Confirm my sign-up'}).click();
    await expect(page.locator('.crm-vol-signup-page')).toBeVisible();
    await expect(page.locator('.crm-error, .error, .crm-inline-error').first()).toBeVisible();
    await expect(page.locator('.crm-volunteer-signup-profiles').getByLabel(/First Name/)).toHaveValue('Only');
  });
});
