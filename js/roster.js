// if this button appears in a modal, close the modal when clicked
CRM.$(function($) {
  $('body').on('click', '.crm-vol-modal-closer', function() {
    $(this).closest('.crm-ajax-container').dialog('close');
  });
});