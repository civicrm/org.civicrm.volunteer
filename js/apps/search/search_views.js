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
//        this.itemView = Search[type + 'FieldView'];
      }
    });

    /**
     * Returns a field's value(s)
     *
     * @param {jQuery object} field
     * @returns {mixed}
     */
    Search.getFieldValue = function (field) {
      var value = [];
      if (field.is(':checkbox') || field.is(':radio')) {
        field.each(function() {
          var item = CRM.$(this);
          if (item.is(':checked')) {
            value.push(item.val());
          }
        });
      } else {
        value = field.val();
      }

      if (_.isArray(value)) {
        if (value.length === 0) {
          value = null;
        } else if (value.length === 1) {
          value = value[0];
        }
      }
      return value;
    };

    Search.fieldsCollectionView = Marionette.CollectionView.extend({
      itemView: fieldView,
      className: 'crm-vol-search-form crm-form-block',

      handleForm: function(e) {
        var dialog = CRM.$("#crm-volunteer-search-dialog");
        dialog.block();
        e.preventDefault();

        var params = {};
        Search.formFields.each(function(item) {

          var field = CRM.$('[name=' + item.get('elementName') + ']');
          var val = Search.getFieldValue(field);
          if (val) {
            var key = item.get('id') ? 'custom_' + item.get('id') : 'filter.group_id';
            if (_.isArray(val)) {
              params[key] = {IN: val};
            } else {
              params[key] = val;
            }
          }
        });

        volunteerApp.Entities.getContacts(params).done(function(result) {
          Search.resultsView.collection.reset(result);
          CRM.$('#crm-vol-search-form-region').closest('.crm-accordion-wrapper').addClass('collapsed');
          dialog.unblock();
        });
      },

      onRender: function() {
        this.$('select').crmSelect2();
        this.$('input[name="crm-vol-search-field-group"]').attr('placeholder', '- any group -').crmEntityRef({
          entity: 'group'
        });

        this.$el.append('<input class="crm-form-submit" type="submit" value="' + ts('Search') + '" />');

        // this is a bit of a hack; submit handlers can't be bound via the events
        // attribute because the events are delegated jQuery events and they fire too late
        CRM.$('form.crm-event-manage-volunteer-search-form-block').submit(this.handleForm);
      }

    });

    Search.contactView = Marionette.ItemView.extend({
      tagName: 'tr',
      template: '#crm-vol-search-contact-tpl',

      attributes: function() {
        return {
          class: (this.model.collection.indexOf(this.model) % 2 ? 'even' : 'odd')
        };
      },

      onRender: function() {
        var rendered_view = this;
        rendered_view.$('[name=selected_contacts]').change(function() {
          var toggle = CRM.$(this).is(':checked');
          $(this).closest('tr').toggleClass('crm-row-selected', toggle);
        });
      }
    });

    Search.resultsCompositeView = Marionette.CompositeView.extend({
      template: '#crm-vol-search-result-tpl',
      itemView: Search.contactView,
      itemViewContainer: 'tbody',

      onRender: function() {
        var rendered_view = this;
        rendered_view.$('[name=select_all_contacts]').change(function() {
          var toggle = CRM.$(this).is(':checked');
          rendered_view.$('[name=selected_contacts]').prop('checked', toggle);
        });
      }
    });
  });
}(CRM.ts('org.civicrm.volunteer')));