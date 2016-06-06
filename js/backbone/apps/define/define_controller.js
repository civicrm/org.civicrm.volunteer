// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {
  var layout;
  Define.startWithParent = false;

  // Kick everything off
  Define.addInitializer(function() {
    layout = new Define.layout();
    volunteerApp.dialogRegion.show(layout);
  });

  // Initialize entities and views
  Define.on('start', function() {

    volunteerApp.Entities.getNeeds({'api.volunteer_assignment.getcount': {}})
      .done(function(arrData) {
        var collectionData = volunteerApp.Entities.Needs.getScheduled(arrData);


        // As needs are created, updated and deleted, their IDs are added to an object.
        // Reopening the dialog resets the data. This is intended to be
        // an extension point; external code can listen for the volunteer:close:define event
        // then access the list of needs indexed by event type (clean, updated, created, deleted)
        //NYCCAH-130: This needs to be reset inside the start function so it runs every time ui is recreated.
        Define.needRegistry = {"clean": [], "updated": [], "created": [], "deleted": []};

        //Store the Clean Needs so 'volunteer:close:define' has reference points.
        _.each(collectionData.models, function (item) {
          Define.needRegistry.clean.push(item.id);
        });

        Define.collectionView = new Define.needsCompositeView({
          'collection': collectionData
        });
        layout.scheduledNeeds.show(Define.collectionView);

        var flexibleNeedModel = new CRM.volunteerApp.Entities.NeedModel(_.findWhere(arrData, {is_flexible: '1'}));
        var flexibleItemView = new Define.flexibleNeedItemView(flexibleNeedModel);
        flexibleItemView.model = flexibleNeedModel;
        layout.flexibleNeeds.show(flexibleItemView);
      });
  });

});
