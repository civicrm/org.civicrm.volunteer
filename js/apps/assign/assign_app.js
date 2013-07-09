// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {
  Assign.startWithParent = false;

  var assignLayout = Marionette.Layout.extend({
    template: "#crm-vol-assign-layout-tpl",
    regions: {
      contactRegion: "#crm-vol-assign-contact-region",
      eventRegion: "#crm-vol-assign-event-region"
    }
  });

  // Kick everything off
  Assign.addInitializer(function() {
    Assign.layout = new assignLayout();
    volunteerApp.dialogRegion.show(Assign.layout);
  });

});
