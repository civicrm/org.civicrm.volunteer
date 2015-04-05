// http://civicrm.org/licensing
CRM.volunteerApp.module('Search', function(Search, volunteerApp, Backbone, Marionette, $, _) {
  var layout;
  Search.startWithParent = false;

  // Kick everything off
  Search.addInitializer(function() {
    layout = new Search.layout();
    volunteerApp.searchRegion.show(layout);
  });

  // Initialize entities and views
  Search.on('start', function() {
    volunteerApp.Entities.getVolCustomFields().done(function(data) {
      Search.collectionView = new Search.fieldsCollectionView({
        'collection': new volunteerApp.Entities.VolCustomFields(data)
      });
      layout.searchForm.show(Search.collectionView);

      console.dir(data);
    });
//      .done(function(arrData) {
//        Assign.flexibleView = new Assign.needsView({
//          collection: volunteerApp.Entities.Needs.getFlexible(arrData)
//        });
//        Assign.scheduledView = new Assign.needsView({
//          collection: volunteerApp.Entities.Needs.getScheduled(arrData)
//        });
//        layout.flexibleRegion.show(Assign.flexibleView);
//        layout.scheduledRegion.show(Assign.scheduledView);
//      });
  });

  // Detach event handlers
  Search.on('stop', function() {
  });
});
