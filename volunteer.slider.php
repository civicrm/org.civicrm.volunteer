<?php

/**
 * Delegated implementation of hook_civicrm_buildForm
 *
 * Customizes the UI for adding custom fields to allow the user to specify whether
 * a multi-select field should use the slider widget or not
 */
function _volunteer_civicrm_buildForm_CRM_Custom_Form_Field($formName, CRM_Core_Form &$form) {
  // add checkbox to the form object
  $form->add('checkbox', 'is_slider_widget', ts('Use Slider Widget?'));

  // add checkbox to the display
  CRM_Core_Region::instance('page-body')->add(array(
   'template' => 'Slider/CRM/Custom/Form/Field.tpl',
  ));

  // reposition and show/hide checkbox
  CRM_Core_Resources::singleton()->addScriptFile('org.civicrm.volunteer', 'js/CRM_Custom_Form_Field.js');
}