// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  Entities.Assignment = Backbone.Model.extend({
    defaults: {
      'display_name': '',
      'sort_name': ''
    }
  });

  Entities.Assignments = Backbone.Collection.extend({
    model: Entities.Assignment,
    comparator: 'sort_name'
  });

});
