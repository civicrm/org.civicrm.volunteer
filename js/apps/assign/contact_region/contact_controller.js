// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign.Contact', function(Contact, volunteerApp, Backbone, Marionette, $, _) {

  // Initialize entities and views
  volunteerApp.Assign.on('start', function() {
    var contactRequest = volunteerApp.request("assign:contacts");
    $.when(contactRequest).done(function(contacts) {
      var contactView = new Contact.ListView({
        collection: contacts
      });
      volunteerApp.Assign.layout.contactRegion.show(contactView);
    });
  });

});
