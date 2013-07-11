// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {
  var assignmentView = Marionette.ItemView.extend({
    template: '#crm-vol-assignment-tpl',
    className: 'crm-volunteer-assignment'
  });

  var needView = Marionette.CompositeView.extend({
    itemView: assignmentView,
    itemViewContainer: '.crm-vol-assignment-list',

    initialize: function(){
      this.collection = new volunteerApp.Entities.Assignments(_.toArray(this.model.get('api.volunteer_assignment.get').values));
      var type = this.model.get('is_flexible') == '1' ? 'flexible' : 'scheduled';
      this.template = '#crm-vol-' + type + '-tpl';
    },

    events: {
      'change #crm-vol-create-contact-select': 'createContactDialog'
    },

    createContactDialog: function(e) {
      var profile = $(e.target).val();
      if(profile.length) {
        var url = CRM.url('civicrm/profile/create', {
          reset: 1,
          snippet: 5,
          gid: profile
        });
        $('<div id="crm-vol-profile-form" class="crm-container">Loading...</div>').dialog({
          title: $(e.target).find(':selected').text(),
          modal: true,
          minWidth: 400,
          open: function() {
            $(e.target).val('');
            $(this).load(url, function() {
              $("#crm-vol-profile-form .cancel.form-submit").click(function() {
                $("#crm-vol-profile-form").dialog('close');
                return false;
              });
              $("#crm-vol-profile-form form").ajaxForm({
                url: url + '&context=dialog',
                dataType: 'json',
                success: function(response) {
                  CRM.alert(ts('%1 has been created.', {1: response.displayName}), ts('Contact Saved'), 'success');
                  $("#crm-vol-profile-form").dialog('close');
                }
              }).validate(CRM.validate.params);
            });
          },
          close: function() {
            $(this).dialog('destroy');
            $(this).remove();
          }
        });
      }
    }
  });

  Assign.needsView = Marionette.CollectionView.extend({
    itemView: needView
  });

});
