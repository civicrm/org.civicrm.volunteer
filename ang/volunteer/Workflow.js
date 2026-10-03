(function(angular, $, _) {
  'use strict';

  var steps = ['details', 'shifts', 'assign', 'roster', 'hours', 'report'];

  angular.module('volunteer').config(function($routeProvider) {
    function access(volProjectManagementAccess) {
      return volProjectManagementAccess();
    }

    angular.forEach({
      shifts: ['VolunteerShifts', '~/volunteer/Shifts.html'],
      assign: ['VolunteerAssign', '~/volunteer/Assign.html'],
      roster: ['VolunteerRoster', '~/volunteer/Roster.html'],
      hours: ['VolunteerHours', '~/volunteer/Hours.html']
    }, function(definition, step) {
      $routeProvider.when('/volunteer/manage/:projectId/' + step, {
        controller: definition[0],
        templateUrl: definition[1],
        resolve: {projectManagementAccess: access}
      });
    });

    $routeProvider.when('/volunteer/manage/:projectId/report', {
      controller: 'VolunteerProjectHoursReport',
      templateUrl: '~/volunteer/HoursReport.html',
      resolve: {
        projectManagementAccess: access,
        volunteerHoursReportAccess: function(volHoursReportAccess) {
          return volHoursReportAccess();
        }
      }
    });

    $routeProvider.when('/volunteer/standalone/:projectId/:step', {
      controller: 'VolunteerStandaloneWorkflow',
      templateUrl: '~/volunteer/WorkflowStandalone.html'
    });
  });

  angular.module('volunteer').factory('volWorkflow', function($q, $location, $route, $window, crmApi4, crmStatus) {
    var ts = CRM.ts('org.civicrm.volunteer');
    var supportingPromise;

    // Set by navigate() and consumed by the shell's $locationChangeStart guard.
    var appInitiatedNavigation = false;

    function navigate(path, search) {
      appInitiatedNavigation = true;
      $location.path(path).search(search || {});
    }

    function consumeAppNavigation() {
      var was = appInitiatedNavigation;
      appInitiatedNavigation = false;
      return was;
    }

    function projectPath(projectId, step) {
      return '/volunteer/manage/' + parseInt(projectId, 10) + '/' + (step || 'details');
    }

    function getProject(projectId) {
      return crmApi4('VolunteerProject', 'search', {
        context: 'edit',
        filters: {id: parseInt(projectId, 10)}
      }).then(function(rows) {
        if (!rows.length) {
          return $q.reject({is_error: 1, error_message: ts('The volunteer project does not exist.')});
        }
        rows[0].is_active = rows[0].is_active == 1;
        return rows[0];
      });
    }

    function getNeeds(projectId, activeOnly) {
      var where = [['project_id', '=', parseInt(projectId, 10)]];
      if (activeOnly) {
        where.push(['is_active', '=', true]);
      }
      return crmApi4('VolunteerNeed', 'get', {
        where: where,
        orderBy: {start_time: 'ASC', id: 'ASC'}
      });
    }

    function getAssignments(projectId) {
      return crmApi4('VolunteerAssignment', 'get', {
        where: [['project_id', '=', parseInt(projectId, 10)]]
      });
    }

    function getSupportingData() {
      if (!supportingPromise) {
        supportingPromise = $q.all({
          workflow: crmApi4('VolunteerUtil', 'getSupportingData', {controller: 'VolunteerWorkflow'}),
          project: crmApi4('VolunteerUtil', 'getSupportingData', {controller: 'VolunteerProject'})
        }).then(function(result) {
          return {
            workflow: result.workflow[0] || {},
            project: result.project[0] || {}
          };
        }).catch(function(error) {
          supportingPromise = null;
          return $q.reject(error);
        });
      }
      return supportingPromise;
    }

    function summarize(needs, assignments) {
      var needIds = {};
      var total = 0;
      angular.forEach(needs, function(need) {
        var quantity = parseInt(need.quantity, 10);
        if (need.is_flexible == 1 || need.is_active == 0 || !quantity) {
          return;
        }
        needIds[need.id] = true;
        total += quantity;
      });
      var filled = _.filter(assignments, function(assignment) {
        return !!needIds[assignment.volunteer_need_id];
      }).length;
      return {filled: filled, total: total};
    }

    // VolunteerAssignment.get returns only Scheduled and Available rows, so it
    // cannot answer "how many spots are filled" once volunteers are marked
    // Attended. getCapacity counts every non-deleted volunteer activity.
    function getCapacity(projectId) {
      return crmApi4('VolunteerAssignment', 'getCapacity', {
        projectId: parseInt(projectId, 10)
      }).then(function(rows) {
        return rows[0] || null;
      }, function() {
        return null;
      });
    }

    // One request for everything a workflow step needs. The server bundles
    // the same guarded reads getProject/getNeeds/getAssignments/getCapacity
    // and getSupportingData perform individually, so a step change costs one
    // round trip instead of six or seven.
    function loadContext(projectId) {
      return crmApi4('VolunteerProject', 'getWorkflowContext', {
        projectId: parseInt(projectId, 10)
      }).then(function(rows) {
        var context = rows[0];
        if (!context || !context.project) {
          return $q.reject({is_error: 1, error_message: ts('The volunteer project does not exist.')});
        }
        context.project.is_active = context.project.is_active == 1;
        context.needs = context.needs || [];
        context.assignments = context.assignments || [];
        context.capacity = context.capacity || null;
        context.supporting = context.supporting || {workflow: {}, project: {}};
        context.beneficiaryNames = context.beneficiary_names || [];
        delete context.beneficiary_names;
        context.summary = context.capacity
          ? {filled: context.capacity.filled, total: context.capacity.total}
          : summarize(context.needs, context.assignments);
        context.filledByNeed = (context.capacity && context.capacity.by_need) || {};
        // The bundle carries the supporting data too; seed the shared cache so
        // a step that also calls getSupportingData() does not fetch it again.
        if (!supportingPromise) {
          supportingPromise = $q.resolve(context.supporting);
        }
        return context;
      });
    }

    function setActive(context, value) {
      // Angular's <select> cannot represent undefined, so before the project
      // has loaded it commits a value back through ngModel and ng-change fires
      // without the user touching anything. Only a real boolean is a real
      // choice; WorkflowShell.html also withholds the control until then.
      if (typeof value !== 'boolean') {
        return $q.resolve();
      }
      if (!context.project || !context.project.id) {
        context.project.is_active = !!value;
        return $q.resolve();
      }
      var previous = !value;
      context.project.is_active = !!value;
      context.statusSaving = true;
      var save = crmApi4('VolunteerProject', 'update', {
        where: [['id', '=', parseInt(context.project.id, 10)]],
        values: {is_active: !!value}
      }).catch(function(error) {
        context.project.is_active = previous;
        return $q.reject(error);
      }).finally(function() {
        context.statusSaving = false;
      });
      return crmStatus({start: ts('Saving status...'), success: ts('Status saved')}, save);
    }

    function preview(projectId) {
      if (!projectId) {
        return;
      }
      var url = CRM.url('civicrm/vol/', '', 'front') + '#/volunteer/opportunities?project=' + parseInt(projectId, 10) + '&hideSearch=1';
      $window.open(url, '_blank', 'noopener');
    }

    function cancel(context) {
      if (context && angular.isFunction(context.isDirty) && context.isDirty()) {
        return confirmDiscard().then(function() { cancelNow(); });
      }
      cancelNow();

      function cancelNow() {
        if (context && context.formContext === 'eventTab') {
          // The tab *is* the event's configuration page, so there is nowhere to
          // navigate to. It used to slide the editor frame away and restore an
          // "Edit Settings" button; that two-state model went with the
          // duplicated action row, so cancelling now means discarding and
          // re-reading. The event is still fired for any third-party listener.
          if (angular.isFunction(context.markPristine)) {
            context.markPristine();
          }
          CRM.$('body').trigger('volunteerProjectCancel');
          $route.reload();
        }
        else {
          navigate('/volunteer/manage');
        }
      }
    }

    function confirmDiscard() {
      var deferred = $q.defer();
      CRM.confirm({
        title: ts('Discard unsaved changes?'),
        message: ts('Your unsaved changes will be lost.')
      }).on('crmConfirm:yes', deferred.resolve);
      return deferred.promise;
    }

    return {
      steps: steps,
      projectPath: projectPath,
      navigate: navigate,
      consumeAppNavigation: consumeAppNavigation,
      getProject: getProject,
      getNeeds: getNeeds,
      getAssignments: getAssignments,
      getCapacity: getCapacity,
      getSupportingData: getSupportingData,
      loadContext: loadContext,
      summarize: summarize,
      setActive: setActive,
      preview: preview,
      cancel: cancel,
      confirmDiscard: confirmDiscard
    };
  });

  angular.module('volunteer').component('volunteerWorkflowShell', {
    bindings: {workflow: '<'},
    transclude: true,
    templateUrl: '~/volunteer/WorkflowShell.html',
    controller: function($location, $rootScope, $scope, $window, volWorkflow) {
      var ctrl = this;
      // Set while a discard has already been confirmed, so the guard below does
      // not re-prompt for the navigation it is itself performing.
      var bypassGuard = false;
      ctrl.ts = CRM.ts('org.civicrm.volunteer');
      ctrl.steps = [
        {id: 'details', label: ctrl.ts('Details')},
        {id: 'shifts', label: ctrl.ts('Shifts & roles')},
        {id: 'assign', label: ctrl.ts('Assign volunteers')},
        {id: 'roster', label: ctrl.ts('Roster')},
        {id: 'hours', label: ctrl.ts('Hours')}
      ];
      if (CRM.checkPerm('edit all volunteer projects') && CRM.checkPerm('view all contacts')) {
        ctrl.steps.push({id: 'report', label: ctrl.ts('Hours report')});
      }
      function navigate(path) {
        bypassGuard = true;
        volWorkflow.navigate(path);
      }

      // $locationChangeStart reports absolute URLs; $location.url() wants the
      // portion Angular owns.
      function appRelative(absoluteUrl) {
        var base = $location.absUrl().slice(0, $location.absUrl().length - $location.url().length);
        return absoluteUrl.indexOf(base) === 0 ? absoluteUrl.slice(base.length) : absoluteUrl;
      }

      ctrl.go = function(step) {
        if (!ctrl.workflow.projectId) {
          return;
        }
        var path = volWorkflow.projectPath(ctrl.workflow.projectId, step);
        if (ctrl.workflow.isDirty && ctrl.workflow.isDirty()) {
          volWorkflow.confirmDiscard().then(function() {
            navigate(path);
          });
        }
        else {
          navigate(path);
        }
      };

      // The in-app tab click is guarded above and window unload by each step's
      // own beforeunload handler, but browser back/forward and a hand-edited
      // hash reach the router directly. Catch those here.
      var unguard = $rootScope.$on('$locationChangeStart', function(event, next) {
        // Navigation the app itself performed -- a save that moves to the next
        // step, a Continue button, a confirmed discard -- is never challenged.
        if (volWorkflow.consumeAppNavigation() || bypassGuard) {
          bypassGuard = false;
          return;
        }
        if (!ctrl.workflow || !ctrl.workflow.isDirty || !ctrl.workflow.isDirty()) {
          return;
        }
        event.preventDefault();
        // The route change that was just cancelled left the module's loading
        // spinner up (see the $routeChangeStart handler in volunteer.js), and
        // its blockUI overlay sits above the confirmation dialog -- so without
        // this the user cannot click the button we are about to show them.
        $('#crm-main-content-wrapper').unblock();
        volWorkflow.confirmDiscard().then(function() {
          // Re-issuing $location.url() after preventDefault does not reliably
          // take effect, and the prevented route change leaves CiviCRM's
          // blockUI up. Drop the dirty state the user just agreed to discard,
          // then let the browser perform the navigation for real.
          if (angular.isFunction(ctrl.workflow.markPristine)) {
            ctrl.workflow.markPristine();
          }
          bypassGuard = true;
          $window.location.href = next;
        }, angular.noop);
      });
      $scope.$on('$destroy', unguard);
      ctrl.save = function() {
        return ctrl.workflow.save && ctrl.workflow.save();
      };
      ctrl.cancel = function() {
        return volWorkflow.cancel(ctrl.workflow);
      };
      ctrl.preview = function() {
        return volWorkflow.preview(ctrl.workflow.projectId);
      };
      ctrl.setActive = function() {
        // crmStatus has already shown the failure and setActive() has
        // restored the previous value; nothing further listens, so swallow
        // the rejection rather than leave it unhandled.
        return volWorkflow.setActive(ctrl.workflow, ctrl.workflow.project.is_active).catch(angular.noop);
      };
      // Hosted inside CiviEvent's Volunteers tab rather than owning the page.
      // The shell then has to drop the chrome the host already provides -- its
      // own breadcrumb, heading and page-level tab bar -- or the tab shows two
      // of each.
      ctrl.isEmbedded = function() {
        return !!ctrl.workflow && ctrl.workflow.formContext === 'eventTab';
      };
    }
  });

  angular.module('volunteer').factory('volWorkflowDialog', function(dialogService) {
    var ts = CRM.ts('org.civicrm.volunteer');
    var titles = {
      shifts: ts('Shifts & roles'),
      assign: ts('Assign volunteers'),
      roster: ts('Volunteer roster'),
      hours: ts('Log hours')
    };

    function normalizeStep(step) {
      var aliases = {Define: 'shifts', Assign: 'assign', Roster: 'roster', Hours: 'hours'};
      return aliases[step] || String(step || '').toLowerCase();
    }

    function open(step, project, openOptions) {
      step = normalizeStep(step);
      openOptions = openOptions || {};
      var model = {
        step: step,
        projectId: parseInt(project.id || project.projectId, 10),
        project: project,
        dialogMode: true,
        needRegistry: {clean: [], created: [], updated: [], deleted: []},
        scopeNeedId: openOptions.scopeNeedId ? parseInt(openOptions.scopeNeedId, 10) : null
      };
      var options = CRM.utils.adjustDialogDefaults({
        autoOpen: false,
        width: '90%',
        height: Math.floor($(window).height() * 0.82),
        title: titles[step] || ts('Volunteer project'),
        dialogClass: 'crm-volunteer-workflow-dialog'
      });
      return dialogService.open('volunteerWorkflowDialog', '~/volunteer/WorkflowDialog.html', model, options)
        .finally(function() {
          if (step === 'shifts') {
            $('body').trigger('volunteer:close:define', [model.projectId, model.needRegistry]);
          }
        })
        // Dismissing a dialog rejects its promise. That is a normal outcome
        // here, not an error, so it must not surface as an unhandled rejection.
        .catch(angular.noop);
    }

    return {open: open, normalizeStep: normalizeStep};
  });

  angular.module('volunteer').run(function(volWorkflowDialog) {
    CRM.volunteerPopup = function(title, tab, projectId, projectTitle) {
      return volWorkflowDialog.open(tab, {id: projectId, title: projectTitle || ''});
    };
  });

  angular.module('volunteer').controller('VolunteerWorkflowDialog', function($scope, $controller) {
    var controllers = {
      shifts: 'VolunteerShifts',
      assign: 'VolunteerAssign',
      roster: 'VolunteerRoster',
      hours: 'VolunteerHours'
    };
    if (controllers[$scope.model.step]) {
      $controller(controllers[$scope.model.step], {$scope: $scope});
    }
  });

  angular.module('volunteer').controller('VolunteerStandaloneWorkflow', function($scope, $route, $controller, volWorkflow) {
    var step = $route.current.params.step;
    $scope.model = {
      step: step,
      projectId: parseInt($route.current.params.projectId, 10),
      dialogMode: false,
      standaloneMode: true,
      needRegistry: {clean: [], created: [], updated: [], deleted: []}
    };
    var controllers = {
      roster: 'VolunteerRoster',
      hours: 'VolunteerHours'
    };
    if (!controllers[step]) {
      volWorkflow.navigate('/volunteer/manage/' + $scope.model.projectId + '/details');
      return;
    }
    $controller(controllers[step], {$scope: $scope});
  });

  angular.module('volunteer').controller('VolunteerProjectHoursReport', function($scope, $route, volWorkflow) {
    var projectId = parseInt($route.current.params.projectId, 10);

    // Afform options are inherited by the reusable report directive. Every
    // Search Kit display applies this value as an additional fixed filter, so
    // the project workflow can omit the redundant Project picker.
    $scope.reportOptions = {project_id: projectId};
    $scope.workflow = {
      step: 'report',
      projectId: projectId,
      project: {id: projectId, title: '', is_active: null},
      summary: {filled: 0, total: 0},
      beneficiaryNames: [],
      manualSave: false,
      autoSave: false,
      formContext: (CRM.vars['org.civicrm.volunteer'] || {}).context || 'standAlone',
      isDirty: function() { return false; }
    };

    volWorkflow.loadContext(projectId).then(function(context) {
      $scope.workflow.project = context.project;
      $scope.workflow.summary = context.summary;
      $scope.workflow.beneficiaryNames = context.beneficiaryNames;
    });
  });

})(angular, CRM.$, CRM._);
