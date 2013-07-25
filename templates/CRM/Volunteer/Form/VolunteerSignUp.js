// http://civicrm.org/licensing
cj(function($) {

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
  function filterShifts() {
    var role = $('#volunteer_role_id').val();

    // hide all options
    $('#volunteer_need_id option').hide();

    // show options for this role
    $('#volunteer_need_id option[data-role="' + role + '"]').show();

    // jQuery's :visible pseudo-class doesn't work for options in some browsers,
    // so we resort to this to select the first visible option
    var i = 0;
    $('#volunteer_need_id option').each(function(){
      if ($(this).css('display') != 'none') {
        i++;
        $(this).prop('selected', true);
        return false;
      }
    });

    // if there are no visible shift options, hide the shift select box altogether
    toggleShiftSelection(i);
  }

  // wire up our functions...
  $('#volunteer_role_id').change(filterShifts);
  $(document).ready(filterShifts);

});