CRM.$(function($) {

  // show commendations on page load
  var volunteerProjectID = $('#crm-log-entry-table').data('vid');
  fetchCommendations(volunteerProjectID);

  // when a new row gets added to the log table, update the display
  $('#crm-log-entry-table input.crm-contact-ref').on("change", function() {
      fetchCommendations(volunteerProjectID);
  });

  $('.volunteer-commendation').click(function(e){
    var url_args = {
      cid: getContactIDOnThisRow($(this)),
      vid: volunteerProjectID
    };

    var activityID = $(this).data('commendation_id');
    if (activityID) {
      url_args.aid = activityID;
    }
    var url = CRM.url('civicrm/volunteer/commendation', url_args);

    showPopup(url);
  });

/**
 * @param {string} url The URL to load in the popup
 * @returns {undefined}
 */
  function showPopup(url) {
    CRM.loadForm(url, {
      dialog: {
        close: unlockContacts,
        draggable: false,
        height: 'auto',
        modal: false,
        resizable: false,
        width: 'auto'
      },
      onCancel: unlockContacts,
    }).on('crmLoad', function(){
      lockContacts();
    }).on('crmFormSuccess', function(){
      unlockContacts();
      fetchCommendations(volunteerProjectID);
    });
  }

  function lockContacts() {
    $('.CRM_Volunteer_Form_Log').closest('.ui-dialog').block();
    return true;
  }

  function unlockContacts() {
    $('.CRM_Volunteer_Form_Log').closest('.ui-dialog').unblock();
    return true;
  }

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
    lockContacts();
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
      unlockContacts();
    });
  }
});