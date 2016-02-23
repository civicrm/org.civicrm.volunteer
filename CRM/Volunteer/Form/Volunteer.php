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
   * The ID of the entity (in case, the event) associated with the volunteer project.
   *
   * @var int
   * @see getEntityId()
   */
  private $entityId = NULL;

  /**
   * A flag used to indicate whether or not Angular should be loaded.
   *
   * @var boolean
   * @see isLoadingTabContent()
   */
  private $loadAngular = FALSE;

  /**
   * The project the form is acting on
   *
   * @var mixed CRM_Volunteer_BAO_Project if a project has been set, else boolean FALSE
   */
  private $_project;

  /**
   * Returns the ID of the entity (in this case, the event) associated with the volunteer project.
   *
   * @return int
   */
  protected function getEntityId() {
    if ($this->entityId === NULL) {
      $this->entityId = $this->_id ? $this->_id : CRM_Utils_Request::retrieve('id', 'Positive', $this, TRUE);
    }
    return $this->entityId;
  }

  /***
   * Get the civiVolunteer Project for this Event.
   * CAUTION: Returns only the first if there are multiple.
   *
   * @returns $project CRM_Volunteer_BAO_Project
   */
  protected function getProject() {
    if ($this->_project === NULL) {
      $this->_project = current(CRM_Volunteer_BAO_Project::retrieve(array(
            'entity_id' => $this->getEntityId(),
            'entity_table' => CRM_Event_DAO_Event::$_tableName,
      )));
    }

    return $this->_project;
  }

  /**
   * Initializes a placeholder volunteer project.
   *
   * Used in cases where no project yet exists for the event, to prepopulate the
   * "create" form.
   *
   * @return \CRM_Volunteer_BAO_Project
   */
  protected function initializeProject() {
    $project = new CRM_Volunteer_BAO_Project();
    $project->id = 0;
    $project->entity_id = $this->getEntityId();
    $project->entity_table = CRM_Event_DAO_Event::$_tableName;

    return $project;
  }

  /**
   * Set variables up before form is built.
   *
   * @return void
   */
  public function preProcess() {
    if ($this->isLoadingTabContent()) {
      $this->loadAngular = TRUE;

      $project = $this->getProject();
      if (!$project) {
        $project = $this->initializeProject();
      }

      $entity = $project->getEntityAttributes();
      $entityTitle = $entity['title'];

      CRM_Core_Resources::singleton()->addVars('org.civicrm.volunteer', array(
        "hash" => "#/volunteer/manage/" . $project->id,
        "projectId" => $project->id,
        "entityTable" => $project->entity_table,
        "entityId" => $project->entity_id,
        "entityTitle" => $entityTitle,
        "context" => 'eventTab',
      ));

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
    if ($this->loadAngular) {
      $ang = new CRM_Volunteer_Page_Angular(null, null, CRM_Core_Resources::singleton());
      $ang->registerResources('ajax-snippet', false);

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

}
