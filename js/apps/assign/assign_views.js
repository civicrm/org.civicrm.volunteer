// http://civicrm.org/licensing
CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {
  var newContactId, dragFrom;

  var assignmentViewSettings = {
    tagName: 'tr',
    className: 'crm-vol-assignment',

    onRender: function () {
      // Store id to facilitate dragging & dropping
      this.$el.attr('data-id', this.model.get('id'));
    }
  };

  Assign.scheduledAssignmentView = Marionette.ItemView.extend(_.extend(assignmentViewSettings, {
    template: '#crm-vol-scheduled-assignment-tpl'
  }));

  Assign.flexibleAssignmentView = Marionette.ItemView.extend(_.extend(assignmentViewSettings, {
    template: '#crm-vol-flexible-assignment-tpl'
  }));

  var needView = Marionette.CompositeView.extend({
    hasBeenInitialized: false,
    profileUrl: '',
    itemViewContainer: '.crm-vol-assignment-list',
    className: 'crm-vol-need crm-form-block',

    initialize: function() {
      this.collection = new volunteerApp.Entities.Assignments(_.toArray(this.model.get('api.volunteer_assignment.get').values));
      var type = this.model.get('is_flexible') == '1' ? 'flexible' : 'scheduled';
      this.isFlexible = !(type == 'scheduled');
      this.template = '#crm-vol-' + type + '-tpl';
      this.itemView = Assign[type + 'AssignmentView'];
    },

    templateHelpers: {
      pseudoConstant: CRM.pseudoConstant
    },

    events: {
      'change .crm-vol-create-contact-select': 'createContactDialog',
      'click .crm-add-vol-contact': 'addNewContact',
      'click .crm-vol-del': 'removeContact'
    },

    onAfterItemAdded: function() { this.doCount(); },
    onItemRemoved: function() { this.doCount(); },

    onRender: function() {
      var thisView = this;
      $(this.itemViewContainer, this.$el).droppable({
        activeClass: "ui-state-default",
        hoverClass: "ui-state-hover",
        drop: function(event, ui) {
          var id = $(ui.draggable).data('id');
          var assignment = dragFrom.collection.get(id);
          dragFrom.collection.remove(assignment);
          assignment.set('volunteer_need_id', thisView.model.get('id'));
          thisView.collection.add(assignment);
          var status = _.invert(CRM.pseudoConstant.volunteer_status);
          CRM.api('volunteer_assignment', 'create', {
            id: id,
            volunteer_need_id: thisView.model.get('id'),
            status_id: status[thisView.isFlexible ? 'Available' : 'Scheduled'],
            time_scheduled_minutes: thisView.model.get('duration')
          });
        }
      });
      this.hasBeenInitialized = true;
      this.doCount();
    },

    doCount: function() {
      if (!this.hasBeenInitialized) {
        return;
      }
      var thisView = this;
      this.isFlexible && this.initAutocomplete();
      var quantity = this.model.get('quantity');
      $('.crm-vol-vacancy, .crm-vol-placeholder', this.$el).remove();
      if (quantity > this.collection.length) {
        var delta = quantity - this.collection.length;
        var msg = this.collection.length ? ts('%1 More Needed', {1: delta}) : ts('%1 Needed', {1: delta});
        $('.crm-vol-assignment-list', this.$el).append('<tr class="crm-vol-vacancy"><td colspan="3">' + msg + '</td></tr>');
      }
      if (this.isFlexible && !this.collection.length) {
        $('.crm-vol-assignment-list', this.$el).append('<tr class="crm-vol-placeholder"><td>' + ts('None') + '</td></tr>');
      }
      // Initialize draggable on any new objects
      $('.crm-vol-assignment:not(.ui-draggable)', this.$el).draggable({
        helper: "clone",
        zindex: 99999999999,
        cancel: '.crm-vol-del',
        containment: '#crm-volunteer-dialog',
        start: function() {
          dragFrom = thisView;
        }
      });
      // If we have room for more volunteers (or quantity is flexible), accept dropping in more
      if (!quantity || quantity > this.collection.length) {
        $(this.itemViewContainer, this.$el).droppable("enable");
      }
      else {
        $(this.itemViewContainer, this.$el).droppable("disable").removeClass('ui-state-disabled');
      }
    },

    createContactDialog: function(e) {
      var thisView = this;
      var profile = $(e.target).val();
      if(profile.length) {
        thisView.profileUrl = CRM.url('civicrm/profile/create', {
          reset: 1,
          snippet: 6,
          gid: profile
        });
        $('<div id="crm-vol-profile-form" class="crm-container"><div class="crm-loading-element">' + ts('Loading') + '...</div></div>').dialog({
          title: $(e.target).find(':selected').text(),
          modal: true,
          minWidth: 400,
          open: function() {
            $(e.target).val('');
            $.getJSON(thisView.profileUrl, function(data) {
              thisView.displayNewContactProfile(data);
            });
          },
          close: function() {
            $(this).dialog('destroy');
            $(this).remove();
          }
        });
      }
    },

    displayNewContactProfile: function(data) {
      var thisView = this;
      $("#crm-vol-profile-form").html(data.content);
      $("#crm-vol-profile-form .cancel.form-submit").click(function() {
        $("#crm-vol-profile-form").dialog('close');
        return false;
      });
      $('#email-Primary').addClass('email');
      $("#crm-vol-profile-form form").ajaxForm({
        // context=dialog triggers civi's profile to respond with json instead of an html redirect
        // but it also results in lots of unwanted scripts being added to the form snippet, so we
        // add it here during submission and not during form retrieval.
        url: thisView.profileUrl + '&context=dialog',
        dataType: 'json',
        success: function(response) {
          if (response.newContactSuccess) {
            $("#crm-vol-profile-form").dialog('close');
            CRM.alert(ts('%1 has been created.', {1: response.displayName}), ts('Contact Saved'), 'success');
            newContactId = response.contactID;
            thisView.addNewContact();
          }
          else {
            thisView.displayNewContactProfile(response);
          }
        }
      }).validate(CRM.validate.params);
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
      return false;
    },

    removeContact: function(e) {
      var thisView = this;
      var id = $(e.currentTarget).closest('tr').data('id');
      var assignment = this.collection.get(id);
      CRM.confirm(function() {
        thisView.collection.remove(assignment);
        CRM.api('volunteer_assignment', 'delete', {id: id});
      }, {
        title: ts('Remove Volunteer'),
        message: ts('Remove %1 from the available list?', {1: assignment.get('display_name')})
      });
      return false;
    }

  });

  Assign.needsView = Marionette.CollectionView.extend({
    itemView: needView,
    className: 'crm-vol-assign-region-inner'
  });

});
