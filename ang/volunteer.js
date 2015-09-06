(function(angular, $, _) {
  // Declare a list of dependencies.
  angular
    .module('volunteer', [
      'crmUi', 'crmUtil', 'ngRoute'
    ])

    // Makes lodash/underscore available in templates
    .run(function($rootScope) {
      $rootScope._ = _;
    })

    .factory('volOppSearch', ['crmApi', '$location', '$route', function(crmApi, $location, $route) {
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
        if (searchParams) {
          userSpecifiedSearchParams = searchParams();
        } else {
          userSpecifiedSearchParams = $route.current.params;
        }

        // update the URL for bookmarkability
        $location.search(userSpecifiedSearchParams);

        angular.forEach(userSpecifiedSearchParams, function(value, key) {
          if (value) {
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
          }
        });

        // handle dates separately from other params
        var dateStartExists = userSpecifiedSearchParams.hasOwnProperty('date_start') && userSpecifiedSearchParams.date_start;
        var dateEndExists = userSpecifiedSearchParams.hasOwnProperty('date_start') && userSpecifiedSearchParams.date_end;
        if (dateStartExists && dateEndExists) {
          apiParams["api.VolunteerNeed.get"].start_time = {BETWEEN: [
            userSpecifiedSearchParams.date_start, userSpecifiedSearchParams.date_end
          ]};
        } else if (dateStartExists) {
          apiParams["api.VolunteerNeed.get"].start_time = {">": userSpecifiedSearchParams.date_start};
        } else if (dateEndExists) {
          apiParams["api.VolunteerNeed.get"].start_time = {"<": userSpecifiedSearchParams.date_end};
        }

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
        search: search
      };

    }]);

})(angular, CRM.$, CRM._);
