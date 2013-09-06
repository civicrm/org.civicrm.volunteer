// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

Define.startWithParent = false;

  // Kick everything off
  Define.addInitializer(function() {

    Define.manageNeeds = new Define.layout();
    Define.needsTableView = new Define.defineNeedsView();
    Define.needsTable = new Define.defineNeedsTable();

    volunteerApp.dialogRegion.show(Define.manageNeeds);
    volunteerApp.dialogRegion.show(Define.needsTable);

    var request = volunteerApp.Entities.getNeeds(true);
    request.done(Define.needsTable.getCollection);
  });
  
});
