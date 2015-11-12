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

    .factory('volOppSearch', ['crmApi', '$location', '$route', function(crmApi, $location, $route) {
      // search result is stored here
      var result = {};

      var getResult = function() {
        return result;
      };

      var clearResult = function() {
        result = {};
      };

      var userSpecifiedSearchParams = {};

      var getUserSpecifiedSearchParams = function() {
        return userSpecifiedSearchParams;
      };

      var search = function(searchParams) {
        clearResult();

        // if no params are passed, get the data out of the URL
        if (searchParams) {
          userSpecifiedSearchParams = searchParams();
        } else {
          userSpecifiedSearchParams = $route.current.params;
        }

        // update the URL for bookmarkability
        $location.search(userSpecifiedSearchParams);

        return crmApi('VolunteerNeed', 'getsearchresult', userSpecifiedSearchParams).then(function(data) {
          result = data.values;
          return getResult();
        });
      };

      return {
        getResult: getResult,
        getParams: getUserSpecifiedSearchParams,
        search: search
      };

    }])


    // Example: <div crm-vol-perm-to-class></div>
    // Adds a class to the element for each volunteer permission the user has.
    // This does not provide security but a better UX; i.e., don't show me
    // buttons I can't use.
    .directive('crmVolPermToClass', function(crmApi) {
      return {
        restrict: 'A',
        scope: {},
        link: function (scope, element, attrs) {
          var classes = [];
          crmApi('VolunteerUtil', 'getperms').then(function(perms) {
            angular.forEach(perms.values, function(value) {
              if (CRM.checkPerm(value.name) === true) {
                classes.push('crm-vol-perm-' + value.safe_name);
              }
            });

            $(element).addClass(classes.join(' '));
          });
        }
      };
    })


    /**
     * This is a service for loading the backbone-based volunteer UIs (and their
     * prerequisite scripts) into angular routes.
     */
    .factory('volBackbone', function(crmApi, crmProfiles, $q) {

      // This was done as a recursive function because the scripts must execute in order.
      function loadNextScript(scripts, callback, fail) {
        var script = scripts.shift();
        CRM.$.getScript(script)
          .done(function(scriptData, status) {
            if(scripts.length > 0) {
              loadNextScript(scripts, callback, fail);
            } else {
              callback();
            }
          }).fail(function(jqxhr, settings, exception) {
            console.log(exception);
            fail(exception);
          });
      }

      function loadSettings(settings) {
        CRM.$.extend(true, CRM, settings);
      }

      function loadStyleFile(url) {
        CRM.$("#backbone_resources").append('<link rel="stylesheet" type="text/css" href="' + url + '" />');
      }

      /**
       * Fetches a URL and puts the fetched HTML into #volunteer_backbone_templates.
       *
       * The intended use is to fetch a Smarty-generated page which contains all
       * of the backbone templates (e.g., <script type="text/template">foo</script>).
       */
      function loadTemplate(index, url) {
        var deferred = $q.defer();
        var divId = 'volunteer_backbone_template_' + index;

        CRM.$("#volunteer_backbone_templates").append("<div id='" + divId + "'></div>");
        CRM.$("#" + divId).load(CRM.url(url, {snippet: 5}), function(response) {
          deferred.resolve(response);
        });

        return deferred.promise;
      }

      function loadScripts(scripts) {
        var deferred = $q.defer();

        // What's this weird stuff going on with jQuery, you ask?
        //
        // Based on a discussion with totten, we anticipate problems with
        // competing versions of jQuery. On a given page, there are two copies
        // of jQuery (CMS's and CRM's), but only one of them includes Civi's
        // custom widgets and preferred add-ons (crmDatepicker, etc). jQuery
        // version problems wouldn't manifest all the time -- in many cases, the
        // different variants of jQuery are interchangeable, but we suspect that
        // certain directives (like crm-ui-datepicker) would fail in snippet
        // mode because they can't access a required jQuery function. So far
        // there don't seem to be any problems, but I'm flagging this as needing
        // more testing and as a potential source of mysterious problems.
        CRM.origJQuery = window.jQuery;
        window.jQuery = CRM.$;

        // We need to put underscore on the global scope or backbone fails to load
        if(!window._) {
          window._ = CRM._;
        }

        loadNextScript(scripts, function () {
          window.jQuery = CRM.origJQuery;
          delete CRM.origJQuery;
          CRM.volunteerBackboneScripts = true;
          deferred.resolve(true);
        }, function(status) {
          deferred.resolve(status);
        });

        return deferred.promise;
      }

      // TODO: Figure out a more authoritative way to check this, rather than
      // simply setting and checking a flag.
      function verifyScripts() {
        return !!CRM.volunteerBackboneScripts;
      }
      function verifyTemplates() {
        return (angular.element("#volunteer_backbone_templates div").length > 0);
      }
      function verifySettings() {
        return !!CRM.volunteerBackboneSettings;
      }

      return {
        verify: function() {
          return (!!window.Backbone && verifyScripts() && verifySettings() && verifyTemplates());
        },
        load: function() {
          var deferred = $q.defer();
          var promises = [];
          var preReqs = {};

          preReqs.volunteer = crmApi('VolunteerUtil', 'loadbackbone');

          if(!crmProfiles.verify()) {
            preReqs.profiles = crmProfiles.load();
          }

          $q.all(preReqs).then(function(resources) {

            if (CRM.$("#backbone_resources").length < 1) {
              CRM.$("body").append("<div id='backbone_resources'></div>");
            }

            if(CRM.$("#volunteer_backbone_templates").length < 1) {
              CRM.$("body").append("<div id='volunteer_backbone_templates'></div>");
            }

            // The settings must be loaded before the libraries
            // because the libraries depend on the settings.
            if(!verifySettings()) {
              loadSettings(resources.volunteer.values.settings);
              CRM.volunteerBackboneSettings = true;
            }

            if(!verifyScripts()) {
              promises.push(loadScripts(resources.volunteer.values.scripts));
            }

            if(!verifyTemplates()) {
              CRM.$.each(resources.volunteer.values.templates, function(index, url) {
                promises.push(loadTemplate(index, url));
              });
            }

            CRM.$.each(resources.volunteer.values.css, function(index, url) {
              loadStyleFile(url);
            });

            $q.all(promises).then(
              function () {
                //I'm not sure what normally triggers this event, but when cramming it
                //into angular the event isn't triggered. So I'm doing it here, otherwise
                //The backbone stuff fails.
                CRM.volunteerApp.trigger("initialize:before");

                deferred.resolve(true);
              },
              function () {
                console.log("Failed to load all backbone resources");
                deferred.reject(ts("Failed to load all backbone resources"));
              }
            );
          });
          return deferred.promise;
        }
      };
    });

})(angular, CRM.$, CRM._);
