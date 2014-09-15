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
        'change :input:not(.timeplugin)': 'updateNeed',
        'blur :input.timeplugin': 'updateNeed',
        'click .crm-vol-del': 'deleteNeed'
      },

      onRender: function() {
        this.$("[name='display_start_date']").addClass('dateplugin').datepicker();

        this.$("[name='display_start_time']").addClass('timeplugin').timeEntry({
          show24Hours: CRM.config.timeInputFormat == 2
        });

        // populate and format time
        if (this.model.get('display_start_time')) {
          this.$("[name='display_start_time']").timeEntry('setTime', this.model.get('display_start_time'));
        }

        if (this.model.get('visibility_id') == visibility.public) {
          this.$("[name='visibility_id']").prop("checked", true);
        }

        if (this.model.get('is_active') == '1') {
          this.$("[name='is_active']").prop("checked", true);
        }
      },

      updateNeed: function(e) {
        var thisView = this;
        var field_name = e.currentTarget.name;
        var value = e.currentTarget.value;

        function pad(number) {
          var r = String(number);
          return (r.length === 1) ? '0' + r : r;
        }

        // preprocess special-case fields
        switch (field_name) {
          case 'display_start_date':
          case 'display_start_time':
            field_name = 'start_time';
            var date =  this.$("[name='display_start_date']").datepicker('getDate');
            var time = this.$("[name='display_start_time']").timeEntry('getTime').toTimeString().split(' ')[0];
            value = '' + date.getFullYear() + '-' + pad(1 + date.getMonth()) + '-' + pad(date.getDate()) + ' ' + time;
            break;
          case 'visibility_id':
            value = e.currentTarget.checked ? e.currentTarget.value : visibility.admin;
            break;
          case 'is_active':
            value = e.currentTarget.checked ? e.currentTarget.value : 0;
            break;
        }

        // update only if a change occurred
        if(this.model.get(field_name) != value) {
          this.model.set(field_name, value);

          var params = {'id': this.model.get('id')};
          params[field_name] = value;
          CRM.api3('VolunteerNeed', 'create', params, true);
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
          CRM.api3('volunteer_need', 'delete', {id: id}, true);
        }, {
          title: ts('Delete %1', {1: role}),
          message: msg
        });
        return false;
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
      itemViewContainer: 'tbody',

      events: {
        'change #crm-vol-define-add-need': 'addNewNeed'
      },

      addNewNeed: function() {
        var params = {
          role_id: $('#crm-vol-define-add-need').val(),
          visibility_id: $('#crm-vol-visibility-id:checked').length ? visibility.public : visibility.admin
        };
        // Reset add another select
        $('#crm-vol-define-add-need').select2('val', '');
        $('#crm-vol-define-needs-table').block();
        this.collection.createNewNeed(params).done(function() {
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
        this.$('tbody').append($('#crm-vol-define-add-row-tpl').html());
        this.$('#crm-vol-define-add-need').crmSelect2();
      }
    });
  });
}(CRM.ts('org.civicrm.volunteer')));
