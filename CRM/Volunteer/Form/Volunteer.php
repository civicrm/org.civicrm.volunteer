<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

/**
 * This class generates form components for processing Event
 *
 */
class CRM_Volunteer_Form_Volunteer extends CRM_Event_Form_ManageEvent {

  /**
   * This function sets the default values for the form. For edit/view mode
   * the default values are retrieved from the database
   *
   * @access public
   *
   * @return array
   */
  function setDefaultValues() {

    $project = current(CRM_Volunteer_BAO_Project::retrieve(array(
      'entity_id' => $this->_id,
      'entity_table' => CRM_Event_DAO_Event::$_tableName,
    )));

    $target_contact_id = $project ? $project->target_contact_id : NULL;

    if (!$target_contact_id) {
      // default to the domain information
      $result = civicrm_api3('Domain', 'get', array('sequential' => 1, 'current_domain' => 1));
      $domain = $result['values'][0]; // if more than one domain, just take the first for now
      $target_contact_id = $domain['contact_id'];
    }

    $defaults = array(
      'is_active' => $project ? $project->is_active: 0,
      'target_contact_id' => $target_contact_id,
    );

    $params = array('entity_id' => $project->entity_id);

    /*$forms = array();
    $daoForm = new CRM_Multiform_DAO_EntityForm();
    $daoForm->copyValues($params); $daoForm->find();
    while ($daoForm->fetch()) {
      $forms[(int) $daoForm->id] = clone $daoForm;
    }
    $daoForm->free();*/
    //$forms = CRM_Multiform_BAO_EntityForm::getEntityForms($params);
    $forms = civicrm_api3('EntityForm', 'get', $params);

    if ($forms['count'] > 1) {
      #TODO: translate
      CRM_Core_Session::setStatus('Found multiple custom forms for this project. This feature is not implemented yet');
    }

    if ($forms['count'] === 0) {
      #TODO: translate
      CRM_Core_Session::setStatus('No custom forms found, assigning the reserved profile, "Volunteer Sign Up" to a new MultiForm entity.');

      ## create form;
      $params = array(
        'entity_table' => 'civicrm_event',
        'entity_id' => $project->entity_id,
        'title' => 'Volunteer Sign Up'
      );
      $api_result = civicrm_api3('EntityForm', 'create', $params);
      $form_id = $api_result['id'];

      ## assign profile to form (UFJoin)
      $api_result = civicrm_api3('UFGroup', 'get', array('name' => 'volunteer_sign_up'));
      $params = array(
        'entity_table' => 'entity_form',
        'module' => 'MultiForm',
        'entity_id' => $form_id,
        'uf_group_id' => $api_result['id'],
      );
      $create_result = CRM_Core_BAO_UFJoin::create($params);

      $groupids = array($api_result['id']);
    }
    else {
      $groupids = array();
      foreach (array_keys($forms['values']) as $id){
        $ufJoinParams = array(
          'entity_table' => 'entity_form',
          'module' => 'MultiForm',
          'entity_id' => $id,
        );
        $groupids[] = CRM_Core_BAO_UFJoin::findUFGroupId($ufJoinParams);
      }
    }

    foreach ($groupids as $key => $value) {
      self::buildProfileWidget($this, $key);
      $defaults["custom_signup_profiles[$key]"] = $value;
    }
    $this->assign('profileSignUpMultiple', array_keys($groupids));

    return $defaults;
   }

  /**
   * Function to set variables up before form is built
   *
   * @access public
   */
  public function preProcess() {
    parent::preProcess();

    $unmet = CRM_Volunteer_Upgrader::checkExtensionDependencies();
    if (in_array('com.ginkgostreet.multiform', $unmet)) {
      $msg = CRM_Volunteer_Upgrader::getUnmetDependencyErrorMessage('com.ginkgostreet.multiform');
      $this->assign('msg', $msg);
    }

    $this->assign('isModulePermissionSupported', CRM_Core_Config::singleton()->userPermissionClass->isModulePermissionSupported());
  }

