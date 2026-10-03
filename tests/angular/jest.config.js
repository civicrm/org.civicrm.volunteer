'use strict';

// AngularJS unit tests for the CiviVolunteer module, run under Jest with a
// jsdom document. tests/angular/setup.js boots the real browser stack --
// jQuery, jQuery UI, select2, CiviCRM's Common.js and crm.ajax.js, AngularJS
// 1.8 with angular-mocks, and core's crmUi/crmUtil/api4/crmDialog modules --
// then the extension's own module, so a test exercises real dependency
// injection, real digest cycles and the real templates.
//
// Run from the extension root:
//   npm run test:angular
// CiviCRM core is located automatically when the extension sits inside a
// composer-managed site; otherwise point CIVICRM_CORE at a civicrm-core
// checkout (see setup.js).

const path = require('path');

module.exports = {
  rootDir: path.resolve(__dirname, '..', '..'),
  testEnvironment: 'jsdom',
  testEnvironmentOptions: {
    url: 'https://example.test/civicrm/volunteer/manage',
    pretendToBeVisual: true,
  },
  testMatch: ['<rootDir>/tests/angular/**/*.test.js'],
  setupFilesAfterEnv: ['<rootDir>/tests/angular/setup.js'],
  // The stack is loaded once per test file; keep files independent.
  resetModules: false,
  verbose: true,
};
