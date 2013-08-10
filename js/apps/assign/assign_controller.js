// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {

  // Initialize entities and views
  Assign.on('start', function() {
    var request = volunteerApp.Entities.getNeeds(true);
    
    request.done(function(needsCollection) {
      
      var flexibleView = new Assign.needsView({
        collection: volunteerApp.Entities.Needs.getFlexible(needsCollection)
      });
      
      var scheduledView = new Assign.needsView({
        collection: volunteerApp.Entities.Needs.getScheduled(needsCollection)
      });

      Assign.layout.flexibleRegion.show(flexibleView);
      Assign.layout.scheduledRegion.show(scheduledView);
    });
  });

});
