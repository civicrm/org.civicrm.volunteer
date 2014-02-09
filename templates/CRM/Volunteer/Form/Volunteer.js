// http://civicrm.org/licensing
cj(function($) {
  // move the help icon into the label for the beneficiary
  $("label[for=volunteer_target_contact_1]").append("&nbsp;")
  $('#org_civicrm_volunteer-event_tab_config .org_civicrm_volunteer-beneficiary_help .helpicon').appendTo(
    "label[for=volunteer_target_contact_1]"
  );
});