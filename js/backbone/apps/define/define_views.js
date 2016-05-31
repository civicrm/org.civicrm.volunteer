// http://civicrm.org/licensing
(function (ts){
  CRM.volunteerApp.module('Define', function(Define, volunteerApp, Backbone, Marionette, $, _) {

    var visibility = CRM.pseudoConstant.volunteer_need_visibility;

    Define.layout = Marionette.Layout.extend({
      template: "#crm-vol-define-layout-tpl",
      regions: {
        scheduledNeeds: "#crm-vol-define-scheduled-needs-region",
        flexibleNeeds: "#crm-vol-define-flexible-needs-region"
      }
    });

    // allows us to toggle different views for the same model
    var itemViewSettings = {
      attributes: function() {
        return {
          class: 'crm-vol-define-need ' + (this.model.collection.indexOf(this.model) % 2 ? 'even' : 'odd')
        };
      },

      templateHelpers: {
        pseudoConstant: CRM.pseudoConstant,
        RenderUtil: CRM.volunteerApp.RenderUtil,
        visibilityValue: visibility.public
      },

      events: {
        'change :input:not(.timeplugin, [name=schedule_type])': 'updateNeed',
        'change select[name=schedule_type]': 'changeScheduleType',
        'blur :input.timeplugin': 'updateNeed',
        'click .crm-vol-del': 'deleteNeed'
      },

      onRender: function() {
        this.$('.crm-select2').crmSelect2();

        this.$("[name='display_start_date'], [name='display_end_date']").addClass('dateplugin').datepicker();

        this.$("[name='display_start_time'], [name='display_end_time']").addClass('timeplugin').timeEntry({
          show24Hours: CRM.config.timeInputFormat == 2
        });

        // populate and format time
        if (this.model.get('display_start_time')) {
          this.$("[name='display_start_time']").timeEntry('setTime', this.model.get('display_start_time'));
        }
        if (this.model.get('display_end_time')) {
          this.$("[name='display_end_time']").timeEntry('setTime', this.model.get('display_end_time'));
        }

        if (this.model.get('visibility_id') == visibility.public) {
          this.$("[name='visibility_id']").prop("checked", true);
        }

        if (this.model.get('is_active') == '1') {
          this.$("[name='is_active']").prop("checked", true);
        }

        this.initializeTimeComponents();
      },

      initializeTimeComponents: function () {
        var mode = '';
        var needViewItem = this;

        var durationValue = needViewItem.model.get('duration');
        if (needViewItem.model.get('userAdded') === true) {
          mode = '';
        } else if (durationValue === '' || durationValue < 1) {
          mode = 'open';
        } else if (!needViewItem.model.get('end_time')) {
          mode = 'shift';
        } else {
          mode = 'flexible';
        }

        needViewItem.$('select[name=schedule_type]').val(mode);
        this.toggleTimeComponents(mode, false);
      },

      changeScheduleType: function (e) {
        this.toggleTimeComponents(e.currentTarget.value);
      },

      /**
       * Shows/hides time fields according to schedule type (mode).
       *
       * @param {String} mode
       *   'shift,' 'flexible,' and 'open' are supported. If another string is
       *   passed, all time components will be hidden.
       * @param {Boolean} save
       *   Whether or not the field toggling should trigger updateNeeds. Default to true.
       */
      toggleTimeComponents: function (mode, save) {
        save = (typeof (save) === 'undefined') ? true : save;

        var needViewItem = this;
        var start = needViewItem.$('.time_components .start_datetime').hide();
        var end = needViewItem.$('.time_components .end_datetime').hide();
        var duration = needViewItem.$('.time_components .duration').hide();

        switch (mode) {
          case 'shift':
            start.show();
            end.find('.dateplugin').datepicker("setDate", null);
            var endTimeField = end.find('.timeplugin').timeEntry("setTime", null);
            duration.show();

            if (save) {
              endTimeField.trigger('blur');
            }
            break;
          case 'flexible':
            start.show();
            end.show();
            duration.show();
            break;
          case 'open':
            var durationField = duration.find(':input').val('');
            end.find('.dateplugin').datepicker("setDate", null);
            var endTimeField = end.find('.timeplugin').timeEntry("setTime", null);
            start.find('.dateplugin').datepicker("setDate", "+0");
            var startTimeField = start.find('.timeplugin').timeEntry("setTime", '00:00:00');

            if (save) {
              durationField.trigger('change');
              endTimeField.trigger('blur');
              startTimeField.trigger('blur');
            }
            break;
        }
      },

      updateNeed: function(e) {
        var field_name = e.currentTarget.name;
        var thisNeed = this;
        var value = e.currentTarget.value;

        function pad(number) {
          var r = String(number);
          return (r.length === 1) ? '0' + r : r;
        }

        /**
         * Helper function to put together the date/time from user input.
         *
         * @param {String} when
         *   Either 'start' or 'end.'
         * @returns {String}
         *   A string representation of the time.
         */
        function getUserInputDateTime(when) {
          var date = thisNeed.$("[name='display_" + when + "_date']").datepicker('getDate');
          var time = thisNeed.$("[name='display_" + when + "_time']").timeEntry('getTime');

          if (!date && !time) {
            value = '';
          } else if (!date) {
            // Don't save a datetime field unless the date is set. (Resetting
            // the dateTime value to that of the model short-circuits
            // updateNeed's API call.)
            value = thisNeed.model.get(when + '_time');
          } else {
            // format the time; if not set, use the last second of the day for
            // the end of a window, and the first second of the day for the
            // beginning of a window
            if (!time) {
              time = (when === 'end' ? '23:59:00' : '00:00:00');
            } else {
              time = time.toTimeString().split(' ')[0];
            }

            value = '' + date.getFullYear() + '-' + pad(1 + date.getMonth()) + '-' + pad(date.getDate()) + ' ' + time;
          }

          return value;
        }


        // preprocess special-case fields
        switch (field_name) {
          case 'display_start_date':
          case 'display_start_time':
          case 'display_end_date':
          case 'display_end_time':
            var when = field_name.substring(0, 11) === 'display_end' ? 'end' : 'start';
            field_name = when + '_time';
            value = getUserInputDateTime(when);
            break;
          case 'visibility_id':
            value = e.currentTarget.checked ? e.currentTarget.value : visibility.admin;
            break;
          case 'is_active':
            value = e.currentTarget.checked ? e.currentTarget.value : 0;
            break;
        }

        // update only if a change occurred
        if (thisNeed.model.get(field_name) != value) {
          thisNeed.model.set(field_name, value);

          var params = {'id': thisNeed.model.get('id')};
          params[field_name] = value;
          CRM.api3('VolunteerNeed', 'create', params, true).done(function() {
            // As needs are updated, their IDs are added to an array
            // This is intended to be an extension point; external code
            // can listen for the 'volunteer:close:define' event then access the list of
            // needs.
            Define.registerNeedChange("updated", params.id);
          });
        }
      },

      deleteNeed: function() {
        var id = this.model.get('id');
        var count = this.model.get('api.volunteer_assignment.getcount') || 0;
        var role = CRM.pseudoConstant.volunteer_role[this.model.get('role_id')];
        // FIXME: the JS implementation of CiviCRM's string translator doesn't yet support plurals
        // DESIRED CODE:
        // var msg = ts("There is currently %count volunteer assigned to this need. The volunteer's activity history will be preserved, but they will be disconnected from this shift.", {count: count,plural: "There are currently %count volunteers assigned to this need. The volunteers' activity histories will be preserved, but they will be disconnected from this shift."});
        // STOPGAP CODE:
        var msg = (count == 1
          ? ts("There is currently %1 volunteer assigned to this need. The volunteer's activity history will be preserved, but they will be disconnected from this shift.", {1: count})
          : (count == 0
              ? ts("There are currently %1 volunteers assigned to this need.", {1: count})
              : ts("There are currently %1 volunteers assigned to this need. The volunteers' activity histories will be preserved, but they will be disconnected from this shift.", {1: count})
            )
        );
        // END FIXME
        CRM.confirm(function() {
          Define.collectionView.collection.remove(id);
          CRM.api3('volunteer_need', 'delete', {id: id}, true).done(function() {
            //Store the deleted need ID so the "volunteer:close:define" event
            //Can send it to any listeners
            Define.registerNeedChange("deleted", id);
          });
        }, {
          title: ts('Delete %1', {1: role}),
          message: msg
        });
        return false;
      }
    };

    Define.registerNeedChange = function(action, id) {
      Define.needRegistry.clean = _.without(Define.needRegistry.clean, id);

      //Only remove from the "created" category if we are deleting it.
      if(action === "deleted") {
        Define.needRegistry.created = _.without(Define.needRegistry.created, id);
        Define.needRegistry.updated = _.without(Define.needRegistry.updated, id);
      }

      //Only push it to the list if we haven't already.
      if (Define.needRegistry[action].indexOf(id) === -1) {
        //If it is an Update, but this need is new, leave it in "created".
        if (action !== "update" || Define.needRegistry.created.indexOf(id) === -1) {
          Define.needRegistry[action].push(id);
        }
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
      itemViewContainer: '#crm-vol-define-needs-table > tbody',

      events: {
        'change #crm-vol-define-add-need': 'addNewNeed'
      },

      addNewNeed: function() {
        var params = {
          role_id: $('#crm-vol-define-add-need').val()
        };
        // Reset add another select
        $('#crm-vol-define-add-need').select2('val', '');
        $('#crm-vol-define-needs-table').block();
        this.collection.createNewNeed(params).done(function(data) {
          //Register the new ID
          Define.registerNeedChange("created", data.id);
          $('#crm-vol-define-needs-table').unblock();
        });
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
        this.$('#crm-vol-define-needs-table > tbody').append($('#crm-vol-define-add-row-tpl').html());
        this.$('#crm-vol-define-add-need').crmSelect2();
      }
    });
  });
}(CRM.ts('org.civicrm.volunteer')));
