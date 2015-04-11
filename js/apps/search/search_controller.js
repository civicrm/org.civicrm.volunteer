// http://civicrm.org/licensing
CRM.volunteerApp.module('Search', function(Search, volunteerApp, Backbone, Marionette, $, _) {
  var layout;
  Search.startWithParent = false;
  Search.params = {};
  Search.resultsPerPage = 25;

  // Kick everything off
  Search.addInitializer(function() {
    layout = new Search.layout();
    volunteerApp.searchRegion.show(layout);
  });

  Search.saveAssignments = function(){
    var scheduledView = CRM.volunteerApp.module('Assign').scheduledView;
    var need = scheduledView.collection.get(Search.need_id);
    var needView = scheduledView.children.findByModel(need);
    var status = _.invert(CRM.pseudoConstant.volunteer_status);

    CRM.$('.crm-event-manage-volunteer-results-form-block [name=selected_contacts]:checked').each(function() {
      var contactID = $(this).val();
      var params = {
        contact_id: contactID,
        volunteer_need_id: need.get('id'),
        status_id: status['Available'],
        activity_date_time: need.get('start_time')
      };
      needView.collection.createNewAssignment(params);
    });

    $(this).dialog('close');;
  };

  // Initialize entities and views
  Search.on('start', function(params) {
    Search.need_id = params.need_id;
    Search.cnt_open_assignments = params.cnt_open_assignments;

    volunteerApp.Entities.getVolCustomFields().done(function(data) {
      Search.formFields = new volunteerApp.Entities.VolCustomFields(data);
      Search.formFields.push({
        column_name: 'group',
        html_type: 'Text',
        label: 'Group'
      });
      Search.formView = new Search.fieldsCollectionView({
        'collection': Search.formFields
      });
      layout.searchForm.show(Search.formView);
    });

    Search.pagerData = new volunteerApp.Entities.ContactPagerModel();
    Search.pager = new Search.pagerView({
      'model': Search.pagerData
    });
    layout.searchPager.show(Search.pager);

    Search.results = new volunteerApp.Entities.Contacts();
    Search.resultsView = new Search.resultsCompositeView({
      'collection': Search.results
    });
    layout.searchResults.show(Search.resultsView);
  });

  // Detach event handlers
  Search.on('stop', function() {
  });
});
