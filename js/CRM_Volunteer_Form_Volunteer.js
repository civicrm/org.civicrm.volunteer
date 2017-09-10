CRM.$(function ($) {

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


    //If the buttons weren't visible, show them.
    if (CRM.vars['org.civicrm.volunteer'].projectId == 0) {
      $("#crm-volunteer-event-action-items").fadeIn();
    }

    //Store the projectId for later use (This is important if
    //we just created a new project)
    CRM.vars['org.civicrm.volunteer'].projectId = projectId;
    CRM.vars['org.civicrm.volunteer'].hash = CRM.vars['org.civicrm.volunteer'].hash.replace(/[0-9]*$/, projectId);

    // Hide the editor frame
    angFrame.slideUp();
    //show the "edit" button
    $("#crm-volunteer-event-edit").fadeIn();
  });

  // Handle Project Edit Cancel
  $("body").on("volunteerProjectCancel", function() {
    //Hide the editor frame
    angFrame.slideUp();
    //show the "edit" button
    $("#crm-volunteer-event-edit").fadeIn();
  });


  // Wire up the edit settings button
  $("#crm-volunteer-event-edit").click(function(event) {
    //hide the "edit" button
    $("#crm-volunteer-event-edit").fadeOut();

    //Refresh the editor frame's data
    location.hash = CRM.vars['org.civicrm.volunteer'].hash;
    CRM.$("body").trigger("volunteerProjectRefresh");

    //Show the editor frame
    angFrame.slideDown();
  });


  // Wire up the define button
  $("#crm-volunteer-event-define").click(function(event) {
    if (CRM.vars['org.civicrm.volunteer'].projectId != 0) {
      CRM.volunteerPopup(ts('Define Volunteer Opportunities'), 'Define', CRM.vars['org.civicrm.volunteer'].projectId);
    }
  });

  // Wire up the assign button
  $("#crm-volunteer-event-assign").click(function(event) {
    if (CRM.vars['org.civicrm.volunteer'].projectId != 0) {
      CRM.volunteerPopup(ts('Assign Volunteers'), 'Assign', CRM.vars['org.civicrm.volunteer'].projectId);
    }
  });

  // wire up the log Hours button
  $("#crm-volunteer-event-log-hours").click(function(event) {
    if (CRM.vars['org.civicrm.volunteer'].projectId != 0) {
      var url = CRM.url("civicrm/volunteer/loghours", "reset=1&action=add&vid=" + CRM.vars['org.civicrm.volunteer'].projectId);
      var dialogSettings = {"dialog":{"width":"85%", "height":"80%"}};
      var formSuccess = false;
      var $el = $(this);

      //Create the Dialog
      var dialog = CRM.loadForm(url, dialogSettings);
      // Trigger events from the dialog on the original link element
      $el.trigger('crmPopupOpen', [dialog]);

      dialog.on('crmFormSuccess.crmPopup crmPopupFormSuccess.crmPopup', function() {
        formSuccess = true;
      });
      dialog.on('dialogclose.crmPopup', function(e, data) {
        if (formSuccess) {
          $el.trigger('crmPopupFormSuccess', [dialog, data]);
        }
        $el.trigger('crmPopupClose', [dialog, data]);
      });

    }
  });

  // Hide the Edit button by default
  $("#crm-volunteer-event-edit").hide();

  if (CRM.vars['org.civicrm.volunteer'].projectId == 0) {
    $("#crm-volunteer-event-action-items").hide();
  }

});