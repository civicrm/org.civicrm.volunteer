// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

  Define.layout = Marionette.Layout.extend({
    template: "#crm-vol-define-layout-tpl",
    regions: {
      newNeeds: "#crm-vol-define-needs-region"
    },

    events: {
      'click #crm-vol-define-done': 'closeModal'
    },

    closeModal: function() {
      $('#crm-volunteer-dialog').dialog('close');
      return false;
    }
  });

  Define.needItemView = Marionette.ItemView.extend({
    template: '#crm-vol-define-new-need-tpl',
    tagName: 'tr',
    className: 'crm-vol-define-need',

    templateHelpers: {
      pseudoConstant: CRM.pseudoConstant,
      RenderUtil: CRM.volunteerApp.RenderUtil
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

      var publicVisibilityValue = _.invert(CRM.pseudoConstant.volunteer_need_visibility).Public;

      this.$("[name='visibility_id']").val(publicVisibilityValue);

      this.$("[name='visibility_id']").each(function(){
        if ($(this).data('stored') == publicVisibilityValue) {
          $(this).prop("checked", true);
        }
      });

      this.$("[name='is_active']").each(function(){
        if ($(this).data('stored') == 1) {
          $(this).prop("checked", true);
        }
      });
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
              : _.invert(CRM.pseudoConstant.volunteer_need_visibility).Admin;
          break;
        case 'is_active':
          value = e.currentTarget.checked ? e.currentTarget.value : 0;
          break;
      }
      this.model.set(field_name, value);

      var params = {'id': this.model.get('id')};
      params[field_name] = value;
      CRM.api('VolunteerNeed', 'create', params);
    },

    deleteNeed: function() {
      var thisView = this;
      CRM.confirm(function() {
        var id = thisView.model.get('id');
        Define.collectionView.collection.remove(id);
        CRM.api('volunteer_need', 'delete', {id: id});
      }, {
        title: ts('Delete Need'),
        message: ts('There are currently %1 volunteer(s) assigned to this need.', {1: thisView.model.get('api.volunteer_assignment.getcount') || '0'})
      });
      return false;
    }
  });

  Define.needsCompositeView = Marionette.CompositeView.extend({
    id: "manage_needs",
    template: "#crm-vol-define-table-tpl",
    itemView: Define.needItemView,
    itemViewContainer: 'tbody',
    className: 'crm-block crm-form-block crm-event-manage-volunteer-form-block',

    events: {
      'change #crm-vol-define-add-need': 'addNewNeed'
    },

    addNewNeed: function() {
      var params = {project_id: volunteerApp.project_id, role_id: $('#crm-vol-define-add-need').val()};
      var newNeed = new this.collection.model(params);
      this.collection.add(newNeed);
      // restore add another text
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
