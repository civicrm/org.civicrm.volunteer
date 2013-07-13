// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {
  var newContactId, dragFrom;

  var assignmentView = Marionette.ItemView.extend({
    template: '#crm-vol-assignment-tpl',
    className: 'crm-vol-assignment',

    onRender: function () {
      // Store id to facilitate dragging & dropping
      this.$el.attr('data-id', this.model.get('id'));
    }
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
      'click .crm-add-vol-contact': 'addNewContact'
    },

    onRender: function() {
      var thisView = this;
      this.isFlexible && this.initAutocomplete();
      var quantity = this.model.get('quantity');
      if (quantity > this.collection.length) {
        var delta = quantity - this.collection.length;
        var msg = this.collection.length ? ts('%1 More Needed', {1: delta}) : ts('%1 Needed', {1: delta});
        $('.crm-vol-assignment-list', this.$el).append('<div class="crm-vol-vacancy">' + msg + '</div>');
      }
      if (this.isFlexible && !this.collection.length) {
        $('.crm-vol-assignment-list', this.$el).append('<div class="crm-vol-placeholder">' + ts('None') + '</div>');
      }
      // Allow volunteers to be dragged out of this view
      $('.crm-vol-assignment', this.$el).draggable({
        helper: "clone",
        zindex: 99999999999,
        containment: '#crm-volunteer-dialog',
        start: function() {
          dragFrom = thisView;
        }
      });
      // If we have room for more volunteers (or quantity is flexible), accept dropping in more
      if (!quantity || quantity > this.collection.length) {
        $(this.itemViewContainer, this.$el).droppable({
          activeClass: "ui-state-default",
          hoverClass: "ui-state-hover",
          drop: function(event, ui) {
            var id = $(ui.draggable).data('id');
            var assignment = dragFrom.collection.get(id);
            dragFrom.collection.remove(assignment);
            dragFrom.render();
            assignment.set('volunteer_need_id', thisView.model.get('id'));
            thisView.collection.add(assignment);
            thisView.render();
            CRM.api('volunteer_assignment', 'create', {id: id, volunteer_need_id: thisView.model.get('id')});
          }
        });
      }
    },

    createContactDialog: function(e) {
      var thisView = this;
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
                  newContactId = response.contactID;
                  thisView.addNewContact();
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

    addNewContact: function() {
      if (newContactId) {
        $('.crm-add-volunteer', this.$el).val('');
        this.collection.createNewAssignment({contact_id: newContactId, volunteer_need_id: this.model.get('id')});
        newContactId = null;
      }
    }

  });

  Assign.needsView = Marionette.CollectionView.extend({
    itemView: needView,
    className: 'crm-vol-assign-region-inner'
  });

});
