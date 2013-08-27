// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

  var myViewSettings = {
    tagName: 'tr',
    className: 'crm-vol-definement'
  };


  Define.layout = Marionette.Layout.extend({
    template: "#crm-vol-define-layout-tpl",
    regions: {
      newNeeds: "#crm-vol-define-needs-region"
    }
  });

// *** declare a view
  Define.defineNeedsView = Marionette.ItemView.extend(_.extend(myViewSettings, {
    template: '#crm-vol-define-new-need-tpl'
  }));

  Define.defineNeedsTable = Marionette.CompositeView.extend({
  id: "manage_needs",
  template: "#crm-vol-define-layout-tpl",
  itemView: Define.defineNeedsView,
  className: 'crm-block crm-form-block crm-event-manage-volunteer-form-block',

  appendHtml: function(collectionView, itemView){
    collectionView.$("tbody").append(itemView.el);
  }
});

});