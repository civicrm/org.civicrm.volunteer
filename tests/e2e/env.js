'use strict';

/**
 * Where the end-to-end suite runs, and how it reaches the site's command
 * line. Three settings, read from the environment or from tests/e2e/.env
 * (gitignored; see .env.example at the extension root):
 *
 *   CIVIVOLUNTEER_E2E_URL    the site, e.g. https://clean-drupal.ddev.site:8443
 *   CIVIVOLUNTEER_E2E_SITE   the site's root directory (holds vendor/bin/cv)
 *   CIVIVOLUNTEER_E2E_SHELL  prefix that runs a command inside the site,
 *                            e.g. "ddev exec"; empty for a local install
 *
 * The suite needs cv (for fixtures and database assertions) and drush (for
 * the one-time admin login link and the anonymous role's permission), both
 * from the site's vendor/bin.
 */

const fs = require('fs');
const path = require('path');
const {execFileSync} = require('child_process');

function readDotEnv() {
  const file = path.join(__dirname, '.env');
  if (!fs.existsSync(file)) {
    return {};
  }
  const values = {};
  for (const line of fs.readFileSync(file, 'utf8').split('\n')) {
    const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
    if (match && !line.trim().startsWith('#')) {
      values[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
    }
  }
  return values;
}

const dotEnv = readDotEnv();
const setting = (name) => process.env[name] !== undefined ? process.env[name] : dotEnv[name];

const url = setting('CIVIVOLUNTEER_E2E_URL');
const site = setting('CIVIVOLUNTEER_E2E_SITE');
const shell = setting('CIVIVOLUNTEER_E2E_SHELL') || '';

if (!url || !site) {
  throw new Error(
    'The end-to-end suite needs CIVIVOLUNTEER_E2E_URL and CIVIVOLUNTEER_E2E_SITE ' +
    '(and CIVIVOLUNTEER_E2E_SHELL for a containerised site). Set them in the ' +
    'environment or in tests/e2e/.env; see .env.example.'
  );
}

/** Runs a shell command inside the site and returns its stdout. */
function run(command, options = {}) {
  return execFileSync('sh', ['-c', (shell ? shell + ' ' : '') + command], {
    cwd: site,
    input: options.input,
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', options.quiet ? 'pipe' : 'inherit'],
    maxBuffer: 16 * 1024 * 1024,
  });
}

/** Calls an API4 action through cv and returns the rows. */
function api4(entity, action, params = {}) {
  const output = run('vendor/bin/cv api4 ' + entity + '.' + action + ' --in=json --out=json', {
    input: JSON.stringify(params),
  });
  return JSON.parse(output);
}

/** A fresh one-time admin login link. */
function loginUrl() {
  const lines = run('vendor/bin/drush uli --uri=' + url).trim().split('\n');
  return lines[lines.length - 1].trim();
}

const anonymousPermission = 'register to volunteer';

function anonymousMayRegister() {
  const output = run("vendor/bin/drush php:eval 'print json_encode(\\Drupal\\user\\Entity\\Role::load(\"anonymous\")->getPermissions());'", {quiet: true});
  return JSON.parse(output.trim()).includes(anonymousPermission);
}

function grantAnonymousRegistration() {
  run("vendor/bin/drush role:perm:add anonymous '" + anonymousPermission + "'");
}

function revokeAnonymousRegistration() {
  run("vendor/bin/drush role:perm:remove anonymous '" + anonymousPermission + "'");
}

const stateDir = path.join(__dirname, '.fixtures');
const authDir = path.join(__dirname, '.auth');
const stateFile = path.join(stateDir, 'state.json');
const adminState = path.join(authDir, 'admin.json');

function readState() {
  return JSON.parse(fs.readFileSync(stateFile, 'utf8'));
}

function writeState(state) {
  fs.mkdirSync(stateDir, {recursive: true});
  fs.writeFileSync(stateFile, JSON.stringify(state, null, 2));
}

module.exports = {
  url, site, shell, run, api4, loginUrl,
  anonymousMayRegister, grantAnonymousRegistration, revokeAnonymousRegistration,
  stateDir, authDir, stateFile, adminState, readState, writeState,
};
