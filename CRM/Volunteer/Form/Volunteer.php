<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
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
   * Function to set variables up before form is built
   *
   * @return void
   * @access public
   */
  function preProcess() {
    parent::preProcess();
  }

  /**
   * This function sets the default values for the form. For edit/view mode
   * the default values are retrieved from the database
   *
   * @access public
   *
   * @return array
   */
  function setDefaultValues() {
    $defaults = array(
      'is_active' => CRM_Volunteer_BAO_Project::isActive($this->_id, CRM_Event_DAO_Event::$_tableName),
    );

    return $defaults;
  }

  /**
   * Function to build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    $this->add(
      'checkbox',
      'is_active',
      ts('Enable Volunteer Management?')
    );

    parent::buildQuickForm();
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

    $params['entity_id'] = $this->_id;
    $params['entity_table'] = CRM_Event_DAO_Event::$_tableName;

    // see if this project already exists
    $projects = CRM_Volunteer_BAO_Project::retrieve($params);

    $form['is_active'] = CRM_Utils_Array::value('is_active', $form, FALSE);
    file_put_contents('/tmp/civi.debug', count($projects), FILE_APPEND);
    if (count($projects) === 1) {
      $p = current($projects);
      if ($form['is_active'] === '1') {
        $p->enable();
      } else {
        $p->disable();
      }
    // if the project doesn't already exist and the user enabled vol management
    } elseif ($form['is_active'] === '1') {
      $project = CRM_Volunteer_BAO_Project::create($params);

  // commented out until the BAOs exist
  //  $need = array(
  //    'project_id' => $project->id,
  //    'is_flexible' => '1',
  //    'visibility_id' => '1',
  //  );
  //  CRM_Volunteer_BAO_Need::add($need);
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
    return ts('Volunteers');
  }
}

