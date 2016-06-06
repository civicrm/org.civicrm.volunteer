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
        // This looks strange but is meant to allow us to drop in a setting
        // to allow an admin to manage cart-related settings. The stub
        // function below will likely be replaced with an API call.
        settings: function (crmApi) {
          return {
            volunteer_floating_cart_enabled: true,
            volunteer_show_cart_contents: false
          };
        },
        supporting_data: function(crmApi) {
          return crmApi('VolunteerUtil', 'getsupportingdata', {
            controller: 'VolOppsCtrl'
          });
        }
      }
    });
  });

  angular.module('volunteer').controller('VolOppsCtrl', function ($route, $scope, $window, $timeout, crmStatus, crmUiHelp, volOppSearch, countries, settings, supporting_data) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'ang/VolOppsCtrl'}); // See: templates/ang/VolOppsCtrl.hlp

    var volOppsInCart = {};
    $scope.shoppingCart = volOppsInCart;
    $scope.showCartContents = settings.volunteer_show_cart_contents;

    // on page load, search based on the URL params
    volOppSearch.search();

    //VOL-190: Allow hiding of search pane based on url param
    $scope.hideSearch = false;
    $scope.allowShowSearch = false;
    if ($route.current.params.hasOwnProperty('hideSearch')) {
      if ($route.current.params.hideSearch === "always") {
        $scope.hideSearch = true;
        $scope.allowShowSearch = false;
      }
      if ($route.current.params.hideSearch === "1") {
        $scope.hideSearch = true;
        $scope.allowShowSearch = true;
      }
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
     * Provides help text for the "shopping cart."
     *
     * @returns {String}
     */
    $scope.helpText = function () {
      var help = '';

      if (!$scope.hideSearch) {
        help += ts("Use this search form to find the volunteer opportunity near you that best matches with your personal skill set, interests, and time availability.");
      }

      help += ' ' + ts('Click the checkbox for each volunteer opportunity you wish to add to your schedule, then click the "Sign Up!" button to supply your contact information and complete your registration.');

      return help;
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
      var delay = 500;
      var animSrc = (need.inCart) ? ".crm-vol-opp-need-" + need.id : ".crm-vol-opp-cart .ui-widget-content";
      var animTarget = (need.inCart) ? ".crm-vol-opp-cart .ui-widget-content" : ".crm-vol-opp-need-" + need.id;

      if ($scope.showCartContents) {
        animSrc = (need.inCart) ? ".crm-vol-opp-need-" + need.id : ".crm-vol-opp-cart-need-" + need.id;
        animTarget = (need.inCart) ? ".crm-vol-opp-cart-list tr:last" : ".crm-vol-opp-need-" + need.id;
      }
      $(animSrc).effect( "transfer", { className: 'crm-vol-opp-cart-transfer', to: $( animTarget ) }, delay);

      $timeout(function() {
      if (need.inCart) {
        volOppsInCart[need.id] = need;
      } else {
        delete volOppsInCart[need.id];
      }
      }, delay);
    };

    $scope.toggleCartList = function () {
      $scope.showCartContents = !$scope.showCartContents;
    };

    $scope.$watch('shoppingCart', function(oldValue, newValue) {
      $scope.itemCountInCart = _.size($scope.shoppingCart);
    }, true);

    $scope.proximityUnits = [
      {value: 'km', label: ts('km')},
      {value: 'miles', label: ts('miles')}
    ];

    // Logic for managing Cart Floating - TODO: refactor as a directive
    $scope.cartIsFloating = false;

    if (settings.volunteer_floating_cart_enabled) {
      var cartDelay = 200;

      var cartTop = $("div.crm-vol-opp-cart").offset().top;
      $(window).on("scroll", function (e) {
        var cartShouldFloat = ($(window).scrollTop() > cartTop);
        if ($scope.cartIsFloating !== cartShouldFloat) {
          $(".crm-vol-opp-cart").fadeOut(cartDelay);
          $timeout(function () {
            $scope.cartIsFloating = cartShouldFloat;
            $(".crm-vol-opp-cart").fadeIn(cartDelay);
          }, cartDelay);
        }
      });
    }
  });

})(angular, CRM.$, CRM._);
