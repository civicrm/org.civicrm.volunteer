(function (angular, $, _) {

  angular.module('volunteer').config(function ($routeProvider) {
    $routeProvider.when('/volunteer/opportunities', {
      controller: 'VolOppsCtrl',
      // update the search params in the URL without reloading the route
      reloadOnSearch: false,
      templateUrl: '~/volunteer/VolOppsCtrl.html'
    });
  });

  angular.module('volunteer').controller('VolOppsCtrl', function ($scope, $window, crmStatus, crmUiHelp, volOppSearch) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'ang/VolOppsCtrl'}); // See: templates/ang/VolOppsCtrl.hlp

    var volOppsInCart = {};

    // on page load, search based on the URL params
    volOppSearch.search();

    $scope.searchParams = volOppSearch.userSpecifiedSearchParams;
    // set default dates
    var today = new Date();
    if (!$scope.searchParams.hasOwnProperty('date_start')) {
      $scope.searchParams.date_start =
        [today.getFullYear(), today.getMonth() + 1, today.getDate()].join('-');
    }
    if (!$scope.searchParams.hasOwnProperty('date_end')) {
      var end = new Date();
      end.setDate(today.getDate() + 30);
      $scope.searchParams.date_end =
        [end.getFullYear(), end.getMonth() + 1, end.getDate()].join('-');
    }

    $scope.volOppData = volOppSearch.getResult;

    $scope.checkout = function () {
      var path = 'civicrm/volunteer/signup';
      var query = {reset: 1};

      if (volOppsInCart.length > 0) {
        query.needs = _.keys(volOppsInCart);
        $window.location.href = CRM.url(path, query);
      } else {
        CRM.alert(
          ts('Please select at least volunteer opportunity to sign up for.'),
          ts('Submission Error'),
          'alert'
        );
      }
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
      CRM.alert(project.description, project.title, 'info', {expires: 0});
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

  });

})(angular, CRM.$, CRM._);
