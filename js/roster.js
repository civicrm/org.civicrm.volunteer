CRM.$(function($) {
  function getDialogContent($control) {
    return $control.closest('.ui-dialog-content');
  }

  $('body')
    .off('click.crmVolunteerRoster', '.crm-vol-roster-print')
    .on('click.crmVolunteerRoster', '.crm-vol-roster-print', function(event) {
      event.preventDefault();

      var $dialogContent = getDialogContent($(this));
      var printUrl = $dialogContent.length
        ? $dialogContent.closest('.ui-dialog').find('.crm-dialog-titlebar-print').attr('href')
        : null;

      if (printUrl) {
        window.open(printUrl, '_blank', 'noopener');
      }
      else {
        window.print();
      }
    })
    .off('click.crmVolunteerRoster', '.crm-vol-modal-closer')
    .on('click.crmVolunteerRoster', '.crm-vol-modal-closer', function(event) {
      event.preventDefault();

      var $dialogContent = getDialogContent($(this));
      if ($dialogContent.length) {
        $dialogContent.dialog('close');
      }
    });
});
