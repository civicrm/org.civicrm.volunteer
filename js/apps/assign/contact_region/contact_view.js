// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign.Contact', function(Contact, volunteerApp, Backbone, Marionette, $, _) {
  var individualView = Marionette.ItemView.extend({
    template: '#crm-vol-individual-tpl',
    className: "crm-volunteer-contact"
  });

  Contact.ListView = Marionette.CompositeView.extend({
    tagName: "div",
    itemView: individualView,
    itemViewContainer: '#crm-vol-contact-list',
    template: '#crm-vol-contacts-tpl',

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
        $('<div id="crm-vol-profile-form" class="crm-container"/>').dialog({
          title: $(e.target).find(':selected').text(),
          modal: true,
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
});
