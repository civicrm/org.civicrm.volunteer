(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/log', {
        controller: 'VolunteerLogHours',
        resolve: {
          supportingData: function(crmApi) {
            return crmApi('VolunteerUtil', 'getsupportingdata', {
              controller: 'VolunteerLogHours'
            }).then(function(success) {
              return success.values;
            });
          }
        },
        templateUrl: '~/volunteer/LogHours.html'
      });
    }
  );

  angular.module('volunteer').controller('VolunteerLogHours', function($scope, $window, $location, $route, crmApi, crmUiAlert, crmUiHelp, supportingData) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/Volunteer/Form/Volunteer'}); // See: templates/CRM/volunteer/Project.hlp

    // Having two collections for the same model is somewhat dirty, but there's no
    // (worthwhile) way to filter the list and preserve two-way binding in the view.
    var newTimeEntries = [];
    var existingTimeEntries = [];
    $scope.newTimeEntries = newTimeEntries;
    $scope.existingTimeEntries = existingTimeEntries;

    var projects = {};

    // Hardcoded for now. Located in the controller for future extensibility
    // (e.g., set via retrieved setting).
    $scope.locBlockHeading = ts('Location:');
    $scope.projects = projects;
    $scope.supportingData = supportingData;
    $scope.wizardSelections = {};
    $scope.submitted = false; // TODO: can probably use a framework provided variable instead.

    $scope.addNewTimeEntry = function() {
      if ($scope.wizardSelections.projectId) {
        var flexibleNeedId = $scope.projects[$scope.wizardSelections.projectId]['api.VolunteerNeed.getvalue'];
        $scope.newTimeEntries.push({
          assignee_contact_id: CRM.vars['org.civicrm.volunteer'].currentContactId,
          volunteer_need_id: flexibleNeedId
        });
      }
    };

    $scope.goHome = function() {
      $window.location.href = '/';
    };

    $scope.goTo = function(url) {
      $location.path(url);
    }

    $scope.saveTimeEntries = function() {
      $scope.submitted = true;
      CRM.$('div[ng-form=enterDetailsForm]').block();
      var requests = _.map($scope.newTimeEntries.concat($scope.existingTimeEntries), function(paramsObj) {
        paramsObj.status_id = _.invert(supportingData.volunteer_status)['Completed'];
        return ['VolunteerAssignment', 'create', paramsObj];
      });
      crmApi(requests).then(function(success){
        CRM.$('div[ng-form=enterDetailsForm]').unblock();
      }, function(fail){
        // Do something with the failure... I suspect cases where the Internet
        // connection fails land here... not sure what else. Since we are making
        // multiple requests through one API connection, the result is a little
        // different, and we should expect to handle application failures (e.g.,
        // "required field X missing") in the success handler.
      });
    };

    $scope.selectProject = function(id) {
      $scope.wizardSelections.projectId = id;
    };

    $scope.startOver = function() {
      $route.reload();
    }

    // Refresh the project list when the beneficiary is changed.
    $scope.$watch('wizardSelections.beneficiaryId', function (newValue, oldValue, scope) {
      if (newValue) {
        crmApi('VolunteerProject', 'get', {
          project_contacts: {volunteer_beneficiary: newValue},
          'api.VolunteerProject.getlocblockdata': {
            id: '$value.loc_block_id',
            options: {limit: 0},
            return: 'all',
            sequential: 1
          },
          'api.VolunteerNeed.getvalue': {
            is_active: 1,
            is_flexible: 1,
            return: 'id'
          }
        }).then(function(success) {
          // format the location data for easier use
          var values = success.values;
          _.each(values, function(value, key) {
            var loc_block = value['api.VolunteerProject.getlocblockdata']['count'] ? value['api.VolunteerProject.getlocblockdata']['values'][0] : {};
            values[key]['loc_block'] = loc_block;
            delete values[key]['api.VolunteerProject.getlocblockdata'];
          });
          $scope.projects = values;
        }, function (fail){
          // do something with the failure, eh?
        });
      }
    });

    // Refresh the list of time entries when the project is changed.
    $scope.$watch('wizardSelections.projectId', function (newValue, oldValue, scope) {
      // reset the list of time entries
      $scope.existingTimeEntries = [];
      // always start with an empty row
      $scope.newTimeEntries = [];
      $scope.addNewTimeEntry();

      if (newValue) {
        // TODO: This has the potential to be pretty inefficient. We're pulling
        // a list of all activities assigned to the contact and then filtering
        // them via the chained API. It would be better to modify
        // api.VolunteerAssignment.get to take parameters related to table
        // civicrm_activity_contact so that we can filter on assignee.
        crmApi('ActivityContact', 'get', {
          contact_id: "user_contact_id",
          record_type_id: "Activity Assignees",
          sequential: 1,
          "api.VolunteerAssignment.get": {
            id: "$value.activity_id",
            project_id: newValue,
            sequential: 1
          }
        }).then(function(success) {
          _.each(success.values, function(value, key) {
            if (value['api.VolunteerAssignment.get'].count) {
              // Account for inconsistent property names: role_id in get; volunteer_role_id in create.
              _.each(value['api.VolunteerAssignment.get'].values, function(v, k) {
                v.volunteer_role_id = v.role_id;
              });
              Array.prototype.push.apply($scope.existingTimeEntries, value['api.VolunteerAssignment.get'].values);
            }
          });
        }, function (fail){
          // do something with the failure, eh?
        });
      }
    });

  });

})(angular, CRM.$, CRM._);
