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


require_once 'CRM/Volunteer/Angular/Manager.php';

/**
 * This class generates form components for processing Event
 *
 */
class CRM_Volunteer_Form_Volunteer extends CRM_Event_Form_ManageEvent {

  /**
   * A flag used to indicate whether or not Angular should be loaded.
   *
   * @var boolean
   * @see isLoadingTabContent()
   */
  private $loadAngular = FALSE;

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

  /***
   * Get the civiVolunteer Project for this Event.
   * CAUTION: Returns only the first if there are multiple.
   *
   * @returns $project CRM_Volunteer_BAO_Project
   */
  protected function getProject($params = NULL) {
    if ($this->_project === NULL) {
      $this->minimumProjectParams($params);
      $this->_project = current(CRM_Volunteer_BAO_Project::retrieve($params));

      if ($this->_project) {
        $beneficiaryIds = CRM_Volunteer_BAO_Project::getContactsByRelationship($this->_project->id, 'volunteer_beneficiary');
        $this->_project->target_contact_id = implode(',', $beneficiaryIds);
      }
    }

    return $this->_project;
  }

  /***
   * Save (or create a project)
   */
  protected function saveProject($params) {
    $targetContacts = CRM_Utils_Array::value('target_contact_id', $params);
    unset($params['target_contact_id']);

    if (!empty($targetContacts)) {
      $params['project_contacts']['volunteer_beneficiary'] = explode(',', $targetContacts);
    }

    $this->minimumProjectParams($params);
    $this->_project = CRM_Volunteer_BAO_Project::create($params);
    // if we created a project:
    if (!array_key_exists('id', $params)) {
      $form = $this->getSubmitValues();
      if (CRM_Utils_Array::value('is_active', $form, 0) === '1') {
        // create the flexible need
        $need = array(
          'project_id' => $this->_project->id,
          'is_flexible' => '1',
          'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
        );
        CRM_Volunteer_BAO_Need::create($need);
      }
    }
    return $this->_project;
  }

  /**
   * Use Page properties to set default params for retrieving a project BAO
   *
   * @param array $params by reference
   */
  function minimumProjectParams(&$params) {
    if(!is_array($params)) {
      $params = array();
    }
    if(!array_key_exists('entity_id', $params)) {
      if($this->_id) {
        $params['entity_id'] = $this->_id;
      } else {
        $params['entity_id'] = CRM_Utils_Array::value('id', $_REQUEST);
      }
    }
    if (!array_key_exists('entity_table', $params)) {
      $params['entity_table'] = CRM_Event_DAO_Event::$_tableName;
    }
  }


  /**
   * Does a UFJoin lookup and caches it for future use.
   *
   * @return array of UFGroup (profile) IDs
   */
  private function getProfileIDs() {
    if (empty($this->_profile_ids) && $this->getProject() !== FALSE) {
      $dao = new CRM_Core_DAO_UFJoin();
      $dao->entity_table = CRM_Volunteer_BAO_Project::$_tableName;
      $dao->entity_id = $this->getProject()->id;
      $dao->orderBy('weight asc');
      $dao->find();
      while ($dao->fetch()) {
        $this->_profile_ids[] = $dao->uf_group_id;
      }
    }
    if (empty($this->_profile_ids)) {
      $this->_profile_ids[] = civicrm_api3('UFGroup', 'getvalue', array(
        'name' => 'volunteer_sign_up',
        'return' => 'id',
      ));
    }

    return $this->_profile_ids;
  }

  /**
   * Set variables up before form is built.
   *
   * @return void
   */
  public function preProcess() {
    if ($this->isLoadingTabContent()) {
      $this->loadAngular = TRUE;
    } else {
      parent::preProcess();
    }
  }

  /**
   * Distinguishes between different invocations of the form class.
   *
   * It is possible for this form class to be loaded twice in what the user
   * perceives as a single page load. If the Event tabset is not already loaded
   * (i.e., the user clicks Configure > Volunteers from the Manage Events
   * screen), then the class is called twice: the first time to defer to its
   * parent for the construction of the event tabset; the second time via AJAX
   * to load content into the frame for the Volunteer tab.
   *
   * @return boolean
   */
  private function isLoadingTabContent() {
    return CRM_Utils_Request::retrieve('snippet', 'String') === "json";
  }

  /**
   * Build the form object
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    if($this->loadAngular) {

      $ang = new CRM_Volunteer_Page_Angular(null, null, CRM_Core_Resources::singleton());
      $ang->registerResources('ajax-snippet', false);


      $pid = ($this->getProject()->id) ? $this->getProject()->id: 0;
      $entity = array();
      $this->minimumProjectParams($entity);

      $result = civicrm_api3('Event', 'getvalue', array(
        'return' => "title",
        'id' => $entity['entity_id'],
      ));

      CRM_Core_Resources::singleton()->addVars('org.civicrm.volunteer', array(
        "hash" => "#/volunteer/manage/" . $pid,
        "projectId" => $pid,
        "entityTable" => $entity['entity_table'],
        "entityId" => $entity['entity_id'],
        "entityTitle" => $result,
        "useEventedButtons" => true
      ));


      CRM_Core_Resources::singleton()->addScriptFile('org.civicrm.volunteer', 'js/CRM_Volunteer_Form_Volunteer.js', -1000, 'ajax-snippet');

      CRM_Core_Resources::singleton()->addStyleFile('org.civicrm.volunteer', 'css/volunteer_events.css');

      // Low weight, go before all the other Angular scripts. The trick is only needed in snippet mode.
      CRM_Core_Resources::singleton()->addScript("CRM.origJQuery = window.jQuery; window.jQuery = CRM.$;", -1001, 'ajax-snippet');
      //High weight, go after all the other Angular scripts. The trick is only needed in snippet mode.
      CRM_Core_Resources::singleton()->addScript("window.jQuery = CRM.origJQuery; delete CRM.origJQuery", 1000, 'ajax-snippet');

    } else {
      parent::buildQuickForm();
    }
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

    if (empty($profiles)) {
      $profiles[] = civicrm_api3('UFGroup', 'getvalue', array(
        'name' => 'volunteer_sign_up',
        'return' => 'id',
      ));
    }

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
