$(function () {
  var widgetWrapper = $('#contributors-widget');
  if (widgetWrapper.length === 1) {
    $.getJSON("https://api.github.com/repos/civicrm/org.civicrm.volunteer/contributors", function (result) {
      $.each(result, function () {
        widgetWrapper.append('<div class="contributor"><a href="' + this.html_url + '">'
                + '<img src="' + this.avatar_url + '" />'
                + '<span class="handle">' + this.login + '</span> '
                + '<span class="commits">(' + this.contributions + ' commit'
                // pluralize only if appropriate
                + (this.contributions > 1 ? 's' : '')
                + ')</span></a></div>');
      });
    });
  }
});