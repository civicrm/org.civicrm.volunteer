CRM.$(function ($) {
  // prepareTab() publishes this; without it there is no project context to
  // work with and the tab was reached by a route that should not have allowed
  // it. Bail rather than throwing.
  var vars = CRM.vars && CRM.vars['org.civicrm.volunteer'];
  if (!vars) {
    return;
  }

  // Moving a CKEditor instance breaks it because of its use of iframes
  // (https://stackoverflow.com/a/28650844); so we must destroy and re-create it
  var description = $('#crm-vol-form-textarea-wrapper textarea');
  CRM.wysiwyg.destroy(description);

  var angFrame = $('#crm_volunteer_angular_frame');
  $('.crm-volunteer-event-action-items-all').after(angFrame);
  CRM.wysiwyg.create(description);
  angFrame.show();

  // Handle Project Save
  $("body").on("volunteerProjectSaveComplete", function(event, projectId) {
    // Store the projectId for later use. This matters when the project was
    // just created: the tab was bootstrapped with an id of 0.
    vars.projectId = projectId;
    vars.hash = vars.hash.replace(/\/\d+(?:\/details)?$/, '/' + projectId + '/details');
  });
});
