// http://civicrm.org/licensing
cj(function($) {
  var url = CRM.url('civicrm/volunteer/signup', {
    reset: 1,
    vid: CRM.volunteer.project_id
  });
  var btn = '<a href="' + url + '" title="' + CRM.volunteer.button_text
    + '" class="button crm-volunteer_signup-button"><span>'
    + CRM.volunteer.button_text + '</span></a>';

  // check to see if the online registration button is present
  if ($('.register_link-section').length > 0) {
    $('.register_link-section').append(btn);
  } else {
    // insert button before calendar, ical, etc
    $('.action-link').before(
      '<div class="action-link section register_link-section register_link-bottom">'
      + btn + '</div>'
    );
    // insert button at top
    $('.event_summary-section').prepend(
      '<div class="action-link section register_link-section register_link-top">'
      + btn + '</div>'
    );
  }
});