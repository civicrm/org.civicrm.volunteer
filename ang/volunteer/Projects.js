(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/manage', {
        controller: 'VolunteerProjects',
        templateUrl: '~/volunteer/Projects.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          projectData: function(crmApi) {
            return crmApi('VolunteerProject', 'get', {"sequential": 1});
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('volunteer').controller('VolunteerProjects', function($scope, crmApi, crmStatus, crmUiHelp, projectData, $location) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('volunteer');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/volunteer/Projects'}); // See: templates/CRM/volunteer/Projects.hlp

    // We have myContact available in JS. We also want to reference it in HTML.

    $scope.searchParams = {};
    $scope.projects = projectData.values;
    $scope.batchAction = "";
    $scope.allSelected = false;
    $scope.needBase = CRM.url("civicrm/volunteer/need");
    $scope.assignBase = CRM.url("civicrm/volunteer/assign");

    $scope.showLogHours = function() {
      var url = CRM.url("civicrm/volunteer/loghours", "reset=1&action=add&vid=" + this.project.id);
      var settings = {"dialog":{"width":"85%", "height":"80%"}};
      CRM.loadForm(url, settings);
    };

    $scope.batchActions = {
      "enable": {
        label: ts("Enable"),
        run: function() {
          CRM.confirm(ts("Are you sure you want to Enable the selected Projects?"))
            .on('crmConfirm:yes', function() {
              $.each($scope.projects, function (index, project) {
                if (project.selected) {
                  project.is_active = 1;
                  crmApi("VolunteerProject", "create", {id: project.id, is_active: project.is_active});
                }
              });
              $scope.$apply();
            });
        }
      },
      "disable": {
        label: ts("Disable"),
        run: function() {
          CRM.confirm(ts("Are you sure you want to Disable the selected Projects?"))
            .on('crmConfirm:yes', function() {
              $.each($scope.projects, function (index, project) {
                if (project.selected) {
                  project.is_active = 0;
                  crmApi("VolunteerProject", "create", {id: project.id, is_active: project.is_active});
                }
              });
              $scope.$apply();
            });
        }
      },
      "delete": {
        label: ts("Delete"),
        run: function() {
          //todo: Implement delete.
          console.log("Batch Delete");
        }
      }
    };
    $scope.runBatch = function() {
      if(!!$scope.batchAction) {
        $scope.batchActions[$scope.batchAction].run();
      }
    };
    $scope.watchSelected = function() {
      var all = true;
      $.each($scope.projects, function(index, project) {
        all = (all && project.selected);
      });
      $scope.allSelected = all;
    };
    $scope.selectAll = function() {
      if($scope.allSelected) {
        $.each($scope.projects, function(index, project) {
          project.selected = true;
        });
      } else {
        $.each($scope.projects, function(index, project) {
          project.selected = false;
        });
      }
    };
  });

})(angular, CRM.$, CRM._);
