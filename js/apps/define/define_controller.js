// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

Define.startWithParent = false;

var defineLayout = Marionette.Layout.extend({
    template: "#crm-vol-define-layout-tpl",
    regions: {
      newNeeds: "#crm-vol-define-newNeed-tpl"
    }
  });

  // Kick everything off
  Define.addInitializer(function() {
    Define.layout = new defineLayout();
    volunteerApp.dialogRegion.show(Define.layout);
  });

  // Initialize entities and views
  Define.on('start', function() {
    var request = volunteerApp.Entities.getNeeds(true);
    request.done(bindNeeds);

  });

  function bindNeeds(needsCollection) {

    var viewNeeds = new Define.defineNeedsView(
          { collection: needsCollection }
     );
/* ain't workin'  */
     Define.layout.newNeeds.show(viewNeeds);

    };

});
