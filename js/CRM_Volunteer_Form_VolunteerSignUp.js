(function(ts) {
  CRM.$(function($) {
    function getTitle(el) {
      var titleSource = el.clone();
      titleSource.find('.crm-vol-description').remove();
      return $.trim(titleSource.text());
    }

    function getDescription(el) {
      //This wrapper was added when we allowed HTML in the project description
      //to keep the icon visible, instead of the HTML from the description
      //covering over the icon
      return el.find("[class$=-description-wrapper]").html();
    }

    $('.crm-vol-description').css('cursor', 'pointer').click(function () {
      var description = getDescription($(this));
      var title =  getTitle($(this).parent());
      CRM.alert(description, title, 'info', {expires: 0});
    });

    // On a validation round-trip, take the volunteer directly to the first
    // field that needs attention while retaining CiviCRM's inline messages.
    var firstInvalidField = $('.crm-vol-signup-page')
      .find('input.error, select.error, textarea.error, input.crm-error, select.crm-error, textarea.crm-error')
      .filter(':visible')
      .first();
    if (firstInvalidField.length) {
      firstInvalidField.trigger('focus');
    }
  });
}(CRM.ts('org.civicrm.volunteer')));
