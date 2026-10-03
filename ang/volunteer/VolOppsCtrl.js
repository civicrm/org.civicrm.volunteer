(function(angular, _, $) {
  'use strict';

  angular.module('volunteer').config(function($routeProvider) {
    $routeProvider.when('/volunteer/opportunities', {
      controller: 'VolOppsCtrl',
      reloadOnSearch: false,
      templateUrl: '~/volunteer/VolOppsCtrl.html',
      resolve: {
        countries: function(crmApi4) {
          return crmApi4('VolunteerUtil', 'getCountries', {}, 'id');
        },
        supporting_data: function(crmApi4) {
          return crmApi4('VolunteerUtil', 'getSupportingData', {controller: 'VolOppsCtrl'})
            .then(function(result) { return result[0] || {}; });
        }
      }
    });
  });

  angular.module('volunteer').controller('VolOppsCtrl', function(
    $route, $scope, $window, $filter, crmStatus, crmApi4,
    volOppSearch, countries, supporting_data
  ) {
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    $scope.countries = countries;
    $scope.roles = supporting_data.roles || {};
    $scope.searchParams = volOppSearch.params;
    $scope.searchParams.proximity = $scope.searchParams.proximity || {};
    // Country rows flag the value from CiviCRM Core's
    // defaultContactCountry setting. Keep a bookmarked country when present;
    // otherwise make the configured default an active location filter.
    var defaultCountry = _.find(values(countries), function(country) {
      return country.is_default == 1;
    });
    $scope.defaultCountryId = defaultCountry ? parseInt(defaultCountry.id, 10) : null;
    var requestedCountry = hasValue($scope.searchParams.proximity.country)
      ? $scope.searchParams.proximity.country
      : $scope.searchParams.proximity.country_id;
    var selectedCountry = _.find(values(countries), function(country) {
      return String(country.id) === String(requestedCountry)
        || String(country.name).toLowerCase() === String(requestedCountry).toLowerCase()
        || String(country.iso_code).toLowerCase() === String(requestedCountry).toLowerCase();
    });
    if (selectedCountry) {
      $scope.searchParams.proximity.country = parseInt(selectedCountry.id, 10);
    }
    else {
      delete $scope.searchParams.proximity.country;
    }
    delete $scope.searchParams.proximity.country_id;
    if (hasValue($scope.searchParams.proximity.state_province_id)) {
      var stateProvinceId = parseInt(
        $scope.searchParams.proximity.state_province_id,
        10
      );
      if (stateProvinceId > 0) {
        $scope.searchParams.proximity.state_province_id = stateProvinceId;
      }
      else {
        delete $scope.searchParams.proximity.state_province_id;
      }
    }
    if ($scope.searchParams.proximity.unit === 'mile') {
      $scope.searchParams.proximity.unit = 'miles';
    }
    // The form has a draft location model. This lets the country field show
    // Core's default without silently filtering the initial opportunity list.
    // A bookmarked location remains applied immediately; otherwise the
    // default becomes active only when the visitor clicks Apply filters.
    $scope.locationFilters = angular.copy($scope.searchParams.proximity);
    if (!selectedCountry && $scope.defaultCountryId) {
      $scope.locationFilters.country = $scope.defaultCountryId;
    }
    $scope.searchParams.timeFilter = $scope.searchParams.timeFilter || 'all';
    $scope.proximityAvailable = !!supporting_data.proximity_available;
    if (!$scope.proximityAvailable) {
      delete $scope.searchParams.proximity.radius;
      delete $scope.searchParams.proximity.unit;
      delete $scope.locationFilters.radius;
      delete $scope.locationFilters.unit;
    }
    $scope.isCampaignEnabled = !!CRM.volunteer.isCampaignEnabled;
    $scope.campaignFilter = CRM.volunteer.campaignFilter;
    $scope.volOppData = volOppSearch.results;
    $scope.shoppingCart = {};
    $scope.groups = [];
    $scope.flexibleNeeds = [];
    $scope.hasSearched = false;
    $scope.moreFiltersOpen = false;
    $scope.stateProvinces = [];
    $scope.stateProvincesLoading = false;
    var stateProvinceRequest = 0;

    function loadStateProvinces(countryId) {
      countryId = parseInt(countryId, 10);
      var request = ++stateProvinceRequest;
      if (!(countryId > 0)) {
        $scope.stateProvinces = [];
        $scope.stateProvincesLoading = false;
        return;
      }

      $scope.stateProvincesLoading = true;
      crmApi4('StateProvince', 'get', {
        select: ['id', 'name'],
        where: [['country_id', '=', countryId]],
        orderBy: {name: 'ASC'},
        limit: 0
      }).then(function(states) {
        if (request === stateProvinceRequest) {
          $scope.stateProvinces = states;
        }
      }).catch(function(error) {
        if (request === stateProvinceRequest) {
          $scope.stateProvinces = [];
          CRM.alert(
            error && error.error_message
              ? error.error_message
              : ts('The State/Province choices could not be loaded.'),
            ts('Location unavailable'),
            'error'
          );
        }
      }).finally(function() {
        if (request === stateProvinceRequest) {
          $scope.stateProvincesLoading = false;
        }
      });
    }

    $scope.countryChanged = function() {
      delete $scope.locationFilters.state_province_id;
    };

    $scope.$watch('locationFilters.country', function(countryId) {
      loadStateProvinces(countryId);
    });

    var requestedSelections = _.chain($scope.searchParams.selected || [])
      .map(function(id) { return parseInt(id, 10); })
      .filter(function(id) { return id > 0; })
      .uniq()
      .value();
    var restorePending = requestedSelections.length > 0;

    $scope.hideSearch = false;
    $scope.allowShowSearch = false;
    if ($route.current.params.hideSearch === 'always') {
      $scope.hideSearch = true;
    }
    else if ($route.current.params.hideSearch === '1') {
      $scope.hideSearch = true;
      $scope.allowShowSearch = true;
    }

    function values(object) {
      return _.isArray(object) ? object : _.values(object || {});
    }

    function dateFromSiteValue(value) {
      var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      return match ? new Date(+match[1], +match[2] - 1, +match[3], +match[4], +match[5]) : null;
    }

    function isFlexible(need) {
      return need.is_flexible == 1;
    }

    function isOngoing(need) {
      return !isFlexible(need) && !need.end_time && !need.duration;
    }

    function groupResults() {
      var groups = [];
      var byDate = {};
      var ongoing = [];
      $scope.flexibleNeeds = [];
      angular.forEach(values($scope.volOppData()), function(need) {
        if (isFlexible(need)) {
          $scope.flexibleNeeds.push(need);
          return;
        }
        if (isOngoing(need)) {
          ongoing.push(need);
          return;
        }
        var key = String(need.start_time || '').slice(0, 10);
        if (!byDate[key]) {
          var date = dateFromSiteValue(need.start_time);
          byDate[key] = {
            key: key,
            label: date ? $filter('date')(date, 'EEEE, MMMM d') : ts('Upcoming shifts'),
            needs: []
          };
          groups.push(byDate[key]);
        }
        byDate[key].needs.push(need);
      });
      if (ongoing.length) {
        groups.push({key: 'no-fixed-time', label: ts('No fixed time'), needs: ongoing});
      }
      $scope.groups = groups;
    }

    function reconcileSelections() {
      var foundRequested = {};
      angular.forEach(values($scope.volOppData()), function(need) {
        var id = parseInt(need.id, 10);
        if ($scope.shoppingCart[id] || requestedSelections.indexOf(id) !== -1) {
          need.inCart = true;
          $scope.shoppingCart[id] = need;
          foundRequested[id] = true;
        }
      });
      if (restorePending) {
        var unavailable = _.filter(requestedSelections, function(id) { return !foundRequested[id]; });
        if (unavailable.length) {
          CRM.alert(
            unavailable.length === 1
              ? ts('A previously selected opportunity is no longer available and was removed.')
              : ts('%1 previously selected opportunities are no longer available and were removed.', {
                1: unavailable.length
              }),
            ts('Selection updated'),
            'warning'
          );
        }
        restorePending = false;
        // The restore is done. Leaving these in place re-seeded the cart on
        // every later search, so a shift the user removed came back as soon as
        // they touched a filter.
        requestedSelections = [];
      }
      groupResults();
      volOppSearch.setSelected(_.keys($scope.shoppingCart));
    }

    function runSearch(withStatus) {
      $scope.hasSearched = false;
      var promise = volOppSearch.search().then(reconcileSelections);
      if (withStatus) {
        promise = crmStatus({start: ts('Searching...'), success: ts('Search complete')}, promise);
      }
      return promise.catch(function(error) {
        CRM.alert(
          error && error.error_message
            ? error.error_message
            : ts('The location search could not be completed.'),
          ts('Search not completed'),
          'error'
        );
      }).finally(function() { $scope.hasSearched = true; });
    }

    runSearch(false);

    function hasValue(value) {
      if (angular.isString(value)) {
        return value.trim() !== '';
      }
      return value !== undefined && value !== null;
    }

    function checkHasLocationAddress() {
      return _.some([
        $scope.locationFilters.street_address,
        $scope.locationFilters.city,
        $scope.locationFilters.postal_code,
        $scope.locationFilters.state_province_id,
        $scope.locationFilters.country,
        $scope.locationFilters.country_id
      ], hasValue);
    }

    function checkHasLocationSearch() {
      return _.reduce($scope.locationFilters, function(previous, value, key) {
        return key === 'unit' ? previous : (previous || hasValue(value));
      }, false);
    }

    function updateLocationSearchState() {
      $scope.hasLocationSearch = checkHasLocationSearch();
      $scope.isProximitySearch = $scope.proximityAvailable
        && hasValue($scope.locationFilters.radius);
      $scope.locationCenterMissing = $scope.isProximitySearch && !checkHasLocationAddress();
    }

    updateLocationSearchState();
    $scope.$watch('locationFilters', function() {
      updateLocationSearchState();
    }, true);

    $scope.showSearch = function() {
      $scope.hideSearch = false;
      $scope.allowShowSearch = false;
      $scope.moreFiltersOpen = true;
    };

    $scope.toggleMoreFilters = function() {
      $scope.moreFiltersOpen = !$scope.moreFiltersOpen;
    };

    $scope.setTimeFilter = function(filter) {
      $scope.searchParams.timeFilter = filter;
      return runSearch(true);
    };

    $scope.applyFilters = function(form) {
      $scope.filterValidationAttempted = true;
      if (form.$invalid || $scope.locationCenterMissing) {
        angular.forEach(form.$error, function(controls) {
          angular.forEach(controls, function(control) {
            control.$setDirty();
            control.$setTouched();
          });
        });
        return;
      }
      $scope.filterValidationAttempted = false;
      return $scope.search();
    };

    $scope.search = function() {
      if (!$scope.hasLocationSearch) {
        $scope.searchParams.proximity = {};
      }
      else {
        $scope.searchParams.proximity = angular.copy($scope.locationFilters);
        if (!$scope.proximityAvailable) {
          delete $scope.searchParams.proximity.radius;
          delete $scope.searchParams.proximity.unit;
        }
      }
      return runSearch(true);
    };

    $scope.clearSearch = function(form) {
      angular.forEach(['date_start', 'date_end', 'role_id', 'beneficiary'], function(key) {
        delete $scope.searchParams[key];
      });
      $scope.searchParams.proximity = {};
      $scope.locationFilters = $scope.defaultCountryId
        ? {country: $scope.defaultCountryId}
        : {};
      $scope.locationCenterMissing = false;
      $scope.filterValidationAttempted = false;
      if (form) {
        form.$setPristine();
        form.$setUntouched();
      }
      return runSearch(true);
    };

    $scope.toggleSelection = function(need) {
      var id = parseInt(need.id, 10);
      need.inCart = !$scope.shoppingCart[id];
      if (need.inCart) {
        $scope.shoppingCart[id] = need;
      }
      else {
        delete $scope.shoppingCart[id];
      }
      volOppSearch.setSelected(_.keys($scope.shoppingCart));
    };

    $scope.checkout = function() {
      var ids = _.keys($scope.shoppingCart);
      if (!ids.length) {
        return;
      }
      $window.location.href = CRM.url('civicrm/volunteer/signup', {
        reset: 1,
        needs: ids,
        dest: $route.current.params.dest || 'list',
        return: volOppSearch.returnContext(ids)
      });
    };

    $scope.itemCountInCart = function() {
      return _.size($scope.shoppingCart);
    };

    $scope.resultCount = function() {
      return values($scope.volOppData()).length;
    };

    // Announced through a small role="status" element. The whole results list
    // used to be the live region, so every filter change read out every card,
    // group heading and description.
    $scope.resultsLabel = function() {
      var count = $scope.resultCount();
      if (!count) {
        return ts('No opportunities found');
      }
      return count === 1
        ? ts('1 opportunity found')
        : ts('%1 opportunities found', {1: count});
    };

    $scope.remaining = function(need) {
      if (need.quantity === null || need.quantity === '' || need.quantity === undefined) {
        return null;
      }
      return Math.max(0, parseInt(need.quantity, 10) - (parseInt(need.quantity_assigned, 10) || 0));
    };

    $scope.durationLabel = function(need) {
      if (need.end_time || isOngoing(need)) {
        return ts('flexible');
      }
      var minutes = parseInt(need.duration, 10);
      if (!minutes) {
        return '';
      }
      if (minutes % 60 === 0) {
        var hours = minutes / 60;
        return hours === 1 ? ts('1 hour') : ts('%1 hours', {1: hours});
      }
      return ts('%1 minutes', {1: minutes});
    };

    $scope.cardDescription = function(need) {
      return need.role_description || need.project.description || '';
    };

    $scope.proximityUnits = [
      {value: 'km', label: ts('km')},
      {value: 'miles', label: ts('miles')}
    ];
  });

})(angular, CRM._, CRM.$);
