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
        Define.collectionView = new Define.needsCompositeView({
          'collection': volunteerApp.Entities.Needs.getScheduled(arrData)
        });
        layout.scheduledNeeds.show(Define.collectionView);

        var flexibleNeedModel = new CRM.volunteerApp.Entities.NeedModel(_.findWhere(arrData, {is_flexible: '1'}));
        var flexibleItemView = new Define.flexibleNeedItemView(flexibleNeedModel);
        flexibleItemView.model = flexibleNeedModel;
        layout.flexibleNeeds.show(flexibleItemView);
      });
  });

});
