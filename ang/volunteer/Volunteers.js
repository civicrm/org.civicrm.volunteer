(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/assign/:projectId', {
        controller: 'VolunteerVolunteers',
        templateUrl: '~/volunteer/Volunteers.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          volunteers: function(crmApi, $route) {
            return crmApi('VolunteerAssignment', 'get', {
              //Todo: This needs to be hooked up when the schema makes sense
              //project_id: $route.current.params.projectId,
              return: ['contact_id', 'display_name', 'email', 'phone_number']
            });
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('volunteer').controller('VolunteerVolunteers', function($scope, crmApi, crmStatus, crmUiHelp, volunteers) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/volunteer/Volunteers'}); // See: templates/CRM/volunteer/Volunteers.hlp

    // We have myContact available in JS. We also want to reference it in HTML.
    $scope.volunteers = volunteers.values;
    $scope.contact_url = CRM.url("civicrm/contact/view");

    $scope.actionsMenu = function() {
      alert("Action Menu Away!");
    };

    $scope.save = function save() {
      return crmStatus(ts('Saved, todo: actually save'));
    };
  });

})(angular, CRM.$, CRM._);
