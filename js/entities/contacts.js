// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  Entities.ContactModel = Backbone.Model.extend({});

  Entities.Contacts = Backbone.Collection.extend({
    model: Entities.ContactModel,
    comparator: 'sort_name'
  });

  Entities.getContacts = function(params) {
    var defaults = {
      'sequential': 1,
      'return': [
        'contact_id',
        'sort_name',
        'email',
        'phone',
        'city',
        'state_province'
      ],
      'options': {
        'limit': 25,
        'offset': 0
      }
    };
    params = params || {};
    params = _.extend(defaults, params);

    var defer = $.Deferred();
    CRM.api('Contact', 'get', params, {
      success: function(data) {
        defer.resolve(_.toArray(data.values));
      }
    });
    return defer.promise();
  };
});