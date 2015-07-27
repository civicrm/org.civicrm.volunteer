(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/needs/:projectId', {
        controller: 'VolunteerNeeds',
        templateUrl: '~/volunteer/Needs.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          needs: function(crmApi, $route) {
            return crmApi('VolunteerNeed', 'get', {
              project_id: $route.current.params.projectId
            });
          },
          roles: function(crmApi) {
            return crmApi('OptionGroup', 'get', {
              name: 'volunteer_role',
              "sequential": 1,
              "api.OptionValue.get": {is_active: 1, 'return': ['label', 'value']}
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
  angular.module('volunteer').controller('VolunteerNeeds', function($scope, crmApi, crmStatus, crmUiHelp, needs, roles) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/volunteer/Needs'}); // See: templates/CRM/volunteer/Needs.hlp

    // We have myContact available in JS. We also want to reference it in HTML.
    $scope.needs = needs.values;
    $scope.roles = roles.values[0]['api.OptionValue.get'].values;
    //todo: Connect this to data
    $scope.allowSpecification = true;

    $scope.save = function save() {
      return crmStatus({start: ts('Saving...'), success: ts('Saved: todo')});
    };
  });

})(angular, CRM.$, CRM._);
