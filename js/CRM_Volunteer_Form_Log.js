CRM.$(function($) {
  $('#addMoreVolunteer').click(function(e){
    $('div.hiddenElement:first').show().removeClass('hiddenElement').addClass('crm-grid-row').css('display', 'table-row');
    e.preventDefault();
  });

  // Add ability to remove a row. Because "adding" just unhides the first hidden
  // row, it is more sensible to remove the row rather than clear and hide it.
  // Otherwise, the "added" row could show up anywhere in the table rather than
  // at the bottom as expected.
  $('.crm-vol-remove-row').click(function(e) {
    e.preventDefault();
    var row = $(this).closest('.crm-grid-row');
    // Animation is applied to children because elemnents with display:table don't slideUp.
    row.find('.crm-grid-cell').slideUp(100, function(){
      row.remove();
    });
  });
});
