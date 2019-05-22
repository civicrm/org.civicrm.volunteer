(function (angular, $, _) {

  angular.module('volunteer').config(function ($routeProvider) {
    $routeProvider.when('/volunteer/appeals', {
      controller: 'VolApplsCtrl',
      // update the search params in the URL without reloading the route
     // reloadOnSearch: false,
      templateUrl: '~/volunteer/VolApplsCtrl.html',
      resolve: {
       
            beneficiaries: function (crmApi) {
             
              return ["Ms. abc pqr"]
            },
            projectAppealsData: function(crmApi) {
           
            return ([
            {id:1,appeal_description:"appeal desription",title:"Appeal for test project",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
            {id:2,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
             {id:3,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
              {id:4,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
               {id:5,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
                {id:6,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
                 {id:7,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
                  {id:8,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
                   {id:9,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"},
                    {id:10,appeal_description:"aa",title:"test2",location:"locaction 1 Pune, 411123",beneficiaries:"Ms. abc pqr"}

            ])
          },
          projectData: function(crmApi) {            
            return [];
          }

      }
    });
  });

  angular.module('volunteer').controller('VolApplsCtrl', function ($route, $scope,beneficiaries,projectAppealsData,projectData) {
    // The ts() and hs() functions help load strings for this module.
    /********************************************************/
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var appeals=$scope.appeals = projectAppealsData;
      $scope.beneficiaries = beneficiaries;
      $scope.projects= projectData;     
      $scope.currentTemplate = "~/volunteer/AppealList.html";
        console.log("-",$scope.currentTemplate);
      $scope.changeview = function(tpl){
        $scope.currentTemplate = tpl;        
      }

    $scope.maxSize = 5;     // Limit number for pagination display number.  
    $scope.totalCount = 0;  // Total number of items in all pages. initialize as a zero  
    $scope.pageIndex = 1;   // Current page number. First page is 1.-->  
    $scope.pageSizeSelected = 4; // Maximum number of items per page.  

    $scope.getAppeals = function () {  
        // $http.get("http://localhost:52859/api/Employee?pageIndex=" + $scope.pageIndex + "&pageSize=" + $scope.pageSizeSelected).then(  
        //                function (response) {  
        //                    $scope.employees = response.data.employees;  
        //                    $scope.totalCount = response.data.totalCount;  
        //                },  
        //                function (err) {  
        //                    var error = err;  
        //                });
        $scope.appeals= projectAppealsData; 
        $scope.totalCount = 10;
    }  
     //Loading employees list on first time  
    $scope.getAppeals();  
    //This method is calling from pagination number  
    $scope.pageChanged = function () {  
        $scope.getAppeals();  
    };  
    //This method is calling from dropDown  
    $scope.changePageSize = function () {  
        $scope.pageIndex = 1;  
        $scope.getAppeals();  
    }
  /**********************************/
  /*
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'ang/VolApplsCtrl'}); // See: templates/ang/VolOppsCtrl.hlp

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
    $scope.searchParams = volOppSearch.params;
    $scope.volOppData = volOppSearch.results;

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

   
    $scope.helpText = function () {
      var help = '';

      if (!$scope.hideSearch) {
        help += ts("Use this search form to find the volunteer opportunity near you that best matches with your personal skill set, interests, and time availability.");
      }

      help += ' ' + ts('Click the checkbox for each volunteer opportunity you wish to add to your schedule, then click the "Sign Up!" button to supply your contact information and complete your registration.');

      return help;
    };

   
    var checkIsProximitySearch = function() {
      //Reduce the entire set of proximity params down to a single boolean.
      return _.reduce($scope.searchParams.proximity, function(previous, value, key) {
        //ignore "unit" by returning the previous value
        if ( key === "unit") {
          return previous;
        } else {
          return (previous || !!value);
        }
        //Initial Value
      }, false);
    };

    //Make Proximity Search status available to scope
    $scope.isProximitySearch = checkIsProximitySearch();

    //Watch the proximity search params only and update the
    //isProximitySearch flag when the params change.
    //Using a watcher here rather than passing a function
    //to scope keeps the number of iterations to a minimum
    $scope.$watch('searchParams.proximity', function(){
      $scope.isProximitySearch = checkIsProximitySearch();
    }, true);

    $scope.showSearch = function() {
      $scope.hideSearch = false;
      $scope.allowShowSearch = false;
    };

    $scope.search = function () {
      if (!$scope.isProximitySearch) {
        //Set the proximity object to empty because if we
        //pass an object with empty keys to search() the backend
        //returns an error that distance, units and postal code
        //are all required, even though the user is not attempting
        //to do a proximity search.
        delete $scope.searchParams.proximity;
      }
      return crmStatus(
        {start: ts('Searching...'), success: ts('Search complete')},
        volOppSearch.search()
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

   */

  });

})(angular, CRM.$, CRM._);
