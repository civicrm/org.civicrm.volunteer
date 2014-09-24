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
   * The profile IDs associated with this form
   *
   * @var array
   */
  private $_profile_ids = array();

  /**
   * The project the form is acting on
   *
   * @var mixed CRM_Volunteer_BAO_Project if a project has been set, else boolean FALSE
   */
  private $_project;

  /**
   * This function sets the default values for the form. For edit/view mode
   * the default values are retrieved from the database
   *
   * @access public
   *
   * @return array
   */
  function setDefaultValues() {
    $target_contact_id = $this->_project ? $this->_project->target_contact_id : NULL;

    if (!$target_contact_id) {
      // default to the domain information
      $domain = civicrm_api3('Domain', 'getsingle', array('current_domain' => 1));
      $target_contact_id = $domain['contact_id'];
    }

    $defaults = array(
      'is_active' => $this->_project ? $this->_project->is_active : 0,
      'target_contact_id' => $target_contact_id,
    );

    foreach ($this->getProfileIDs() as $key => $value) {
      CRM_Volunteer_Form_IncludeProfile::buildProfileWidget($this, $key);
      $defaults["custom_signup_profiles[$key]"] = $value;
    }

    return $defaults;
   }

/**
  * Does a UFJoin lookup and caches it for future use.
  *
  * @return array of UFGroup (profile) IDs
  */
  private function getProfileIDs() {
    if (empty($this->_profile_ids) && $this->_project !== FALSE) {
      $dao = new CRM_Core_DAO_UFJoin();
      $dao->entity_table = CRM_Volunteer_BAO_Project::$_tableName;
      $dao->entity_id = $this->_project->id;
      $dao->orderBy('weight asc');
      $dao->find();
      while ($dao->fetch()) {
        $this->_profile_ids[] = $dao->uf_group_id;
      }
    }
    return $this->_profile_ids;
  }

  /**
   * Function to set variables up before form is built
   *
   * @access public
   */
  public function preProcess() {
    parent::preProcess();

    $this->_project = current(CRM_Volunteer_BAO_Project::retrieve(array(
      'entity_id' => $this->_id,
      'entity_table' => CRM_Event_DAO_Event::$_tableName,
    )));

    // Retrieve the profile IDs associated with the project; if none exist,
    // create a dummy using CiviVolunteer's built-in profile. Note: this is
    // necessary to ensure backwards compatibility with versions pre-dating the
    // profile selection widget.
    if (empty($this->getProfileIDs())) {
      $this->_profile_ids[] = civicrm_api3('UFGroup', 'getvalue', array(
        'name' => 'volunteer_sign_up',
        'return' => 'id',
      ));
    }
    $this->assign('profileSignUpMultiple', array_keys($this->_profile_ids));
    $this->assign('profileSignUpCounter', count($this->_profile_ids));

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
    parent::buildQuickForm();

    $this->add(
      'checkbox',
      'is_active',
      ts('Enable Volunteer Management?', array('domain' => 'org.civicrm.volunteer'))
    );

    $this->addEntityRef('target_contact_id', ts('Select Beneficiary', array('domain' => 'org.civicrm.volunteer')), array('create' => TRUE, 'select' => array('allowClear' => FALSE)));

    $this->assign('vid', ($this->_project !== FALSE ? $this->_project->id : NULL));
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
    $form = $this->getSubmitValues();
    $form['is_active'] = CRM_Utils_Array::value('is_active', $form, 0);

    $params = array(
      'entity_id' => $this->_id,
      'entity_table' => CRM_Event_DAO_Event::$_tableName,
      'is_active' => $form['is_active'],
      'target_contact_id' => $form['target_contact_id'],
    );

    if ($this->_project) {
      $params['id'] = $this->_project->id;
    }

    // save the project record
    $this->_project = CRM_Volunteer_BAO_Project::create($params);

    // if the project doesn't already exist and the user enabled vol management,
    // create the flexible need
    if (!$this->_project && $form['is_active'] === '1') {
      $need = array(
        'project_id' => $this->_project->id,
        'is_flexible' => '1',
        'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
      );
      CRM_Volunteer_BAO_Need::create($need);
    }

    $this->saveProfileSelections($form['custom_signup_profiles']);

    self::validateProfileForDedupe($form['custom_signup_profiles']);

    parent::endPostProcess();
  }

  /**
   * Associates user-selected profiles with the volunteer project
   *
   * @param array $profiles
   */
  function saveProfileSelections($profiles) {
    // first delete all past entries
    $params = array(
      'entity_table' => CRM_Volunteer_BAO_Project::$_tableName,
      'entity_id' => $this->_project->id,
    );
    CRM_Core_BAO_UFJoin::deleteAll($params);

    // store the new selections
    foreach($profiles as $key => $profile_id) {
      CRM_Core_BAO_UFJoin::create(array(
        'entity_id' => $this->_project->id,
        'entity_table' => CRM_Volunteer_BAO_Project::$_tableName,
        'is_active' => 1,
        'module' => 'CiviVolunteer',
        'uf_group_id' => $profile_id,
        'weight' => $key,
      ));
    }
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
   * Return a descriptive name for the page, used in wizard header
   *
   * @return string
   * @access public
   */
  public function getTitle() {
    return ts('Volunteers', array('domain' => 'org.civicrm.volunteer'));
  }
}
