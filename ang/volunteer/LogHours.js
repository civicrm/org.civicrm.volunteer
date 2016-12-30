(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/log', {
        controller: 'VolunteerLogHours',
        templateUrl: '~/volunteer/LogHours.html'
      });
    }
  );

  angular.module('volunteer').controller('VolunteerLogHours', function($scope, $location, $route, crmApi, crmUiAlert, crmUiHelp) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/Volunteer/Form/Volunteer'}); // See: templates/CRM/volunteer/Project.hlp

    var projects = {};

    // Hardcoded for now. Located in the controller for future extensibility
    // (e.g., set via retrieved setting).
    $scope.locBlockHeading = ts('Location:');
    $scope.projects = projects;
    $scope.wizardSelections = {};

    $scope.selectProject = function(id) {
      $scope.wizardSelections.projectId = id;
    };

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

  });

})(angular, CRM.$, CRM._);
