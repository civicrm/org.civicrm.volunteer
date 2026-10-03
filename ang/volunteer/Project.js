(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      var projectRoute = {
        controller: 'VolunteerProject',
        templateUrl: '~/volunteer/Project.html',
        resolve: {
          projectManagementAccess: function(volProjectManagementAccess) {
            return volProjectManagementAccess();
          },
          // The country select binds to the map key, so ask API4 to index by ID.
          countries: function(crmApi4) {
            return crmApi4('VolunteerUtil', 'getCountries', {}, 'id');
          },
          project: function(crmApi4, $route) {
            if ($route.current.params.projectId == 0) {
              return {
                id: 0
              };
            }
            var notFound = function() {
              // Route resolves run outside the controller, so there is no
              // local ts() bound to this extension's domain here.
              var ts = CRM.ts('org.civicrm.volunteer');
              CRM.alert(
                ts('No volunteer project exists with an ID of %1', {1: $route.current.params.projectId}),
                ts('Not Found'),
                'error'
              );
            };
            // APIv3's getsingle reported "not found" as an error; API4 simply
            // returns no rows, so the empty case is handled here too.
            return crmApi4('VolunteerProject', 'search', {
              context: 'edit',
              filters: {id: $route.current.params.projectId}
            }).then(
              // success
              function (projects) {
                if (!projects.length) {
                  notFound();
                  return;
                }
                return projects[0];
              },
              // error
              notFound
            );
          },
          // getSupportingData describes one bundle, so API4 returns one row.
          supporting_data: function(crmApi4) {
            return crmApi4('VolunteerUtil', 'getSupportingData', {
              controller: 'VolunteerProject'
            }).then(function(result) {
              return result[0] || {};
            });
          },
          relationship_data: function(crmApi4, $route) {
            return crmApi4('VolunteerProjectContact', 'get', {
              select: ['contact_id', 'relationship_type_id'],
              where: [['project_id', '=', $route.current.params.projectId]]
            }).then(function(projectContacts) {
              var relationships = {};
              $(projectContacts).each(function (index, vpc) {
                if (!relationships.hasOwnProperty(vpc.relationship_type_id)) {
                  relationships[vpc.relationship_type_id] = [];
                }
                relationships[vpc.relationship_type_id].push(vpc.contact_id);
              });
              return relationships;
            });
          },
          // The location select binds to the map key, so flatten the rows to
          // an id => title map. The api4 AJAX endpoint only accepts a string
          // index, so this cannot be delegated to the server.
          location_blocks: function(crmApi4, $route) {
            return crmApi4('VolunteerProject', 'getLocationOptions', {
              projectId: $route.current.params.projectId
            }).then(function(rows) {
              var options = {};
              angular.forEach(rows, function(row) {
                options[row.id] = row.title;
              });
              return options;
            });
          }
        }
      };
      $routeProvider.when('/volunteer/manage/:projectId', projectRoute);
      $routeProvider.when('/volunteer/manage/:projectId/details', angular.copy(projectRoute));
    }
  );


  angular.module('volunteer').controller('VolunteerProject', function($scope, $sanitize, $location, $q, $route, $window, crmApi4, crmUiAlert, crmUiHelp, countries, project, relationship_data, supporting_data, location_blocks, volWorkflow) {

    /**
     * We use custom "dirty" logic rather than rely on Angular's native
     * functionality because we need to make a separate API call to
     * create/update the locBlock object (a distinct entity from the project)
     * if any of the locBlock fields have changed, regardless of whether other
     * form elements are dirty.
     */
    $scope.locBlockIsDirty = false;
    // Holder for the form controller. The <form> lives inside the workflow
    // shell's transclusion, so a bare name= would publish onto that child scope
    // and never reach this controller.
    $scope.forms = {};

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

    var relationships = {};

    var setFormDefaults = function() {
      if(project.id == 0) {
        // Cloning is used so that the defaults aren't subject to data-binding (i.e., by user action in the form)
        project = _.extend(_.clone(supporting_data.defaults), project);
        relationships = _.clone(supporting_data.defaults.relationships);

        if (CRM.vars['org.civicrm.volunteer'].entityTable) {
          project.entity_table = CRM.vars['org.civicrm.volunteer'].entityTable;
          project.entity_id = CRM.vars['org.civicrm.volunteer'].entityId;
        }
        // For an associated Entity, make the title of the project default to
        // the title of the entity
        if (CRM.vars['org.civicrm.volunteer'].entityTitle) {
          project.title = CRM.vars['org.civicrm.volunteer'].entityTitle;
        }
        // An event carries a campaign of its own, which is a better default for
        // its volunteer project than the site-wide
        // volunteer_project_default_campaign that supporting_data.defaults
        // supplied above. Precedence ends up: whatever the user picks in the
        // form > the event's campaign > the site-wide default.
        if (CRM.vars['org.civicrm.volunteer'].entityCampaignId) {
          project.campaign_id = CRM.vars['org.civicrm.volunteer'].entityCampaignId;
        }
      } else {
        relationships = relationship_data;
      }
    };

    setFormDefaults();

    // If the user doesn't have this permission, there is no sense in assigning
    // relationship data to the model or submitting it to the API.
    if (CRM.checkPerm('edit volunteer project relationships')) {
      project.project_contacts = relationships;
    }

    if (CRM.vars['org.civicrm.volunteer'] && CRM.vars['org.civicrm.volunteer'].context) {
      $scope.formContext = CRM.vars['org.civicrm.volunteer'].context;
    } else {
      $scope.formContext = 'standAlone';
    }

    switch ($scope.formContext) {
      case 'eventTab':
        var saveAndNextCallback = function (projectId) {
          CRM.$("body").trigger("volunteerProjectSaveComplete", projectId);
          volWorkflow.navigate(volWorkflow.projectPath(projectId, 'shifts'));
        };
        $scope.saveAndNextLabel = ts('Save and set up shifts');
        break;

      default:
        var saveAndNextCallback = function (projectId) {
          volWorkflow.navigate(volWorkflow.projectPath(projectId, 'shifts'));
        };
        $scope.saveAndNextLabel = ts('Save and set up shifts');
    }

    $scope.countries = countries;
    $scope.locationBlocks = location_blocks;
    $scope.locationBlocks[0] = "Create a new Location";
    $scope.locBlock = {};
    $scope.stateProvinces = [];
    $scope.stateProvincesLoading = false;
    var stateProvinceRequest = 0;

    var loadStateProvinces = function(countryId) {
      countryId = parseInt(countryId, 10);
      var request = ++stateProvinceRequest;
      if (!(countryId > 0)) {
        $scope.stateProvinces = [];
        $scope.stateProvincesLoading = false;
        return $q.resolve([]);
      }

      $scope.stateProvincesLoading = true;
      return crmApi4('StateProvince', 'get', {
        select: ['id', 'name'],
        where: [['country_id', '=', countryId]],
        orderBy: {name: 'ASC'},
        limit: 0
      }).then(function(states) {
        if (request === stateProvinceRequest) {
          $scope.stateProvinces = states;
        }
        return states;
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
        return [];
      }).finally(function() {
        if (request === stateProvinceRequest) {
          $scope.stateProvincesLoading = false;
        }
      });
    };

    $scope.countryChanged = function() {
      // A State/Province from the previous country is not a valid address and
      // can also cause the geocoder to resolve the wrong place.
      delete $scope.locBlock.address.state_province_id;
    };

    $scope.$watch('locBlock.address.country_id', function(countryId) {
      loadStateProvinces(countryId);
    });

    // If the user doesn't have this permission, there is no sense in keeping
    // profile data on the model or submitting it to the API.
    var nextProfileClientId = 1;
    if (!CRM.checkPerm('edit volunteer registration profiles')) {
      delete project.profiles;
    } else {
      project.profiles = project.profiles || [];
      $.each(project.profiles, function (key, data) {
        data._client_id = 'profile-' + nextProfileClientId++;
        if(data.module_data && typeof(data.module_data) === "string") {
          try {
            data.module_data = JSON.parse(data.module_data);
          } catch (e) {
            data.module_data = {audience: 'primary'};
          }
        }
        data.module_data = data.module_data || {audience: 'primary'};
        data.uf_group_id = parseInt(data.uf_group_id, 10);
      });
    }

    $scope.campaignFilter = CRM.volunteer.campaignFilter;
    // The BAOs guard their Campaign reads; the form did not, so with
    // CiviCampaign switched off it offered a picker that could never resolve
    // anything, for a field core itself hides.
    $scope.isCampaignEnabled = !!CRM.volunteer.isCampaignEnabled;
    $scope.relationship_types = supporting_data.relationship_types;
    $scope.phone_types = supporting_data.phone_types;
    $scope.supporting_data = supporting_data;
    project.is_active = (project.is_active == "1");
    $scope.project = project;
    $scope.profiles = $scope.project.profiles;
    $scope.relationships = $scope.project.project_contacts;
    $scope.showProfileBlock = CRM.checkPerm('edit volunteer registration profiles');
    $scope.showRelationshipBlock = CRM.checkPerm('edit volunteer project relationships');
    $scope.profileOptions = [];
    $scope.profileOptionsById = {};
    $scope.canManageProfiles = false;
    $scope.createProfileUrl = null;
    $scope.profileOptionsLoading = false;

    // Angular form controllers can be marked dirty while CiviCRM's enhanced
    // selects render their initial values. Compare the editable model with a
    // clean snapshot so navigation warnings reflect actual value changes, not
    // synthetic change events from widget initialization. Project status is
    // excluded because the header saves it immediately and independently.
    var detailsState = function() {
      var state = angular.copy($scope.project);
      delete state.is_active;
      delete state.location;
      return state;
    };
    var pristineDetailsState = detailsState();
    var pristineLocBlockState = angular.copy($scope.locBlock);

    $scope.workflow = {
      step: 'details',
      projectId: parseInt(project.id, 10) || 0,
      project: project,
      summary: {filled: 0, total: 0},
      beneficiaryNames: [],
      manualSave: true,
      autoSave: false,
      saving: false,
      formContext: $scope.formContext,
      isDirty: function() {
        return !!($scope.locBlockIsDirty || !angular.equals(pristineDetailsState, detailsState()));
      },
      // Called when the user confirms they want to discard their edits.
      markPristine: function() {
        pristineDetailsState = detailsState();
        pristineLocBlockState = angular.copy($scope.locBlock);
        $scope.locBlockIsDirty = false;
        if ($scope.forms.projectForm) {
          $scope.forms.projectForm.$setPristine();
        }
      }
    };

    var refreshWorkflowContext = function() {
      if (!$scope.workflow.projectId) {
        return $q.resolve();
      }
      return volWorkflow.loadContext($scope.workflow.projectId).then(function(context) {
        $scope.workflow.summary = context.summary;
        $scope.workflow.beneficiaryNames = context.beneficiaryNames;
      });
    };
    refreshWorkflowContext();

    /**
     * Refresh the picker and field summaries while preserving inactive or
     * missing profiles already assigned to the project.
     *
     * @returns {Promise}
     */
    $scope.refreshProfiles = function() {
      if (!$scope.showProfileBlock || $scope.profileOptionsLoading) {
        return $q.resolve();
      }

      var selectedIds = _.chain($scope.profiles)
        .pluck('uf_group_id')
        .map(function(id) { return parseInt(id, 10); })
        .filter(function(id) { return id > 0; })
        .uniq()
        .value();

      $scope.profileOptionsLoading = true;
      return crmApi4('VolunteerUtil', 'getProfiles', {
        profileIds: selectedIds
      }).then(function(result) {
        // getProfiles describes one bundle, so API4 returns one row.
        var values = result[0] || {};
        $scope.profileOptions = values.profiles || [];
        $scope.profileOptionsById = {};
        angular.forEach($scope.profileOptions, function(option) {
          $scope.profileOptionsById[option.id] = option;
        });
        $scope.canManageProfiles = !!values.can_manage;
        $scope.createProfileUrl = values.create_url || null;
      }).finally(function() {
        $scope.profileOptionsLoading = false;
      });
    };

    $scope.getProfileOption = function(profileId) {
      return $scope.profileOptionsById[parseInt(profileId, 10)] || null;
    };

    var profileWindow = angular.element(window);
    var refreshProfilesOnFocus = function() {
      $scope.$applyAsync($scope.refreshProfiles);
    };
    profileWindow.on('focus.civivolunteerProfiles', refreshProfilesOnFocus);
    $scope.$on('$destroy', function() {
      profileWindow.off('focus.civivolunteerProfiles', refreshProfilesOnFocus);
    });
    $scope.refreshProfiles();

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
        crmApi4("VolunteerProject", "getLocation", {
          id: $scope.project.loc_block_id,
          projectId: $scope.project.id
        }).then(function(locBlocks) {
          $scope.locBlockSkipDirtyCheck = true;
          $scope.locBlock = locBlocks[0];
          pristineLocBlockState = angular.copy($scope.locBlock);
          $scope.locBlockIsDirty = false;
        }, function(error) {
          CRM.alert(error && error.error_message);
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
    $scope.$watch('project.loc_block_id', function (newValue, oldValue) {
      // Angular invokes a watcher once when it is registered. The explicit
      // refresh above already requested the initial location, so this first
      // call is initialization rather than a user selection. A new-location
      // default (zero) still needs its address fields initialized below.
      if (newValue === oldValue && newValue != 0) {
        return;
      }
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
      // On its first invocation Angular passes the same value twice. Ignoring
      // it keeps an empty project location pristine on initial page load.
      if (newValue === oldValue) {
        return;
      }
      if ($scope.locBlockSkipDirtyCheck) {
        $scope.locBlockSkipDirtyCheck = false;
        pristineLocBlockState = angular.copy(newValue);
      }
      $scope.locBlockIsDirty = !angular.equals(newValue, pristineLocBlockState);
    }, true);

    $scope.addProfile = function() {
      $scope.profiles.push({
        "_client_id": "profile-" + nextProfileClientId++,
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

      // VOL-263: If the profiles aren't displayed, then there's no validation to do.
      if (!CRM.checkPerm('edit volunteer registration profiles')) {
        return valid;
      }

      if ($scope.profiles.length === 0) {
        CRM.alert(ts("You must select at least one Profile"), "Required");
        return false;
      }

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

    $scope.validate = function() {
      var valid = true;
      var relationshipsValid = validateRelationships();

      if(!$scope.project.title) {
        CRM.alert(ts("Title is a required field"), "Required");
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
    var validateRelationships = function() {
      var isValid = true;

      // VOL-263: If the relationships aren't displayed, then there's no validation to do.
      if (!CRM.checkPerm('edit volunteer project relationships')) {
        return isValid;
      }

      var requiredRelationshipTypes = ['volunteer_beneficiary', 'volunteer_manager', 'volunteer_owner'];

      _.each(requiredRelationshipTypes, function(value) {
        var thisRelType = _.find(supporting_data.relationship_types, function(relType) {
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
     * Helper function which serves as a harness for the API calls which
     * constitute form submission.
     *
     * TODO: The value of loc_block_id is a little too magical. "" means the
     * location is empty. "0" means the location is new, i.e., about to be
     * created. Any other int-like string represents the ID of an existing
     * location. This magic could perhaps be encapsulated in a function whose
     * job it is to return an operation: "create" or "update."
     *
     * @returns {Mixed} Returns project ID on success, boolean FALSE on failure.
     */
    var doSave = function() {
      if ($scope.validate()) {
        // When the loc block ID is an empty string, it indicates that the
        // location is blank. Thus, there is no loc block to create/edit.
        if ($scope.locBlockIsDirty && $scope.project.loc_block_id !== "") {
          // Save nested location data inside the project aggregate transaction.
          $scope.project.location = angular.copy($scope.locBlock);
        }
        else {
          // Do not resend location data left on the model by a prior save.
          delete $scope.project.location;
        }
        return _saveProject();
      } else {
        return $q.reject(false);
      }
    };

    /**
     * Helper function which saves a volunteer project and creates a flexible
     * need if appropriate.
     *
     * @returns {Mixed} Returns project ID on success.
     */
    var _saveProject = function() {
      // Zero is a bit of a magic number the form uses to connote creation of
      // a new location; this value should never be passed to the API.
      if ($scope.project.loc_block_id === "0") {
        delete $scope.project.loc_block_id;
      }

      var projectForApi = angular.copy($scope.project);
      angular.forEach(projectForApi.profiles || [], function(profile) {
        delete profile._client_id;
      });

      return crmApi4('VolunteerProject', 'commit', {values: projectForApi}).then(
        function(saved) {
          delete $scope.project.location;
          $scope.project.id = project.id = parseInt(saved[0].id, 10);
          $scope.workflow.projectId = $scope.project.id;
          $scope.workflow.markPristine();
          return $scope.project.id;
        },
        function(fail) {
          var text = ts('Your submission was not saved. Resubmitting the form is unlikely to resolve this problem. Please contact a system administrator.');
          var title = ts('A technical problem has occurred');
          crmUiAlert({text: text, title: title, type: 'error'});
        }
      );
    };

    $scope.saveAndDone = function () {
      $scope.workflow.saving = true;
      return doSave().then(function (projectId) {
        if (projectId) {
          crmUiAlert({text: ts('Changes saved successfully'), title: ts('Saved'), type: 'success'});
          if ($scope.formContext === 'eventTab') {
            CRM.$("body").trigger("volunteerProjectSaveComplete", projectId);
          }
          return refreshWorkflowContext().then(function() {
            if ($route.current.params.projectId == 0) {
              volWorkflow.navigate(volWorkflow.projectPath(projectId, 'details'));
            }
            return projectId;
          });
        }
      }).catch(angular.noop).finally(function() {
        $scope.workflow.saving = false;
      });
    };

    $scope.saveAndNext = function() {
      $scope.workflow.saving = true;
      return doSave().then(function(projectId) {
        if (projectId) {
          crmUiAlert({text: ts('Changes saved successfully'), title: ts('Saved'), type: 'success'});
          saveAndNextCallback(projectId);
        }
      }).catch(angular.noop).finally(function() {
        $scope.workflow.saving = false;
      });
    };

    $scope.cancel = function() {
      return volWorkflow.cancel($scope.workflow);
    };

    $scope.previewDescription = function() {
      CRM.alert($sanitize($scope.project.description || ''), _.escape($scope.project.title || ''), 'info', {expires: 0});
    };

    $scope.workflow.save = $scope.saveAndDone;

    var beforeUnload = function(event) {
      if (!$scope.workflow.isDirty()) {
        return;
      }
      event.preventDefault();
      event.returnValue = '';
      return '';
    };
    angular.element($window).on('beforeunload.civivolunteerProject', beforeUnload);
    $scope.$on('$destroy', function() {
      angular.element($window).off('beforeunload.civivolunteerProject', beforeUnload);
    });

    //Handle Refresh requests
    CRM.$("body").on("volunteerProjectRefresh", function() {
      $route.reload();
    });


  });

})(angular, CRM.$, CRM._);
