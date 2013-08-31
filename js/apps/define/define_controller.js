// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

Define.startWithParent = false;

  // Kick everything off
  Define.addInitializer(function() {

    var request = volunteerApp.Entities.getNeeds(true);

    request.done(
      function(needsCollection) {
        Define.manageNeeds = new Define.layout();
        Define.needsTableView = new Define.defineNeedsView();
        Define.needsTable = new Define.defineNeedsTable({collection: needsCollection});

        volunteerApp.dialogRegion.show(Define.manageNeeds);
        volunteerApp.dialogRegion.show(Define.needsTable);
        cj('#crm-vol-define-needs-dialog').attr('data-project_id', needsCollection.models[0].attributes.project_id);

  });

    
  });

  
 
});
