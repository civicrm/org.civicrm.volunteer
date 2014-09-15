CRM.$(function($) {

  // show commendations on page load
  var volunteerProjectID = $('#crm-log-entry-table').data('vid');
  fetchCommendations(volunteerProjectID);

  // when a new row gets added to the log table, update the display
  $('#crm-log-entry-table input.crm-contact-ref').on("change", function() {
      fetchCommendations(volunteerProjectID);
  });

  $('.volunteer-commendation').click(function(e){
    // prevent unnecessary AJAX calls by disabling additional clicks for the same contact
    if ($(this).not('.active').length) {
      closePopup();

      // lock commendation icons for this contact
      var cid = getContactIDOnThisRow($(this));
      getRowsByContactID(cid).each(function() {
        $(this).find('.volunteer-commendation').addClass('active');
      });

      var url_args = {
        cid: cid,
        vid: volunteerProjectID
      };

      var activityID = $(this).data('commendation_id');
      if (activityID) {
        url_args.aid = activityID;
      }
      var url = CRM.url('civicrm/volunteer/commendation', url_args);
      var position = $(this).offset();
      var coords = {
        left: position.left,
        top: position.top + $(this).height()
      };

      showPopup(url, coords);
    }
  });

/**
 * @param {string} url The URL to load in the popup
 * @param {object} coords An object containing the properties top and left,
 *  which are numbers indicating the new top and left coordinates for the elements.
 * @returns {undefined}
 */
  function showPopup(url, coords) {
    if ($('#org_civicrm_volunteer-commendation_popup').length === 0) {
      $('body').append('<div id="org_civicrm_volunteer-commendation_popup"></div>');
      CRM.loadForm(url, {
        onCancel: closePopup,
        target: '#org_civicrm_volunteer-commendation_popup'
      }).on('crmFormSuccess', function(){
        closePopup();
        fetchCommendations(volunteerProjectID);
      });
    } else {
      $('#org_civicrm_volunteer-commendation_popup').removeClass('hiddenElement')
        .crmSnippet('option', 'url', url).crmSnippet('refresh');
    }

    $('#org_civicrm_volunteer-commendation_popup').offset(coords);
  }

  function closePopup() {
    // remove "active" lock for all commendations
    $('.volunteer-commendation').removeClass('active');

    $('#org_civicrm_volunteer-commendation_popup').addClass('hiddenElement');
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