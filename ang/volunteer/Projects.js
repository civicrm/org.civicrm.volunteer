(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer', {
        controller: 'VolunteerProjects',
        templateUrl: '~/volunteer/Projects.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          projects: function(crmApi) {
            return crmApi('VolunteerProject', 'get', {});
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('volunteer').controller('VolunteerProjects', function($scope, crmApi, crmStatus, crmUiHelp, projects) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/volunteer/Projects'}); // See: templates/CRM/volunteer/Projects.hlp

    // We have myContact available in JS. We also want to reference it in HTML.

    $scope.projects = projects.values;

  /*
  $scope.save = function save() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Contact', 'create', {
          id: myContact.id,
          first_name: myContact.first_name,
          last_name: myContact.last_name
        })
      );
    };
    */
  });

})(angular, CRM.$, CRM._);
