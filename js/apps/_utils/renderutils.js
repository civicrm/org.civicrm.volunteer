/* FROM: https://github.com/civicrm/civihr/blob/master/hrjob/js/renderutil.js */

CRM.volunteerApp.module('RenderUtil', function(RenderUtil, volunteerApp, Backbone, Marionette, $, _){
  CRM.volunteerApp.on("initialize:before", function(){
    RenderUtil._select = _.template($('#renderutil-select-template').html());
    RenderUtil.select = function(args) {
      var defaults = {
        selected: ''
      };
      return RenderUtil._select(_.extend(defaults, args));
    };
    /*RenderUtil.standardButtons = _.template($('#renderutil-standardButtons-template').html());
    RenderUtil.required = _.template($('#common-required-template').html());
    RenderUtil.toggle = _.template($('#renderutil-toggle-template').html());*/
  });
});