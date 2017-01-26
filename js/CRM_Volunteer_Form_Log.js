CRM.$(function($) {
  $('#addMoreVolunteer').click(function(e){
    $('div.hiddenElement:first').show().removeClass('hiddenElement').addClass('crm-grid-row').css('display', 'table-row');
    e.preventDefault();
  });
});
