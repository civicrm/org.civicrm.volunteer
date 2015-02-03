(function(ts) {
  CRM.$(function($) {
    var slider_cb = $('.crm-volunteer-slider-custom-field-form-block-is_slider_widget');
    var data_type_sel = $('[name=data_type\\[1\\]]');

    // move the slider checkbox into the main form
    $('#showoption').after(slider_cb);

    // wire up the field for HTML type to display the slider checkbox when set to multi-select
    data_type_sel.change(function (){
      slider_cb.toggle(($(this).val() === 'Multi-Select'));
    });

    data_type_sel.trigger('change');
  });
}(CRM.ts('org.civicrm.volunteer')));