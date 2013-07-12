// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {
  var newContactId;

  var assignmentView = Marionette.ItemView.extend({
    template: '#crm-vol-assignment-tpl',
    className: 'crm-vol-assignment'
  });

  var needView = Marionette.CompositeView.extend({
    itemView: assignmentView,
    itemViewContainer: '.crm-vol-assignment-list',
    className: 'crm-vol-need',

    initialize: function() {
      this.collection = new volunteerApp.Entities.Assignments(_.toArray(this.model.get('api.volunteer_assignment.get').values));
      var type = this.model.get('is_flexible') == '1' ? 'flexible' : 'scheduled';
      this.isFlexible = !(type == 'scheduled');
      this.template = '#crm-vol-' + type + '-tpl';
    },

    templateHelpers: {
      pseudoConstant: CRM.pseudoConstant
    },

    events: {
      'change .crm-vol-create-contact-select': 'createContactDialog',
      'click .crm-add-vol-contact': 'addContact'
    },

    onRender: function() {
      this.isFlexible && this.initAutocomplete();
    },

    createContactDialog: function(e) {
      var that = this;
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
                // context=dialog triggers civi's profile to respond with json instead of an html redirect
                // but it also results in lots of unwanted scripts being added to the form snippet, so we
                // add it here during submission and not during form retrieval.
                url: url + '&context=dialog',
                dataType: 'json',
                success: function(response) {
                  $("#crm-vol-profile-form").dialog('close');
                  CRM.alert(ts('%1 has been created.', {1: response.displayName}), ts('Contact Saved'), 'success');
                  newContactId = response.cid;
                  that.addContact();
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
    },

    initAutocomplete: function() {
      var contactUrl = CRM.url('civicrm/ajax/rest', 'className=CRM_Contact_Page_AJAX&fnName=getContactList&json=1');
      $('.crm-add-volunteer', this.$el).autocomplete(contactUrl, {
        width: 200,
        selectFirst: false,
        minChars: 1,
        matchContains: true,
        delay: 400
      }).result(function(event, data) {
        newContactId = data[1];
      });
    },

    addContact: function() {
      if (newContactId) {
        $('.crm-add-volunteer', this.$el).val('');
        var contact = new volunteerApp.Entities.Assignment({
          contact_id: newContactId,
          display_name: 'FIXME',
          volunteer_need_id: this.model.get('id')
        });
        this.collection.add(contact);
      }
      newContactId = null;
    }

  });

  Assign.needsView = Marionette.CollectionView.extend({
    itemView: needView,
    className: 'crm-vol-assign-region-inner'
  });

});
