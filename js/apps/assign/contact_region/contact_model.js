// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign.Contact', function(Contact, volunteerApp, Backbone, Marionette, $, _) {

  Contact.Individual = Backbone.Model.extend({
    defaults: {
      'display_name': ''
    }
  });

  Contact.Individuals = Backbone.Collection.extend({
    model: Contact.Individual,
    comparator: 'sort_name'
  });

  function getContacts() {
    var defer = $.Deferred();
    var promise = defer.promise();
    var individuals = [];
    CRM.api('contact', 'get', {}, {
      success: function(data) {
        $.each(data.values, function() {
          individuals.push(_.pick(this, 'id', 'display_name', 'sort_name'));
        });
        defer.resolve(new Contact.Individuals(individuals));
      }
    });

    return promise;
  }

  volunteerApp.reqres.setHandler("assign:contacts", function() {
    return getContacts();
  });
});
