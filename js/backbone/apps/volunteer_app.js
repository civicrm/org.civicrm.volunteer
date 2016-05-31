// http://civicrm.org/licensing
CRM.volunteerApp = new Backbone.Marionette.Application();
CRM.volunteerApp.addRegions({
  dialogRegion: '#crm-volunteer-dialog',
  searchRegion: '#crm-volunteer-search-dialog'
});

CRM.$(function($) {
  // Wait for all scripts to load before starting app
  CRM.volunteerApp.start();

  CRM.volunteerDialogSettings = function(title) {
    var settings = {
      modal: true,
      title: title,
      width: '85%',
      height: parseInt($(window).height() * .80),
      buttons: [{text: ts('Done'), click: function() {$(this).dialog('close');}, icons: {primary: 'ui-icon-close'}}],
      close: function () {
        if(CRM.volunteerApp.tab == "Define") {
          $("body").trigger("volunteer:close:define", [CRM.volunteerApp.project_id, CRM.volunteerApp.Define.needRegistry]);
        }
        CRM.volunteerApp.module(CRM.volunteerApp.tab).stop();
      }
    };
    return settings;
  };

  CRM.volunteerPopup = function(title, tab, vid) {
    CRM.volunteerApp.tab = tab;
    CRM.volunteerApp.project_id = vid;
    $('#crm-volunteer-dialog').dialog(CRM.volunteerDialogSettings(title));
    CRM.volunteerApp.module(CRM.volunteerApp.tab).start();
  };

  // FIXME: This could be rendered and managed by the volunteerApp for more internal consistency
  $("#crm-container").on('click', 'a.crm-volunteer-popup', function(e) {
    CRM.volunteerPopup($(this).text(), $(this).data('tab'), $(this).data('vid'));
    e.preventDefault();
  });

});