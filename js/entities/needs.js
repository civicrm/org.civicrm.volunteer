// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  Entities.NeedModel = Backbone.Model.extend({
    defaults: {
      'display_start_date': null, // generated in getNeeds
      'display_start_time': null, // generated in getNeeds
      'is_active' : 1,
      'is_flexible': 0,
      'duration': 0,
      'role_id': null,
      'start_time': CRM.volunteer.default_date,
      'quantity': null,
      'filled': null,
      'visibility_id': CRM.pseudoConstant.volunteer_need_visibility.public
    }
  });

  Entities.Needs = Backbone.Collection.extend({
    model: Entities.NeedModel,
    comparator: 'start_time',
    createNewNeed : function(params) {
      params = _.extend({
        project_id: volunteerApp.project_id,
        start_time: CRM.volunteer.default_date
      }, params);
      formatDate(params);
      var need = new this.model(params);
      this.add(need);
      return CRM.api3('volunteer_need', 'create', params, true)
        .done(function(result) {
          need.set('id', result.id);
        });
   }
 });

  Entities.getNeeds = function(params) {
    defaults = {
      'options': {'limit': 0}
    };
    params = params || {};
    params = _.extend(defaults, params);

    var defer = $.Deferred();
    params.project_id = volunteerApp.project_id;
    CRM.api('volunteer_need', 'get', params, {
      success: function(data) {
        // generate user-friendly date and time strings
        $.each(data.values, function (k, v) {
          formatDate(data.values[k]);
        });
        defer.resolve(_.toArray(data.values));
      }
    });
    return defer.promise();
  };

  function formatDate (arrayData) {
    if (arrayData.start_time) {
      var timeDate = arrayData.start_time.split(" ");
      var date = $.datepicker.parseDate("yy-mm-dd", timeDate[0]);
      arrayData.display_start_date = $.datepicker.formatDate($.datepicker._defaults.dateFormat, date);
      arrayData.display_start_time = timeDate[1].substring(0, 5);
    }
  }

  Entities.Needs.getFlexible = function(arrData) {
    return new Entities.Needs(_.where(arrData, {is_flexible: '1'}));
  };

  Entities.Needs.getScheduled = function(arrData) {
    return new Entities.Needs(_.where(arrData, {is_flexible: '0'}));
  };

});
