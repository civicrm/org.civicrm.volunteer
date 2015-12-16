// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  Entities.Assignment = Backbone.Model.extend({
    defaults: {
      'phone': '',
      'email': ''
    }
  });

  Entities.Assignments = Backbone.Collection.extend({
    model: Entities.Assignment,
    comparator: 'sort_name',

    createNewAssignment: function(params) {
      var thisCollection = this;
      var defer = CRM.$.Deferred();
      CRM.api3('volunteer_assignment', 'create', params, true)
        .done(function(result) {
          defer.resolve();
          var id = result.id;
          var assignment = new Entities.Assignment(result.values[id]);
          thisCollection.add(assignment);
      });
      return defer.promise();
    }
  });

});
