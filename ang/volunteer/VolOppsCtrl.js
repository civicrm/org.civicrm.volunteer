(function (angular, $, _) {

  angular.module('volunteer').config(function ($routeProvider) {
    $routeProvider.when('/volunteer/opportunities', {
      controller: 'VolOppsCtrl',
      // update the search params in the URL without reloading the route
      reloadOnSearch: false,
      templateUrl: '~/volunteer/VolOppsCtrl.html',
      resolve: {
        // TODO: this code is reusable; where should it live?
        countries: function(crmApi) {
          var settings = crmApi('Setting', 'get', {
            "return": ["countryLimit", "defaultContactCountry"],
            sequential: 1
          });

          return settings.then(function (settingsData) {
            var countries = crmApi('Country', 'get', {
              id: {IN: settingsData.values[0].countryLimit}
            });

            return countries.then(function (countriesData) {
              angular.forEach(countriesData.values, function(c) {
                // since we are wrapping CiviCRM's API, and it provides even boolean data as quoted strings, we'll do the same
                countriesData.values[c.id].is_default = (c.id === settingsData.values[0].defaultContactCountry) ? "1" : "0";
              });
              return countriesData.values;
            });
          });
        }
      }
    });
  });

  angular.module('volunteer').controller('VolOppsCtrl', function ($route, $scope, $window, crmStatus, crmUiHelp, volOppSearch, countries) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'ang/VolOppsCtrl'}); // See: templates/ang/VolOppsCtrl.hlp

    var volOppsInCart = {};

    // on page load, search based on the URL params
    volOppSearch.search();

    $scope.countries = countries;
    $scope.searchParams = volOppSearch.getParams;
    $scope.volOppData = volOppSearch.getResult;

    $scope.checkout = function () {
      if ($route.current.params.hasOwnProperty('dest') && $route.current.params.dest) {
        var dest = $route.current.params.dest;
      } else {
        var dest = 'list';
      }

      var path = 'civicrm/volunteer/signup';
      var query = {
        reset: 1,
        needs: _.keys(volOppsInCart),
        dest: dest
      };

      $window.location.href = CRM.url(path, query);
    };

    /**
     * Returns true if a proximity search has been started; else false.
     *
     * @returns {Boolean}
     */
    $scope.isProximitySearch = function () {
      var result = false;
      $('.crm-vol-proximity input').each(function() {
        var val = $(this).val();
        result = (val != '' && val != '?' );

        // a single populated field is enough to make it a proximity search,
        // so we can break the loop
        if (result) {
          return false;
        }
      });
      return result;
    };

    $scope.search = function () {
      return crmStatus(
        {start: ts('Searching...'), success: ts('Search complete')},
        volOppSearch.search($scope.searchParams)
      );
    };

    $scope.shoppingCart = function () {
      return volOppsInCart;
    };

    $scope.showProjectDescription = function (project) {
      var description = project.description;
      var addressBlock = '';
      var campaignBlock = '';

      if (project.hasOwnProperty('campaign_title') && !_.isEmpty(project.campaign_title)) {
        campaignBlock = '<p><strong>' + ts('Campaign:') + '</strong><br />' + project.campaign_title + '</p>';
      }

      if (project.hasOwnProperty('location')) {
        if (!_.isEmpty(project.location.streetAddress)) {
          addressBlock += project.location.streetAddress + '<br />';
        }
        if (!_.isEmpty(project.location.city)) {
          addressBlock += project.location.city + '<br />';
        }
        if (!_.isEmpty(project.location.postalCode)) {
          addressBlock += project.location.postalCode;
        }
      }
      if (!_.isEmpty(addressBlock)) {
        addressBlock = '<p><strong>Location:</strong><br />' + addressBlock + '</p>';
      }
      CRM.alert(description + campaignBlock + addressBlock, project.title, 'info', {expires: 0});
    };

    $scope.showRoleDescription = function (need) {
      CRM.alert(need.role_description, need.role_label, 'info', {expires: 0});
    };

    $scope.toggleSelection = function (need) {
      need.inCart = !need.hasOwnProperty('inCart') ? true : !need.inCart;

      // if the need was just added to the cart...
      if (need.inCart) {
        volOppsInCart[need.id] = need;
      } else {
        delete volOppsInCart[need.id];
      }
    };

    $scope.proximityUnits = [
      {value: 'km', label: ts('km')},
      {value: 'miles', label: ts('miles')}
    ];

  });

})(angular, CRM.$, CRM._);
