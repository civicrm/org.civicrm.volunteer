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
        Assign.flexibleView = new Assign.needsView({
          collection: volunteerApp.Entities.Needs.getFlexible(arrData)
        });
        Assign.scheduledView = new Assign.needsView({
          collection: volunteerApp.Entities.Needs.getScheduled(arrData)
        });
        layout.flexibleRegion.show(Assign.flexibleView);
        layout.scheduledRegion.show(Assign.scheduledView);
      });
    // Hide menu when clicking away
    $('body').on('click', ':not(".crm-vol-menu-items *")', function(e) {
      $('.crm-vol-menu-items').remove();
    });
  });
  // Detach event handlers
  Assign.on('stop', function() {
    $('body').off('click', ':not(".crm-vol-menu-items *")');
  });

});