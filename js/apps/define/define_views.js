// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

  Define.layout = Marionette.Layout.extend({
    template: "#crm-vol-define-layout-tpl",
    regions: {
      newNeeds: "#crm-vol-define-needs-region"
    }
  });

  Define.defineNeedsView = Marionette.ItemView.extend({
    template: '#crm-vol-define-new-need-tpl',
    tagName: 'tr',
    className: 'crm-vol-define-need',

    templateHelpers: {
      pseudoConstant: CRM.pseudoConstant,
      RenderUtil: CRM.volunteerApp.RenderUtil
    },

    events: {
      'change :input': 'updateNeed',
      'click .crm-vol-del': 'deleteNeed'
    },

    onRender: function() {
      $('#crm-vol-define-needs-region .crm-loading-element').closest('tr').remove();

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

    updateNeed: function(e) {console.log(e);
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

      var request = Define.needsTable.collection.createNewNeed(this.model);
      request.done(function(r) {
        if (r.is_error == 0) {
          CRM.alert('', ts('Saved'), 'success');
          thisView.model.set('id', r.id);
        }
      });
    },

    deleteNeed: function() {
      var thisView = this;
      CRM.confirm(function() {
        var id = thisView.model.get('id');
        Define.needsTable.collection.remove(id);
        CRM.api('volunteer_need', 'delete', {id: id});
        Define.needsTable.render();
      }, {
        title: ts('Delete Need'),
        message: ts('There are currently %1 volunteer(s) assigned to this need.', {1: thisView.model.get('api.volunteer_assignment.getcount')})
      });
      return false;
    }
  });

  Define.defineNeedsTable = Marionette.CompositeView.extend({
    id: "manage_needs",
    template: "#crm-vol-define-layout-tpl",
    itemView: Define.defineNeedsView,
    itemViewContainer: 'tbody',
    className: 'crm-block crm-form-block crm-event-manage-volunteer-form-block',

    events: {
      'click #addNewNeed': 'addNewNeed',
      'click #crm-vol-define-needs-dialog .sorting' : 'changeSort'
    },

    changeSort: function(sender) {
      // FIXME: no need to pull data again to sort it
      Define.sortField = $(sender.currentTarget).attr('id');
      var request = volunteerApp.Entities.getNeeds({'api.volunteer_assignment.getcount': {}});
      request.done(this.getCollection);
    },

    // no API calls here; this just updates the UI
    addNewNeed: function () {
      var newNeed = new this.collection.model({project_id: volunteerApp.project_id});
      this.collection.add(newNeed);
      this.render();
      return false;
    },

    getCollection: function(data_array) {
      Define.needsTable.collection = volunteerApp.Entities.Needs.getScheduled(data_array);

      if (Define.sortField) {
        Define.needsTable.collection.comparator = Define.sortField;
        Define.needsTable.collection.sort();
      }

      Define.needsTable.render();
    }

  });

});
