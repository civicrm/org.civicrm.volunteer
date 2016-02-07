(function(ts) {
  CRM.$(function($) {
    function getTitle(el) {
      var title = '';
      el.contents().each(function () {
        if (this.nodeType === 3) {
          title += this.textContent;
        }
      });
      return title;
    }

    function getDescription(el) {
      return el.text();
    }

    $('.crm-vol-description').css('cursor', 'pointer').click(function () {
      var description = getDescription($(this));
      var title =  getTitle($(this).parent());
      CRM.alert(description, title, 'info', {expires: 0});
    });
  });
}(CRM.ts('org.civicrm.volunteer')));
