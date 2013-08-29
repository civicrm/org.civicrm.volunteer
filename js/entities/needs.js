// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  var NeedModel = Backbone.Model.extend({
    defaults: {
      'is_flexible': 0,
      'is_flexible_form_value': null,
      'duration': 0,
      'role_id': null,
      'start_time': null,
      'display_start': null,
      'num_needed': null,
      'filled': null,
      'visibility': null,
      'links': null
    }
  });

  Entities.Needs = Backbone.Collection.extend({
    model: NeedModel,
    comparator: 'start_time'
  });

  Entities.createNewNeed = function(params) {
      var thisCollection = this.Needs;
      CRM.api('volunteer_need', 'create', params, {
        success: function(result) {
          var id = result.id;
          var need = new Entities.NeedModel(result.values[id]);
          thisCollection.add(need);
        }
      });
    }

  Entities.getNeeds = function(alsoFetchAssignments) {
    var defer = $.Deferred();
    var params = {project_id: volunteerApp.vid};
    if (alsoFetchAssignments) {
      params['api.volunteer_assignment.get'] = {};
    }
    CRM.api('volunteer_need', 'get', params, {
      success: function(data) {
        var needsCollection = new Entities.Needs(_.toArray(data.values));

        for( index in needsCollection.models) {
          need = needsCollection.models[index];
          if (need.attributes.is_flexible == 1) {
            need.attributes.is_flexible_form_value = 'checked';
          }
        }

        defer.resolve(needsCollection);
        console.log('getNeeds::needsCollection =>', needsCollection)
        console.trace();
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
