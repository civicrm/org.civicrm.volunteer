(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/manage/:projectId', {
        controller: 'VolunteerProject',
        templateUrl: '~/volunteer/Project.html',
        resolve: {
          project: function(crmApi, $route) {
            return crmApi('VolunteerProject', 'getsingle', {
              id: $route.current.params.projectId
            });
          },
          relationship_types: function(crmApi) {
            return crmApi('OptionValue', 'get', {
              "sequential": 1,
              "option_group_id": "volunteer_project_relationship"
            });
          },
          relationship_data: function(crmApi, $route) {
            return crmApi('VolunteerProjectContact', 'get', {
              "sequential": 1,
              "project_id": $route.current.params.projectId
            });
          },
          profiles: function(crmApi, $route) {
            return crmApi('UFJoin', 'get', {
              "sequential": 1,
              "module": "CiviVolunteer",
              "entity_id": $route.current.params.projectId
            });
          },
          is_entity: function() { return false; },
          profile_status: function(crmProfiles) {
            return crmProfiles.load();
          }
        }
      }).when('/volunteer/manage/:entityTable/:entityId', {
        controller: 'VolunteerProject',
        templateUrl: '~/volunteer/Project.html',
        resolve: {
          project: function(crmApi, $route) {
            return crmApi('VolunteerProject', 'getsingle', {
              entity_table: $route.current.params.entityId,
              entity_id: $route.current.params.entityTable
            });
          },
          is_entity: function() { return true; },
          profile_status: function(crmProfiles) {
            return crmProfiles.load();
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  angular.module('volunteer').controller('VolunteerProject', function($scope, crmApi, crmStatus, crmUiHelp, crmProfiles, project, is_entity, profile_status, relationship_types, relationship_data, profiles) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/Volunteer/Form/Volunteer'}); // See: templates/CRM/volunteer/Project.hlp
    project.profileCount = 0;


    var relationships = {};
    $(relationship_data.values).each(function(index, relationship) {
      if (!relationships.hasOwnProperty(relationship.relationship_type_id)) {
        relationships[relationship.relationship_type_id] = [];
      }
      relationships[relationship.relationship_type_id].push(relationship.contact_id);
    });

    $scope.relationships = relationships;
    $scope.profiles = profiles.values;
    $scope.relationship_types = relationship_types.values;
    $scope.profile_status = profile_status;
    $scope.is_entity = is_entity;
    project.is_active = (project.is_active === "1");
    $scope.project = project;

    $scope.addProfile = function() {
      $scope.profiles.push({"is_active": "1", "module": "CiviVolunteer", "entity_id": project.id});
    }
  });

})(angular, CRM.$, CRM._);
