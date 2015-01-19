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
  $form->add('checkbox', 'is_slider_widget', ts('Use Slider Widget?'));

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

  $widgetized_fields = _volunteer_get_slider_fields();

  if ($is_slider_widget) {
    $widgetized_fields[] = $custom_field_id;
  } else {
    $key = array_search($custom_field_id, $widgetized_fields);
    unset($widgetized_fields[$key]);
  }

  $widgetized_fields = array_unique($widgetized_fields);
  sort($widgetized_fields);

  CRM_Core_BAO_Setting::setItem($widgetized_fields, 'CiviVolunteer Configurations', 'slider_widget_fields');
}

/**
 * Helper function to get the list of fields IDs which have had the slider widget
 * applied to them.
 *
 * @return array
 */
function _volunteer_get_slider_fields() {
  return civicrm_api3('setting', 'getvalue', array(
    'name' => 'slider_widget_fields',
    'group' => 'CiviVolunteer Configurations',
  ));
}