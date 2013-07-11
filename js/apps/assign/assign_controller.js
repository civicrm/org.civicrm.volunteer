// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {

  // Initialize entities and views
  Assign.on('start', function() {
    var request = volunteerApp.Entities.getNeeds(true);
    $.when(request).done(function(needs) {
      needs = volunteerApp.Entities.sortNeeds(needs);
      var flexibleView = new Assign.needsView({
        collection: needs.flexible
      });
      Assign.layout.flexibleRegion.show(flexibleView);
      var scheduledView = new Assign.needsView({
        collection: needs.scheduled
      });
      Assign.layout.scheduledRegion.show(scheduledView);
    });
  });

});
