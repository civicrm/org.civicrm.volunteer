CRM.$(function($) {

  // show commendations on page load
  var volunteerProjectID = $('#crm-log-entry-table').data('vid');
  fetchCommendations(volunteerProjectID);

  // when a new row gets added to the log table, update the display
  $('#crm-log-entry-table input.crm-contact-ref').on("change", function() {
      fetchCommendations(volunteerProjectID);
  });

  // TODO: finish this function
  $('.volunteer-commendation').click(function(){
    var contactID = getContactIDOnThisRow($(this));
    CRM.loadForm('/civicrm/todo/unbuilt/form', {
      target: '#org_civicrm_volunteer-commendation_popup'
    });
  });

  /**
   * @param {Element} el A row in the log hours table
   * @returns {Integer} CiviCRM contact ID
   */
  function getContactIDOnThisRow(el) {
    var row = el.closest('.crm-grid-row');
    return row.find("input[name$='[contact_id]']").val();
  }

  /**
   * @param {Integer} id CiviCRM contact ID
   * @returns {jQuery Object} Row elements representing the given contact
   */
  function getRowsByContactID(id) {
    return $('#crm-log-entry-table').find("input[name$='[contact_id]'][value='" + id + "']").closest('.crm-grid-row');
  }

  /**
   * Fetches commendations from the server and updates the display accordingly
   *
   * @param {Integer} vid Volunteer project ID
   */
  function fetchCommendations(vid) {
    $('#crm-log-entry-table').block();
    CRM.api3('VolunteerCommendation', 'get', {
      "volunteer_project_id": vid
    }).done(function(result) {
      $.each(result.values, function(commendation_id, data){
        var contactRows = getRowsByContactID(data.volunteer_contact_id);
        contactRows.each(function(){
          $(this).find('.volunteer-commendation').data('commendation_id', commendation_id)
            .addClass('commended');
        });
      });
      $('#crm-log-entry-table').unblock();
    });
  }
});