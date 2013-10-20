// http://civicrm.org/licensing
/*
 * On load, the autocomplete select box gets focus. We use this event to
 * set the default target contact to the domain organization. We immediately
 * unbind the event so that user-initiated focus events don't overwrite the
 * user's value.
 */
cj(function($) {
  var autocompleteSelector = '#volunteer_target_contact_1';
  var contactIDSelector = '[name="volunteer_target_contact_select_id[1]"]';

  var label = CRM.volunteer.domain.display_name;
  if (CRM.volunteer.domain.email) {
    label += ' :: ' + CRM.volunteer.domain.email;
  }

  $(document).on('focus', autocompleteSelector, function(){
    $(document).off('focus', autocompleteSelector);
    $(autocompleteSelector).val(label);
    $(contactIDSelector).val(CRM.volunteer.domain.contact_id);
  });
});
