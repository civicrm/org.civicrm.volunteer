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
   * Function to set variables up before form is built
   *
   * @return void
   * @access public
   */
  function preProcess() {
    parent::preProcess();

    // CRM_Contact_Form_NewContact needs to believe it is engaged in an add
    // operation, else it expects a contact ID, which we don't have yet. For
    // some reason, setting this in buildQuickForm doesn't work.
    $this->_action = CRM_Core_Action::ADD;

    // get the domain information
    $params = array(
      'api.contact.getsingle' => array(
        'return' => array('display_name'),
      ),
      'sequential' => 1,
    );
    $result = civicrm_api3('Domain', 'get', $params);
    $domain = $result['values'][0]; // if more than one domain, just take the first for now

    $ccr = CRM_Core_Resources::singleton();
    $ccr->addScriptFile('org.civicrm.volunteer', 'templates/CRM/Volunteer/Form/Volunteer.js');
    $ccr->addSetting(array(
      'volunteer' => array(
        'domain' => array(
          'contact_id' => $domain['api.contact.getsingle']['contact_id'],
          'display_name' => $domain['api.contact.getsingle']['display_name'],
          'email' => $domain['domain_email'],
        ),
      )
    ));
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
    $vid = NULL;

    $this->add(
      'checkbox',
      'is_active',
      ts('Enable Volunteer Management?')
    );

    CRM_Contact_Form_NewContact::buildQuickForm($this, 1, NULL, FALSE, 'volunteer_target_', ts('Select Beneficiary'));

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
    $form['is_active'] = CRM_Utils_Array::value('is_active', $form, FALSE);

    // form does not allow more than one target, so just grab the first one
    $target_contact_id = $form['volunteer_target_contact_select_id'][0];

    $params = array(
      'entity_id' => $this->_id,
      'entity_table' => CRM_Event_DAO_Event::$_tableName,
    );

    // see if this project already exists
    $projects = CRM_Volunteer_BAO_Project::retrieve($params);

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
    return ts('Volunteers');
  }
}

