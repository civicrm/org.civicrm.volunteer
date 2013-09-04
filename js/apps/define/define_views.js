// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

  var myViewSettings = {
    tagName: 'tr',
    className: 'crm-vol-define-need',

    onRender: function() {
      this.$el.attr('data-id', this.model.get('id'));
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
      pseudoConstant: CRM.pseudoConstant
    }
  }));

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
      'change :input': 'updateNeed',
    },

    addNewNeed: function () {
      var newNeed = new this.collection.model;
      this.collection.add(newNeed);
    },

    updateNeed: function(e) {
      var id = cj(e.currentTarget).closest('tr').data('id');
      var need = {
        id: id,
        project_id: cj('#crm-vol-define-needs-dialog').data('project_id'),
        };

        var field_name = e.currentTarget.name;

        //TODO: handle fields such as display_start that don't

        need[field_name] = e.currentTarget.value;
        this.collection.createNewNeed(need);
    },

});

});