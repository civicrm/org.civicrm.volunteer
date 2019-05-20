(function(angular, $, _) {

  angular.module('volunteer').config(function($routeProvider) {
      $routeProvider.when('/volunteer/manage_appeals', {
        controller: 'VolunteerAppeal',
        templateUrl: '~/volunteer/Appeals.html',
         resolve: {
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
            projectAppealsData: function(crmApi) {
            return crmApi('VolunteerAppeal', 'get', {
              //id:1
            }).then(function (data) {              
              let projectAppeals=[];              
              for(let key in data.values) {
                projectAppeals.push(data.values[key]);
              }
              return projectAppeals;              
            },function(error) {
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
          }

         }
      });
    }
  );

  // TODO for VOL-276: Remove reference to beneficiaries object, based on deprecated API.
  angular.module('volunteer').controller('VolunteerAppeal', function ($scope,crmApi,projectData,projectAppealsData,beneficiaries) {
     var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
    //var hs = $scope.hs = crmUiHelp({file: 'CRM/volunteer/Projects'}); // See: templates/CRM/volunteer/Projects.hlp

     $scope.abc="pqr";
     $scope.appeals = projectAppealsData;
     $scope.beneficiaries = beneficiaries;
     $scope.projects= projectData;
    
     //$scope.projects.indexOf(createItem.artNr)
         /**
     * Utility for stringifying locations which may have varying levels of detail.
     *
     * @param array project
     *   An item from the projectData provider.
     * @return string
     *   With HTML tags.
     */
    $scope.formatLocation = function (project) {
      var projDetails;
      angular.forEach($scope.projects, function (obj, key) {                
               if(obj.id==project){
                projDetails=$scope.projects[key];
               }
      }); 
      project= projDetails;      
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
      var projDetails;
      angular.forEach($scope.projects, function (obj, key) {
               if(obj.id==project){
                projDetails=$scope.projects[key];
               }
      });
      project=projDetails;
      var displayNames = [];

      _.each(project.beneficiaries, function (item) {
        displayNames.push($scope.beneficiaries[item].display_name);
      });

      return displayNames.sort().join('<br />');
    };

  });
})(angular, CRM.$, CRM._);
