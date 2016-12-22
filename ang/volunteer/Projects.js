(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/manage', {
        controller: 'VolunteerProjects',
        templateUrl: '~/volunteer/Projects.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          // TODO for VOL-276: Factor this out. api.VolunteerUtil.getbeneficiaries
          // is deprecated in favor of api.VolunteerProjectContact.getList.
          beneficiaries: function (crmApi) {
            return crmApi('VolunteerUtil', 'getbeneficiaries').then(function(data) {
              return data.values;
            }, function(error) {
              if (error.is_error) {
                CRM.alert(error.error_message, ts("Error"), "error");
              } else {
                return error;
              }
            });
          },
          projectData: function(crmApi) {
            return crmApi('VolunteerProject', 'get', {
              sequential: 1,
              context: 'edit',
              'api.VolunteerProjectContact.get': {
                relationship_type_id: "volunteer_beneficiary"
              },
              'api.VolunteerProject.getlocblockdata': {
                id: '$value.loc_block_id',
                options: {limit: 0},
                return: 'all',
                sequential: 1
              }
            }).then(function (data) {
              // make the beneficiary IDs readily available for the live filter
              return _.each(data.values, function (element, index, list) {
                var beneficiaryIds = [];
                _.each(element['api.VolunteerProjectContact.get']['values'], function (el) {
                  beneficiaryIds.push(el.contact_id);
                });
                list[index].beneficiaries = beneficiaryIds;
              });
            });
          },
          campaigns: function(crmApi) {
            return crmApi('VolunteerUtil', 'getcampaigns').then(function(data) {
              return data.values;
            });
          },
          volunteerBackbone: function(volBackbone) {
            return volBackbone.load();
          }
        }
      });
    }
  );

  // TODO for VOL-276: Remove reference to beneficiaries object, based on deprecated API.
  angular.module('volunteer').controller('VolunteerProjects', function ($scope, $filter, crmApi, crmStatus, crmUiHelp, projectData, $location, volunteerBackbone, beneficiaries, campaigns, $window) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/volunteer/Projects'}); // See: templates/CRM/volunteer/Projects.hlp

    $scope.searchParams = {
      is_active: 1
    };
    $scope.projects = projectData;
    $scope.batchAction = "";
    $scope.allSelected = false;
    // TODO for VOL-276: Remove reference to beneficiaries object, based on deprecated API.
    $scope.beneficiaries = beneficiaries;
    $scope.campaigns = campaigns;
    $scope.needBase = CRM.url("civicrm/volunteer/need");
    $scope.assignBase = CRM.url("civicrm/volunteer/assign");
    $scope.urlPublicVolOppSearch = CRM.url('civicrm/vol/', '', 'front') + '#/volunteer/opportunities';
    $scope.canAccessAllProjects = CRM.checkPerm('edit all volunteer projects') || CRM.checkPerm('delete all volunteer projects');

    $scope.associatedEntityTitle = function(project) {
      if (project.entity_attributes && project.entity_attributes.title) {
        return project.entity_attributes.title;
      } else {
        return '--';
      }
    };

    $scope.canLinkToAssociatedEntity = function(project) {
      // Checking for string 'null' is probably unnecessary. We encountered such
      // records earlier in development, but this was likely a transient bug.
      return (project.entity_id && project.entity_table && project.entity_table !== 'null');
    };

    /**
     * Utility for stringifying locations which may have varying levels of detail.
     *
     * @param array project
     *   An item from the projectData provider.
     * @return string
     *   With HTML tags.
     */
    $scope.formatLocation = function (project) {
      var result = '';

      var locBlockData = project['api.VolunteerProject.getlocblockdata'].values;
      if (_.isEmpty(locBlockData)) {
        return result;
      }

      var address = locBlockData[0].address;
      if (_.isEmpty(address)) {
        return result;
      }

      if (address.street_address) {
        result += address.street_address;
      }

      if (address.street_address && (address.city || address.postal_code)) {
        result += '<br />';
      }

      if (address.city) {
        result += address.city;
      }

      if (address.city && address.postal_code) {
        result += ', ' + address.postal_code;
      } else if (address.postal_code) {
        result += address.postal_code;
      }

      return result;
    };

    // TODO for VOL-276: Replace or obviate the need for this method. This is
    // the blocker to removing the deprecated api.VolunteerUtil.getbeneficiaries.
    // Other related changes are trivial.
    $scope.formatBeneficiaries = function (project) {
      var displayNames = [];

      _.each(project.beneficiaries, function (item) {
        displayNames.push($scope.beneficiaries[item].display_name);
      });

      return displayNames.sort().join('<br />');
    };

    $scope.linkToAssociatedEntity = function(project) {
      if(project.entity_id && project.entity_table) {
        var url = false;
        switch(project.entity_table) {
          case 'civicrm_event':
            url = CRM.url("civicrm/event/manage/settings", "reset=1&action=update&id=" + project.entity_id);
            break;
          default:
            CRM.alert(ts("We couldn't find a link to this item"));
            break;
        }

        if(url) {
          $window.open(url, "_blank");
        }
      }
    };

    $scope.showLogHours = function() {
      var url = CRM.url("civicrm/volunteer/loghours", "reset=1&action=add&vid=" + this.project.id);
      var settings = {"dialog":{"width":"85%", "height":"80%"}};
      CRM.loadForm(url, settings);
    };

    $scope.showRoster = function() {
      var url = CRM.url("civicrm/volunteer/roster", "project_id=" + this.project.id);
      var settings = {"dialog":{"width":"85%", "height":"80%"}};
      CRM.loadPage(url, settings);
    };

    $scope.backbonePopup = function(title, tab, projectId) {
      CRM.volunteerPopup(title, tab, projectId);
    };

    $scope.clearCampaign = function() {
      if ($scope.searchParams.campaign_id == "") {
        delete $scope.searchParams.campaign_id;
      }
    };

    $scope.batchActions = {
      "enable": {
        label: ts("Enable"),
        run: function() {
          CRM.confirm({message: ts("Are you sure you want to Enable the selected Projects?")})
            .on('crmConfirm:yes', function() {
              $.each($scope.projects, function (index, project) {
                if (project.selected) {
                  project.is_active = 1;
                  crmApi("VolunteerProject", "create", {id: project.id, is_active: project.is_active}, true);
                }
              });
              $scope.$apply();
            });
        }
      },
      "disable": {
        label: ts("Disable"),
        run: function() {
          CRM.confirm({message: ts("Are you sure you want to Disable the selected Projects?")})
            .on('crmConfirm:yes', function() {
              $.each($scope.projects, function (index, project) {
                if (project.selected) {
                  project.is_active = 0;
                  crmApi("VolunteerProject", "create", {id: project.id, is_active: project.is_active}, true);
                }
              });
              $scope.$apply();
            });
        }
      },
      "delete": {
        label: ts("Delete"),
        run: function() {
          CRM.confirm({message: ts("Are you sure you want to Delete the selected Projects?")})
            .on('crmConfirm:yes', function() {
              $.each($scope.projects, function (index, project) {
                if (project.selected) {
                  crmApi("VolunteerProject", "delete", {id: project.id}, true).then(function() {
                    $scope.projects.splice(index, 1);
                  });
                }
              });
            });
        }
      }
    };
    $scope.runBatch = function() {
      if(!!$scope.batchAction) {
        $scope.batchActions[$scope.batchAction].run();
      }
    };

    /**
     * Keeps the "select all" checkbox synced with the project checkboxes.
     *
     * Additions to/subtractions from the selected set will cause the "select
     * all" checkbox to be checked or unchecked as appropriate.
     */
    $scope.watchSelected = function() {
      var all = true;
      var filter = $filter('filter');
      var projectsInView = filter($scope.projects, $scope.searchParams);

      $.each(projectsInView, function(index, project) {
        all = (all && project.selected);
      });
      $scope.allSelected = all;
    };

    /**
     * Handles clicks of the "select all" checkbox.
     *
     * When clicked, all visible projects are selected. When unclicked, they are
     * deselected.
     */
    $scope.selectAll = function() {
      var filter = $filter('filter');
      var projectsInView = filter($scope.projects, $scope.searchParams);
      var toggle = $scope.allSelected;

      $.each(projectsInView, function(index, project) {
        project.selected = toggle;
      });
    };

    /**
     * Reset the checkboxes whenever the search criteria are changed. Eliminates
     * the possibility of accidental deletions by ensuring that selections for
     * bulk operations are always visible.
     */
    $scope.$watch('searchParams', function() {
      $scope.allSelected = false;
      $.each($scope.projects, function(index, project) {
        project.selected = false;
      });
    }, true);
  });

})(angular, CRM.$, CRM._);
