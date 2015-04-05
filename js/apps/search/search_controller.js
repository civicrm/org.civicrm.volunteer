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
      var collection = new volunteerApp.Entities.VolCustomFields(data);
      collection.push({
        column_name: 'group',
        html_type: 'Text',
        label: 'Group'
      });
      Search.collectionView = new Search.fieldsCollectionView({
        'collection': collection
      });
      layout.searchForm.show(Search.collectionView);
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
