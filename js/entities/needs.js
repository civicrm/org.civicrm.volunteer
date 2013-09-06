// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  var NeedModel = Backbone.Model.extend({
    defaults: {
      'display_start_date': null, // generated in getNeeds
      'display_start_time': null, // generated in getNeeds
      'is_active' : 1,
      'is_flexible': 0,
      'duration': 0,
      'role_id': null,
      'start_time': null,
      'num_needed': null,
      'filled': null,
      'visibility_id': _.invert(CRM.pseudoConstant.volunteer_need_visibility).Public
    }
  });

  Entities.Needs = Backbone.Collection.extend({
    model: NeedModel,
    comparator: 'start_time',
    createNewNeed : function(params) {
      var defer = $.Deferred();
      CRM.api('volunteer_need', 'create', params, {
        success: function(result) {
          defer.resolve(result.id);
        }
      });
      return defer.promise();
   }
 });

  Entities.getNeeds = function(alsoFetchAssignments) {
    var defer = $.Deferred();
    var params = {project_id: volunteerApp.vid};
    if (alsoFetchAssignments) {
      params['api.volunteer_assignment.get'] = {};
    }
    CRM.api('volunteer_need', 'get', params, {

      success: function(data) {
        // generate user-friendly date and time strings
        $.each(data.values, function (k, v){
          if (data.values[k].start_time) {
            // TODO: respect user-configured time formats
            var timeDate = data.values[k].start_time.split(" ");
            var date = $.datepicker.parseDate("yy-mm-dd", timeDate[0]);
            data.values[k].display_start_date = $.datepicker.formatDate("MM d, yy", date);
            data.values[k].display_start_time = timeDate[1].substring(0, 5);
          }
        });
        defer.resolve(_.toArray(data.values));
      }

    });
    return defer.promise();
  };

  Entities.Needs.getFlexible = function(arrData) {
    var needsCollection = new Entities.Needs(arrData);
    return new Entities.Needs(needsCollection.where({is_flexible: '1'}));
  };

  Entities.Needs.getScheduled = function(arrData) {
    var needsCollection = new Entities.Needs(arrData);
    return new Entities.Needs(needsCollection.where({is_flexible: '0'}));
  };

});
