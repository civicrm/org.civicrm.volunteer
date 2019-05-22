(function (angular, $, _) {

  angular.module('volunteer').config(function ($routeProvider) {
    $routeProvider.when('/volunteer/appeals', {
      controller: 'VolApplsCtrl',
      // update the search params in the URL without reloading the route     
      templateUrl: '~/volunteer/VolApplsCtrl.html'
    });
  });

  angular.module('volunteer').controller('VolApplsCtrl', function ($route, $scope,crmApi) {    
      var ts = $scope.ts = CRM.ts('org.civicrm.volunteer');
      $scope.search="";           
      $scope.currentTemplate = "~/volunteer/AppealList.html"; //default view is list view
      $scope.totalRec;
      $scope.currentPage = 1;
      $scope.pageSize = 2; 

      //Change reult view
      $scope.changeview = function(tpl){
        $scope.currentTemplate = tpl;        
      }

      //Get appeal data with search text and/or pagination
      getAppeals = function (currentPage,search) {       
        let params={};
        currentPage?params.page_no=currentPage:null;        
        search?params.search_appeal=search:null;
         return crmApi('VolunteerAppeal', 'getsearchresult', params)
         .then(function (data) {            
              let projectAppeals=[];              
              for(let key in data.values.appeal) {
                projectAppeals.push(data.values.appeal[key]);
              }            
              $scope.appeals=projectAppeals;
              $scope.totalRec=data.values.total_appeal;              
              $scope.numberOfPages= Math.ceil($scope.totalRec/$scope.pageSize);            
            },function(error) {
                if (error.is_error) {
                    CRM.alert(error.error_message, ts("Error"), "error");
                } else {
                    return error;
                }
            }); 

      }  

     //Loading  list on first time  
     getAppeals($scope.currentPage,$scope.search);  //

    //update current page to previouse and get result data
     $scope.prevPageData=function(){
      $scope.currentPage=$scope.currentPage-1;
      getAppeals($scope.currentPage,$scope.search);
     }

    //update current page to next and get result data
     $scope.nextPageData=function(){     
      $scope.currentPage=$scope.currentPage+1;      
      getAppeals($scope.currentPage,$scope.search);
     }

    //reset page count and search data
     $scope.searchRes=function(){
       $scope.currentPage = 1;
       getAppeals(null,$scope.search);
     }

  });

})(angular, CRM.$, CRM._);
