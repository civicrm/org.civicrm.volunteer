(function (angular, $, _) {

  angular.module('volunteer').config(function ($routeProvider) {
    $routeProvider.when('/volunteer/opportunities', {
      controller: 'VolOppsCtrl',
      // update the search params in the URL without reloading the route
      reloadOnSearch: false,
      templateUrl: '~/volunteer/VolOppsCtrl.html'
    });
  });

  angular.module('volunteer').factory('volOppSearch', ['crmApi', '$location', '$route', function(crmApi, $location, $route) {
    // search result is stored here
    var result = {
      projects: {},
      needs: {}
    };

    var getResult = function() {
      return result;
    };

    var clearResult = function() {
      result = {
        projects: {},
        needs: {}
      };
    };

    var userSpecifiedSearchParams = {};

    var getUserSpecifiedSearchParams = function() {
      return userSpecifiedSearchParams;
    };

    var getDefaultSearchParams = function() {
      return {
        is_active: 1,
        sequential: 0,
        options: {limit: 0},
        "api.VolunteerNeed.get": {
          is_active: 1,
          options: {limit: 0},
          sequential: 0,
          visibility_id: "public"
        }
      };
    };

    var search = function(searchParams) {
      var apiParams = getDefaultSearchParams();
      clearResult();

      // if no params are passed, get the data out of the URL
      userSpecifiedSearchParams = searchParams || $route.current.params;

      // update the URL for bookmarkability
      $location.search(userSpecifiedSearchParams);

      angular.forEach(userSpecifiedSearchParams, function(value, key) {
        switch(key) {
          case "beneficiary":
            if (!apiParams.hasOwnProperty('project_contacts')) {
              apiParams.project_contacts = {};
            }
            apiParams.project_contacts.volunteer_beneficiary = value.split(',');
            break;
          case "role":
            apiParams["api.VolunteerNeed.get"].role_id = {IN: value.split(',')};
            break;
        }
      });

      var api = crmApi('VolunteerProject', 'get', apiParams);
      return api.then(function(data) {

        angular.forEach(data.values, function(p) {
          if (p['api.VolunteerNeed.get'].count > 0) {
            result.projects[p.id] = p;
          }
        });

        angular.forEach(result.projects, function(project, key) {
          angular.forEach(project["api.VolunteerNeed.get"].values, function(need) {
            result.needs[need.id] = need;
          });
          delete result.projects[key]["api.VolunteerNeed.get"];
        });

        return getResult();
      });
    };

    return {
      getResult: getResult,
      getParams: getUserSpecifiedSearchParams,
      search: search,
      userSpecifiedSearchParams: userSpecifiedSearchParams
    };

  }]);

  angular.module('volunteer').controller('VolOppsCtrl', function ($scope, crmStatus, crmUiHelp, volOppSearch) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'ang/VolOppsCtrl'}); // See: templates/ang/VolOppsCtrl.hlp

    // on page load, search based on the URL params
    volOppSearch.search();

    $scope.volOppData = volOppSearch.getResult;
    $scope.searchParams = volOppSearch.userSpecifiedSearchParams;

    $scope.search = function () {
      return crmStatus(
        {start: ts('Searching...'), success: ts('Search complete')},
        volOppSearch.search($scope.searchParams)
      );
    };

    $scope.showProjectDescription = function (project) {
      CRM.alert(project.description, project.title, 'info', {expires: 0});
    };

    $scope.showRoleDescription = function (need) {
      CRM.alert(need.role_description, need.role_label, 'info', {expires: 0});
    };

  });

})(angular, CRM.$, CRM._);
