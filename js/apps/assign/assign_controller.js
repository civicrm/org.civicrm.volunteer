// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {
  var layout;
  Assign.startWithParent = false;

  // Kick everything off
  Assign.addInitializer(function() {
    layout = new Assign.layout();
    volunteerApp.dialogRegion.show(layout);
  });

  // Initialize entities and views
  Assign.on('start', function() {
    volunteerApp.Entities.getNeeds({'api.volunteer_assignment.get': {}, 'is_active': 1})
      .done(function(arrData) {
        var flexibleView = new Assign.needsView({
          collection: volunteerApp.Entities.Needs.getFlexible(arrData)
        });
        var scheduledView = new Assign.needsView({
          collection: volunteerApp.Entities.Needs.getScheduled(arrData)
        });
        layout.flexibleRegion.show(flexibleView);
        layout.scheduledRegion.show(scheduledView);
      });
  });

});
