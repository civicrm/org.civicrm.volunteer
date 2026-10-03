(function(angular, _, $) {
  'use strict';

  angular.module('volunteer').controller('VolunteerHours', function($scope, $route, $location, $window, $q, crmApi4, crmStatus, volWorkflow) {
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var model = $scope.model || {};
    var projectId = parseInt(model.projectId || $route.current.params.projectId, 10);
    var cleanState = '';
    var commendations = {};

    $scope.dialogMode = !!model.dialogMode;
    $scope.projectId = projectId;
    $scope.loading = true;
    $scope.rows = [];
    $scope.needs = [];
    $scope.statuses = [];
    var requestedNeedId = parseInt(model.scopeNeedId || ($location.search() || {}).needId, 10);
    // Bound through an object: HoursBody.html renders inside an ng-include, so a
    // bare primitive would be shadowed on that child scope and never reach here.
    $scope.ui = {
      scopeNeedId: requestedNeedId ? String(requestedNeedId) : 'all',
      previousScopeNeedId: requestedNeedId ? String(requestedNeedId) : 'all',
      bulkHours: null
    };

    $scope.workflow = {
      step: 'hours',
      projectId: projectId,
      project: model.project || {id: projectId, title: '', is_active: null},
      summary: {filled: 0, total: 0},
      beneficiaryNames: [],
      manualSave: true,
      autoSave: false,
      saving: false,
      formContext: (CRM.vars['org.civicrm.volunteer'] || {}).context || 'standAlone',
      isDirty: function() { return currentState() !== cleanState; },
      markPristine: function() { cleanState = currentState(); }
    };

    function payloadRows() {
      return _.map($scope.rows, function(row) {
        return {
          id: row.id || null,
          assignee_contact_id: row.assignee_contact_id || null,
          volunteer_need_id: row.volunteer_need_id || null,
          status_id: parseInt(row.status_id, 10) || null,
          hours: row.hours === '' || row.hours === null ? null : Number(row.hours),
          details: row.details || ''
        };
      });
    }

    function currentState() {
      return angular.toJson(payloadRows());
    }

    function prepareData(data) {
      $scope.needs = _.filter(data.needs || [], function(need) { return need.is_flexible != 1; });
      $scope.statuses = data.statuses || [];
      $scope.completedStatusId = parseInt(data.completed_status_id, 10);
      $scope.noShowStatusId = parseInt(data.no_show_status_id, 10);
      $scope.flexibleNeedId = parseInt(data.flexible_need_id, 10);
      $scope.rows = _.map(data.rows || [], function(row) {
        row.status_id = parseInt(row.status_id, 10);
        row.hours = row.time_completed_minutes === null || row.time_completed_minutes === ''
          ? null
          : Math.round((parseInt(row.time_completed_minutes, 10) / 60) * 100) / 100;
        return row;
      });
      cleanState = currentState();
    }

    function loadContext() {
      return volWorkflow.loadContext(projectId).then(function(context) {
        model.project = context.project;
        $scope.workflow.project = context.project;
        $scope.workflow.summary = context.summary;
        $scope.workflow.beneficiaryNames = context.beneficiaryNames;
      });
    }

    function loadCommendations() {
      return crmApi4('VolunteerCommendation', 'get', {
        where: [['volunteer_project_id', '=', projectId]]
      }).then(function(rows) {
        commendations = {};
        angular.forEach(rows, function(row) {
          commendations[parseInt(row.volunteer_contact_id, 10)] = parseInt(row.id, 10);
        });
      }, angular.noop);
    }

    function loadRows() {
      $scope.loading = true;
      var needId = $scope.ui.scopeNeedId === 'all' ? null : parseInt($scope.ui.scopeNeedId, 10);
      return crmApi4('VolunteerAssignment', 'getHourEntries', {
        projectId: projectId,
        volunteerNeedId: needId
      }).then(function(result) {
        prepareData(result[0] || {});
        $scope.ui.previousScopeNeedId = $scope.ui.scopeNeedId;
      }).finally(function() {
        $scope.loading = false;
      });
    }

    $scope.changeScope = function() {
      var requested = $scope.ui.scopeNeedId;
      if (!$scope.workflow.isDirty()) {
        return loadRows();
      }
      $scope.ui.scopeNeedId = $scope.ui.previousScopeNeedId;
      return volWorkflow.confirmDiscard().then(function() {
        $scope.ui.scopeNeedId = requested;
        return loadRows();
      });
    };

    $scope.addVolunteer = function() {
      var needId = $scope.ui.scopeNeedId === 'all'
        ? $scope.flexibleNeedId
        : parseInt($scope.ui.scopeNeedId, 10);
      var need = _.findWhere($scope.needs, {id: needId}) || _.find($scope.needs, function(item) {
        return parseInt(item.id, 10) === needId;
      });
      $scope.rows.push({
        _new: true,
        assignee_contact_id: null,
        assignee_display_name: '',
        volunteer_need_id: needId,
        role_label: need ? need.role_label : ts('General availability'),
        display_time: need ? need.display_time : ts('All shifts'),
        time_scheduled_minutes: need ? need.duration : null,
        status_id: $scope.completedStatusId,
        hours: null,
        details: ''
      });
    };

    $scope.removeNewRow = function(row) {
      if (row._new) {
        $scope.rows = _.without($scope.rows, row);
      }
    };

    $scope.markAllAttended = function() {
      angular.forEach($scope.rows, function(row) { row.status_id = $scope.completedStatusId; });
    };

    $scope.applyBulkHours = function() {
      // Number(null) is 0, so without this an empty box would silently set
      // everybody's hours to zero instead of asking for a value.
      if ($scope.ui.bulkHours === null || $scope.ui.bulkHours === '' || $scope.ui.bulkHours === undefined) {
        CRM.alert(ts('Enter the number of hours to apply.'), ts('No hours entered'), 'warning');
        return;
      }
      var hours = Number($scope.ui.bulkHours);
      if (!isFinite(hours) || hours < 0) {
        CRM.alert(ts('Enter a non-negative number of hours.'), ts('Invalid hours'), 'warning');
        return;
      }
      angular.forEach($scope.rows, function(row) {
        if (parseInt(row.status_id, 10) === $scope.completedStatusId) {
          row.hours = hours;
        }
      });
    };

    $scope.attendedSummary = function() {
      var attended = _.filter($scope.rows, function(row) {
        return parseInt(row.status_id, 10) === $scope.completedStatusId;
      });
      var hours = _.reduce(attended, function(sum, row) {
        var value = Number(row.hours);
        return sum + (isFinite(value) ? value : 0);
      }, 0);
      return ts('%1 hours across %2 volunteers marked Attended', {
        1: Math.round(hours * 100) / 100,
        2: attended.length
      });
    };

    function validateRows() {
      var invalid = _.find($scope.rows, function(row) {
        var hours = row.hours;
        return (row._new && !parseInt(row.assignee_contact_id, 10))
          || !parseInt(row.status_id, 10)
          || (hours !== null && hours !== '' && (!isFinite(Number(hours)) || Number(hours) < 0));
      });
      if (invalid) {
        CRM.alert(ts('Every new row needs a volunteer, and every row needs a valid status and non-negative hours.'), ts('Check hour entries'), 'warning');
        return false;
      }
      return true;
    }

    $scope.saveHours = function() {
      // The sheet is not saved and the user has been told why. The buttons
      // that call this do not chain on the result, so resolve rather than
      // leave a rejection nobody handles.
      if (!validateRows()) {
        return $q.resolve();
      }
      var entries = _.map(payloadRows(), function(row) {
        return {
          id: row.id,
          assignee_contact_id: row.assignee_contact_id,
          volunteer_need_id: row.volunteer_need_id,
          status_id: row.status_id,
          time_completed_minutes: (row.hours === null || row.hours === '' || row.hours === undefined)
            ? null
            : Math.round(Number(row.hours) * 60),
          details: row.details
        };
      });
      $scope.workflow.saving = true;
      var needId = $scope.ui.scopeNeedId === 'all' ? null : parseInt($scope.ui.scopeNeedId, 10);
      var request = crmApi4('VolunteerAssignment', 'logHours', {
        projectId: projectId,
        volunteerNeedId: needId,
        entries: entries
      }).then(function(result) {
        prepareData(result[0] || {});
        return loadContext();
      }).finally(function() {
        $scope.workflow.saving = false;
      });
      return crmStatus({start: ts('Saving hours…'), success: ts('Volunteer hours saved')}, request)
        .catch(angular.noop);
    };
    $scope.workflow.save = $scope.saveHours;

    $scope.commendVolunteer = function(row) {
      var contactId = parseInt(row.assignee_contact_id, 10);
      if (!contactId) {
        CRM.alert(ts('Choose a volunteer before adding a commendation.'), ts('Volunteer required'), 'warning');
        return;
      }
      var params = {vid: projectId, cid: contactId};
      if (commendations[contactId]) {
        params.aid = commendations[contactId];
      }
      CRM.loadForm(CRM.url('civicrm/volunteer/commendation', params)).on('crmFormSuccess', loadCommendations);
    };

    $scope.hasCommendation = function(row) {
      return !!commendations[parseInt(row.assignee_contact_id, 10)];
    };

    $scope.closeWorkflowDialog = function() {
      if (!$scope.workflow.isDirty()) {
        $scope.volunteerWorkflowDialog.close();
        return;
      }
      return volWorkflow.confirmDiscard().then(function() {
        $scope.volunteerWorkflowDialog.close();
      });
    };

    $scope.cancelWorkflow = function() {
      return volWorkflow.cancel($scope.workflow);
    };

    var unload = function(event) {
      if ($scope.workflow.isDirty()) {
        event.preventDefault();
        event.returnValue = '';
      }
    };
    $window.addEventListener('beforeunload', unload);
    $scope.$on('$destroy', function() { $window.removeEventListener('beforeunload', unload); });

    $q.all([loadContext(), loadRows(), loadCommendations()]);
  });

})(angular, CRM._, CRM.$);
