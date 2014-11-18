// http://civicrm.org/licensing
CRM.$(function($) {

  /**
   * Used to show/hide the Shift dropbox
   *
   * @param tog int Zero to hide; anything else to show.
   */
  function toggleShiftSelection(tog) {
    if (tog === 0) {
      $('.volunteer_shift-section').hide();
      $('#volunteer_need_id').val('');
    } else {
      $('.volunteer_shift-section').show();
    }
  }

  /**
   * Update Shift options on change of Volunteer Role
   */
  function filterShifts(event) {
    var selected_role = $('#volunteer_role_id').val();

    // remove all options
    $('#volunteer_need_id').empty();

    // add only options for this role
    var shifts = event.data;
    shifts.each(function() {
      if (selected_role == $(this).data('role')) {
        $('#volunteer_need_id').append($(this));
      }
    });

    // if there are no shift options, hide the shift select box altogether
    var shift_count = $('#volunteer_need_id option').length;
    toggleShiftSelection(shift_count);
  }

  // capture all shifts as they were on page load; this is a static list
  var shifts = $('#volunteer_need_id option');

  // wire up our functions...
  $('#volunteer_role_id').change(shifts, filterShifts);
  $('#volunteer_role_id').trigger('change');

});