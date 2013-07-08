// http://civicrm.org/licensing
cj(function($) {

  $('#addMoreVolunteer').click(function(){
    $('div.hiddenElement div:first:parent').parent().show().removeClass('hiddenElement').addClass('crm-grid-row').css('display', 'table-row');

    return false;
  });

});