   /**
   * Function to build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    $vid = NULL;

    parent::buildQuickForm();

    $this->add(
      'checkbox',
      'is_active',
      ts('Enable Volunteer Management?', array('domain' => 'org.civicrm.volunteer'))
    );

    $this->addEntityRef('target_contact_id', ts('Select Beneficiary', array('domain' => 'org.civicrm.volunteer')), array('create' => TRUE, 'select' => array('allowClear' => FALSE)));

    $params = array(
      'entity_id' => $this->_id,
      'entity_table' => CRM_Event_DAO_Event::$_tableName,
    );
    $projects = CRM_Volunteer_BAO_Project::retrieve($params);

    if (count($projects) === 1) {
      $p = current($projects);
      $vid = $p->id;
    }

    $this->assign('vid', $vid);
  }

  /**
   * Function to process the form. Enables/disables Volunteer Project. If the
   * Project does not already exist, it is created, along with a "flexible" Need.
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $form = $this->exportValues();
    $form['is_active'] = CRM_Utils_Array::value('is_active', $form, 0);

    $params = array(
      'entity_id' => $this->_id,
      'entity_table' => CRM_Event_DAO_Event::$_tableName,
    );

    // see if this project already exists
    $projects = CRM_Volunteer_BAO_Project::retrieve($params);

    if (count($projects)) {
      // force an update rather than an insert
      $params['id'] = current($projects)->id;
    }

    // save the project record
    $params += array(
      'is_active' => $form['is_active'],
      'target_contact_id' => $form['target_contact_id'],
    );
    $project = CRM_Volunteer_BAO_Project::create($params);

    // if the project doesn't already exist and the user enabled vol management,
    // create the flexible need
    if (count($projects) !== 1 && $form['is_active'] === '1') {
      $need = array(
        'project_id' => $project->id,
        'is_flexible' => '1',
        'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
      );
      CRM_Volunteer_BAO_Need::create($need);
    }

    /***
     * process profiles
     */

    $params = array(
      'entity_table' => 'civicrm_event',
      'entity_id' => $this->id,
      'return' => 'id'
      );
    $api_result = civicrm_api3('EntityForm', 'getsingle', $params);
    $form_id = $api_result['id'];

    foreach($form['custom_signup_profiles'] as $idx => $profile_id) {
      $params = array(
        'entity_table' => 'entity_form',
        'module' => 'MultiForm',
        'entity_id' => $form_id,
        'uf_group_id' => $profile_id,
      );
      $api_result = CRM_Core_BAO_UFJoin::create($params);
      CRM_Core_Session::setStatus(var_export($api_result, true), 'api_result', 'error');
    }

    parent::endPostProcess();
  }

  private function buildMultiProfileSelects() {
//    $addSignUpProfile = CRM_Utils_Array::value('addSignUpProfile', $_GET, FALSE);
//    $signUpProfileNum = CRM_Utils_Array::value('signUpProfileNum', $_GET, 0);
//    $requestAddProfile = CRM_Utils_Array::value('addProfile', $_GET, FALSE);
    //$this->signUpProfileNumAdd = CRM_Utils_Array::value('addProfileNumAdd', $_GET, 0);

//    $this->assign('addSignUpProfile', $addSignUpProfile);
//    $this->assign('signUpProfileNum', $signUpProfileNum);
//
//    $paramsAddProfile = "id={$this->_id}&addProfile=1&qfKey={$this->controller->_key}";
//    $this->assign('paramsAddProfile', $paramsAddProfile);
//
    $arrProfiles = array(0 => 1, 1 => 2);
    foreach ($arrProfiles as $key => $value) {
      self::buildProfileWidget($this, $key, '', '&nbsp;');
      $arrWidgets["custom_signup_profiles[$key]"] = $value;
    }

    return $arrWidgets;
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
  function buildProfileWidget(&$form, $count, $prefix = '', $label = 'Include Profile', $configs = null) {
    extract( ( is_null($configs) ) ? self::getProfileSelectorTypes() : $configs );
    $element = $prefix . "custom_signup_profiles[$count]";
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
  /**
   * Return a descriptive name for the page, used in wizard header
   *
   * @return string
   * @access public
   */
  public function getTitle() {
    return ts('Volunteers', array('domain' => 'org.civicrm.volunteer'));
  }
}
