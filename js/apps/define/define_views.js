// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

  var visibility = CRM.pseudoConstant.volunteer_need_visibility;

  Define.layout = Marionette.Layout.extend({
    template: "#crm-vol-define-layout-tpl",
    regions: {
      scheduledNeeds: "#crm-vol-define-scheduled-needs-region",
      flexibleNeeds: "#crm-vol-define-flexible-needs-region"
    },

    events: {
      'click #crm-vol-define-done': 'closeModal'
    },

    closeModal: function() {
      $('#crm-volunteer-dialog').dialog('close');
      return false;
    }
  });

  // allows us to toggle different views for the same model
  var itemViewSettings = {
    className: 'crm-vol-define-need',

    templateHelpers: {
      pseudoConstant: CRM.pseudoConstant,
      RenderUtil: CRM.volunteerApp.RenderUtil,
      visibilityValue: visibility.public
    },

    events: {
      'change :input:not(.timeplugin)': 'updateNeed',
      'blur :input.timeplugin': 'updateNeed',
      'click .crm-vol-del': 'deleteNeed'
    },

    onRender: function() {
      // TODO: respect user-configured time formats
      this.$("[name='display_start_date']").addClass('dateplugin').datepicker({
        dateFormat: "MM d, yy"
      });
      this.$("[name='display_start_time']").addClass('timeplugin').timeEntry({
        defaultTime: this.$("[name='display_start_time']").val()
      });

      if (this.model.get('visibility_id') == visibility.public) {
        this.$("[name='visibility_id']").prop("checked", true);
      }

      if (this.model.get('is_active') == '1') {
        this.$("[name='is_active']").prop("checked", true);
      }
    },

    updateNeed: function(e) {
      var thisView = this;
      var field_name = e.currentTarget.name;
      var value = e.currentTarget.value;

      switch (field_name) {
        case 'display_start_date':
        case 'display_start_time':
          field_name = 'start_time';
          value = this.$("[name='display_start_date']").val()
              + ' ' + this.$("[name='display_start_time']").val();
          break;
        case 'visibility_id':
          value = e.currentTarget.checked ? e.currentTarget.value
              : visibility.admin;
          break;
        case 'is_active':
          value = e.currentTarget.checked ? e.currentTarget.value : 0;
          break;
      }

      // update only if a change occurred
      if(this.model.get(field_name) != value) {
        this.model.set(field_name, value);

        var params = {'id': this.model.get('id')};
        params[field_name] = value;
        CRM.api('VolunteerNeed', 'create', params);
      }
    },

    deleteNeed: function() {
      var id = this.model.get('id');
      var count = this.model.get('api.volunteer_assignment.getcount') || 0;
      var role = CRM.pseudoConstant.volunteer_role[this.model.get('role_id')];
      CRM.confirm(function() {
        Define.collectionView.collection.remove(id);
        CRM.api('volunteer_need', 'delete', {id: id});
      }, {
        title: ts('Delete %1', {1: role}),
        message: count == 1 ? ts('There is currently 1 volunteer assigned to this need.') : ts('There are currently %1 volunteers assigned to this need.', {1: count})
      });
      return false;
    }
  };

  Define.scheduledNeedItemView = Marionette.ItemView.extend(_.extend(itemViewSettings, {
    template: '#crm-vol-define-scheduled-need-tpl',
    tagName: 'tr'
  }));

  Define.flexibleNeedItemView = Marionette.ItemView.extend(_.extend(itemViewSettings, {
    template: '#crm-vol-define-flexible-need-tpl',
    tagName: 'div'
  }));

  Define.needsCompositeView = Marionette.CompositeView.extend({
    id: "manage_needs",
    template: "#crm-vol-define-table-tpl",
    itemView: Define.scheduledNeedItemView,
    itemViewContainer: 'tbody',

    events: {
      'change #crm-vol-define-add-need': 'addNewNeed'
    },

    addNewNeed: function() {
      var params = {
        project_id: volunteerApp.project_id,
        role_id: $('#crm-vol-define-add-need').val(),
        visibility_id: $('#crm-vol-visibility-id:checked').length ? visibility.public : visibility.admin
      };console.log(params);
      var newNeed = new this.collection.model(params);
      this.collection.add(newNeed);
      // Reset add another select
      $('#crm-vol-define-add-need').val('');
      $('#crm-vol-define-needs-table').block();
      CRM.api('VolunteerNeed', 'create', params, {
        success: function (data) {
          newNeed.set('id', data.id);
          $('#crm-vol-define-needs-table').unblock();
          CRM.alert('', ts('Saved'), 'success');
        }
      });
      return false;
    },

    appendHtml: function(thisView, itemView) {
      var container = thisView.$(thisView.itemViewContainer);
      var addRow = thisView.$('#crm-vol-define-add-row');
      if (addRow.length) {
        addRow.before(itemView.el);
      }
      else {
        container.append(itemView.el);
      }
    },

    onRender: function() {
      this.$('tbody').append($('#crm-vol-define-add-row-tpl').html());
    }
  });
});
