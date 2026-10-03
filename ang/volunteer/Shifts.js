(function(angular, $, _) {
  'use strict';

  /**
   * Pure date and attribute predicates for the shared Shifts page/dialog.
   *
   * Keep this factory in Shifts.js rather than a separate source file. CiviCRM
   * expands Angular module globs when it builds the module manifest, so a file
   * added after that manifest was cached may not reach the browser even after
   * ordinary Drupal cache rebuilds.
   *
   * Need timestamps are site-local wall time. Parsing their components as UTC
   * gives us a stable numeric ordering without asking the browser to reinterpret
   * them in its own timezone.
   */
  angular.module('volunteer').factory('volShiftFilters', function() {
    function timestamp(value, endOfDay) {
      if (typeof value !== 'string' || !value.trim()) {
        return null;
      }
      var match = value.trim().match(/^(\d{4})-?(\d{2})-?(\d{2})(?:[ T]?(\d{2})(?::?(\d{2}))?(?::?(\d{2}))?)?$/);
      if (!match) {
        return null;
      }
      var hasTime = match[4] !== undefined;
      var parts = {
        year: parseInt(match[1], 10),
        month: parseInt(match[2], 10),
        day: parseInt(match[3], 10),
        hour: hasTime ? parseInt(match[4] || 0, 10) : (endOfDay ? 23 : 0),
        minute: hasTime ? parseInt(match[5] || 0, 10) : (endOfDay ? 59 : 0),
        second: hasTime ? parseInt(match[6] || 0, 10) : (endOfDay ? 59 : 0)
      };
      var result = Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, parts.second);
      var date = new Date(result);
      if (date.getUTCFullYear() !== parts.year || date.getUTCMonth() !== parts.month - 1 ||
        date.getUTCDate() !== parts.day || date.getUTCHours() !== parts.hour ||
        date.getUTCMinutes() !== parts.minute || date.getUTCSeconds() !== parts.second) {
        return null;
      }
      return result;
    }

    function scheduleMode(need) {
      if (need.schedule_mode === 'fixed' || need.schedule_mode === 'window' || need.schedule_mode === 'ongoing') {
        return need.schedule_mode;
      }
      if (need.end_time) {
        return 'window';
      }
      return parseInt(need.duration, 10) > 0 ? 'fixed' : 'ongoing';
    }

    function needInterval(need) {
      var start = timestamp(need.start_time, false);
      if (start === null) {
        return null;
      }
      var mode = scheduleMode(need);
      var end = null;
      if (mode === 'window') {
        end = timestamp(need.end_time, false);
        if (end === null || end < start) {
          return null;
        }
      }
      else if (mode === 'fixed') {
        var duration = parseInt(need.duration, 10);
        if (!(duration > 0)) {
          return null;
        }
        end = start + (duration * 60000);
      }
      return {start: start, end: end, mode: mode};
    }

    function buildRange(scope, presets, custom) {
      var values;
      if (scope === 'all') {
        return {valid: true, from: null, to: null};
      }
      if (scope === 'custom') {
        values = custom || {};
      }
      else if (scope === 'upcoming') {
        values = {from: presets && presets.now, to: null};
      }
      else {
        values = presets && presets[scope];
      }
      values = values || {};
      var hasFrom = typeof values.from === 'string' && !!values.from.trim();
      var hasTo = typeof values.to === 'string' && !!values.to.trim();
      var from = hasFrom ? timestamp(values.from, false) : null;
      var to = hasTo ? timestamp(values.to, true) : null;
      var valid = (!hasFrom || from !== null) && (!hasTo || to !== null) &&
        (from === null || to === null || from <= to);
      return {valid: valid, from: from, to: to};
    }

    function matches(need, filters, range) {
      var interval = needInterval(need);
      var mode = interval ? interval.mode : scheduleMode(need);
      if (filters.dateScope !== 'all') {
        if (!interval) {
          return false;
        }
        if (range.from !== null && interval.end !== null && interval.end < range.from) {
          return false;
        }
        if (range.to !== null && interval.start > range.to) {
          return false;
        }
      }
      if (filters.scheduleMode && filters.scheduleMode !== 'any' && mode !== filters.scheduleMode) {
        return false;
      }
      if (filters.roleId && parseInt(need.role_id, 10) !== parseInt(filters.roleId, 10)) {
        return false;
      }
      if (filters.signupStatus === 'accepting' && !need.accepting) {
        return false;
      }
      if (filters.signupStatus === 'not_accepting' && need.accepting) {
        return false;
      }
      return true;
    }

    function filter(needs, filters, range) {
      return (needs || []).filter(function(need) {
        return matches(need, filters, range);
      });
    }

    return {
      timestamp: timestamp,
      scheduleMode: scheduleMode,
      needInterval: needInterval,
      buildRange: buildRange,
      matches: matches,
      filter: filter
    };
  });

  angular.module('volunteer').controller('VolunteerShifts', function($scope, $route, $location, $q, crmApi4, volWorkflow, volShiftFilters) {
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var model = $scope.model || {};
    var projectId = parseInt(model.projectId || $route.current.params.projectId, 10);
    var pending = {};
    var clientIdSeq = 0;

    $scope.dialogMode = !!model.dialogMode;
    $scope.projectId = projectId;
    $scope.needs = [];
    $scope.flexibleNeed = null;
    $scope.roles = [];
    $scope.visibility = {};
    $scope.loading = true;
    $scope.visibleNeeds = [];
    $scope.shiftFilterPresets = {};
    $scope.ui = {
      shiftFilters: {
        dateScope: 'upcoming',
        dateFrom: '',
        dateTo: '',
        scheduleMode: 'any',
        roleId: null,
        signupStatus: 'any'
      },
      appliedShiftFilters: null,
      shiftFilterError: false
    };

    $scope.workflow = {
      step: 'shifts',
      projectId: projectId,
      project: model.project || {id: projectId, title: '', is_active: null},
      summary: {filled: 0, total: 0},
      beneficiaryNames: [],
      manualSave: false,
      autoSave: true,
      pending: false,
      saveError: false,
      formContext: (CRM.vars['org.civicrm.volunteer'] || {}).context || 'standAlone',
      isDirty: function() { return false; }
    };

    // Unique within the page load, so `track by` never collides on rows
    // added in the same millisecond.
    function nextClientId(prefix) {
      clientIdSeq += 1;
      return prefix + '-' + clientIdSeq;
    }

    function todayAtMidnight() {
      var date = new Date();
      function pad(value) { return value < 10 ? '0' + value : String(value); }
      return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' 00:00:00';
    }

    function prepareNeed(need) {
      // A draft row has no role yet. parseInt(null) is NaN, which the role
      // <select> cannot match, so it would show Angular's "?" placeholder
      // instead of "Select a role".
      need.role_id = need.role_id === null || need.role_id === undefined || need.role_id === ''
        ? null
        : parseInt(need.role_id, 10);
      need.is_active = need.is_active == 1;
      need.is_flexible = need.is_flexible == 1;
      need.public = parseInt(need.visibility_id, 10) === parseInt($scope.visibility.public, 10);
      need.accepting = !!need.is_active;
      need.assignment_count = parseInt(need.assignment_count, 10) || 0;
      if (need.end_time) {
        need.schedule_mode = 'window';
      }
      else if (!need.duration) {
        need.schedule_mode = 'ongoing';
      }
      else {
        need.schedule_mode = 'fixed';
      }
      return need;
    }

    function defaultShiftFilters() {
      return {
        dateScope: 'upcoming',
        dateFrom: '',
        dateTo: '',
        scheduleMode: 'any',
        roleId: null,
        signupStatus: 'any'
      };
    }

    function recomputeVisibleNeeds(filters) {
      filters = angular.copy(filters || $scope.ui.appliedShiftFilters || defaultShiftFilters());
      var range = volShiftFilters.buildRange(filters.dateScope, $scope.shiftFilterPresets, {
        from: filters.dateFrom,
        to: filters.dateTo
      });
      if (!range.valid) {
        $scope.ui.shiftFilterError = true;
        return false;
      }
      $scope.ui.shiftFilterError = false;
      $scope.ui.appliedShiftFilters = filters;
      $scope.visibleNeeds = volShiftFilters.filter($scope.needs, filters, range);
      return true;
    }

    function keepNeedVisible(need) {
      if ($scope.visibleNeeds.indexOf(need) < 0) {
        $scope.visibleNeeds.push(need);
      }
    }

    $scope.setShiftDateScope = function(scope) {
      $scope.ui.shiftFilters.dateScope = scope;
      $scope.ui.shiftFilters.dateFrom = '';
      $scope.ui.shiftFilters.dateTo = '';
      var filters = angular.copy($scope.ui.shiftFilters);
      recomputeVisibleNeeds(filters);
    };

    $scope.applyCustomShiftRange = function(form) {
      if (form && form.$invalid) {
        $scope.ui.shiftFilterError = true;
        return;
      }
      var filters = angular.copy($scope.ui.shiftFilters);
      if (!filters.dateFrom && !filters.dateTo) {
        filters.dateScope = 'all';
        $scope.ui.shiftFilters.dateScope = 'all';
      }
      else {
        filters.dateScope = 'custom';
      }
      if (recomputeVisibleNeeds(filters)) {
        $scope.ui.shiftFilters.dateScope = filters.dateScope;
      }
    };

    $scope.applyShiftOptionFilters = function() {
      var filters = angular.copy($scope.ui.appliedShiftFilters || defaultShiftFilters());
      filters.scheduleMode = $scope.ui.shiftFilters.scheduleMode;
      filters.roleId = $scope.ui.shiftFilters.roleId;
      filters.signupStatus = $scope.ui.shiftFilters.signupStatus;
      recomputeVisibleNeeds(filters);
    };

    $scope.resetShiftFilters = function() {
      $scope.ui.shiftFilters = defaultShiftFilters();
      recomputeVisibleNeeds($scope.ui.shiftFilters);
    };

    function refresh() {
      $scope.loading = true;
      return $q.all({
        context: volWorkflow.loadContext(projectId),
        support: volWorkflow.getSupportingData()
      }).then(function(result) {
        model.project = result.context.project;
        $scope.workflow.project = result.context.project;
        $scope.workflow.summary = result.context.summary;
        $scope.workflow.beneficiaryNames = result.context.beneficiaryNames;
        $scope.workflow.supporting = result.support.workflow;
        $scope.roles = result.support.workflow.roles || [];
        $scope.visibility = result.support.workflow.visibility || {};
        $scope.shiftFilterPresets = result.support.workflow.shift_filter_presets || {};
        var assignmentsByNeed = _.countBy(result.context.assignments, 'volunteer_need_id');
        angular.forEach(result.context.needs, function(need) {
          need.assignment_count = assignmentsByNeed[need.id] || 0;
          prepareNeed(need);
        });
        $scope.flexibleNeed = _.findWhere(result.context.needs, {is_flexible: true})
          || _.findWhere(result.context.needs, {is_flexible: '1'});
        if ($scope.flexibleNeed) {
          prepareNeed($scope.flexibleNeed);
        }
        $scope.needs = _.filter(result.context.needs, function(need) { return !need.is_flexible; });
        recomputeVisibleNeeds($scope.ui.shiftFilters);
        if (model.needRegistry && !model.needRegistry.clean.length) {
          model.needRegistry.clean = _.pluck($scope.needs, 'id');
        }
      }).finally(function() {
        $scope.loading = false;
      });
    }

    function apiValues(need) {
      var values = {
        project_id: projectId,
        role_id: parseInt(need.role_id, 10),
        quantity: need.quantity === '' || need.quantity === null ? null : parseInt(need.quantity, 10),
        visibility_id: need.public ? parseInt($scope.visibility.public, 10) : parseInt($scope.visibility.admin, 10),
        is_active: !!need.accepting,
        is_flexible: false,
        start_time: need.start_time || null,
        end_time: need.end_time || null,
        duration: need.duration === '' || need.duration === null ? null : parseInt(need.duration, 10)
      };
      if (need.schedule_mode === 'fixed') {
        values.end_time = null;
      }
      else if (need.schedule_mode === 'ongoing') {
        values.start_time = need.start_time || todayAtMidnight();
        values.end_time = null;
        values.duration = null;
      }
      return values;
    }

    // The registry shape the volunteer:close:define event has always
    // carried for third-party listeners: a need created in this session stays
    // reported as created even if it is edited again before the dialog closes.
    function markRegistry(action, id) {
      if (!model.needRegistry || !id) {
        return;
      }
      var wasCreated = (model.needRegistry.created || []).indexOf(id) >= 0;
      if (action === 'updated' && wasCreated) {
        return;
      }
      angular.forEach(['clean', 'created', 'updated', 'deleted'], function(key) {
        if (key !== action) {
          model.needRegistry[key] = _.without(model.needRegistry[key], id);
        }
      });
      if (model.needRegistry[action].indexOf(id) < 0) {
        model.needRegistry[action].push(id);
      }
    }

    function track(need, promise) {
      var key = need.id || need._clientId;
      need.saveState = 'saving';
      pending[key] = promise;
      $scope.workflow.pending = true;
      $scope.workflow.saveError = false;
      return promise.then(function(result) {
        need.saveState = 'saved';
        return result;
      }, function(error) {
        need.saveState = 'error';
        $scope.workflow.saveError = true;
        CRM.alert(error && error.error_message ? error.error_message : ts('This shift could not be saved.'), ts('Not saved'), 'error');
        return $q.reject(error);
      }).finally(function() {
        if (pending[key] === promise) {
          delete pending[key];
        }
        $scope.workflow.pending = Object.keys(pending).length > 0;
      });
    }

    $scope.saveNeed = function(need) {
      if (!need.role_id) {
        return $q.resolve();
      }
      var values = apiValues(need);
      // Serialize writes for a row so a slower earlier autosave cannot
      // overwrite the user's most recent edit.
      var promise = (need._saveQueue || $q.resolve()).catch(angular.noop).then(function() {
        if (need.id) {
          return crmApi4('VolunteerNeed', 'update', {
            where: [['id', '=', parseInt(need.id, 10)]],
            values: values
          }).then(function(rows) {
            markRegistry('updated', parseInt(need.id, 10));
            return rows[0];
          });
        }
        return crmApi4('VolunteerNeed', 'create', {values: values}).then(function(rows) {
          need.id = parseInt(rows[0].id, 10);
          markRegistry('created', need.id);
          return rows[0];
        });
      });
      need._saveQueue = promise;
      return track(need, promise).catch(angular.noop);
    };

    $scope.changeSchedule = function(need) {
      if (need.schedule_mode === 'ongoing') {
        need.start_time = need.start_time || todayAtMidnight();
        need.end_time = null;
        need.duration = null;
      }
      else {
        need.duration = need.duration || 60;
        need.start_time = need.start_time || todayAtMidnight();
        if (need.schedule_mode === 'fixed') {
          need.end_time = null;
        }
      }
      return $scope.saveNeed(need);
    };

    $scope.addShift = function() {
      var need = prepareNeed({
        _clientId: nextClientId('new'),
        role_id: null,
        quantity: 1,
        is_active: true,
        accepting: true,
        public: true,
        schedule_mode: 'fixed',
        start_time: todayAtMidnight(),
        duration: 60,
        assignment_count: 0,
        saveState: 'draft'
      });
      $scope.needs.push(need);
      keepNeedVisible(need);
    };

    $scope.duplicateNeed = function(need) {
      var duplicate = angular.copy(need);
      delete duplicate.id;
      delete duplicate._saveQueue;
      duplicate._clientId = nextClientId('copy');
      duplicate.assignment_count = 0;
      duplicate.saveState = 'draft';
      $scope.needs.push(duplicate);
      keepNeedVisible(duplicate);
      return $scope.saveNeed(duplicate);
    };

    $scope.deleteNeed = function(need) {
      if (!need.id) {
        $scope.needs = _.without($scope.needs, need);
        $scope.visibleNeeds = _.without($scope.visibleNeeds, need);
        return;
      }
      var message = need.assignment_count
        ? ts('This shift has %1 assignment(s). Their activity history will be preserved in general availability.', {1: need.assignment_count})
        : ts('Delete this shift?');
      CRM.confirm({title: ts('Delete shift'), message: message}).on('crmConfirm:yes', function() {
        $scope.$applyAsync(function() {
          var promise = crmApi4('VolunteerNeed', 'delete', {where: [['id', '=', parseInt(need.id, 10)] ]})
            .then(function() {
              $scope.needs = _.without($scope.needs, need);
              $scope.visibleNeeds = _.without($scope.visibleNeeds, need);
              markRegistry('deleted', parseInt(need.id, 10));
            });
          track(need, promise);
        });
      });
    };

    $scope.saveFlexible = function() {
      if (!$scope.flexibleNeed) {
        return;
      }
      var value = $scope.flexibleNeed.public
        ? parseInt($scope.visibility.public, 10)
        : parseInt($scope.visibility.admin, 10);
      var promise = crmApi4('VolunteerNeed', 'update', {
        where: [['id', '=', parseInt($scope.flexibleNeed.id, 10)]],
        values: {visibility_id: value}
      }).then(function() {
        markRegistry('updated', parseInt($scope.flexibleNeed.id, 10));
      });
      return track($scope.flexibleNeed, promise).catch(angular.noop);
    };

    function waitForSaves() {
      return $q.all(_.values(pending)).then(function() {
        var incomplete = _.find($scope.needs, function(need) { return !need.id; });
        if (incomplete) {
          CRM.alert(ts('Choose a role for each new shift before continuing.'), ts('Shift incomplete'), 'warning');
          return $q.reject(false);
        }
        if ($scope.workflow.saveError) {
          return $q.reject(false);
        }
      });
    }

    $scope.continueToAssign = function() {
      return waitForSaves().then(function() {
        volWorkflow.navigate(volWorkflow.projectPath(projectId, 'assign'));
      }).catch(angular.noop);
    };

    $scope.closeWorkflowDialog = function() {
      return waitForSaves().then(function() {
        $scope.volunteerWorkflowDialog.close();
      }).catch(angular.noop);
    };

    $scope.cancelWorkflow = function() {
      return volWorkflow.cancel($scope.workflow);
    };

    refresh();
  });

})(angular, CRM.$, CRM._);
