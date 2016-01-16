// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  Entities.VolCustomFieldModel = Backbone.Model.extend({});

  Entities.VolCustomFields = Backbone.Collection.extend({
    model: Entities.VolCustomFieldModel,
    comparator: 'weight'
  });

  Entities.getVolCustomFields = function() {
    var defer = CRM.$.Deferred();
    CRM.api3('VolunteerUtil', 'getcustomfields', {}, {
      success: function(data) {
        defer.resolve(data.values);
      }
    });
    return defer.promise();
  };

  /**
   * Retrieves option values for the given option group IDs
   *
   * @param {Array} ids IDs of option groups for which to retrieve option values
   * @returns {Promise} The callback should expect data in the format of
   *                 {option_group_id: [api.OptionValue.getsingle, api.OptionValue.getsingle...]}
   *                 e.g. {19: [Object, Object, Object], 21: [Object]}
   */
  Entities.getOptions = function(ids) {
    var defer = CRM.$.Deferred();

    CRM.api3('OptionValue', 'get', {
      'is_active': 1,
      'opt_group_id': {'IN': ids},
      'options': {
        'limit': 0,
        'sort': 'weight'
      }
    }).done(function(data) {
      var options = _.groupBy(data.values, 'option_group_id');
      defer.resolve(options);
    });

    return defer.promise();
  };
});