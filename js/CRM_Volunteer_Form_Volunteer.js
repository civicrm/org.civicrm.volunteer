CRM.$(function($) {

  //Defer loading of Angular
  //Because we are loading Angular through an ajax interface
  //we need to defer loading until everything is ready.
  //The batarang module employs this method to defer loading
  //see: https://docs.angularjs.org/guide/bootstrap
  //maybeBootstrap modified from that found at: https://github.com/angular/batarang
  var DEFER_LABEL = 'NG_DEFER_BOOTSTRAP!';
  window.name = DEFER_LABEL + window.name;
  function maybeBootstrap() {
    if (typeof angular === 'undefined' || !angular.resumeBootstrap) {
      return setTimeout(maybeBootstrap, 1);
    }
    window.name = window.name.substring(DEFER_LABEL.length);
    angular.resumeBootstrap();
  }
  maybeBootstrap();


  if (CRM.vars['org.civicrm.volunteer'].hash) {
    location.hash = CRM.vars['org.civicrm.volunteer'].hash;
  }

  //Handle Project Save
  $("body").on("volunteerProjectSaveComplete", function(event, projectId) {


    //If the buttons weren't visible, show them.
    if (CRM.vars['org.civicrm.volunteer'].projectId == 0) {
      $("#crm-volunteer-event-action-items").fadeIn();
    }

    //Store the projectId for later use (This is important if
    //we just created a new project)
    CRM.vars['org.civicrm.volunteer'].projectId = projectId;
    CRM.vars['org.civicrm.volunteer'].hash = CRM.vars['org.civicrm.volunteer'].hash.replace(/[0-9]*$/, projectId);

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
    location.hash = CRM.vars['org.civicrm.volunteer'].hash;
    CRM.$("body").trigger("volunteerProjectRefresh");

    //Show the editor frame
    $("#crm_volunteer_angular_frame").slideDown();
  });


  //Wire up the define button
  $("#crm-volunteer-event-define").click(function(event) {
    if (CRM.vars['org.civicrm.volunteer'].projectId != 0) {
      CRM.volunteerPopup(ts('Define Volunteer Opportunities'), 'Define', CRM.vars['org.civicrm.volunteer'].projectId);
    }
  });

  //Wire up the assign button
  $("#crm-volunteer-event-assign").click(function(event) {
    if (CRM.vars['org.civicrm.volunteer'].projectId != 0) {
      CRM.volunteerPopup(ts('Assign Volunteers'), 'Assign', CRM.vars['org.civicrm.volunteer'].projectId);
    }
  });

  //wire up the log Hours button
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

  //Hide the Edit button by defult
  $("#crm-volunteer-event-edit").hide();

  if (CRM.vars['org.civicrm.volunteer'].projectId == 0) {
    $("#crm-volunteer-event-action-items").hide();
  }

});