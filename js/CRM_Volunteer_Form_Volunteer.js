CRM.$(function($) {
  
  if (CRM.VolunteerAngularSettings.Hash) {
    location.hash = CRM.VolunteerAngularSettings.Hash;
  }

  //Handle Project Save
  $("body").on("volunteerProjectSaveComplete", function(event, projectId) {


    //If the buttons weren't visible, show them.
    if(CRM.VolunteerAngularSettings.ProjectId == 0) {
      $("#crm-volunteer-event-action-items").fadeIn();
    }

    //Store the ProjectId for later use (This is important if
    //we just created a new project)
    CRM.VolunteerAngularSettings.ProjectId = projectId;
    CRM.VolunteerAngularSettings.Hash = CRM.VolunteerAngularSettings.Hash.replace(/[0-9]*$/, projectId);

    //Hide the editor frame
    $("#crm_volunteer_angular_frame").slideUp();
    //show the "edit" button
    $("#crm-volunteer-event-edit").fadeIn();
  });

  //Handle Project Edit Cancel
  $("body").on("volunteerProjectCancel", function() {
    //Hide the editor frame
    $("#crm_volunteer_angular_frame").slideUp();
    //show the "edit" button
    $("#crm-volunteer-event-edit").fadeIn();
  });


  //Wire up the edit settings button
  $("#crm-volunteer-event-edit").click(function(event) {
    //hide the "edit" button
    $("#crm-volunteer-event-edit").fadeOut();

    //Refresh the editor frame's data
    location.hash = CRM.VolunteerAngularSettings.Hash;
    CRM.$("body").trigger("volunteerProjectRefresh");

    //Show the editor frame
    $("#crm_volunteer_angular_frame").slideDown();
  });


  //Wire up the define button
  $("#crm-volunteer-event-define").click(function(event) {
    if (CRM.VolunteerAngularSettings.ProjectId != 0) {
      CRM.volunteerPopup(ts('Define Needs'), 'Define', CRM.VolunteerAngularSettings.ProjectId);
    }
  });

  //Wire up the assign button
  $("#crm-volunteer-event-assign").click(function(event) {
    if (CRM.VolunteerAngularSettings.ProjectId != 0) {
      CRM.volunteerPopup(ts('Assign Volunteers'), 'Assign', CRM.VolunteerAngularSettings.ProjectId);
    }
  });

  //wire up the log Hours button
  $("#crm-volunteer-event-log-hours").click(function(event) {
    if (CRM.VolunteerAngularSettings.ProjectId != 0) {
      var url = CRM.url("civicrm/volunteer/loghours", "reset=1&action=add&vid=" + CRM.VolunteerAngularSettings.ProjectId);
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

  //Hide the Edit button by defult
  $("#crm-volunteer-event-edit").hide();

  if(CRM.VolunteerAngularSettings.ProjectId == 0) {
    $("#crm-volunteer-event-action-items").hide();
  }

});