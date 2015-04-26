// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  Entities.VolCustomFieldModel = Backbone.Model.extend({});

  Entities.VolCustomFields = Backbone.Collection.extend({
    model: Entities.VolCustomFieldModel,
    comparator: 'weight'
  });

  Entities.allowedCustomFieldTypes = ['AdvMulti-Select', 'Autocomplete-Select', 'CheckBox', 'Multi-Select', 'Radio', 'Select', 'Text'];

  Entities.getVolCustomFields = function(params) {
    var defaults = {
      'extends': 'Individual',
      'name': 'Volunteer_Information',
      'api.customField.get': {
        'html_type': {'IN': Entities.allowedCustomFieldTypes},
        'is_active': 1,
        'is_searchable': 1
      },
      'options': {'limit': 0}
    };
    params = params || {};
    params = _.extend(defaults, params);

    var defer = CRM.$.Deferred();
    CRM.api3('CustomGroup', 'getsingle', params, {
      success: function(data) {
        var customFields = data['api.customField.get'].values;

        // get options for select lists
        var optionListIDs = [];
        CRM.$.each(customFields, function (i, field) {
          if (field.hasOwnProperty('option_group_id') && optionListIDs.indexOf(field.option_group_id) === -1) {
            optionListIDs.push(field.option_group_id);
          }
        });

        Entities.getOptions(optionListIDs)
          .done(function(optionList){
            CRM.$.each(customFields, function(i, field) {
              if (field.hasOwnProperty('option_group_id')) {
                customFields[i].options = optionList[field.option_group_id];

              // Boolean fields don't use option groups, so we supply one
              } else if (field.data_type === 'Boolean' && field.html_type === 'Radio') {
                customFields[i].options = [
                  {
                    is_active: 1,
                    is_default: 1,
                    label: ts("Yes"),
                    value: 1,
                    weight: 1
                  },
                  {
                    is_active: 1,
                    is_default: 0,
                    label: ts("No"),
                    value: 0,
                    weight: 2
                  }
                ];
              }
            });

            defer.resolve(_.toArray(customFields));
          });
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