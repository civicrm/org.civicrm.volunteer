<?php

/**
 * Delegated implementation of hook_civicrm_buildForm
 *
 * Customizes the UI for adding custom fields to allow the user to specify whether
 * a multi-select field should use the slider widget or not
 */
function _volunteer_civicrm_buildForm_CRM_Custom_Form_Field($formName, CRM_Core_Form &$form) {
  // set default value for the checkbox
  $field_id = $form->getVar('_id');
  $widgetized_fields = _volunteer_get_slider_fields();
  $form->_defaultValues['is_slider_widget'] = in_array($field_id, $widgetized_fields);

  // add checkbox to the form object
  $form->add('checkbox', 'is_slider_widget', ts('Use Slider Selector?'));

  // add checkbox to the display
  CRM_Core_Region::instance('page-body')->add(array(
   'template' => 'Slider/CRM/Custom/Form/Field.tpl',
  ));

  // reposition and show/hide checkbox
  CRM_Core_Resources::singleton()->addScriptFile('org.civicrm.volunteer', 'js/CRM_Custom_Form_Field.js');
}

/**
 * Delegated implementation of hook_civicrm_postProcess
 *
 * Handles the "Use Slider Widget?" field added to the custom fields UI
 */
function _volunteer_civicrm_postProcess_CRM_Custom_Form_Field($formName, &$form) {
  $is_slider_widget = CRM_Utils_Array::value('is_slider_widget', $form->_submitValues);
  $custom_field_id = $form->getVar('_id');

  $verb = $is_slider_widget ? CRM_Core_Action::ADD : CRM_Core_Action::DELETE;
  _volunteer_update_slider_fields(array($verb => $custom_field_id));
}

/**
 * Delegated implementation of hook_civicrm_buildForm
 *
 * Registers the form to allow use of the volunteer slider widget.
 */
function _volunteer_civicrm_buildForm_CRM_Profile_Form_Edit($formName, CRM_Core_Form &$form) {
  $form->allowVolunteerSliderWidget = TRUE;
}

/**
 * For forms which have registered as slider-enabled, add the JS and CSS necessary
 * to render the slider widget(s).
 *
 * @param CRM_Core_Form $form
 */
function _volunteer_addSliderWidget(CRM_Core_Form &$form) {
  if (isset($form->allowVolunteerSliderWidget) && $form->allowVolunteerSliderWidget) {
    $db_widgetized_fields = _volunteer_get_slider_fields();
    foreach ($db_widgetized_fields as &$value) {
      $value = 'custom_' . $value;
    }

    // Compare the global list of widget-enabled fields with the list of elements in this
    // form to get a list of widget-enabled fields in this form.
    $form_element_names = array_flip($form->_elementIndex);
    $widgetized_fields = array_intersect($form_element_names, $db_widgetized_fields);

    foreach ($widgetized_fields as $field_name) {
      $css_classes = CRM_Utils_Array::value('class', $form->getElement($field_name)->_attributes);
      $form->getElement($field_name)->_attributes['class'] = trim($css_classes . ' volunteer_slider');
    }

    if (count($widgetized_fields)) {
      $ccr = CRM_Core_Resources::singleton();
      $ccr->addScriptFile('org.civicrm.volunteer', 'js/slider.js');
      $ccr->addStyleFile('org.civicrm.volunteer', 'css/slider.css');
    }
  }
}

/**
 * Helper function to get the list of fields IDs which have had the slider widget
 * applied to them.
 *
 * @return array
 */
function _volunteer_get_slider_fields() {
  $result = civicrm_api3('setting', 'getvalue', array(
    'name' => 'slider_widget_fields',
    'group' => 'CiviVolunteer Configurations',
  ));
  return is_array($result) ? $result : array();
}

/**
 * Add or remove fields from the slider widget datastore.
 *
 * @param array $params Arrays of custom field IDs which ought to be added or
 *              removed from the slider widget datastore, keyed by CRM_Core_Action::ADD for
 *              fields which should use the widget, and CRM_Core_Action::DELETE for fields which
 *              should not. If the same custom field ID appears in both the CRM_Core_Action::ADD
 *              and CRM_Core_Action::DELETE arrays, it will be removed.
 */
function _volunteer_update_slider_fields(array $params) {
  $add = CRM_Utils_Array::value(CRM_Core_Action::ADD, $params, array());
  if (!is_array($add)) {
    $add = array($add);
  }

  $remove = CRM_Utils_Array::value(CRM_Core_Action::DELETE, $params, array());
  if (!is_array($remove)) {
    $remove = array($remove);
  }

  $widgetized_fields = _volunteer_get_slider_fields();

  foreach ($add as $custom_field_id) {
    $widgetized_fields[] = $custom_field_id;
  }
  $widgetized_fields = array_unique($widgetized_fields);

  foreach ($remove as $custom_field_id) {
    $key = array_search($custom_field_id, $widgetized_fields);
    unset($widgetized_fields[$key]);
  }

  sort($widgetized_fields);
  civicrm_api3('Setting', 'create', array(
    'slider_widget_fields' => $widgetized_fields,
  ));

}