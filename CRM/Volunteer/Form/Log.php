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
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

/**
 * This class provides the functionality for batch entry for Logging Volunteer Hours
 */
class CRM_Volunteer_Form_Log extends CRM_Core_Form {

  /**
   * maximum log records that will be displayed
   *
   */
  protected $_rowCount = 1;

  /**
   * Batch information
   */
  protected $_batchInfo = array();

  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  function preProcess() {
    $this->_vid = CRM_Utils_Request::retrieve('vid', 'Positive', $this, TRUE);
    $this->_batchInfo['item_count'] = 10;

    $resources = CRM_Core_Resources::singleton();
    $resources->addScriptFile('org.civicrm.volunteer', 'templates/CRM/Volunteer/Form/Log.js');
  }

  /**
   * Build the form
   *
   * @access public
   *
   * @return void
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Batch Data Entry for Logging Volunteer Hours'));

    $this->addFormRule(array('CRM_Volunteer_Form_Log', 'formRule'), $this);
    $this->addButtons(array(
      array(
        'type' => 'upload',
        'name' => ts('Save'),
        'isDefault' => TRUE
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
      )
    ));

    $volunteerRole = CRM_Volunteer_BAO_Need::buildOptions('role_id', 'create');
    $volunteerStatus = CRM_Activity_BAO_Activity::buildOptions('status_id', 'create');

    $attributes = array(
      'size' => 6,
      'maxlength' => 14
    );

    $params = array('project_id' => $this->_vid);
    $this->_volunteerData = CRM_Volunteer_BAO_Assignment::retrieve($params);
    $count = count($this->_volunteerData);

    for ($rowNumber = 1; $rowNumber <= $this->_batchInfo['item_count']; $rowNumber++) {
      $extra = array();
      if ($rowNumber <= $count) {
        //readonly for some fields
        $extra = array(
          'READONLY' => TRUE,
          'style' => "background-color:#EBECE4"
        );
      }

      CRM_Contact_Form_NewContact::buildQuickForm($this, $rowNumber, NULL, TRUE, 'primary_');

      $element = $this->add('select', "field[$rowNumber][volunteer_role]", '', $volunteerRole);
      if (!empty($extra)) {
        $element->freeze();
      }
      $this->add('select', "field[$rowNumber][volunteer_status]", '', $volunteerStatus);
      $this->addDateTime("field[$rowNumber][start_date]", '', FALSE, array('formatType' => 'activityDateTime'));

      $this->add('text', "field[$rowNumber][scheduled_duration]", '', array_merge($attributes, $extra));
      $this->add('text', "field[$rowNumber][actual_duration]", '', $attributes);

    }


    $this->assign('rowCount', $this->_batchInfo['item_count']);
    $this->assign('showVolunteerRow', $count);

    // don't set the status message when form is submitted.
    $buttonName = $this->controller->getButtonName('submit');
  }

  /**
   * form validations
   *
   * @param array $params   posted values of the form
   * @param array $files    list of errors to be posted back to the form
   * @param array $self     form object
   *
   * @return array list of errors to be posted back to the form
   * @static
   * @access public
   */
  static function formRule($params, $files, $self) {
    $errors = array();
    $volunteerStatus = CRM_Activity_BAO_Activity::buildOptions('status_id', 'validate');

    foreach ($params['field'] as $key => $value) {
      if ($value['volunteer_status'] == CRM_Utils_Array::key('Completed', $volunteerStatus)) {
        $errors["field[$key][actual_duration]"] = ts('Please enter the actual duration for Completed volunteer activity');
      }
    }

    if (!empty($errors)) {
      return $errors;
    }

    return TRUE;
  }


  /**
   * This function sets the default values for the form.
   *
   * @access public
   *
   * @return None
   */
  function setDefaultValues() {
    $defaults = array();
    $i = 1;
    foreach ($this->_volunteerData as $activityID => $data) {
      $defaults['field'][$i]['scheduled_duration'] = $data->time_scheduled_minutes;
      $defaults['field'][$i]['actual_duration'] = $data->time_completed_minutes;
      $defaults['field'][$i]['volunteer_role'] = $data->role_id;
      $defaults['field'][$i]['volunteer_status'] = $data->status_id;
      $startDate = CRM_Utils_Date::customFormat($data->start_time, "%m/%E/%Y;%l:%M %P");
      $date = explode(';', $startDate);
      $defaults['field'][$i]['start_date'] = $date[0];
      $defaults['field'][$i]['start_date_time'] = $date[1];

      //FIXME: missing contact ID from the retrieve BAO method
      $defaults["primary_contact_select_id[$i]"] = $data->contact_id;
      $i++;
    }

    return $defaults;
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $params = $this->controller->exportValues($this->_name);
    //CRM_Core_Error::debug('p',$params);
    //exit;
  }

}

