// http://civicrm.org/licensing
CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

  var myViewSettings = {
    tagName: 'tr',
    className: 'crm-vol-definement',

    onRender: function () {
      /* do some stuff */
    }
  };

// *** declare a view
  Define.defineNeedsView = Marionette.ItemView.extend(_.extend(myViewSettings, {
    template: '#crm-vol-define-layout-tpl'
  }));

});