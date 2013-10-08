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

    $params = array('project_id' => $this->_vid);
    $this->_volunteerData = CRM_Volunteer_BAO_Assignment::retrieve($params);


    $projects = CRM_Volunteer_BAO_Project::retrieve(array('id' => $this->_vid));
    $project = $projects[$this->_vid];

    $this->_entityID = $project->entity_id;
    $this->_entityTable = $project->entity_table;
    $this->_title = $project->title;

  }

  /**
   * Build the form
   *
   * @access public
   *
   * @return void
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Log Volunteer Hours - %1', array(1 => $this->_title)));

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

    $count = count($this->_volunteerData);
    for ($rowNumber = 1; $rowNumber <= $this->_batchInfo['item_count']; $rowNumber++) {
      $extra = array();
      if ($rowNumber <= $count) {
        //readonly for some fields
        $extra = array(
          'READONLY' => TRUE,
          'style' => "background-color:#EBECE4",
          'disabled' => 'disabled'
        );

        $this->add('text', "primary_contact[$rowNumber]", '', $extra);
        $this->add('text', "field[$rowNumber][start_date]", '', $extra);
        $this->add('text', "field[$rowNumber][volunteer_role]", '', array_merge($attributes, $extra));
      }
      else {
        CRM_Contact_Form_NewContact::buildQuickForm($this, $rowNumber, NULL, FALSE, 'primary_');
        $this->addDateTime("field[$rowNumber][start_date]", '', FALSE, array('formatType' => 'activityDateTime'));
        $this->add('select', "field[$rowNumber][volunteer_role]", '', array('' => ts('-select-')) + $volunteerRole);
      }

      $this->add('select', "field[$rowNumber][volunteer_status]", '', $volunteerStatus);
      $this->add('text', "field[$rowNumber][scheduled_duration]", '', array_merge($attributes, $extra));
      $this->add('text', "field[$rowNumber][actual_duration]", '', $attributes);
      $this->add('text', "field[$rowNumber][activity_id]");
    }

    $this->assign('rowCount', $this->_batchInfo['item_count']);
    $this->assign('showVolunteerRow', $count);

    switch ($this->_entityTable) {
      case 'civicrm_event':
        $path = 'civicrm/event/manage/volunteer';
        $query = "reset=1&action=update&id={$this->_entityID}";
        break;
    }
    $url = CRM_Utils_System::url($path, $query);

    $session = CRM_Core_Session::singleton();
    $session->replaceUserContext($url);
  }

  /**
   * form validations
   *
   * @param array $params   posted values of the form
   * @param array $files    list of errors to be posted back to the form
   *
   *
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
      if ($key > count($self->_volunteerData) && !empty($params['primary_contact_select_id'][$key])) {
        if ((!$value['actual_duration']) && $value['volunteer_status'] == CRM_Utils_Array::key('Completed', $volunteerStatus) ) {
          $errors["field[$key][actual_duration]"] = ts('Please enter the actual duration for Completed volunteer activity');
        }
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
    $volunteerRole = CRM_Volunteer_BAO_Need::buildOptions('role_id', 'create');
    $volunteerStatus = CRM_Activity_BAO_Activity::buildOptions('status_id', 'validate');

    foreach ($this->_volunteerData as $data) {
      $defaults['field'][$i]['scheduled_duration'] = $data['time_scheduled_minutes'];
      $defaults['field'][$i]['actual_duration'] = $data['time_completed_minutes'];
      $defaults['field'][$i]['volunteer_role'] = CRM_Utils_Array::value($data['role_id'], $volunteerRole);
      $defaults['field'][$i]['volunteer_status'] = $data['status_id'];
      $defaults['field'][$i]['activity_id'] = $data['id'];
      $defaults['field'][$i]['start_date'] = CRM_Utils_Date::customFormat($data['start_time'], "%m/%E/%Y %l:%M %P");
      $defaults["primary_contact"][$i] = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $data['contact_id'], 'sort_name');
      $i++;
    }

    $completed = CRM_Utils_Array::key('Completed', $volunteerStatus);
    for ($j = $i; $j< $this->_batchInfo['item_count']; $j++) {
      $defaults['field'][$j]['volunteer_status'] = $completed;
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

    $count = 0;
    foreach ($params['field'] as $key => $value) {
      if (!empty($params['primary_contact_select_id'][$key]) or !empty($params['primary_contact'][$key])) {
        if (!empty($value['activity_id'])) {
          // update the activity record

          $volunteer = array(
            'status_id' => $value['volunteer_status'],
            'id' => $value['activity_id'],
            'time_completed_minutes' => CRM_Utils_Array::value('actual_duration', $value),
            'time_scheduled_minutes' => CRM_Utils_Array::value('scheduled_duration', $value),
          );
          CRM_Volunteer_BAO_Assignment::createVolunteerActivity($volunteer);
        }
        else {
          //create need record
          $needs = array(
            'project_id' => $this->_vid,
            'duration' => CRM_Utils_Array::value('actual_duration', $value),
            'role_id' => CRM_Utils_Array::value('volunteer_role', $value),
            'is_active' => 1,
          );
          if (empty($value['start_date'])) {
            $needs['is_flexible'] = 1;
          }
          else {
            $needs['is_flexible'] = 0;
            $needs['start_time'] = CRM_Utils_Date::processDate($value['start_date'], $value['start_date_time'], TRUE);
          }

          $need = CRM_Volunteer_BAO_Need::create($needs);

          //create new Volunteer activity records
          $volunteer = array(
            'assignee_contact_id' => $params['primary_contact_select_id'][$key],
            'status_id' => $value['volunteer_status'],
            'subject' => $this->_title . ' Volunteering',
            'volunteer_need_id' => $need->id,
            'time_completed_minutes' => CRM_Utils_Array::value('actual_duration', $value),
            'time_scheduled_minutes' => CRM_Utils_Array::value('scheduled_duration', $value),
          );
          if (!empty($needs['start_time'])) {
            $volunteer['activity_date_time'] = $needs['start_time'];
          }

          CRM_Volunteer_BAO_Assignment::createVolunteerActivity($volunteer);
        }
        $count++;
      }
    }

    $statusMsg = ts('Volunteer hours have been recorded for %1 volunteers', array(1 => $count));

    if (CRM_Utils_Array::value('snippet', $_REQUEST) == CRM_Core_Smarty::PRINT_JSON) {
      CRM_Utils_System::civiExit(json_encode(array(
        'message' => $statusMsg,
      )));
    }
    else {
      CRM_Core_Session::setStatus($statusMsg, ts('Saved'), 'success');
    }

  }

}

