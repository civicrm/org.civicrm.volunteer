// http://civicrm.org/licensing
(function (ts){
  CRM.volunteerApp.module('Search', function(Search, volunteerApp, Backbone, Marionette, $, _) {

    Search.layout = Marionette.Layout.extend({
      template: "#crm-vol-search-layout-tpl",
      regions: {
        searchForm: "#crm-vol-search-form-region",
        searchPager: "#crm-vol-search-pager",
        searchResults: "#crm-vol-search-results-region"
      }
    });

    var fieldView = Marionette.CompositeView.extend({
      hasBeenInitialized: false,
      profileUrl: '',
      className: 'crm-vol-search-field crm-section',

      initialize: function() {
        // @todo: These field type lists are a little redundant with Entities.allowedCustomFieldTypes;
        // there's probably a smarter way to do this.

        /**
         * Keys will be used to select the appropriate template for the field, i.e.
         * '#crm-vol-search-field-' + type + '-tpl'. The array of values represents
         * the HTML type (i.e., api.customField.getsingle.html_type).
         *
         * @type Object
         */
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
      }
    });

    /**
     * Returns a field's value(s)
     *
     * TODO: It might be more consistent to make this a method of fieldView, above.
     *
     * @param {jQuery object} field
     * @returns {mixed} Array of values if any exist, else null
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
        var v = field.val();
        if (_.isArray(v) && v.length > 0) {
          // some widgets return an array; in this case, we can return as-is
          value = v;
        } else if (v) {
          value.push(v);
        }
      }

      if (value.length === 0) {
        value = null;
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

        Search.params = {};
        Search.formFields.each(function(item) {

          var field = CRM.$('[name=' + item.get('elementName') + ']');
          var val = Search.getFieldValue(field);
          if (val) {
            // For custom fields, give the param to contact search the name custom_n.
            // For the group field, name the param filter.group_id.
            var key = item.get('id') ? 'custom_' + item.get('id') : 'filter.group_id';
            if (val.length > 1) {
              Search.params[key] = {IN: val};
            } else {
              Search.params[key] = val[0];
            }
          }
        });

        volunteerApp.Entities.getContacts().done(function(result) {
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

        var btn = CRM.$('<button></button>', {
          'type': 'submit',
          'class': 'crm-button crm-form-submit'
        }).button({
          icons: {primary: 'ui-icon-search'},
          label: ts('Search')
        });
        var btn_wrapper = CRM.$('<div></div>', {class: 'crm-submit-buttons'})
                .append(btn);
        this.$el.append(btn_wrapper);

        // styling hack to ensure white icons
        this.$('.crm-button').addClass('button');
        this.$('.crm-button .ui-icon').addClass('icon');

        // this is a bit of a hack; submit handlers can't be bound via the events
        // attribute because the events are delegated jQuery events and they fire too late
        CRM.$('form.crm-event-manage-volunteer-search-form-block').submit(this.handleForm);
      }

    });

    Search.pagerView = Marionette.ItemView.extend({
      template: '#crm-vol-search-pager-tpl',

      attributes: function() {
        return {
          class: 'crm-pager'
        };
      },

      modelEvents: {
        'change': function() {
          this.render();
        }
      },

      onRender: function() {
        if (this.model.get('total') === 0) {
          this.$el.hide();
        } else {
          this.$el.show();
        }

        this.$('.crm-button-type-back').button({
          icons: {primary: 'ui-icon-triangle-1-w'}
        });
        this.$('.crm-button-type-next').button({
          icons: {secondary: 'ui-icon-triangle-1-e'}
        });

        // styling hack to ensure white icons
        this.$('.crm-button').addClass('button');
        this.$('.crm-button .ui-icon').addClass('icon');

        this.$('.crm-button').click(function(e) {
          e.preventDefault();
          var dialog = CRM.$("#crm-volunteer-search-dialog");
          dialog.block();

          var increment = $(this).is('.crm-button-type-back') ? -(Search.resultsPerPage) : Search.resultsPerPage;
          Search.params.options.offset += increment;

          volunteerApp.Entities.getContacts().done(function(result) {
            Search.resultsView.collection.reset(result);
            dialog.unblock();
          });
        });
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

          var contact_checkboxes = $('#crm-vol-search-results-region [name=selected_contacts]');
          var cnt_selected = contact_checkboxes.filter(':checked').length;
          if (cnt_selected >= Search.cnt_open_assignments) {
            contact_checkboxes.not(':checked').prop('disabled', true);
          } else {
            contact_checkboxes.prop('disabled', false);
          }

          var buttonPane = CRM.$('#crm-volunteer-search-dialog').siblings('.ui-dialog-buttonpane');
          var btn = buttonPane.find('button.crm-vol-search-assign');
          if (cnt_selected > 0) {
            btn.button('enable');
          } else {
            btn.button('disable');
          }
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
          var selectAll = CRM.$(this).is(':checked');
          var contacts = rendered_view.$('[name=selected_contacts]');

          if (selectAll) {
            var max = Search.cnt_open_assignments - contacts.filter(':checked').length;
            contacts.not(':checked').slice(0, max).prop('checked', true);
          } else {
            contacts.prop('checked', false);
          }

          contacts.first().trigger('change');
        });
      }
    });
  });
}(CRM.ts('org.civicrm.volunteer')));