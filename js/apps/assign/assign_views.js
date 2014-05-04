// http://civicrm.org/licensing
(function (ts){
  CRM.volunteerApp.module('Assign', function(Assign, volunteerApp, Backbone, Marionette, $, _) {
    var newContactId, dragFrom, infoDialog;

    Assign.layout = Marionette.Layout.extend({
      template: "#crm-vol-assign-layout-tpl",
      regions: {
        flexibleRegion: "#crm-vol-assign-flexible-region",
        scheduledRegion: "#crm-vol-assign-scheduled-region"
      },

      events: {
        'click #crm-vol-define-done': 'closeModal'
      },

      closeModal: function() {
        $('#crm-volunteer-dialog').dialog('close');
        return false;
      }
    });

    var menuItemTemplate = _.template($('#crm-vol-menu-item-tpl').html());

    var assignmentViewSettings = {
      tagName: 'tr',
      attributes: function() {
        return {
          class: 'crm-vol-assignment ' + (this.model.collection.indexOf(this.model) % 2 ? 'even' : 'odd'),
          // Store ids to facilitate dragging & dropping
          'data-id': this.model.get('id'),
          'data-cid': this.model.get('contact_id')
        };
      },

      templateHelpers: {
        contactUrl: function(cid) {
          return CRM.url('civicrm/contact/view', {reset: 1, cid: cid});
        }
      },

      events: {
        'click a.crm-vol-info': function() {
          infoDialog && infoDialog.close && infoDialog.close();
          infoDialog = CRM.alert(this.model.get('details'), this.model.get('display_name'), 'info', {expires: 0});
          return false;
        },
        'click a.crm-vol-menu-parent': function () {return false;},
        'click a.crm-vol-menu-button': function() {
          $('.crm-vol-menu-items').remove();
          var $menu = $($('#crm-vol-menu-tpl').html());
          $((this.isFlexible ? '' : '.crm-vol-menu-move-to, ') + '.crm-vol-menu-copy-to', $menu).append(menuItemTemplate({
            cid: 'flexible',
            title: '<em>' + ts('Available Volunteers') + '</em>',
            time: ''
          }));
          $.each(Assign.scheduledView.getOpenSlots(this.$el), function() {
            $('.crm-vol-menu-move-to, .crm-vol-menu-copy-to', $menu).append(menuItemTemplate(this));
          });
          $menu.appendTo($('.crm-vol-menu', this.$el));
          this.$('.crm-vol-menu-list').crmVolMenu();
          return false;
        }
      }
    };

    Assign.scheduledAssignmentView = Marionette.ItemView.extend(_.extend(assignmentViewSettings, {
      template: '#crm-vol-scheduled-assignment-tpl',
      isFlexible: false
    }));

    Assign.flexibleAssignmentView = Marionette.ItemView.extend(_.extend(assignmentViewSettings, {
      template: '#crm-vol-flexible-assignment-tpl',
      isFlexible: true
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
        'click .crm-vol-menu-item a': 'moveContact',
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
            thisView.addAssignment(assignment.clone());
            dragFrom.collection.remove(assignment);
          },
          accept: function($item) {
            return thisView.acceptVolunteers($item);
          }
        });
        this.hasBeenInitialized = true;
        this.doCount();
      },

      // Adds a cloned or existing assignment to the view
      addAssignment: function(assignment) {
        var thisView = this, callback;
        assignment.set('volunteer_need_id', this.model.get('id'));
        this.collection.add(assignment);
        var status = _.invert(CRM.pseudoConstant.volunteer_status),
          params = {
            activity_date_time: this.model.get('start_time'),
            volunteer_need_id: this.model.get('id'),
            status_id: status[this.isFlexible ? 'Available' : 'Scheduled']
          };
        // A simple move
        if (assignment.get('id')) {
          params.id = assignment.get('id');
          // hack - fix order and even/odd problems
          thisView.render();
        }
        // Cloning - copy params and set ID when returned by server
        else {
          _.extend(params, _.pick(assignment.attributes, 'contact_id', 'details'));
          callback = {success: function(result) {
            assignment.set('id', result.id);
            CRM.alert('', ts('Copied'), 'success');
            // refresh the data-id property (also needed for other reasons - see above hack)
            thisView.render();
          }};
        }
        CRM.api('volunteer_assignment', 'create', params, callback);
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
        if (!quantity && !this.collection.length) {
          $('.crm-vol-assignment-list', this.$el).append('<tr class="crm-vol-placeholder"><td colspan="3">' + ts('None') + '</td></tr>');
        }
        // Initialize draggable on any new objects
        $('.crm-vol-assignment:not(.ui-draggable)', this.$el).draggable({
          helper: "clone",
          zindex: 99999999999,
          cancel: '.crm-vol-menu',
          containment: '#crm-volunteer-dialog',
          start: function() {
            dragFrom = thisView;
            $('.crm-vol-need table').removeClass('row-highlight');
          },
          stop: function() {
            $('.crm-vol-need table').addClass('row-highlight');
          }
        });
      },

      acceptVolunteers: function($item) {
        // If we have no room for more volunteers
        if (this.model.get('quantity') && this.model.get('quantity') <= this.collection.length) {
          return false;
        }
        // If this activity is already in the collection
        if (this.collection.get($item.data('id'))) {
          return false;
        }
        // If any activity for the same contact is already in a non-flexible collection
        return this.isFlexible || !(this.collection.where({contact_id: $item.attr('data-cid')}).length);
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
          var status = _.invert(CRM.pseudoConstant.volunteer_status);
          $('.crm-add-volunteer', this.$el).val('');
          var params = {
            contact_id: newContactId,
            volunteer_need_id: this.model.get('id'),
            status_id: status['Available'],
            activity_date_time: this.model.get('start_time')
          };
          this.collection.createNewAssignment(params);
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
          $('.crm-vol-menu-items').remove();
          CRM.api('volunteer_assignment', 'delete', {id: id});
        }, {
          title: ts('Delete Volunteer'),
          message: ts('Remove %1 from %2?', {
            1: assignment.get('display_name'),
            2: this.isFlexible ? ts('Available Volunteers') : CRM.pseudoConstant.volunteer_role[this.model.get('role_id')]
          })
        });
        return false;
      },

      moveContact: function(e) {
        var targetView, newAssignment,
          id = $(e.currentTarget).closest('tr').data('id'),
          assignment = this.collection.get(id),
          cid = $(e.currentTarget).attr('href').substr(1);
        if (cid === 'flexible') {
          targetView = Assign.flexibleView.children.findByIndex(0);
        }
        else {
          targetView = Assign.scheduledView.children.findByCid(cid);
        }
        if ($(e.currentTarget).is('.crm-vol-menu-move-to a')) {
          this.collection.remove(assignment);
          newAssignment = assignment;
        }
        else {
          newAssignment = assignment.clone();
          newAssignment.set('id', null);
          $('.crm-vol-menu-items').remove();
        }
        targetView.addAssignment(newAssignment);
        return false;
      }

    });

    Assign.needsView = Marionette.CollectionView.extend({
      itemView: needView,
      className: 'crm-vol-assign-region-inner',
      getOpenSlots: function($el) {
        var slots = [];
        this.children.each(function(view) {
          if (view.acceptVolunteers($el)) {
            slots.push({
              cid: view.cid,
              title: CRM.pseudoConstant.volunteer_role[view.model.get('role_id')],
              time: view.model.get('display_time')
            });
          }
        });
        return slots;
      }
    });

  });
}(CRM.ts('org.civicrm.volunteer')));