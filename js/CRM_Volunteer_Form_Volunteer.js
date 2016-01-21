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

  //Hide the Edit button by defult
  $("#crm-volunteer-event-edit").hide();

  if(CRM.VolunteerAngularSettings.ProjectId == 0) {
    $("#crm-volunteer-event-action-items").hide();
  }

});