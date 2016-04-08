(function (angular, $, _) {

  angular.module('volunteer').config(function ($routeProvider) {
    $routeProvider.when('/volunteer/opportunities', {
      controller: 'VolOppsCtrl',
      // update the search params in the URL without reloading the route
      reloadOnSearch: false,
      templateUrl: '~/volunteer/VolOppsCtrl.html',
      resolve: {
        countries: function(crmApi) {
          return crmApi('VolunteerUtil', 'getcountries', {}).then(function(result) {
            return result.values;
          });
        },
        supporting_data: function(crmApi) {
          return crmApi('VolunteerUtil', 'getsupportingdata', {
            controller: 'VolOppsCtrl'
          });
        }
      }
    });
  });

  angular.module('volunteer').controller('VolOppsCtrl', function ($route, $scope, $window, crmStatus, crmUiHelp, volOppSearch, countries, supporting_data) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'ang/VolOppsCtrl'}); // See: templates/ang/VolOppsCtrl.hlp

    var volOppsInCart = {};

    // on page load, search based on the URL params
    volOppSearch.search();

    //VOL-190: Allow hiding of search pane based on url param
    if ($route.current.params.hasOwnProperty('hideSearch')) {
      if ($route.current.params.hideSearch === "always") {
        $scope.hideSearch = true;
        $scope.allowShowSearch = false;
      } else if (
        $route.current.params.hideSearch === "false" ||
        $route.current.params.hideSearch === "0" ||
        $route.current.params.hideSearch === "" ||
        $route.current.params.hideSearch === null
      ) {
        $scope.hideSearch = false;
        $scope.allowShowSearch = false;
      } else {
        $scope.hideSearch = true;
        $scope.allowShowSearch = true;
      }
    } else {
      $scope.hideSearch = false;
      $scope.allowShowSearch = false;
    }


    $scope.countries = countries;
    $scope.roles = supporting_data.values.roles;
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

    $scope.showSearch = function() {
      $scope.hideSearch = false;
      $scope.allowShowSearch = false;
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
        if (!_.isEmpty(project.location.street_address)) {
          addressBlock += project.location.street_address + '<br />';
        }
        if (!_.isEmpty(project.location.city)) {
          addressBlock += project.location.city + '<br />';
        }
        if (!_.isEmpty(project.location.postal_code)) {
          addressBlock += project.location.postal_code;
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
