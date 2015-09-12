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
  angular.module('volunteer').controller('VolunteerProject', function($scope, crmApi, crmStatus, crmUiHelp, crmProfiles, project, is_entity, profile_status) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/Volunteer/Volunteer'}); // See: templates/CRM/volunteer/Project.hlp

    project.profileCount = 0;

    $scope.profile_status = profile_status;
    $scope.is_entity = is_entity;
    $scope.project = project;

    $scope.addProfile = function() {
      angular.element('#org_civicrm_volunteer-sign-up-profiles .crm-button-add-profile').hide();

      urlPath = CRM.url('civicrm/volunteer/manage/includeprofile', { profileCount : project.profileCount, snippet: 4 } ) ;
      project.profileCount++;

      //This will be replaced by pushing items onto a list.
      angular.element('#org_civicrm_volunteer-sign-up-profiles').append('<div class="additional_profile"></div>');
      var $el = angular.element('#org_civicrm_volunteer-sign-up-profiles .additional_profile:last');
      $el.load(urlPath, function() { $el.trigger('crmLoad') });

      // if profiles are being added that means more than one is displayed, in
      // which case all "remove" links should be displayed
      //$('#org_civicrm_volunteer-sign-up-profiles .crm-button-rem-profile').show();
    }
  });

})(angular, CRM.$, CRM._);
