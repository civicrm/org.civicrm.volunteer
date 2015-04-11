// http://civicrm.org/licensing
CRM.volunteerApp = new Backbone.Marionette.Application();
CRM.volunteerApp.addRegions({
  dialogRegion: '#crm-volunteer-dialog',
  searchRegion: '#crm-volunteer-search-dialog'
});

CRM.$(function($) {
  // Wait for all scripts to load before starting app
  CRM.volunteerApp.start();

  CRM.volunteerApp.searchDialogSettings = {
    modal: true,
    title: 'Find Volunteers',
    width: '75%',
    height: parseInt($(window).height() * .70),
    buttons: [
      {
        text: ts('Save'),
//        disabled: true,
        click: CRM.volunteerApp.module('Search').saveAssignments,
        icons: {
          primary: 'ui-icon-check'
        }
      },
      {
        text: ts('Cancel'),
        click: function() {
          $(this).dialog('close');
        },
        icons: {
          primary: 'ui-icon-close'
        }
      }
    ],
    close: function() {
      CRM.volunteerApp.module('Search').stop();
    }
  };

  function dialogSettings($el) {
    var settings = {
      modal: true,
      title: $el.text(),
      width: '85%',
      height: parseInt($(window).height() * .80),
      buttons: [{text: ts('Done'), click: function() {$(this).dialog('close');}, icons: {primary: 'ui-icon-close'}}],
      close: function() {
        CRM.volunteerApp.module(CRM.volunteerApp.tab).stop();
      }
    };
    return settings;
  }

  // FIXME: This could be rendered and managed by the volunteerApp for more internal consistency
  $("#crm-container").on('click', 'a.crm-volunteer-popup', function(e) {
    CRM.volunteerApp.tab = $(this).data('tab');
    CRM.volunteerApp.project_id = $(this).data('vid');
    $('#crm-volunteer-dialog').dialog(dialogSettings($(this)));
    CRM.volunteerApp.module(CRM.volunteerApp.tab).start();
    e.preventDefault();
  });

});