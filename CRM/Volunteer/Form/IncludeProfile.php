<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Volunteer_Form_IncludeProfile extends CRM_Core_Form {
  function buildQuickForm() {
    $profileCount = CRM_Utils_Array::value('profileCount', $_GET, FALSE);
    self::buildProfileWidget($this, $profileCount);
    $this->assign('profileCount', $profileCount);

    parent::buildQuickForm();
  }

  /**
   * Subroutine to insert a Profile Editor widget
   * depends on getProfileSelectorTypes
   *
   * @param array &$form
   * @param int $count unique index
   * @param string $prefix dom element ID prefix
   * @param string $label Label
   * @param array $configs Optional, for addProfileSelector(), defaults to using getProfileSelectorTypes()
   */
  public static function buildProfileWidget(&$form, $count, $prefix = '', $label = 'Include Profile', $configs = NULL) {
    extract( ( is_null($configs) ) ? self::getProfileSelectorTypes() : $configs );
    $element = $prefix . "custom_signup_profiles[$count]";
    $form->assign('profileItem', $count);
    $form->addProfileSelector( $element,  $label, $allowCoreTypes, $allowSubTypes, $profileEntities);
  }

  /**
   * Create initializers for addprofileSelector
   *
   * @return array( 'allowCoreTypes' => array(), 'allowSubTypes' => array(), 'profileEntities' => array() )
   */
  static function getProfileSelectorTypes() {
    $configs = array(
      'allowCoreTypes' => array(),
      'allowSubTypes' => array(),
      'profileEntities' => array(),
    );

    $configs['allowCoreTypes'][] = 'Contact';
    $configs['allowCoreTypes'][] = 'Individual';
    $configs['allowCoreTypes'][] = 'Volunteer';

    $configs['profileEntities'][] = array('entity_name' => 'contact_1', 'entity_type' => 'IndividualModel');

   return $configs;
  }
}