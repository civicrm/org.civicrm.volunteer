// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

Define.startWithParent = false;

  // Kick everything off
  Define.addInitializer(function() {
    var layout = new Define.layout();
    volunteerApp.dialogRegion.show(layout);

    var request = volunteerApp.Entities.getNeeds({'api.volunteer_assignment.getcount': {}});
    request.done(function(arrData) {
      Define.collectionView = new Define.needsCompositeView({
        'collection': volunteerApp.Entities.Needs.getScheduled(arrData)
      });
      layout.newNeeds.show(Define.collectionView);
    });
  });

});
