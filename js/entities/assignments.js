// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  var assignment = Backbone.Model.extend({
    defaults: {
      'display_name': '',
      'sort_name': ''
    }
  });

  Entities.Assignments = Backbone.Collection.extend({
    model: assignment,
    comparator: 'sort_name'
  });

});
