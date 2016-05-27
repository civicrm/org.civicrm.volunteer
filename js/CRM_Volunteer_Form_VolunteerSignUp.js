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
      //This wrapper was added when we allowed HTML in the project description
      //to keep the icon visible, instead of the HTML from the description
      //covering over the icon
      return el.find(".vol-project-description-wrapper").html();
    }

    $('.crm-vol-description').css('cursor', 'pointer').click(function () {
      var description = getDescription($(this));
      var title =  getTitle($(this).parent());
      CRM.alert(description, title, 'info', {expires: 0});
    });
  });
}(CRM.ts('org.civicrm.volunteer')));
