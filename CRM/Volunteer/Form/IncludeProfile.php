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
   **/
  public static function buildProfileWidget(&$form, $count, $prefix = '', $label = 'Include Profile', $configs = null) {
    extract( ( is_null($configs) ) ? self::getProfileSelectorTypes() : $configs );
    $element = $prefix . "custom_signup_profiles[$count]";
    $form->assign('profileItem', $count);
    $form->addProfileSelector( $element,  $label, $allowCoreTypes, $allowSubTypes, $profileEntities);
  }
  /**
   * Create initializers for addprofileSelector
   *
   * @return array( 'allowCoreTypes' => array(), 'allowSubTypes' => array(), 'profileEntities' => array() )
   **/
  static function getProfileSelectorTypes() {
    $configs = array(
      'allowCoreTypes' => array(),
      'allowSubTypes' => array(),
      'profileEntities' => array(),
    );

    $configs['allowCoreTypes'][] = 'Contact';
    $configs['allowCoreTypes'][] = 'Individual';
    $configs['allowCoreTypes'][] = 'Participant';

    $configs['profileEntities'][] = array('entity_name' => 'contact_1', 'entity_type' => 'IndividualModel');
    $configs['profileEntities'][] = array('entity_name' => 'participant_1', 'entity_type' => 'ParticipantModel');

   return $configs;
  }

  function postProcess() {
    parent::postProcess();
  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
