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
                // default new projects to active
                is_active: "1",
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
            if ($route.current.params.projectId == 0) {
              //return {"values": []};
              return crmApi('VolunteerProject', 'defaults', {});
            } else {
              return crmApi('VolunteerProjectContact', 'get', {
                "sequential": 1,
                "project_id": $route.current.params.projectId
              });
            }
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


  angular.module('volunteer').controller('VolunteerProject', function($scope, $location, $q, crmApi, crmUiAlert, crmUiHelp, countries, project, profile_status, campaigns, relationship_data, supporting_data, location_blocks, volBackbone) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/Volunteer/Form/Volunteer'}); // See: templates/CRM/volunteer/Project.hlp


    var relationships = {};
    if(project.id == 0) {
      relationships = relationship_data['values'];
      var originalRelationships = {};
    } else {
      $(relationship_data.values).each(function (index, relationship) {
        if (!relationships.hasOwnProperty(relationship.relationship_type_id)) {
          relationships[relationship.relationship_type_id] = [];
        }
        relationships[relationship.relationship_type_id].push(relationship.contact_id);
      });
      var originalRelationships = _.clone(relationships);
    }


    $scope.countries = countries;
    $scope.locationBlocks = location_blocks.values;
    $scope.locationBlocks[0] = "Create a new Location";
    $scope.locBlock = {};
    if (_.isEmpty(project.profiles)) {
      project.profiles = [{
        "is_active": "1",
        "module": "CiviVolunteer",
        "entity_table": "civicrm_volunteer_project",
        "weight": "1",
        "uf_group_id": supporting_data.values.default_profile
      }];
    }
    $scope.relationships = relationships;
    $scope.campaigns = campaigns;
    $scope.relationship_types = supporting_data.values.relationship_types;
    $scope.phone_types = supporting_data.values.phone_types;
    $scope.supporting_data = supporting_data.values;
    $scope.profile_status = profile_status;
    project.is_active = (project.is_active == "1");
    $scope.project = project;
    $scope.profiles = $scope.project.profiles;


    $scope.refreshLocBlock = function() {
      if (!!$scope.project.loc_block_id) {
        crmApi("VolunteerProject", "getlocblockdata", {
          "return": "all",
          "sequential": 1,
          "id": $scope.project.loc_block_id
        }).then(function(result) {
          if(!result.is_error) {
            $scope.locBlock = result.values[0];
          } else {
            CRM.alert(result.error);
          }
        });
      }
    };
    //Refresh as soon as we are up and running because we don't have this data yet.
    $scope.refreshLocBlock();

    $scope.locBlockChanged = function() {
      if($scope.project.loc_block_id == 0) {
        $scope.locBlock = {
          address: {
            country: _.findWhere(countries, {is_default: "1"}).id
          }
        };

        $("#crm-vol-location-block .crm-accordion-body").slideDown({complete: function() {
          $("#crm-vol-location-block .crm-accordion-wrapper").removeClass("collapsed");
        }});
      } else {
        //Load the data from the server.
        $scope.refreshLocBlock();
      }
    };
    $scope.locBlockDirty = function() {
      $scope.locBlockIsDirty = true;
    };

    $scope.addProfile = function() {
      $scope.profiles.push({
        "entity_table": "civicrm_volunteer_project",
        "is_active": "1",
        "module": "CiviVolunteer",
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
    $scope.validateProject = function() {
      var valid = true;


      if(!$scope.project.title) {
        CRM.alert(ts("Title is a required field"), "Required");
        valid = false;
      }

      if ($scope.profiles.length === 0) {
        CRM.alert(ts("You must select at least one Profile"), "Required");
        valid = false;
      }
      $.each($scope.profiles, function(index, profile) {
        if(!profile.uf_group_id) {
          CRM.alert(ts("Please select at least one profile, and remove empty selections"), "Required");
          valid = false;
        }
      });


      //Do some validation here...

      return valid;
    };

    /**
     * Helper function which actually saves a form submission.
     *
     * @returns {Mixed} Returns project ID on success, boolean FALSE on failure.
     */
    saveProject = function() {
      if ($scope.validateProject()) {

        if($scope.project.loc_block_id == 0) {
          $scope.locBlockIsDirty = true;
        }

        return crmApi('VolunteerProject', 'create', $scope.project).then(function(result) {
          var projectId = result.values.id;

          //Save the LocBlock
          if($scope.locBlockIsDirty) {
            $scope.locBlock.entity_id = projectId;
            $scope.locBlock.id = result.values.loc_block_id;
            crmApi('VolunteerProject', 'savelocblock', $scope.locBlock);
          }


          //save the relationships
          var rPromises = [];
          $.each($scope.relationships, function(rType, rData) {
            if(typeof(rData) === "string") {
              rData = rData.split(",");
            }
            $.each(rData, function (index, contactId) {
              if(contactId && (!originalRelationships.hasOwnProperty(rType) || originalRelationships[rType].indexOf(contactId) === -1)) {
                rPromises.push(crmApi("VolunteerProjectContact", "create", {project_id: projectId, relationship_type_id: rType, contact_id: contactId}));
              }
            });
          });

          $q.all(rPromises).then(function(x) {
            //Remove the extraneous relationships
            crmApi('VolunteerProjectContact', 'get', {
              "project_id": projectId
            }).then(function(result) {
              if (result.count > 0) {

                var rels = {};
                $.each($scope.relationships, function (rType, rTypeData) {
                  if(typeof(rTypeData) === "string") {
                    rels[rType] = rTypeData.split(",");
                  } else {
                    rels[rType] = rTypeData;
                  }
                });

                $.each(result.values, function (index, relation) {
                  if (!rels.hasOwnProperty(relation.relationship_type_id) || rels[relation.relationship_type_id].indexOf(relation.contact_id) === -1) {
                    crmApi("VolunteerProjectContact", "delete", {"id": relation.id});
                  }
                });
              }
            });
          });

          return projectId;
        });
      } else {
        return false;
      }
    };

    $scope.saveAndDone = function() {
      saveProject().then(function(projectId) {
        if (projectId) {
          crmUiAlert({text: ts('Changes saved successfully'), title: ts('Saved'), type: 'success'});
          $location.path( "/volunteer/manage" );
        }
      });
    };

    $scope.saveAndNext = function() {
      saveProject().then(function(projectId) {
        if (projectId) {
          crmUiAlert({text: ts('Changes saved successfully'), title: ts('Saved'), type: 'success'});

          volBackbone.load().then(function() {
            CRM.volunteerPopup(ts('Define Needs'), 'Define', projectId);
            $location.path( "/volunteer/manage" );
          });
        }
      });
    };

    $scope.cancel = function() {
      $location.path( "/volunteer/manage" );
    };
  });

})(angular, CRM.$, CRM._);
