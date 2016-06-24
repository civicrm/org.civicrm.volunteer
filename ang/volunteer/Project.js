(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/manage/:projectId', {
        controller: 'VolunteerProject',
        templateUrl: '~/volunteer/Project.html',
        resolve: {
          countries: function(crmApi) {
            return crmApi('VolunteerUtil', 'getcountries', {}).then(function(result) {
              return result.values;
            });
          },
          project: function(crmApi, $route) {
            if ($route.current.params.projectId == 0) {
              return {
                id: 0
              };
            } else {
              return crmApi('VolunteerProject', 'getsingle', {
                id: $route.current.params.projectId
              }).then(
                // success
                null,
                // error
                function () {
                  CRM.alert(
                    ts('No volunteer project exists with an ID of %1', {1: $route.current.params.projectId}),
                    ts('Not Found'),
                    'error'
                  );
                }
              );
            }
          },
          supporting_data: function(crmApi) {
            return crmApi('VolunteerUtil', 'getsupportingdata', {
              controller: 'VolunteerProject'
            });
          },
          campaigns: function(crmApi) {
            return crmApi('VolunteerUtil', 'getcampaigns').then(function(data) {
              return data.values;
            });
          },
          relationship_data: function(crmApi, $route) {
            return crmApi('VolunteerProjectContact', 'get', {
              "sequential": 1,
              "project_id": $route.current.params.projectId
            });
          },
          location_blocks: function(crmApi) {
            return crmApi('VolunteerProject', 'locations', {});
          },
          profile_status: function(crmProfiles) {
            return crmProfiles.load();
          }
        }
      });
    }
  );


  angular.module('volunteer').controller('VolunteerProject', function($scope, $location, $q, $route, crmApi, crmUiAlert, crmUiHelp, countries, project, profile_status, campaigns, relationship_data, supporting_data, location_blocks, volBackbone) {

    /**
     * We use custom "dirty" logic rather than rely on Angular's native
     * functionality because we need to make a separate API call to
     * create/update the locBlock object (a distinct entity from the project)
     * if any of the locBlock fields have changed, regardless of whether other
     * form elements are dirty.
     */
    $scope.locBlockIsDirty = false;

    /**
     * This flag allows the code to distinguish between user- and
     * server-initiated changes to the locBlock fields. Without this flag, the
     * changes made to the locBlock fields when a location is fetched from the
     * server would cause the watch function to mark the locBlock dirty.
     */
    $scope.locBlockSkipDirtyCheck = false;

    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/Volunteer/Form/Volunteer'}); // See: templates/CRM/volunteer/Project.hlp

    var volRelData = {};
    var relationships = {};
    var showRelationshipType = {};
    if(project.id == 0) {
      //Cloning these two objects so that their original values aren't subject to data-binding
      project = _.extend(_.clone(supporting_data.values.defaults), project);
      volRelData = _.clone(supporting_data.values.defaults.relationships);

      if (CRM.vars['org.civicrm.volunteer'].entityTable) {
        project.entity_table = CRM.vars['org.civicrm.volunteer'].entityTable;
        project.entity_id = CRM.vars['org.civicrm.volunteer'].entityId;
      }
      //For an associated Entity, make the title of the project default to
      //The title of the entity
      if (CRM.vars['org.civicrm.volunteer'].entityTitle) {
        project.title = CRM.vars['org.civicrm.volunteer'].entityTitle;
      }
    } else {
      $(relationship_data.values).each(function (index, relationship) {
        if (!volRelData.hasOwnProperty(relationship.relationship_type_id)) {
          volRelData[relationship.relationship_type_id] = [];
        }
        volRelData[relationship.relationship_type_id].push({
          contact_id: relationship.contact_id,
          can_be_read_by_current_user: relationship.can_be_read_by_current_user
        });
      });
    }

    // flatten the data a bit to make it easier to work with in the template
    _.each(volRelData, function (contacts, relTypeId) {
      relationships[relTypeId] = [];
      showRelationshipType[relTypeId] = true;
      _.each(contacts, function (contact) {
        relationships[relTypeId].push(contact.contact_id);
        //This reduces all contact permissions down to a single bool for the overall type
        //We are doing a logical AND here so that if the user does not posses the permission
        //to view one of the associated contacts, we do not show them the widget.
        //This is because if we show the widget it will remove any contact they do not
        //have permission to view. This would lead to a user editing a project, being able
        //to see one of two beneficiaries, saving the project, and the beneficiary they
        //could not see is removed silently from the project.
        showRelationshipType[relTypeId] = showRelationshipType[relTypeId] && contact.can_be_read_by_current_user;
      });
    });
    project.project_contacts = relationships;
    $scope.showRelationshipType = showRelationshipType;

    if (CRM.vars['org.civicrm.volunteer'] && CRM.vars['org.civicrm.volunteer'].context) {
      $scope.formContext = CRM.vars['org.civicrm.volunteer'].context;
    } else {
      $scope.formContext = 'standAlone';
    }

    switch ($scope.formContext) {
      case 'eventTab':
        var cancelCallback = function (projectId) {
          CRM.$("body").trigger("volunteerProjectCancel");
        };
        var saveAndNextCallback = function (projectId) {
          CRM.$("body").trigger("volunteerProjectSaveComplete", projectId);
        };
        $scope.saveAndNextLabel = ts('Save');
        break;

      default:
        var cancelCallback = function (projectId) {
          $location.path("/volunteer/manage");
        };
        var saveAndNextCallback = function (projectId) {
          volBackbone.load().then(function () {
            CRM.volunteerPopup(ts('Define Volunteer Opportunities'), 'Define', projectId);
            $location.path("/volunteer/manage");
          });
        };
        $scope.saveAndNextLabel = ts('Continue');
    }

    $scope.countries = countries;
    $scope.locationBlocks = location_blocks.values;
    $scope.locationBlocks[0] = "Create a new Location";
    $scope.locBlock = {};

    $.each(project.profiles, function (key, data) {
      if(data.module_data && typeof(data.module_data) === "string") {
        data.module_data = JSON.parse(data.module_data);
      }
    });

    $scope.campaigns = campaigns;
    $scope.relationship_types = supporting_data.values.relationship_types;
    $scope.phone_types = supporting_data.values.phone_types;
    $scope.supporting_data = supporting_data.values;
    $scope.profile_status = profile_status;
    project.is_active = (project.is_active == "1");
    $scope.project = project;
    $scope.profiles = $scope.project.profiles;
    $scope.relationships = $scope.project.project_contacts;
    // VOL-223: Used to determine visibility of relationship block
    $scope.showRelationshipBlock = _.reduce($scope.showRelationshipType, function(a, b) {return (a || b); });

    /**
     * Populates locBlock fields based on user selection of location.
     *
     * Makes an API request.
     *
     * @see $scope.locBlockIsDirty
     * @see $scope.locBlockSkipDirtyCheck
     */
    $scope.refreshLocBlock = function() {
      if (!!$scope.project.loc_block_id) {
        crmApi("VolunteerProject", "getlocblockdata", {
          "return": "all",
          "sequential": 1,
          "id": $scope.project.loc_block_id
        }).then(function(result) {
          if(!result.is_error) {
            $scope.locBlockSkipDirtyCheck = true;
            $scope.locBlock = result.values[0];
            $scope.locBlockIsDirty = false;
          } else {
            CRM.alert(result.error);
          }
        });
      }
    };
    //Refresh as soon as we are up and running because we don't have this data yet.
    $scope.refreshLocBlock();

    /**
     * If the user selects the option to create a new locBlock (id = 0), set
     * some defaults and display the necessary fields. Otherwise, fetch the
     * location data so we can display it for editing.
     */
    $scope.$watch('project.loc_block_id', function (newValue) {
      if (newValue == 0) {
        $scope.locBlock = {
          address: {
            country_id: _.findWhere(countries, {is_default: "1"}).id
          }
        };

        $("#crm-vol-location-block .crm-accordion-body").slideDown({complete: function() {
          $("#crm-vol-location-block .crm-accordion-wrapper").removeClass("collapsed");
        }});
      } else {
        //Load the data from the server.
        $scope.refreshLocBlock();
      }
    });

    /**
     * @see $scope.locBlockIsDirty
     * @see $scope.locBlockSkipDirtyCheck
     */
    $scope.$watch('locBlock', function(newValue, oldValue) {
      if ($scope.locBlockSkipDirtyCheck) {
        $scope.locBlockSkipDirtyCheck = false;
      } else {
        $scope.locBlockIsDirty = true;
      }
    }, true);

    $scope.addProfile = function() {
      $scope.profiles.push({
        "entity_table": "civicrm_volunteer_project",
        "is_active": "1",
        "module": "CiviVolunteer",
        "module_data": {audience: "primary"},
        "weight": getMaxProfileWeight() + 1
      });
    };

    var getMaxProfileWeight = function() {
      var weights = [0];
      $.each($scope.profiles, function (index, data) {
        weights.push(parseInt(data.weight));
      });
      return _.max(weights);
    };

    $scope.removeProfile = function(index) {
      $scope.profiles.splice(index, 1);
    };

    $scope.validateProfileSelections = function() {
      var hasAdditionalProfileType = false;
      var hasPrimaryProfileType = false;
      var valid = true;

      $.each($scope.profiles, function (index, data) {
        if(!data.uf_group_id) {
          CRM.alert(ts("Please select at least one profile, and remove empty selections"), "Required", 'error');
          valid = false;
        }

        if(data.module_data.audience == "additional" || data.module_data.audience == "both") {
          if(hasAdditionalProfileType) {
            CRM.alert(ts("You may only have one profile that is used for group registrations"), ts("Warning"), 'error');
            valid = false;
          } else {
            hasAdditionalProfileType = true;
          }
        }

        if (data.module_data.audience == "primary" || data.module_data.audience == "both") {
          hasPrimaryProfileType = true;
        }
      });

      if (!hasPrimaryProfileType) {
        CRM.alert(ts("Please select at least one profile that is used for individual registrations"), ts("Warning"), 'error');
        valid = false;
      }

      return valid;
    };

    $scope.validateProject = function() {
      var valid = true;
      var relationshipsValid = validateRelationships();

      if(!$scope.project.title) {
        CRM.alert(ts("Title is a required field"), "Required");
        valid = false;
      }

      if ($scope.profiles.length === 0) {
        CRM.alert(ts("You must select at least one Profile"), "Required");
        valid = false;
      }

      valid = (valid && relationshipsValid && $scope.validateProfileSelections());

      return valid;
    };

  /**
   * Helper validation function.
   *
   * Ensures that a value is set for each required project relationship.
   *
   * @returns {Boolean}
   */
    validateRelationships = function() {
      var isValid = true;

      var requiredRelationshipTypes = ['volunteer_beneficiary', 'volunteer_manager', 'volunteer_owner'];

      _.each(requiredRelationshipTypes, function(value) {
        var thisRelType = _.find(supporting_data.values.relationship_types, function(relType) {
          return (relType.name === value);
        });

        if (_.isEmpty(relationships[thisRelType.value])) {
          CRM.alert(ts("The %1 relationship must not be blank.", {1: thisRelType.label}), ts("Required"));
          isValid = false;
        }
      });

      return isValid;
    };

    /**
     * Helper function which actually saves a form submission.
     *
     * @returns {Mixed} Returns project ID on success, boolean FALSE on failure.
     */
    saveProject = function() {
      if ($scope.validateProject()) {

        return crmApi('VolunteerProject', 'create', $scope.project).then(function(result) {
          var projectId = result.values.id;

          // VOL-140: For legacy reasons, a new flexible need should be created
          // for each project. Pretty sure we want to re-architect this soon.
          if ($scope.project.id === 0) {
            crmApi('VolunteerNeed', 'create', {
              is_flexible: 1,
              project_id: projectId,
              visibility_id: 'admin'
            });
          }

          //Save the LocBlock
          if($scope.locBlockIsDirty) {
            $scope.locBlock.entity_id = projectId;
            $scope.locBlock.id = result.values.loc_block_id;
            crmApi('VolunteerProject', 'savelocblock', $scope.locBlock);
          }

          return projectId;
        });
      } else {
        return $q.reject(false);
      }
    };

    $scope.saveAndDone = function () {
      saveProject().then(function (projectId) {
        if (projectId) {
          crmUiAlert({text: ts('Changes saved successfully'), title: ts('Saved'), type: 'success'});
          $location.path("/volunteer/manage");
        }
      });
    };

    $scope.saveAndNext = function() {
      saveProject().then(function(projectId) {
        if (projectId) {
          crmUiAlert({text: ts('Changes saved successfully'), title: ts('Saved'), type: 'success'});
          saveAndNextCallback(projectId);
        }
      });
    };

    $scope.cancel = function() {
      cancelCallback();
    };

    $scope.previewDescription = function() {
      CRM.alert($scope.project.description, $scope.project.title, 'info', {expires: 0});
    };

    //Handle Refresh requests
    CRM.$("body").on("volunteerProjectRefresh", function() {
      $route.reload();
    });


  });

})(angular, CRM.$, CRM._);
