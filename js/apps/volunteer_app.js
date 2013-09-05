// http://civicrm.org/licensing
CRM.volunteerApp = new Backbone.Marionette.Application();
CRM.volunteerApp.addRegions({
  dialogRegion: '#crm-volunteer-dialog'
});

cj(function($) {
  // Wait for all scripts to load before starting app
  CRM.volunteerApp.start();

  // FIXME: This could be rendered and managed by the volunteerApp for more internal consistency
  $("#crm-container").on('click', 'a.crm-volunteer-popup', function() {
    var moduleName = CRM.volunteerApp.tab = $(this).data('tab');
    CRM.volunteerApp.vid = $(this).data('vid');
    $('#crm-volunteer-dialog').dialog({
      modal: true,
      title: $(this).text(),
      minWidth: 800,
      position: {my: 'top', at: 'top+25%', of: window},
      close: function() {
        CRM.volunteerApp.module(CRM.volunteerApp.tab).stop();
      }
    });
    CRM.volunteerApp.module(moduleName).start();
    return false;
  });
});
