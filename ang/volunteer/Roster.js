(function(angular, _, $) {
  'use strict';

  angular.module('volunteer').controller('VolunteerRoster', function($scope, $route, $window, crmApi4, volWorkflow) {
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var model = $scope.model || {};
    var capabilities = {canSendEmail: false, emailActivityTypeId: null, sms: {enabled: false, activity_type_id: null}};
    var projectId = parseInt(model.projectId || $route.current.params.projectId, 10);
    model.project = model.project || {id: projectId, title: '', is_active: null};

    $scope.dialogMode = !!model.dialogMode;
    $scope.projectId = projectId;
    $scope.loading = true;
    // Bound through an object on purpose; see note above about child scopes.
    $scope.ui = {includePast: false, search: '', statusFilter: '', allSelected: false};
    $scope.selected = {};
    $scope.rows = [];
    $scope.groups = [];
    $scope.statuses = [];

    $scope.workflow = {
      step: 'roster',
      projectId: projectId,
      project: model.project,
      summary: {filled: 0, total: 0},
      beneficiaryNames: [],
      manualSave: false,
      autoSave: false,
      formContext: (CRM.vars['org.civicrm.volunteer'] || {}).context || 'standAlone',
      isDirty: function() { return false; }
    };

    function normalize(value) {
      return String(value || '').toLowerCase();
    }

    function applyFilters() {
      var needle = normalize($scope.ui.search);
      var visible = _.filter($scope.rows, function(row) {
        var matchesText = !needle || normalize([
          row.assignee_display_name, row.assignee_email, row.assignee_phone,
          row.role_label, row.display_time, row.status_label
        ].join(' ')).indexOf(needle) >= 0;
        var matchesStatus = !$scope.ui.statusFilter || row.status_name === $scope.ui.statusFilter;
        return matchesText && matchesStatus;
      });
      var grouped = _.groupBy(visible, function(row) {
        return row.display_time || row.start_time || ts('Unscheduled');
      });
      $scope.visibleRows = visible;
      $scope.groups = _.map(grouped, function(rows, label) {
        return {label: label, start: rows[0].start_time || '', rows: rows};
      }).sort(function(a, b) {
        return String(a.start).localeCompare(String(b.start));
      });
      $scope.ui.allSelected = visible.length > 0 && _.every(visible, function(row) { return !!$scope.selected[row.id]; });
    }

    function loadHeaderContext() {
      return volWorkflow.loadContext(projectId).then(function(context) {
        model.project = context.project;
        $scope.workflow.project = context.project;
        $scope.workflow.summary = context.summary;
        $scope.workflow.beneficiaryNames = context.beneficiaryNames;
        var workflowData = (context.supporting && context.supporting.workflow) || {};
        capabilities = {
          canSendEmail: workflowData.can_send_email !== false,
          emailActivityTypeId: workflowData.email_activity_type_id || null,
          sms: workflowData.sms || {enabled: false, activity_type_id: null}
        };
      }, angular.noop);
    }

    // Core's own contact actions gate Email on outbound mail being configured
    // and SMS on an active provider plus the 'send SMS' permission; mirror that
    // rather than offering a button that leads to a dead form.
    $scope.canEmail = function(row) {
      return !!row.assignee_email && $scope.canSendEmail();
    };

    // The bulk action leads to the same core form, so it is gated the same way:
    // offering it without outbound mail configured just leads to a dead end.
    $scope.canSendEmail = function() {
      return !!capabilities.canSendEmail && !!capabilities.emailActivityTypeId;
    };

    $scope.canSms = function(row) {
      return !!row.assignee_phone && !!capabilities.sms.enabled && !!capabilities.sms.activity_type_id;
    };

    function openActivityForm(path, contactId, activityTypeId) {
      CRM.loadForm(CRM.url(path, {
        reset: 1,
        action: 'add',
        cid: parseInt(contactId, 10),
        atype: activityTypeId
      }));
    }

    $scope.emailVolunteer = function(row) {
      openActivityForm('civicrm/activity/email/add', row.assignee_contact_id, capabilities.emailActivityTypeId);
    };

    $scope.smsVolunteer = function(row) {
      openActivityForm('civicrm/activity/sms/add', row.assignee_contact_id, capabilities.sms.activity_type_id);
    };

    function load() {
      $scope.loading = true;
      return crmApi4('VolunteerAssignment', 'getRoster', {
          projectId: projectId,
          includePast: !!$scope.ui.includePast
        }).then(function(result) {
        var roster = result[0] || {};
        $scope.rows = roster.rows || [];
        model.project.id = projectId;
        model.project.title = roster.project_title || model.project.title;
        $scope.workflow.project.id = projectId;
        $scope.workflow.project.title = roster.project_title || $scope.workflow.project.title;
        $scope.statuses = _.chain($scope.rows)
          .map(function(row) { return {name: row.status_name, label: row.status_label || row.status_name}; })
          .uniq(false, function(status) { return status.name; })
          .sortBy('label')
          .value();
        applyFilters();
      }).finally(function() {
        $scope.loading = false;
      });
    }

    $scope.filterRows = applyFilters;
    $scope.reload = load;

    $scope.toggleAll = function() {
      angular.forEach($scope.visibleRows, function(row) {
        $scope.selected[row.id] = !!$scope.ui.allSelected;
      });
    };

    $scope.selectedCount = function() {
      return _.filter($scope.rows, function(row) { return !!$scope.selected[row.id]; }).length;
    };

    $scope.emailSelected = function() {
      var contactIds = _.chain($scope.rows).filter(function(row) {
        return !!$scope.selected[row.id] && !!row.assignee_email;
      }).pluck('assignee_contact_id').map(function(id) { return parseInt(id, 10); }).uniq().value();
      if (!contactIds.length) {
        CRM.alert(ts('Select at least one volunteer with an email address.'), ts('No recipients'), 'warning');
        return;
      }
      CRM.loadForm(CRM.url('civicrm/activity/email/add', {
        reset: 1,
        action: 'add',
        atype: capabilities.emailActivityTypeId,
        cid: contactIds.join(',')
      }));
    };

    $scope.printRoster = function() {
      $window.print();
    };

    function csvCell(value) {
      value = String(value === null || value === undefined ? '' : value);
      if (/^[=+\-@]/.test(value)) {
        value = "'" + value;
      }
      return '"' + value.replace(/"/g, '""') + '"';
    }

    $scope.exportCsv = function() {
      var headers = [ts('Volunteer'), ts('Role'), ts('Shift'), ts('Status'), ts('Email'), ts('Phone')];
      var lines = [headers.map(csvCell).join(',')];
      angular.forEach($scope.visibleRows, function(row) {
        lines.push([
          row.assignee_display_name, row.role_label, row.display_time,
          row.status_label, row.assignee_email, row.assignee_phone
        ].map(csvCell).join(','));
      });
      var blob = new Blob(['\ufeff' + lines.join('\r\n')], {type: 'text/csv;charset=utf-8'});
      var url = $window.URL.createObjectURL(blob);
      var link = $window.document.createElement('a');
      link.href = url;
      link.download = 'volunteer-roster-' + projectId + '.csv';
      $window.document.body.appendChild(link);
      link.click();
      link.remove();
      $window.URL.revokeObjectURL(url);
    };

    $scope.contactUrl = function(contactId) {
      return CRM.url('civicrm/contact/view', {reset: 1, cid: parseInt(contactId, 10)});
    };

    $scope.closeWorkflowDialog = function() {
      $scope.volunteerWorkflowDialog.close();
    };

    loadHeaderContext();
    load();
  });

})(angular, CRM._, CRM.$);
