// http://civicrm.org/licensing
cj(function($) {
  var rowCnt = 1;

  $('input[name^="primary_contact_select_id["]').each(function(){
    if ($(this).val()){
      var dataUrl = CRM.url('civicrm/ajax/rest',
        'className=CRM_Contact_Page_AJAX&fnName=getContactList&json=1&context=contact&id=' + $(this).val());
      $.ajax({
        url     : dataUrl,
        async   : false,
        success : function(html){
          htmlText = html.split( '|' , 2);
          $('#primary_contact_' + rowCnt).val(htmlText[0]);
          rowCnt++;
        }
      });
    }
  });

});
