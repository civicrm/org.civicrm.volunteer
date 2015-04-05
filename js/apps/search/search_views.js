// http://civicrm.org/licensing
(function (ts){
  CRM.volunteerApp.module('Search', function(Search, volunteerApp, Backbone, Marionette, $, _) {

    Search.layout = Marionette.Layout.extend({
      template: "#crm-vol-search-layout-tpl",
      regions: {
        searchForm: "#crm-vol-search-form-region",
        searchResults: "#crm-vol-search-results-region"
      }
    });

//    Search.textFieldView = Marionette.ItemView.extend();
//    Search.multiselectFieldView = Marionette.ItemView.extend();

    var fieldView = Marionette.CompositeView.extend({
      hasBeenInitialized: false,
      profileUrl: '',
      className: 'crm-vol-search-field crm-form-block',

      initialize: function() {
        var typeMap = {
          checkRadio: ['CheckBox', 'Radio'],
          select: ['AdvMulti-Select', 'Autocomplete-Select', 'Multi-Select', 'Select'],
          text: ['Text']
        };
        // html_types which allow the user to select multiple values
        var typeMulti = ['AdvMulti-Select', 'CheckBox', 'Multi-Select'];

        var html_type = this.model.get('html_type');
        var type = _.findKey(typeMap, function(group) {
          return _.contains(group, html_type);
        });

        this.model.set({
          elementName: 'crm-vol-search-field-' + this.model.get('column_name'),
          selectMultiple: _.contains(typeMulti, html_type)
        });

        this.template = '#crm-vol-search-field-' + type + '-tpl';
        this.itemView = Search[type + 'FieldView'];
      }
    });


    Search.fieldsCollectionView = Marionette.CollectionView.extend({
      itemView: fieldView,
      className: 'crm-vol-search-form crm-form-block',

      onRender: function() {
        this.$('select').crmSelect2();
        this.$el.append('<input type="submit" value="' + ts('Search') + '" />');

        // this is a bit of a hack; submit handlers can't be bound via the events
        // attribute because the events are delegated jQuery events and they fire too late
        CRM.$('form.crm-event-manage-volunteer-search-form-block').submit(function(e) {
          e.preventDefault();
          console.log('make API call');
        });
      }

    });


  });
}(CRM.ts('org.civicrm.volunteer')));