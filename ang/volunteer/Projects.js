(function(angular, $, _) {
  'use strict';

  angular.module('volunteer').config(function($routeProvider) {
    $routeProvider.when('/volunteer/manage', {
      controller: 'VolunteerProjects',
      templateUrl: '~/volunteer/Projects.html',
      reloadOnSearch: false,
      resolve: {
        projectManagementAccess: function(volProjectManagementAccess) {
          return volProjectManagementAccess();
        },
        projectOverview: function(crmApi4) {
          return crmApi4('VolunteerProject', 'getManageOverview', {
            filters: {context: 'edit'}
          }).then(function(rows) {
            return rows[0] || {summary: {}, projects: [], attention: [], up_next: [], this_week: []};
          });
        }
      }
    });
  });

  angular.module('volunteer').controller('VolunteerProjects', function(
    $scope, $filter, $q, crmApi4, crmStatus, projectOverview,
    $location, volWorkflowDialog
  ) {
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    $scope.filters = {search: '', campaign_id: '', beneficiary: '', status: 'active'};
    $scope.view = ($location.search().view === 'dashboard') ? 'dashboard' : 'list';
    // ProjectsList.html renders through ng-include, and both ngIf and
    // ngInclude create child scopes. A bare primitive bound with ng-model is
    // shadowed there and the controller never sees the user's input, so all
    // two-way view state goes through this object.
    $scope.ui = {batchAction: '', batchActionRunning: false, allSelected: false};
    $scope.campaignFilter = CRM.volunteer.campaignFilter;
    // A campaign filter for a column that can never be populated, and an event
    // link into a component that is switched off, are both worse than nothing.
    $scope.isCampaignEnabled = !!CRM.volunteer.isCampaignEnabled;
    $scope.isEventEnabled = !!CRM.volunteer.isEventEnabled;
    $scope.urlPublicVolOppSearch = CRM.url('civicrm/vol/', '', 'front') + '#/volunteer/opportunities';
    $scope.hoursReportUrl = CRM.url('civicrm/volunteer/hours-report');
    $scope.canViewHoursReport = CRM.checkPerm('edit all volunteer projects')
      && CRM.checkPerm('view all contacts');

    /**
     * Label for the entity a project belongs to.
     *
     * The redesign dropped the "Associated Entity" column and its three
     * helpers, leaving no way to see or reach the event a project was created
     * from. The title is resolved server-side in one batched read; it comes
     * back null for a deleted event, in which case the ID is still worth
     * showing because the link remains useful.
     */
    $scope.associatedEntityTitle = function(project) {
      var entity = project && project.associated_entity;
      if (!entity) {
        return null;
      }
      return entity.title || ts('Event #%1', {1: entity.entity_id});
    };

    $scope.associatedEntityUrl = function(project) {
      var entity = project && project.associated_entity;
      if (!entity || !$scope.isEventEnabled) {
        return null;
      }
      return CRM.url('civicrm/event/manage/settings', 'reset=1&action=update&id=' + entity.entity_id);
    };
    $scope.canAccessAllProjects = CRM.checkPerm('edit all volunteer projects') || CRM.checkPerm('delete all volunteer projects');

    function applyOverview(overview) {
      $scope.summary = overview.summary || {};
      $scope.projects = overview.projects || [];
      $scope.attention = overview.attention || [];
      $scope.upNext = overview.up_next || [];
      $scope.thisWeek = overview.this_week || [];
      // beneficiary_options is keyed by contact ID by the API. Zipping the
      // 'beneficiaries' ID list against the 'beneficiary_names' display list
      // by index mislabels every entry after one whose name did not resolve.
      $scope.beneficiaries = {};
      angular.forEach($scope.projects, function(project) {
        angular.forEach(project.beneficiary_options || {}, function(displayName, contactId) {
          $scope.beneficiaries[contactId] = displayName;
        });
      });
      $scope.ui.batchAction = '';
      $scope.ui.allSelected = false;
      return overview;
    }
    applyOverview(projectOverview);

    function refreshProjects() {
      return crmApi4('VolunteerProject', 'getManageOverview', {
        filters: {context: 'edit'}
      }).then(function(rows) {
        return applyOverview(rows[0] || {summary: {}, projects: [], attention: [], up_next: [], this_week: []});
      });
    }

    $scope.setView = function(view) {
      $scope.view = view === 'dashboard' ? 'dashboard' : 'list';
      $location.search('view', $scope.view === 'dashboard' ? 'dashboard' : null);
    };

    $scope.resetFilters = function() {
      $scope.filters = {search: '', campaign_id: '', beneficiary: '', status: 'active'};
    };

    $scope.visibleProjects = function() {
      var search = String($scope.filters.search || '').toLowerCase();
      var campaignId = String($scope.filters.campaign_id || '');
      var beneficiaryId = String($scope.filters.beneficiary || '');
      return _.filter($scope.projects, function(project) {
        var active = project.is_active == 1;
        if (($scope.filters.status === 'active' && !active) || ($scope.filters.status === 'archived' && active)) {
          return false;
        }
        if (campaignId && String(project.campaign_id || '') !== campaignId) {
          return false;
        }
        if (beneficiaryId && !_.some(project.beneficiaries || [], function(id) { return String(id) === beneficiaryId; })) {
          return false;
        }
        if (search) {
          var haystack = [project.title, project.campaign_label, $scope.associatedEntityTitle(project)]
            .concat(project.beneficiary_names || [], project.upcoming_roles || [])
            .join(' ').toLowerCase();
          if (haystack.indexOf(search) === -1) {
            return false;
          }
        }
        return true;
      });
    };

    $scope.selectedProjectCount = function() {
      return _.where($scope.projects, {selected: true}).length;
    };

    $scope.watchSelected = function() {
      var visible = $scope.visibleProjects();
      $scope.ui.allSelected = visible.length > 0 && _.every(visible, function(project) { return !!project.selected; });
    };

    $scope.selectAll = function() {
      angular.forEach($scope.visibleProjects(), function(project) { project.selected = $scope.ui.allSelected; });
    };

    $scope.$watch('filters', function() {
      $scope.ui.allSelected = false;
      angular.forEach($scope.projects, function(project) { project.selected = false; });
    }, true);

    function selectedProjects() {
      return _.where($scope.projects, {selected: true});
    }

    function setProjectActive(project, isActive) {
      return crmApi4('VolunteerProject', 'update', {
        where: [['id', '=', project.id]], values: {is_active: isActive}
      });
    }

    function deleteProject(project) {
      return crmApi4('VolunteerProject', 'delete', {where: [['id', '=', project.id]]});
    }

    function runBatchRequests(requests, messages) {
      $scope.ui.batchActionRunning = true;
      var operation = $q.all(requests).then(refreshProjects, function(error) {
        return refreshProjects().then(function() { return $q.reject(error); });
      });
      return crmStatus(messages, operation).finally(function() { $scope.ui.batchActionRunning = false; });
    }

    $scope.batchActions = {
      activate: {
        label: ts('Activate'), confirm: ts('Activate the selected projects?'),
        run: function(project) { return setProjectActive(project, true); },
        messages: {start: ts('Activating projects...'), success: ts('Projects activated')}
      },
      archive: {
        label: ts('Archive'), confirm: ts('Archive the selected projects?'),
        run: function(project) { return setProjectActive(project, false); },
        messages: {start: ts('Archiving projects...'), success: ts('Projects archived')}
      },
      delete: {
        label: ts('Delete'), confirm: ts('Delete the selected projects?'), run: deleteProject,
        messages: {start: ts('Deleting projects...'), success: ts('Projects deleted')}
      }
    };

    $scope.runBatch = function() {
      if ($scope.ui.batchActionRunning || !$scope.ui.batchAction || !$scope.selectedProjectCount()) {
        return;
      }
      var action = $scope.batchActions[$scope.ui.batchAction];
      CRM.confirm({message: action.confirm}).on('crmConfirm:yes', function() {
        $scope.$applyAsync(function() {
          runBatchRequests(_.map(selectedProjects(), action.run), action.messages);
        });
      });
    };

    $scope.projectById = function(projectId) {
      return _.find($scope.projects, function(project) { return parseInt(project.id, 10) === parseInt(projectId, 10); });
    };

    $scope.openProjectDialog = function(step, project, scopeNeedId) {
      if (!project) {
        return $q.reject(new Error('Unknown project'));
      }
      // Filling spots or logging hours changes the very numbers this screen is
      // built from -- the metric cards, the attention queue, Up next and the
      // Staffing column all go stale otherwise. crmUiDialog rejects on cancel,
      // so refresh either way.
      return volWorkflowDialog.open(step, project, {scopeNeedId: scopeNeedId})
        .then(refreshProjects, function(reason) {
          return refreshProjects().then(function() { return $q.reject(reason); });
        });
    };

    $scope.openAttention = function(item) {
      return $scope.openProjectDialog(
        item.action_type === 'hours' ? 'hours' : 'assign',
        $scope.projectById(item.project_id),
        item.need_id
      );
    };

    $scope.publicSignupUrl = function(project) {
      return $scope.urlPublicVolOppSearch + '?project=' + encodeURIComponent(project.id) + '&hideSearch=1';
    };

    function localDate(value) {
      if (!value) {
        return null;
      }
      var match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      return match ? new Date(+match[1], +match[2] - 1, +match[3], +match[4], +match[5]) : new Date(value);
    }

    $scope.shortDateTime = function(value) {
      var date = localDate(value);
      return date ? $filter('date')(date, 'EEE MMM d, h:mm a') : ts('No upcoming shift');
    };
    $scope.weekDay = function(value) {
      var date = localDate(value);
      return date ? $filter('date')(date, 'EEE d') : '';
    };
    $scope.shortTime = function(value) {
      var date = localDate(value);
      return date ? $filter('date')(date, 'h:mm a') : '';
    };
  });

})(angular, CRM.$, CRM._);
