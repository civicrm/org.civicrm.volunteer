'use strict';

// Removes what global-setup.js and the specs created, and puts the anonymous
// role's permission back the way it was. Each step is best effort so one
// failure does not leave the rest behind.

const fs = require('fs');
const env = require('./env');

function attempt(label, fn) {
  try {
    fn();
  }
  catch (error) {
    console.warn('e2e teardown: ' + label + ' failed: ' + (error.message || error).toString().split('\n')[0]);
  }
}

function deleteProject(projectId) {
  const assignments = env.api4('VolunteerAssignment', 'get', {where: [['project_id', '=', projectId]]});
  for (const assignment of assignments) {
    attempt('delete assignment ' + assignment.id, () => env.api4('VolunteerAssignment', 'delete', {where: [['id', '=', assignment.id]]}));
  }
  // The generic get hides completed assignments, and a need cannot be
  // deleted while any activity still points at it, so remove every
  // volunteer activity on the project's needs by its custom field.
  const needIds = env.api4('VolunteerNeed', 'get', {select: ['id'], where: [['project_id', '=', projectId]]}).map((n) => n.id);
  if (needIds.length) {
    attempt('delete remaining volunteer activities', () => env.api4('Activity', 'delete', {
      where: [['CiviVolunteer.Volunteer_Need_Id', 'IN', needIds]],
    }));
  }
  // The flexible need refuses deletion by design; deleting the project removes it.
  attempt('delete needs', () => env.api4('VolunteerNeed', 'delete', {where: [['project_id', '=', projectId], ['is_flexible', '=', false]]}));
  attempt('delete project ' + projectId, () => env.api4('VolunteerProject', 'delete', {where: [['id', '=', projectId]]}));
}

module.exports = async function globalTeardown() {
  if (!fs.existsSync(env.stateFile)) {
    return;
  }
  const state = env.readState();
  for (const projectId of [state.projectId].concat(state.createdProjectIds || [])) {
    attempt('remove project ' + projectId, () => deleteProject(projectId));
  }
  for (const contactId of [state.volunteerContactId, state.beneficiaryId].concat(state.createdContactIds || [])) {
    attempt('delete contact ' + contactId, () => env.api4('Contact', 'delete', {where: [['id', '=', contactId]], useTrash: false}));
  }
  attempt('delete contacts the signup created', () => env.api4('Contact', 'delete', {
    where: [['email_primary.email', 'LIKE', 'e2e-signup-' + state.stamp + '%']], useTrash: false,
  }));
  if (!state.anonymousHadPermission) {
    attempt('revoke anonymous registration', () => env.revokeAnonymousRegistration());
  }
  fs.rmSync(env.stateFile, {force: true});
};
