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

    if ($project->target_contact_id) {
      // use the database value if available
      $result = civicrm_api3('Contact', 'getsingle', array(
        'id' => $project->target_contact_id,
        'return' => array('display_name', 'email'),
      ));
      $target_contact_display_name = $result['display_name'];
      $target_contact_email = $result['email'];
      $target_contact_id = $project->target_contact_id;
    } else {
      // otherwise default to the domain information
      $result = civicrm_api3('Domain', 'get', array(
        'api.contact.getsingle' => array(
          'return' => array('display_name'),
        ),
        'sequential' => 1,
      ));
      $domain = $result['values'][0]; // if more than one domain, just take the first for now

      $target_contact_display_name = $domain['api.contact.getsingle']['display_name'];
      $target_contact_email = $domain['domain_email'];
      $target_contact_id = $domain['api.contact.getsingle']['contact_id'];
    }

    // prepopulate the NewContact widget
    $this->assign('contactId', $target_contact_id);
    $defaults = array(
      'is_active' => $project->is_active,
      'volunteer_target_contact[1]' => $target_contact_display_name .
        ($target_contact_email ? ' :: ' . $target_contact_email : ''),
      'volunteer_target_contact_select_id[1]' => $target_contact_id,
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
    $form['is_active'] = CRM_Utils_Array::value('is_active', $form, 0);

    // form does not allow more than one target, so just grab the first one
    $target_contact_id = current($form['volunteer_target_contact_select_id']);

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
      'target_contact_id' => $target_contact_id,
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
    return ts('Volunteers');
  }
}

