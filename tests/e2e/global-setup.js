'use strict';

// Before the specs: log the administrator in and keep the session, let the
// anonymous role reach the public signup, and create the project the specs
// read. Everything created here is removed by global-teardown.js.

const fs = require('fs');
const {chromium} = require('@playwright/test');
const env = require('./env');

async function saveAdminSession() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ignoreHTTPSErrors: true, baseURL: env.url});
  const page = await context.newPage();
  await page.goto(env.loginUrl());
  // Drupal's one-time link lands on a page that asks for one click.
  const confirm = page.getByRole('button', {name: /log in/i});
  if (await confirm.count()) {
    await confirm.click();
  }
  await page.waitForURL((url) => !/\/user\/reset\//.test(url.pathname));
  fs.mkdirSync(env.authDir, {recursive: true});
  await context.storageState({path: env.adminState});
  await browser.close();
}

function nextWeekAt(hour) {
  const date = new Date();
  date.setDate(date.getDate() + 7);
  date.setHours(hour, 0, 0, 0);
  const pad = (n) => String(n).padStart(2, '0');
  return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(hour) + ':00:00';
}

function seed() {
  const stamp = Date.now();
  const admin = env.api4('UFMatch', 'get', {select: ['contact_id'], where: [['uf_id', '=', 1]]})[0];
  if (!admin) {
    throw new Error('No CiviCRM contact is linked to Drupal user 1; the suite needs an administrator contact.');
  }
  const beneficiary = env.api4('Contact', 'create', {
    values: {contact_type: 'Organization', organization_name: 'E2E Friends of the Park ' + stamp},
  })[0];
  const profile = env.api4('UFGroup', 'get', {select: ['id'], where: [['name', '=', 'volunteer_sign_up']]})[0];
  const usher = env.api4('OptionValue', 'get', {
    select: ['value', 'label'], where: [['option_group_id:name', '=', 'volunteer_role'], ['label', '=', 'Usher']],
  })[0];
  if (!profile || !usher) {
    throw new Error('The volunteer_sign_up profile and the Usher role must exist; is CiviVolunteer installed?');
  }

  const title = 'E2E Harvest Festival ' + stamp;
  const project = env.api4('VolunteerProject', 'commit', {
    values: {
      title,
      description: '<p>Seeded by the end-to-end suite.</p>',
      is_active: true,
      project_contacts: {
        volunteer_owner: [admin.contact_id],
        volunteer_manager: [admin.contact_id],
        volunteer_beneficiary: [beneficiary.id],
      },
      profiles: [{uf_group_id: profile.id, module_data: {audience: 'primary'}, is_active: 1, module: 'CiviVolunteer', weight: 1}],
    },
  })[0];
  const need = env.api4('VolunteerNeed', 'create', {
    values: {
      project_id: project.id, role_id: usher.value, quantity: 3, is_flexible: false, is_active: true,
      visibility_id: 1, start_time: nextWeekAt(9), duration: 120,
    },
  })[0];
  const volunteer = env.api4('Contact', 'create', {
    values: {contact_type: 'Individual', first_name: 'Brittney', last_name: 'E2E-Olsen-' + stamp},
  })[0];

  return {
    stamp, title, adminContactId: admin.contact_id, beneficiaryId: beneficiary.id,
    projectId: project.id, needId: need.id, roleLabel: usher.label, roleValue: usher.value,
    volunteerContactId: volunteer.id, volunteerName: 'Brittney E2E-Olsen-' + stamp,
    profileId: profile.id, createdProjectIds: [], createdContactIds: [],
  };
}

module.exports = async function globalSetup() {
  await saveAdminSession();
  const anonymousHadPermission = env.anonymousMayRegister();
  if (!anonymousHadPermission) {
    env.grantAnonymousRegistration();
  }
  const state = seed();
  state.anonymousHadPermission = anonymousHadPermission;
  env.writeState(state);
};
