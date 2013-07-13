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
      CRM.api('volunteer_assignment', 'create', params, {
        success: function(result) {
          var id = result.id;
          var assignment = new Entities.Assignment(result.values[id]);
          thisCollection.add(assignment);
        }
      });
    }
  });

});
