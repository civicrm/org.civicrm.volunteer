// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  var needModel = Backbone.Model.extend({
    defaults: {
      'is_flexible': 0,
      'duration': 0,
      'role_id': null,
      'start_time': null
    }
  });

  Entities.Needs = Backbone.Collection.extend({
    model: needModel,
    comparator: 'start_time'
  });

  Entities.getNeeds = function(alsoFetchAssignments) {
    var defer = $.Deferred();
    var params = {project_id: volunteerApp.vid};
    if (alsoFetchAssignments) {
      params['api.volunteer_assignment.get'] = {};
    }
    CRM.api('volunteer_need', 'get', params, {
      success: function(data) {
        var needsCollection = new Entities.Needs(_.toArray(data.values));
        defer.resolve(needsCollection);
      }
    });
    return defer.promise();
  };

  Entities.Needs.getFlexible = function(needsCollection) {
    return new Entities.Needs(needsCollection.where({is_flexible: '1'}));
  };

  Entities.Needs.getScheduled = function(needsCollection) {
    return new Entities.Needs(needsCollection.where({is_flexible: '0'}));
  };
  
});
