(function(ts) {
  CRM.$(function($) {

    // show commendations on page load
    var volunteerProjectID = $('#crm-log-entry-table').data('vid');
    fetchCommendations(volunteerProjectID);

    // when a new row gets added to the log table, update the display
    $('#crm-log-entry-table [id^=field_][id$=_contact_id]').on("change", function() {
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
          minHeight: 215,
          modal: false,
          resizable: false,
          width: 'auto'
        },
        onCancel: unlockContacts,
      }).on('crmFormLoad', function(){
        // override jQuery-UI button theming
        $('[data-identifier="_qf_Commendation_submit_delete"] .ui-icon' )
          .removeClass('ui-icon-check').addClass('ui-icon-trash');

        lockContacts();

        // prevent commendation layer from disappearing behind volunteer layer
        var commendation_layer = $(this);
        $('[aria-describedby="crm-ajax-dialog-1"] .blockUI').click(function(){
          commendation_layer.dialog('moveToTop');
        });
      }).on('crmFormSuccess', function(event, ajaxResponse){
        switch(ajaxResponse.action) {
          case CRM.constants.CRM_Core_Action.DELETE:
            CRM.status({success: ts('Commendation deleted')}).resolve();
            break;
          case CRM.constants.CRM_Core_Action.ADD:
            CRM.status({success: ts('Commendation created')}).resolve();
            break;
          case CRM.constants.CRM_Core_Action.UPDATE:
            CRM.status({success: ts('Commendation updated')}).resolve();
            break;
        }
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
        volunteer_project_id: vid
      }).done(function(result) {
        // wipe the slate clean
        $('#crm-log-entry-table').find('.volunteer-commendation').data('commendation_id', null)
          .removeClass('commended');
        // populate the table with the fetched data
        var addedRows = getAddedVolunteerRows();
        $.each(result.values, function(commendation_id, data){
          if (addedRows.hasOwnProperty(data.volunteer_contact_id)) {
            $.each(addedRows[data.volunteer_contact_id], function(){
              $(this).find('.volunteer-commendation').data('commendation_id', commendation_id).addClass('commended');
            });
          }
          var contactRows = getRowsByContactID(data.volunteer_contact_id);
          contactRows.each(function(){
            $(this).find('.volunteer-commendation').data('commendation_id', commendation_id)
              .addClass('commended');
          });
        });
        unlockContacts();
      });
    }

    /**
     * Returns rows that were added to the form using the "Add Volunteer" button.
     *
     * @returns {object} Properties are contact IDs. Values are arrays of jQuery objects.
     */
    function getAddedVolunteerRows() {
      var result = {};
      $('#crm-log-entry-table .selector-rows:not(".hiddenElement") input.crm-contact-ref').each(function() {
        var cid = $(this).select2('val');
        if (result[cid]) {
          result[cid].push($(this).closest('.crm-grid-row'));
        } else {
          result[cid] = [$(this).closest('.crm-grid-row')];
        }
      });
      return result;
    }
  });
}(CRM.ts('org.civicrm.volunteer')));