// http://civicrm.org/licensing
CRM.volunteerApp = new Backbone.Marionette.Application();
CRM.volunteerApp.addRegions({
  dialogRegion: '#crm-volunteer-dialog'
});

cj(function($) {
  // Wait for all scripts to load before starting app
  CRM.volunteerApp.start();

  function dialogSettings($el) {
    var settings = {
      modal: true,
      title: $el.text(),
      width: '85%',
      height: parseInt($(window).height() * .80)
    };
    if ($el.is('a.crm-volunteer-popup')) {
      settings['close'] = function() {
        CRM.volunteerApp.module(CRM.volunteerApp.tab).stop();
      }
    }
    return settings;
  }

  // FIXME: This could be rendered and managed by the volunteerApp for more internal consistency
  $("#crm-container").on('click', 'a.crm-volunteer-popup', function() {
    CRM.volunteerApp.tab = $(this).data('tab');
    CRM.volunteerApp.project_id = $(this).data('vid');
    $('#crm-volunteer-dialog').dialog(dialogSettings($(this)));
    CRM.volunteerApp.module(CRM.volunteerApp.tab).start();
    return false;
  });

  // This is a server-side form, not rendered with backbone
  $("#crm-container").on('click', '.crm-event-manage-volunteer-form-block a.button:not([href=#])', function() {
    var url = $(this).attr('href') + '&snippet=6';
    $('#crm-volunteer-dialog').html('<div class="crm-loading-element">' + ts('Loading') + '...</div>').dialog(dialogSettings($(this)));
    $.getJSON(url, function(data) {
      $('#crm-volunteer-dialog').html(data.content);
      $('#addMoreVolunteer').click(function() {
        $('div.hiddenElement div:first:parent').parent().show().removeClass('hiddenElement').addClass('crm-grid-row').css('display', 'table-row');
        return false;
      });
      $('#_qf_Log_cancel').click(function() {
        $('#crm-volunteer-dialog').dialog('close');
        return false;
      });
      $('#Log').ajaxForm({
        dataType:'json',
        url: url,
        beforeSubmit: function(arr, $form, options) {
          $form.block();
        },
        success: function(data) {
          CRM.alert(data.message, ts('Saved'), 'success');
          $('#crm-volunteer-dialog').dialog('close');
        }
      });
    });
    return false;
  });
});

// Workaround for plugin namespace collision
cj.widget("civicrm.crmVolMenu", cj.ui.menu, {});
