// http://civicrm.org/licensing
CRM.volunteerApp.module('Entities', function(Entities, volunteerApp, Backbone, Marionette, $, _) {

  Entities.ContactModel = Backbone.Model.extend({});

  Entities.ContactPagerModel = Backbone.Model.extend({
    defaults: {
      'end': 0,
      'start': 0,
      'total': 0
    }
  });

  Entities.Contacts = Backbone.Collection.extend({
    model: Entities.ContactModel,
    comparator: 'sort_name'
  });

  Entities.getContacts = function() {
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
        'limit': CRM.volunteerApp.module('Search').resultsPerPage,
        'offset': 0
      }
    };
    CRM.volunteerApp.module('Search').params = _.extend(defaults, CRM.volunteerApp.module('Search').params);

    CRM.volunteerApp.module('Search').pagerData.set({
      'end': CRM.volunteerApp.module('Search').params.options.offset + CRM.volunteerApp.module('Search').params.options.limit,
      'start': CRM.volunteerApp.module('Search').params.options.offset + 1
    });

    var defer = $.Deferred();
    CRM.api('Contact', 'get', CRM.volunteerApp.module('Search').params, {
      success: function(data) {
        defer.resolve(_.toArray(data.values));
      }
    });
    return defer.promise();
  };

  Entities.getContactCount = function() {
    var defaults = {
      'options': {
        'limit': 0
      }
    };
    params = _.extend(defaults, CRM.volunteerApp.module('Search').params);

    var defer = $.Deferred();
    CRM.api('Contact', 'getcount', params, {
      success: function(data) {
        defer.resolve(data.result);
      }
    });
    return defer.promise();
  };
});