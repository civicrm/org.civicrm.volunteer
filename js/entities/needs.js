// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  var need = Backbone.Model.extend({
    defaults: {
      'is_flexible': 0,
      'role_id': null,
      'start_time': null
    }
  });

  Entities.Needs = Backbone.Collection.extend({
    model: need,
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
        var needs = new Entities.Needs(_.toArray(data.values));
        defer.resolve(needs);
      }
    });
    return defer.promise();
  };

  Entities.sortNeeds = function(needs) {
    var flexible = needs.where({is_flexible: '1'});
    var scheduled = needs.where({is_flexible: '0'});
    return {
      flexible: new Entities.Needs(flexible),
      scheduled: new Entities.Needs(scheduled)
    }
  };

});
