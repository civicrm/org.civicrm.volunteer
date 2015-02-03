(function(ts) {
  CRM.$(function($) {
    $('select.volunteer_slider').each(function(){
      $(this).select2('destroy').hide().after(buildSlider($(this)));
    });

    function buildSlider(sel) {
      var selID = sel.attr('id');
      var required = sel.is('.required');

      var container = $('<div>', {
        "class": "volunteer_slider",
        "data-original-id": selID
      });

      container.slider({
        /**
         * Update the hidden form elements after the user drops the slider
         */
        change: function(event, ui) {
          // wipe out all previous selections
          $('#' + selID).find('option').prop('selected', false);

          // select all options up to where the user dropped the slider
          for (var i = 0; i <= ui.value; i++) {
            var option = $('#' + selID).find('option')[i];
            $(option).prop('selected', true);
          }
        },
        // Setting the minimum to -1 allows for deselecting the minimum option when the field is not required
        min: (required ? 0 : -1),
        max: sel.find('option').length - 1,

        /**
         * Display the option label for the user's selection
         *
         * @see http://api.jqueryui.com/slider/#event-slide
         *
         * @param {Event} event The slide event
         * @param {Object} ui User input
         */
        slide: function(event, ui) {
          var labelEl = $('label[for=' + selID + ']');

          if (ui.value === -1) {
            var desc = getDescForNoSelection();
          } else {
            var desc = $('#' + selID).find('option')[ui.value].text;
          }

          if (labelEl.find('span.volunteer_option_label').length === 0) {
            labelEl.append($('<span>', {"class": 'volunteer_option_label'}));
          }

          labelEl.find('span.volunteer_option_label').hide().html(desc).fadeIn();
        },

        value: sel.find('option:selected').last().index()
      });

      // trigger the slide event to populate the descriptions on load; lovely syntax
      // courtesy of http://stackoverflow.com/a/1289096
      container.slider('option', 'slide').call(container, {}, {value: container.slider("value")});

      return container;
    }

    function getDescForNoSelection() {
      return '<em>' + ts('Not specified') + '</em>';
    }
  });
}(CRM.ts('org.civicrm.volunteer')));