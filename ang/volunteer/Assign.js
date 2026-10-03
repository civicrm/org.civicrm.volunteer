(function(angular, _, $) {
  'use strict';

  angular.module('volunteer').controller('VolunteerAssign', function($scope, $route, $location, $q, crmApi4, crmStatus, volWorkflow) {
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var model = $scope.model || {};
    var projectId = parseInt(model.projectId || $route.current.params.projectId, 10);
    var pending = [];
    var statuses = {};
    // Filled counts across every status, so a shift whose volunteers have been
    // marked Attended still reads as occupied. Kept in step with the lists as
    // assignments move, rather than refetched on each change.
    var filledByNeed = {};

    $scope.dialogMode = !!model.dialogMode;
    $scope.projectId = projectId;
    $scope.shifts = [];
    $scope.availableNeed = null;
    $scope.availableAssignments = [];
    $scope.selectedNeed = null;
    $scope.roles = [];
    $scope.loading = true;
    $scope.newContacts = {};
    $scope.metrics = {assigned: 0, open: 0, available: 0, total: 0};

    $scope.workflow = {
      step: 'assign',
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

    function statusId(name) {
      return parseInt((statuses[name] || {}).id, 10);
    }

    function assignmentsFor(needId, assignments) {
      return _.filter(assignments, function(assignment) {
        return parseInt(assignment.volunteer_need_id, 10) === parseInt(needId, 10);
      });
    }

    // Only shifts with a finite quantity contribute to capacity, matching
    // volWorkflow.summarize() and the server-side getCapacity action.
    function isCounted(need) {
      return !!parseInt(need.quantity, 10);
    }

    $scope.filledFor = function(need) {
      return parseInt(filledByNeed[need.id], 10) || 0;
    };

    function refreshMetrics() {
      var assigned = 0;
      var total = 0;
      angular.forEach($scope.shifts, function(need) {
        if (!isCounted(need)) {
          return;
        }
        assigned += $scope.filledFor(need);
        total += parseInt(need.quantity, 10);
      });
      $scope.metrics = {
        assigned: assigned,
        open: Math.max(0, total - assigned),
        available: $scope.availableAssignments.length,
        total: total
      };
      $scope.workflow.summary = {filled: assigned, total: total};
    }

    function partition(context) {
      $scope.availableNeed = _.find(context.needs, function(need) { return need.is_flexible == 1; });
      $scope.shifts = _.chain(context.needs).filter(function(need) {
        return need.is_flexible != 1 && need.is_active == 1;
      }).map(function(need) {
        need.assignments = assignmentsFor(need.id, context.assignments);
        need.quantity = parseInt(need.quantity, 10) || 0;
        return need;
      }).value();
      $scope.availableAssignments = $scope.availableNeed
        ? assignmentsFor($scope.availableNeed.id, context.assignments)
        : [];
      selectByIdOrFirst(($scope.selectedNeed && $scope.selectedNeed.id) || model.scopeNeedId);
      refreshMetrics();
    }

    function selectByIdOrFirst(needId) {
      $scope.selectedNeed = (needId && _.find($scope.shifts, function(need) {
        return parseInt(need.id, 10) === parseInt(needId, 10);
      })) || $scope.shifts[0] || null;
    }

    function load() {
      $scope.loading = true;
      return volWorkflow.loadContext(projectId).then(function(context) {
        model.project = context.project;
        $scope.workflow.project = context.project;
        $scope.workflow.beneficiaryNames = context.beneficiaryNames;
        angular.forEach(context.supporting.workflow.statuses || [], function(status) {
          statuses[status.name] = status;
        });
        $scope.roles = context.supporting.workflow.roles || [];
        filledByNeed = angular.extend({}, context.filledByNeed);
        partition(context);
      }).finally(function() {
        $scope.loading = false;
      });
    }

    // Re-read just the assignments, for operations whose new row needs the
    // joined contact columns that create() does not return.
    function refreshAssignments() {
      return volWorkflow.getAssignments(projectId).then(function(assignments) {
        return volWorkflow.getCapacity(projectId).then(function(capacity) {
          if (capacity) {
            filledByNeed = angular.extend({}, capacity.by_need);
          }
          partition({needs: allNeeds(), assignments: assignments});
        });
      });
    }

    function allNeeds() {
      var needs = $scope.shifts.slice();
      if ($scope.availableNeed) {
        needs.push($scope.availableNeed);
      }
      return needs;
    }

    function track(request) {
      pending.push(request);
      $scope.workflow.pending = true;
      $scope.workflow.saveError = false;
      return crmStatus({start: ts('Saving assignment…'), success: ts('Assignment saved')}, request)
        .catch(function(error) {
          $scope.workflow.saveError = true;
          CRM.alert(error && error.error_message ? error.error_message : ts('The assignment could not be saved.'), ts('Not saved'), 'error');
          return $q.reject(error);
        }).finally(function() {
          pending = _.without(pending, request);
          $scope.workflow.pending = pending.length > 0;
        });
    }

    function needValues(need, isAvailable) {
      return {
        volunteer_need_id: parseInt(need.id, 10),
        status_id: statusId(isAvailable ? 'Available' : 'Scheduled'),
        activity_date_time: need.start_time,
        time_scheduled_minutes: need.duration === null ? null : parseInt(need.duration, 10),
        volunteer_role_id: parseInt(need.role_id, 10)
      };
    }

    function isAvailableNeed(need) {
      return !!need && !!$scope.availableNeed
        && parseInt(need.id, 10) === parseInt($scope.availableNeed.id, 10);
    }

    function listFor(needId) {
      if ($scope.availableNeed && parseInt(needId, 10) === parseInt($scope.availableNeed.id, 10)) {
        return $scope.availableAssignments;
      }
      var shift = _.find($scope.shifts, function(need) {
        return parseInt(need.id, 10) === parseInt(needId, 10);
      });
      return shift ? shift.assignments : null;
    }

    function needById(needId) {
      if ($scope.availableNeed && parseInt(needId, 10) === parseInt($scope.availableNeed.id, 10)) {
        return $scope.availableNeed;
      }
      return _.find($scope.shifts, function(need) {
        return parseInt(need.id, 10) === parseInt(needId, 10);
      });
    }

    // -- capacity presentation ------------------------------------------------

    $scope.isFull = function(need) {
      return isCounted(need) && $scope.filledFor(need) >= parseInt(need.quantity, 10);
    };

    $scope.capacityLabel = function(need) {
      if (!need) {
        return '';
      }
      if (isAvailableNeed(need) || !isCounted(need)) {
        return ts('No capacity limit');
      }
      return ts('%1 of %2 filled', {1: $scope.filledFor(need), 2: parseInt(need.quantity, 10)});
    };

    $scope.spotsLabel = function(need) {
      if (!need || isAvailableNeed(need) || !isCounted(need)) {
        return ts('No capacity limit');
      }
      return ts('%1 of %2 spots filled', {1: $scope.filledFor(need), 2: parseInt(need.quantity, 10)});
    };

    // One placeholder row per unfilled spot on the selected shift.
    $scope.openSpots = function(need) {
      if (!need || !isCounted(need)) {
        return [];
      }
      var open = parseInt(need.quantity, 10) - $scope.filledFor(need);
      return open > 0 ? new Array(open) : [];
    };

    $scope.initials = function(name) {
      // CRM._ is lodash 3, where first() takes no count; take() does.
      return _.chain(String(name || '').split(/\s+/))
        .filter(function(part) { return !!part; })
        .take(2)
        .map(function(part) { return part.charAt(0).toUpperCase(); })
        .value()
        .join('');
    };

    // -- selection ------------------------------------------------------------

    $scope.selectShift = function(need) {
      $scope.selectedNeed = need;
    };

    $scope.isSelected = function(need) {
      return !!$scope.selectedNeed && parseInt($scope.selectedNeed.id, 10) === parseInt(need.id, 10);
    };

    // -- mutations ------------------------------------------------------------

    // One pick, one assignment. The entity-reference widget answers a
    // selection twice: core's crmEntityref and Angular's own input binding
    // both listen for the change event, and once the first has committed and
    // this handler has cleared the model, the second sees the same contact
    // as a new value and commits it again. In a browser that created two
    // activities for every person added. Remember what is in flight per
    // shift and let the echo through as a no-op.
    var adding = {};
    $scope.addVolunteer = function(need) {
      var contactId = parseInt($scope.newContacts[need.id], 10);
      if (!contactId) {
        return;
      }
      $scope.newContacts[need.id] = null;
      if (adding[need.id] === contactId) {
        return;
      }
      adding[need.id] = contactId;
      var values = needValues(need, isAvailableNeed(need));
      values.contact_id = contactId;
      return track(crmApi4('VolunteerAssignment', 'create', {values: values}))
        .then(refreshAssignments)
        .catch(angular.noop)
        .finally(function() {
          delete adding[need.id];
        });
    };

    $scope.removeVolunteer = function(assignment) {
      CRM.confirm({
        title: ts('Remove volunteer'),
        message: ts('Remove %1 from this volunteer opportunity?', {1: assignment.assignee_display_name})
      }).on('crmConfirm:yes', function() {
        $scope.$applyAsync(function() {
          var list = listFor(assignment.volunteer_need_id);
          var index = list ? list.indexOf(assignment) : -1;
          if (index >= 0) {
            list.splice(index, 1);
            adjustFilled(assignment.volunteer_need_id, -1);
            refreshMetrics();
          }
          track(crmApi4('VolunteerAssignment', 'delete', {
            where: [['id', '=', parseInt(assignment.id, 10)]]
          })).catch(function() {
            if (index >= 0) {
              list.splice(index, 0, assignment);
              adjustFilled(assignment.volunteer_need_id, 1);
              refreshMetrics();
            }
          });
        });
      });
    };

    function adjustFilled(needId, delta) {
      var key = parseInt(needId, 10);
      filledByNeed[key] = Math.max(0, (parseInt(filledByNeed[key], 10) || 0) + delta);
    }

    /**
     * Move one assignment to another need, updating the lists first so a drag
     * does not wait on the round trip. Reverts on failure.
     */
    $scope.moveTo = function(assignment, target) {
      if (!target || !target.id) {
        return $q.resolve();
      }
      var sourceNeedId = parseInt(assignment.volunteer_need_id, 10);
      var targetNeedId = parseInt(target.id, 10);
      if (sourceNeedId === targetNeedId) {
        return $q.resolve();
      }
      if ($scope.isFull(target)) {
        // Refused, and already explained; nothing chains on the result.
        CRM.alert(ts('%1 is already fully staffed.', {1: $scope.needLabel(target)}), ts('No open spots'), 'warning');
        return $q.resolve();
      }
      var sourceList = listFor(sourceNeedId);
      var targetList = listFor(targetNeedId);
      if (!sourceList || !targetList) {
        return $q.resolve();
      }
      var index = sourceList.indexOf(assignment);
      if (index < 0) {
        return $q.resolve();
      }

      sourceList.splice(index, 1);
      assignment.volunteer_need_id = targetNeedId;
      targetList.push(assignment);
      adjustFilled(sourceNeedId, -1);
      adjustFilled(targetNeedId, 1);
      refreshMetrics();

      return track(crmApi4('VolunteerAssignment', 'update', {
        where: [['id', '=', parseInt(assignment.id, 10)]],
        values: needValues(target, isAvailableNeed(target))
      })).catch(function(error) {
        targetList.splice(targetList.indexOf(assignment), 1);
        assignment.volunteer_need_id = sourceNeedId;
        sourceList.splice(index, 0, assignment);
        adjustFilled(targetNeedId, -1);
        adjustFilled(sourceNeedId, 1);
        refreshMetrics();
        return $q.reject(error);
      }).catch(angular.noop);
    };

    $scope.copyTo = function(assignment, target) {
      if (!target || !target.id) {
        return $q.resolve();
      }
      if ($scope.isFull(target)) {
        CRM.alert(ts('%1 is already fully staffed.', {1: $scope.needLabel(target)}), ts('No open spots'), 'warning');
        return $q.resolve();
      }
      var values = needValues(target, isAvailableNeed(target));
      values.contact_id = parseInt(assignment.assignee_contact_id, 10);
      values.details = assignment.details || '';
      return track(crmApi4('VolunteerAssignment', 'create', {values: values}))
        .then(refreshAssignments)
        .catch(angular.noop);
    };

    // Drop handler shared by the shift rail and the open-spot area.
    $scope.dropAssignment = function(assignmentId, needId) {
      var target = needById(needId);
      var list = null;
      var assignment = null;
      angular.forEach(allNeeds(), function(need) {
        var candidates = listFor(need.id) || [];
        var match = _.find(candidates, function(row) {
          return parseInt(row.id, 10) === parseInt(assignmentId, 10);
        });
        if (match) {
          list = candidates;
          assignment = match;
        }
      });
      if (!assignment || !target || !list) {
        return $q.resolve();
      }
      return $scope.moveTo(assignment, target);
    };

    $scope.targetsFor = function(assignment) {
      var targets = $scope.shifts.slice();
      if ($scope.availableNeed) {
        targets.push($scope.availableNeed);
      }
      return _.filter(targets, function(need) {
        return parseInt(need.id, 10) !== parseInt(assignment.volunteer_need_id, 10);
      });
    };

    $scope.needLabel = function(need) {
      if (!need) {
        return '';
      }
      if (need.is_flexible == 1) {
        return ts('Available volunteers');
      }
      return (need.role_label || ts('Volunteer')) + ' — ' + (need.display_time || need.start_time || ts('Ongoing'));
    };

    $scope.contactUrl = function(contactId) {
      return CRM.url('civicrm/contact/view', {reset: 1, cid: parseInt(contactId, 10)});
    };

    /**
     * Create a shift without leaving the board. It carries default role, spots
     * and timing, which the Shifts & roles step is where you refine.
     */
    $scope.addShift = function() {
      if (!$scope.roles.length) {
        CRM.alert(ts('Set up at least one volunteer role before adding shifts.'), ts('No roles available'), 'warning');
        return $q.resolve();
      }
      var midnight = new Date();
      midnight.setHours(0, 0, 0, 0);
      var values = {
        project_id: projectId,
        role_id: parseInt($scope.roles[0].id, 10),
        quantity: 1,
        is_active: true,
        is_flexible: false,
        start_time: CRM.utils.formatDate
          ? CRM.utils.formatDate(midnight, 'yy-mm-dd') + ' 00:00:00'
          : midnight.toISOString().slice(0, 10) + ' 00:00:00',
        duration: 60
      };
      return track(crmApi4('VolunteerNeed', 'create', {values: values}))
        .then(function(rows) {
          var newId = rows && rows[0] ? parseInt(rows[0].id, 10) : null;
          return load().then(function() {
            if (newId) {
              selectByIdOrFirst(newId);
            }
          });
        })
        .catch(angular.noop);
    };

    $scope.editShifts = function() {
      volWorkflow.navigate(volWorkflow.projectPath(projectId, 'shifts'));
    };

    $scope.goToHours = function(need) {
      var path = volWorkflow.projectPath(projectId, 'hours');
      if (need && need.id) {
        volWorkflow.navigate(path, {needId: parseInt(need.id, 10)});
        return;
      }
      volWorkflow.navigate(path);
    };

    $scope.closeWorkflowDialog = function() {
      return $q.all(pending.slice()).catch(angular.noop).finally(function() {
        $scope.volunteerWorkflowDialog.close();
      });
    };

    $scope.cancelWorkflow = function() {
      return volWorkflow.cancel($scope.workflow);
    };

    load();
  });

  /**
   * Makes an element a jQuery UI drag source carrying an assignment id.
   * jQuery UI ships in CiviCRM's core resource list, so this needs no extra
   * dependency.
   */
  angular.module('volunteer').directive('crmVolDraggableVolunteer', function() {
    return {
      restrict: 'A',
      link: function(scope, element, attrs) {
        element.attr('data-assignment-id', attrs.crmVolDraggableVolunteer);
        element.draggable({
          appendTo: 'body',
          containment: 'document',
          cursor: 'grabbing',
          helper: 'clone',
          revert: 'invalid',
          revertDuration: 150,
          zIndex: 1000,
          start: function(event, ui) {
            $(ui.helper).addClass('crm-vol-drag-helper').width(element.width());
          }
        });
        scope.$on('$destroy', function() {
          if (element.data('ui-draggable')) {
            element.draggable('destroy');
          }
        });
      }
    };
  });

  /**
   * Marks an element as a drop target for a need id. Calls the expression in
   * `crm-vol-drop-target` with `assignmentId` and `needId` locals.
   */
  angular.module('volunteer').directive('crmVolDropTarget', function() {
    return {
      restrict: 'A',
      link: function(scope, element, attrs) {
        element.droppable({
          accept: '[data-assignment-id]',
          activeClass: 'crm-vol-drop-active',
          hoverClass: 'crm-vol-drop-hover',
          tolerance: 'pointer',
          drop: function(event, ui) {
            var assignmentId = parseInt($(ui.draggable).attr('data-assignment-id'), 10);
            var needId = parseInt(attrs.crmVolDropNeed, 10);
            if (!assignmentId || !needId) {
              return;
            }
            scope.$applyAsync(function() {
              scope.$eval(attrs.crmVolDropTarget, {assignmentId: assignmentId, needId: needId});
            });
          }
        });
        scope.$on('$destroy', function() {
          if (element.data('ui-droppable')) {
            element.droppable('destroy');
          }
        });
      }
    };
  });

})(angular, CRM._, CRM.$);
