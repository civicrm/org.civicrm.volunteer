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

    $forms = civicrm_api3('EntityForm', 'get', 
      array('entity_id' => $project->entity_id));

    if ($forms['count'] > 1) {
      CRM_Core_Session::setStatus(ts('Found multiple custom forms for this project. This feature is not implemented yet', array('domain' => 'org.civicrm.volunteer')));
    }

    if ($forms['count'] === 0) {
      CRM_Core_Session::setStatus(ts('No custom forms found, assigning the reserved profile, "Volunteer Sign Up" to a new MultiForm entity.', array('domain' => 'org.civicrm.volunteer')));

      /** create form **/
      $api_result = civicrm_api3('EntityForm', 'create', array(
        'entity_table' => 'civicrm_event',
        'entity_id' => $project->entity_id,
        'title' => 'Volunteer Sign Up'
      ));
      $forms['id'] = $form_id = $api_result['id'];

      /** assign profile to form (UFJoin) **/
      $api_result = civicrm_api3('UFGroup', 'get', array('name' => 'volunteer_sign_up'));
      self::addProfileToFormEntity($form_id, $api_result['id'], 1 );

      $groupids = array($api_result['id']);
    }
    else {
      $groupids = array();
      foreach (array_keys($forms['values']) as $fid){
        // TODO: support for multiple forms.
        $groupids = array_merge($groupids, self::getProfilesForEntityForm($fid));
        if (count($groupids) === 0 ) {
          $api_result = civicrm_api3('UFGroup', 'get', array('name' => 'volunteer_sign_up'));
          self::addProfileToFormEntity($fid, $api_result['id'], 1 );
        }
      }
    }

    foreach ($groupids as $key => $value) {
      CRM_Volunteer_Form_IncludeProfile::buildProfileWidget($this, $key);
      $defaults["custom_signup_profiles[$key]"] = $value;
    }
    $this->assign('profileSignUpMultiple', array_keys($groupids));
    $this->assign('profileSignUpCounter', count($groupids));

    return $defaults;
   }

   /**
    * Does a UFJoin lookup of an entity_form ID
    * 
    * @param type $fid form ID
    * @return array of UFGroup (profile) IDs
    */
   public static function getProfilesForEntityForm($fid) {
      $dao = new CRM_Core_DAO_UFJoin();
      $groupids = array();
      $dao->entity_table = 'entity_form';
      $dao->entity_id = $fid;
      $dao->orderBy('weight asc');
      $dao->find();
      while ($dao->fetch()) {
        $groupids[] = $dao->uf_group_id;
      }
      return $groupids;
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
    /* @var $this CRM_Volunteer_Form_Volunteer */
    $form = $this->getSubmitValues();
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
        'project_id' => $project->_id,
        'is_flexible' => '1',
        'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
      );
      CRM_Volunteer_BAO_Need::create($need);
    }

    /** process profiles **/

    $entity_form = civicrm_api3('EntityForm', 'getsingle', array(
      'entity_table' => 'civicrm_event',
      'entity_id' => $this->id,
      'return' => 'id'
    ));

    // first delete all past entries
    CRM_Core_BAO_UFJoin::deleteAll(
      self::createUFJoinParams($entity_form['id'])
    );

    // store the new selections;
    foreach($form['custom_signup_profiles'] as $idx => $profile_id) {
      self::addProfileToFormEntity($entity_form['id'], $profile_id, $idx);
    }

    self::validateProfileForDedupe($form['custom_signup_profiles']);

    parent::endPostProcess();
  }
  static function addProfileToFormEntity($fid, $pid, $weight) {
    $ufJoinParams = self::createUFJoinParams($fid);
    $ufJoinParams['uf_group_id'] = $pid;
    $ufJoinParams['weight'] = $weight; // really a unique ID
    return CRM_Core_BAO_UFJoin::create($ufJoinParams);
  }
  /**
   * Provided an Entity Form ID, create params for retrieving the profiles
   * @param type $formId
   * @return type
   */
  static function createUFJoinParams($formId) {
    return array(
      'is_active' => 1,
      'module' => 'MultiForm',
      'entity_table' => 'entity_form',
      'entity_id' => $formId,
    );
  }

  static function validateProfileForDedupe($profileIds) {
    $cantDedupe = false;
    switch (CRM_Event_Form_ManageEvent_Registration::canProfilesDedupe($profileIds, 0)) {
      case 0:
        $dedupeTitle = ts('Duplicate Matching Impossible', array('domain' => 'org.civicrm.volunteer'));
        $cantDedupe = ts('The selected profiles do not contain the fields necessary to match volunteer sign ups with existing contacts.  This means all anonymous sign ups will result in a new contact.', array('domain' => 'org.civicrm.volunteer'));
        break;
      case 1:
        $dedupeTitle = 'Duplicate Contacts Possible';
        $cantDedupe = ts('The selected profiles can collect enough information to match sign ups with existing contacts, but not all of the relevant fields are required.  Anonymous sign ups may result in duplicate contacts.', array('domain' => 'org.civicrm.volunteer'));
    }
    if ($cantDedupe) {
      CRM_Core_Session::setStatus($cantDedupe, $dedupeTitle, 'alert dedupenotify', array('expires' => 0));
    }
  }

  /**
   * Return a descriptive name fwor the page, used in wizard header
   *
   * @return string
   * @access public
   */
  public function getTitle() {
    return ts('Volunteers', array('domain' => 'org.civicrm.volunteer'));
  }
}
