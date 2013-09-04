// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

  var myViewSettings = {
    tagName: 'tr',
    className: 'crm-vol-define-need',

    onRender: function() {
      this.$el.attr('data-id', this.model.get('id'));
      // TODO: respect user-configured time formats
      this.$("[name='display_start_date']").addClass('dateplugin').datepicker({
        dateFormat: "MM d, yy"
      });
      this.$("[name='display_start_time']").addClass('timeplugin').timeEntry();

      // format the times
      this.$("[name='display_start_time']").timeEntry(
        'setTime',
        this.$("[name='display_start_time']").val()
        );
    }
  };


  Define.layout = Marionette.Layout.extend({
    template: "#crm-vol-define-layout-tpl",
    regions: {
      newNeeds: "#crm-vol-define-needs-region"
    }
  });

  // *** declare a view
  Define.defineNeedsView = Marionette.ItemView.extend(_.extend(myViewSettings, {
    template: '#crm-vol-define-new-need-tpl',
    templateHelpers: {
      pseudoConstant: CRM.pseudoConstant,
      RenderUtil: CRM.volunteerApp.RenderUtil
    }
  }));
  console.log(CRM.volunteerApp);

  Define.defineNeedsTable = Marionette.CompositeView.extend({
    id: "manage_needs",
    template: "#crm-vol-define-layout-tpl",
    itemView: Define.defineNeedsView,
    className: 'crm-block crm-form-block crm-event-manage-volunteer-form-block',

    appendHtml: function(collectionView, itemView){
      collectionView.$("tbody").append(itemView.el);
    },

    events: {
      'click #addNewNeed': 'addNewNeed',
      'change :input': 'updateNeed'
    },

    addNewNeed: function () {
      var newNeed = new this.collection.model;
      this.collection.add(newNeed);
    },

    updateNeed: function(e) {
      var row = cj(e.currentTarget).closest('tr') ;
      var id = row.data('id');
      var need = {
        id: id,
        project_id: cj('#crm-vol-define-needs-dialog').data('project_id')
      };

      var field_name = e.currentTarget.name;

      switch (field_name) {
        case 'display_start_date':
        case 'display_start_time':
          field_name = 'start_time';
          need[field_name] = row.find("[name='display_start_date']").val()
          + ' ' + row.find("[name='display_start_time']").val();
          break;
        default:
          need[field_name] = e.currentTarget.value;
          break;
      }

      this.collection.createNewNeed(need);
    }

  });

});