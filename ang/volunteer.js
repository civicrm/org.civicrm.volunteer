(function(angular, $, _) {
  // Declare a list of dependencies.
  angular
    .module('volunteer', CRM.angRequires('volunteer'))

    // Makes lodash/underscore available in templates
    .run(function($rootScope) {
      $rootScope._ = _;
    })

    // The public opportunity browser and project management UI share this
    // Angular module, but CiviCRM selects separate frontend/backend themes from
    // the server-side host route. Redirect historical public-host management
    // URLs so administrative screens use the configured backend theme.
    .run(function($rootScope, $location, $window) {
      $rootScope.$on('$routeChangeStart', function(event) {
        if (!(CRM.config && CRM.config.isFrontend)
          || !/^\/volunteer\/manage(?:\/|$)/.test($location.path())) {
          return;
        }

        event.preventDefault();
        var backendHost = CRM.url('civicrm/volunteer/manage', null, 'back');
        $window.location.replace(backendHost + '#' + $location.url());
      });
    })

    // Show/hide "loading" spinner between routes
    .run(function($rootScope) {
      $rootScope.$on('$routeChangeStart', function() {
        CRM.$('#crm-main-content-wrapper').block();
      });

      $rootScope.$on('$routeChangeSuccess', function() {
        CRM.$('#crm-main-content-wrapper').unblock();
      });

      $rootScope.$on('$routeChangeError', function() {
        CRM.$('#crm-main-content-wrapper').unblock();
      });

      // the first route that is loaded fires a $routeChangeSuccess event on
      // completing load, but it doesn't raise $routeChangeStart when it starts,
      // so we will just start the app with the spinner going
      CRM.$('#crm-main-content-wrapper').block();
    })

    // Administrative routes share this Angular module with the public
    // opportunity browser. Server-side callbacks and APIs remain authoritative;
    // this guard gives a clear client-side denial when someone changes only the
    // public page's hash to an administrative route.
    .factory('volProjectManagementAccess', function($q) {
      return function() {
        var allowed = CRM.checkPerm('create volunteer projects')
          || CRM.checkPerm('edit own volunteer projects')
          || CRM.checkPerm('edit all volunteer projects');
        if (allowed) {
          return true;
        }

        var message = CRM.ts('org.civicrm.volunteer')('You do not have permission to manage volunteer projects.');
        CRM.alert(message, CRM.ts('org.civicrm.volunteer')('Access denied'), 'error');
        return $q.reject({is_error: 1, error_message: message});
      };
    })

    // The Search Kit hours report exposes contact-level data across all
    // projects. Keep its workflow tab aligned with the report page and API4
    // entity instead of treating ordinary project-edit access as sufficient.
    .factory('volHoursReportAccess', function($q) {
      return function() {
        var allowed = CRM.checkPerm('edit all volunteer projects')
          && CRM.checkPerm('view all contacts');
        if (allowed) {
          return true;
        }

        var message = CRM.ts('org.civicrm.volunteer')('You do not have permission to view the volunteer hours report.');
        CRM.alert(message, CRM.ts('org.civicrm.volunteer')('Access denied'), 'error');
        return $q.reject({is_error: 1, error_message: message});
      };
    })

    .factory('volOppSearch', ['crmApi4', '$location', '$route', function(crmApi4, $location, $route) {
      //Search params and results are stored here and assigned by reference to the form
      var volOppSearch = {};
      var result = {};

      /**
       * This translates the url params with nested key names
       * into a complex object format that Angular can assign to form objects
       * VOL-240
       *
       * @param params
       * @returns complex object
       */
      var parseQueryParams = function(params) {
        var returnParams = {};
        _.each(params, function(value, name) {
          //Get the base name. will return whole key if no mathing bracket is found.
          var basename = name.replace(/([^\[]*)\[.*/g, "$1");
          //If we have subkeys
          if (basename.length < name.length) {
            var tmp = returnParams[basename] || {};
            //This gives us an array of the key of each level
            var path = name.replace(basename + "[", "").slice(0, -1).split("][");
            var ptr = tmp;
            var last = path.length - 1;
            for(var i in path) {
              //Set the value
              if (i == last) {
                ptr[path[i]] = value;
              } else {
                //If the path doesn't exist, create it.
                if(!ptr.hasOwnProperty(path[i])) {
                  ptr[path[i]] = {};
                }
                //Move the Pointer
                ptr = ptr[path[i]];
              }
            }
            //Set the value in our return object.
            returnParams[basename] = tmp;
          } else {
            returnParams[basename] = value;
          }
        });

        // The radius field is of type number; Angular errors if the value is a string
        if (returnParams['proximity'] && returnParams['proximity']['radius']) {
          returnParams['proximity']['radius'] = parseFloat(returnParams['proximity']['radius']);
        }

        // Angular preserves the brackets in `role_id[]=4`, so the generic
        // nested parser above represents it as {'': '4'}. Normalize both that
        // bookmark form and repeated role_id[] values for the multi-select and
        // the API4 array parameter.
        angular.forEach(['role_id', 'selected'], function(key) {
          if (!returnParams[key]) {
            return;
          }
          var values = angular.isArray(returnParams[key])
            ? returnParams[key]
            : (angular.isObject(returnParams[key])
              ? _.values(returnParams[key])
              : [returnParams[key]]);
          returnParams[key] = _.flatten(values);
        });

        return returnParams;
      };

      volOppSearch.params = parseQueryParams($route.current.params);

      /**
       * Formats the search params for bookmarkable links.
       *
       * @return string
       */
      var buildQueryString = function () {
        // VOL-187: The beneficiary widget is an entityRef; it expects values as CSV rather than an array.
        if (volOppSearch.params.beneficiary && typeof volOppSearch.params.beneficiary !== "string") {
          volOppSearch.params.beneficiary = volOppSearch.params.beneficiary.join(',');
        }

        // clean up the URL by filtering out those params with falsy values
        var cleanUpSearchParams = function (params) {
          return _.transform(params, function (result, value, key) {
            if (typeof value == 'object') {
              result[key] = cleanUpSearchParams(value);
            } else if (value) {
              result[key] = value;
            }
          });
        };
        var searchParams = cleanUpSearchParams(volOppSearch.params);

        // jQuery.param properly handles complex objects (recursively); if we don't do this,
        // we end up with URLs like "proximity=[Object]"
        return CRM.$.param(searchParams);
      }

      /**
       * Translate the form's filter names into API4 parameter names.
       *
       * The form and the bookmarkable URL keep APIv3's snake_case names;
       * API4 action parameters are camelCase, and API4 refuses an unknown
       * parameter outright rather than ignoring it.
       *
       * @param params
       * @returns object
       */
      var toApi4SearchParams = function(params) {
        var map = {
          beneficiary: 'beneficiary',
          project: 'project',
          proximity: 'proximity',
          role_id: 'roleId',
          date_start: 'dateStart',
          date_end: 'dateEnd',
          timeFilter: 'timeFilter',
          campaign_id: 'campaignId'
        };
        var api4Params = {};
        angular.forEach(map, function(api4Name, formName) {
          var value = params[formName];
          if (value === undefined || value === null || value === '') {
            return;
          }
          if (angular.isObject(value) && !angular.isArray(value) && _.isEmpty(value)) {
            return;
          }
          api4Params[api4Name] = value;
        });
        return api4Params;
      };

      volOppSearch.search = function() {
        //Update the URL for bookmarkability
        $location.search(buildQueryString());

        // VOL-187: The beneficiary widget is an entityRef, so the value arrives as CSV rather than an array.
        if (volOppSearch.params.beneficiary && typeof volOppSearch.params.beneficiary === "string") {
          volOppSearch.params.beneficiary = volOppSearch.params.beneficiary.split(',');
        }

        // API4 resolves to the rows themselves rather than an APIv3 envelope.
        // Replace the visible rows only after a successful response. A failed
        // geocode should leave the last valid result set in place while the
        // user corrects the location fields.
        return crmApi4('VolunteerNeed', 'search', toApi4SearchParams(volOppSearch.params))
          .then(function(needs) {
            result = needs;
          });
      };

      // Selection state belongs in the hash URL so Back to shifts can restore
      // the cart without issuing another signup or relying on browser memory.
      volOppSearch.setSelected = function(ids) {
        volOppSearch.params.selected = (ids || []).map(String);
        if (!volOppSearch.params.selected.length) {
          delete volOppSearch.params.selected;
        }
        // Replace rather than push. Picking six shifts would otherwise leave
        // six history entries, so the Back gesture -- the primary way people
        // navigate on a phone -- rewinds the cart one shift at a time instead
        // of leaving the page. Filter changes still push, because there Back
        // undoing the filter is what the user means.
        $location.search(buildQueryString()).replace();
      };

      volOppSearch.returnContext = function(ids) {
        volOppSearch.setSelected(ids);
        var query = buildQueryString();
        return '/volunteer/opportunities' + (query ? '?' + query : '');
      };

      //We are returning this as a function because there is a bug that causes
      //the 'result' to be unbound on the client side (eg, the listing is never refreshed)
      //this function acts as a closure and maintains binding
      volOppSearch.results = function results() { return result; };

      return volOppSearch;

    }])


    // Example: <div crm-vol-perm-to-class></div>
    // Adds a class to the element for each volunteer permission the user has.
    // This does not provide security but a better UX; i.e., don't show me
    // buttons I can't use.
    .directive('crmVolPermToClass', function(crmApi4) {
      return {
        restrict: 'A',
        scope: {},
        link: function (scope, element, attrs) {
          var classes = [];
          crmApi4('VolunteerUtil', 'getPermissions').then(function(perms) {
            angular.forEach(perms, function(value) {
              if (CRM.checkPerm(value.name) === true) {
                classes.push('crm-vol-perm-' + value.safe_name);
              }
            });

            $(element).addClass(classes.join(' '));
          });
        }
      };
    });

})(angular, CRM.$, CRM._);
