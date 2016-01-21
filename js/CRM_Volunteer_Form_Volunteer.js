CRM.$(document).ready(function(event) {

  CRM.origJQuery = window.jQuery; window.jQuery = CRM.$;
  
  if (CRM.VolunteerAngularSettings.Hash) {
    location.hash = CRM.VolunteerAngularSettings.Hash;
  }
});