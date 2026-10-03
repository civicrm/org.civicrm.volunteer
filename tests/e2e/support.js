'use strict';

const base = require('@playwright/test');
const env = require('./env');

/**
 * `test` with two fixtures: `state`, what global-setup.js seeded, and
 * `admin`, a page already signed in as the administrator. Specs that need
 * an anonymous visitor use the plain `page` fixture.
 */
const test = base.test.extend({
  state: async ({}, use) => { await use(env.readState()); },
  admin: async ({browser}, use) => {
    const context = await browser.newContext({storageState: env.adminState, ignoreHTTPSErrors: true, baseURL: env.url});
    const page = await context.newPage();
    await use(page);
    await context.close();
  },
});

/** Records a project or contact the spec created so teardown removes it. */
function remember(kind, id) {
  const state = env.readState();
  const key = kind === 'project' ? 'createdProjectIds' : 'createdContactIds';
  state[key] = (state[key] || []).concat([id]);
  env.writeState(state);
}

/** Waits for the Angular app on a volunteer page to have rendered `selector`. */
async function angularReady(page, selector) {
  await page.waitForSelector('#crm_volunteer_angular_frame');
  await page.waitForSelector(selector, {state: 'visible'});
}

/**
 * Chooses an entry in one of CiviCRM's entity-reference (select2) widgets:
 * opens it, types, and picks the first matching result.
 */
async function pickEntityRef(page, container, query, resultText, options = {}) {
  const widget = page.locator(container).locator('.select2-container').first();
  await widget.click();
  const search = page.locator('.select2-drop-active input.select2-input, .select2-container-active input.select2-input').first();
  await search.fill(query);
  const result = page.locator('.select2-drop-active .select2-result-label', {hasText: resultText}).first();
  await result.waitFor({state: 'visible'});
  await result.click();
  // The choice must be rendered in the widget before anything reads the
  // model -- unless the page consumes and clears the pick, as the Assign
  // search box does.
  if (options.clearsAfterPick) {
    await page.locator(container).locator('.select2-chosen', {hasText: resultText}).waitFor({state: 'hidden'});
  }
  else {
    await page.locator(container).locator('.select2-search-choice, .select2-chosen', {hasText: resultText}).first().waitFor({state: 'visible'});
  }
}

/**
 * CiviCRM's status bubble (CRM.status), which reports saves in progress and
 * their outcome from the bottom of the page. Distinct from notifications.
 */
function statusBubble(page) {
  return page.locator('.crm-status-box-outer .crm-status-box-msg');
}

/** The text of CiviCRM's notification bubbles currently shown. */
async function notifications(page) {
  return page.locator('#crm-notification-container .ui-notify-message').allInnerTexts();
}

/**
 * Waits for the page's URL to match, or fails naming whatever CiviCRM
 * announced instead -- a validation message is far more useful than a
 * timeout when a save does not go through.
 */
async function expectUrl(page, pattern, timeout = 20000, describeState) {
  const started = Date.now();
  const seen = new Set();
  while (Date.now() - started < timeout) {
    if (pattern.test(page.url())) {
      return;
    }
    // Notifications auto-dismiss, so collect them as they appear.
    (await notifications(page)).forEach((t) => seen.add(t.replace(/\s+/g, ' ').trim()));
    await page.waitForTimeout(250);
  }
  const shown = Array.from(seen).join(' | ');
  const state = describeState ? ' State: ' + JSON.stringify(await describeState()) : '';
  throw new Error('Expected the URL to match ' + pattern + ' but it is ' + page.url() +
    (shown ? '. The page announced: ' + shown : '. The page announced nothing.') + state);
}

/** The Angular scope behind an element, for diagnostics on failure. */
function scopeOf(page, selector, expression) {
  return page.evaluate(([sel, expr]) => {
    const scope = window.angular.element(document.querySelector(sel)).scope();
    return JSON.parse(window.angular.toJson(scope.$eval(expr)));
  }, [selector, expression]);
}


module.exports = {test, expect: base.expect, env, remember, angularReady, pickEntityRef, notifications, statusBubble, expectUrl, scopeOf};
