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

    return $defaults;
   }

  /**
   * @access public
   */
  function preProcess() {
    parent::preProcess();
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

    $unmet = CRM_Volunteer_Upgrader::checkExtensionDependencies();

    if (in_array('com.ginkgosreet.multiform', $unmet)) {
      CRM_Volunteer_Upgrader::displayDependencyErrors($unmet);
      return false; // short-circuit form building
    }

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

    parent::endPostProcess();
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
