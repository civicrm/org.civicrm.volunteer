'use strict';

// End-to-end tests against a running CiviCRM site with CiviVolunteer
// installed. See env.js for the three settings that point at the site, and
// tests/README.md for what the suite creates and removes.
//
//   npm run test:e2e
//   npx playwright test --config tests/e2e/playwright.config.js --headed

const {defineConfig} = require('@playwright/test');
const env = require('./env');

module.exports = defineConfig({
  testDir: __dirname,
  testMatch: /.*\.spec\.js/,
  globalSetup: require.resolve('./global-setup'),
  globalTeardown: require.resolve('./global-teardown'),
  // The specs share one site and one set of fixtures; run them one at a time.
  workers: 1,
  fullyParallel: false,
  retries: 0,
  timeout: 90 * 1000,
  expect: {timeout: 15 * 1000},
  reporter: [['list']],
  outputDir: require('path').join(__dirname, '..', '..', 'test-results'),
  use: {
    baseURL: env.url,
    // Local development sites use self-signed certificates.
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    viewport: {width: 1280, height: 900},
  },
  projects: [{name: 'chromium', use: {browserName: 'chromium'}}],
});
