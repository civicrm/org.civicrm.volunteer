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

  /** @var int */
  protected $_vid;

  /** @var array */
  protected $_volunteerData = array();

  /** @var int|null */
  protected $_entityID;

  /** @var string|null */
  protected $_entityTable;

  /** @var string|null */
  protected $_start_date;

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

    // assertProjectPerms() throws. CRM_Utils_System::permissionDenied() only
    // throws on some CMSes, so calling it without returning let execution fall
    // through and render the page on the others.
    CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $this->_vid);

    $this->_batchInfo['item_count'] = 50;

    $params = array('project_id' => $this->_vid);
    $this->_volunteerData = CRM_Volunteer_BAO_Assignment::retrieve($params);

    $project = CRM_Volunteer_BAO_Project::retrieveByID($this->_vid);

    $this->_entityID = $project->entity_id;
    $this->_entityTable = $project->entity_table;
    $this->_title = $project->title;

    $projectDateRange = CRM_Utils_Date::customFormat($project->start_date);

    $this->_title .= ' ( ' . CRM_Utils_Date::customFormat($project->start_date);
    $this->_start_date = $project->start_date;

    if ($project->end_date) {
      $this->_title .= ' - ' . CRM_Utils_Date::customFormat($project->end_date) . ' )';
      $projectDateRange .= ' - ' . CRM_Utils_Date::customFormat($project->end_date);
    }
    else {
      $this->_title .= ' )';
    }

    CRM_Core_Resources::singleton()
        ->addScriptFile('org.civicrm.volunteer', 'js/CRM_Volunteer_Form_Log.js')
        ->addStyleFile('org.civicrm.volunteer', 'css/volunteer-tokens.css')
        ->addStyleFile('org.civicrm.volunteer', 'css/log.css')
        ->addStyleFile('org.civicrm.volunteer', 'css/commendation.css')
        ->addScriptFile('org.civicrm.volunteer', 'js/commendation.js');

    $this->assign('vid', $this->_vid);
    $this->assign('projectTitle', $project->title);
    $this->assign('projectDateRange', $projectDateRange);
  }

  /**
   * Build the form object
   *
   * NOTE: None of the fields in the form can be made required in a strict sense
   * because rows are prebuilt and revealed via JavaScript as needed; i.e., in
   * many cases there will be tens of rows which are invisible to the user and
   * for which the required fields directive will be enforced. Thus, we handle
   * validation in the formRule where requirements can be more conditional.
   * JavaScript adds the client-side required class only when a row is visible.
   *
   * @access public
   *
   * @return void
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Log Volunteer Hours', array('domain' => 'org.civicrm.volunteer')));

    $this->addFormRule(array('CRM_Volunteer_Form_Log', 'formRule'), $this);
    $this->addButtons(array(
      array(
        'type' => 'upload',
        'name' => ts('Save hours', array('domain' => 'org.civicrm.volunteer')),
        'isDefault' => TRUE
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel', array('domain' => 'org.civicrm.volunteer')),
      )
    ));

    $volunteerRole = array_column(
      \Civi::entity('VolunteerNeed')->getOptions('role_id', array(), FALSE, TRUE) ?? array(),
      'label',
      'id'
    );
    $volunteerStatus = array_column(
      \Civi::entity('Activity')->getOptions('status_id', array(), FALSE, TRUE) ?? array(),
      'label',
      'id'
    );

    $attributes = array(
      'size' => 6,
      'maxlength' => 14
    );

    $count = count($this->_volunteerData);
    for ($rowNumber = 1; $rowNumber <= $this->_batchInfo['item_count']; $rowNumber++) {
      $extra = array();
      $entityRefParams = array(
        'create' => TRUE,
        'class' => 'big crm-vol-contact',
        'placeholder' => ts('- select -', array('domain' => 'org.civicrm.volunteer')),
      );
      $isRequired = FALSE;
      $contactField = $this->addEntityRef("field[$rowNumber][contact_id]", '', $entityRefParams, $isRequired);

      $datePickerAttr = array('formatType' => 'activityDateTime');
      if ($rowNumber <= $count) {
        // readonly for some fields
        $contactField->freeze();
        $extra = array(
          'READONLY' => TRUE,
          'disabled' => 'disabled'
        );
        $datePickerAttr += $extra;

        $this->add('text', "field[$rowNumber][volunteer_role]", '', array_merge($attributes, $extra));
      }
      else {
        $this->add('select', "field[$rowNumber][volunteer_role]", '', array('' => ts('-select-', array('domain' => 'org.civicrm.volunteer'))) + $volunteerRole);
      }

      $this->add('datepicker', "field[$rowNumber][start_date]", '', $datePickerAttr);
      $this->add('select', "field[$rowNumber][volunteer_status]", '', $volunteerStatus);
      $this->add('text', "field[$rowNumber][scheduled_duration]", '', array_merge($attributes, $extra));
      $durationAttr = array_merge($attributes, array('class' => 'crm-vol-actual-duration'));
      $this->add('text', "field[$rowNumber][actual_duration]", '', $durationAttr);
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
    $allowedStatusIds = array_map(
      'intval',
      array_column(
        \Civi::entity('Activity')->getOptions('status_id', array(), FALSE, TRUE) ?? array(),
        'id'
      )
    );

    $rows = self::getCompletedRows($params['field']);
    foreach ($rows as $key => $value) {
      $duration = $value['actual_duration'] ?? NULL;
      $statusId = $value['volunteer_status'] ?? NULL;

      if (!$duration) {
        $errors["field[$key][actual_duration]"] =
          ts('Please enter the actual duration volunteered.', array('domain' => 'org.civicrm.volunteer'));
      } elseif (!ctype_digit($duration)) {
        $errors["field[$key][actual_duration]"] =
          ts('Please enter duration as a number.', array('domain' => 'org.civicrm.volunteer'));
      }
      if (!$statusId || !in_array((int) $statusId, $allowedStatusIds, TRUE)) {
        $errors["field[$key][volunteer_status]"] =
          ts('Please select a valid volunteer status.', array('domain' => 'org.civicrm.volunteer'));
      }
    }

    if (!empty($errors)) {
      // show as many rows as there are data for; prevents invalid "Add Volunteer" rows from being hidden
      CRM_Core_Smarty::singleton()->assign('showVolunteerRow', count($rows));

      return $errors;
    }

    return TRUE;
  }

  /**
   * Set default values for the form.
   *
   * @access public
   *
   * @return None
   */
  function setDefaultValues() {
    $defaults = array();
    $i = 1;
    $volunteerRole = array_column(
      \Civi::entity('VolunteerNeed')->getOptions('role_id', array(), FALSE, TRUE) ?? array(),
      'label',
      'id'
    );
    $volunteerStatus = array_column(
      \Civi::entity('Activity')->getOptions('status_id', array(), TRUE) ?? array(),
      'name',
      'id'
    );

    foreach ($this->_volunteerData as $data) {
      $defaults['field'][$i]['scheduled_duration'] = $data['time_scheduled_minutes'];
      $defaults['field'][$i]['actual_duration'] = $data['time_completed_minutes'];
      $defaults['field'][$i]['volunteer_role'] = $volunteerRole[$data['volunteer_role_id']] ?? NULL;
      $defaults['field'][$i]['volunteer_status'] = $data['status_id'];
      $defaults['field'][$i]['activity_id'] = $data['id'];
      $defaults['field'][$i]['start_date'] = $data['activity_date_time'];
      $defaults['field'][$i]["contact_id"] = $data['contact_id'];
      $i++;
    }

    $completed = CRM_Utils_Array::key('Completed', $volunteerStatus);
    for ($j = $i; $j< $this->_batchInfo['item_count']; $j++) {
      $defaults['field'][$j]['volunteer_status'] = $completed;
      $startDate  = CRM_Utils_Date::customFormat($this->_start_date, "%m/%E/%Y  %l:%M %P");
      $date = explode('  ', $startDate);
      $defaults['field'][$j]['start_date'] = $date[0];
      $defaults['field'][$j]['start_date_time'] = $date[1];
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
    $validParams = self::getCompletedRows($params['field']);
    $count = 0;

    // Batch data entry is one aggregate. Each row opens its own savepoint via
    // createVolunteerActivity(), so without an enclosing transaction a failure
    // on row N left rows 1..N-1 committed and the operator with no way to tell
    // how far it got.
    $transaction = CRM_Core_Transaction::create(TRUE);
    try {
      // The flexible need is a property of the project, not of the row.
      $flexibleNeedId = CRM_Volunteer_BAO_Project::getFlexibleNeedID($this->_vid);

      foreach ($validParams as $value) {
        if (!empty($value['activity_id'])) {
          // update the activity record
          $volunteer = array(
            'status_id' => $value['volunteer_status'],
            'id' => $value['activity_id'],
            'time_completed_minutes' => $value['actual_duration'] ?? NULL,
            'time_scheduled_minutes' => $value['scheduled_duration'] ?? NULL,
          );
        }
        else {
          // create new Volunteer activity records
          $volunteer = array(
            'assignee_contact_id' => $value['contact_id'],
            'status_id' => $value['volunteer_status'],
            'subject' => $this->_title . ' Volunteering',
            'volunteer_need_id' => $flexibleNeedId,
            'volunteer_role_id' => $value['volunteer_role'] ?? NULL,
            'time_completed_minutes' => $value['actual_duration'] ?? NULL,
            'time_scheduled_minutes' => $value['scheduled_duration'] ?? NULL,
          );
          if (!empty($value['start_date'])) {
            $volunteer['activity_date_time'] = CRM_Utils_Date::processDate($value['start_date'], $value['start_date_time'], TRUE);
          }
        }

        CRM_Volunteer_BAO_Assignment::createVolunteerActivity($volunteer);
        $count++;
      }

      $transaction->commit();
    }
    catch (Throwable $e) {
      $transaction->rollback()->commit();
      Civi::log()->error('Volunteer hour logging failed on row {row} of {total}: {message}', array(
        'row' => $count + 1,
        'total' => count($validParams),
        'message' => $e->getMessage(),
      ));
      throw new CRM_Core_Exception(
        ts('Volunteer hours could not be logged. No rows were saved; please review the entries and try again.', array('domain' => 'org.civicrm.volunteer')),
        0,
        array(),
        $e
      );
    }

    $statusMsg = ts('Volunteer hours have been logged.', array('domain' => 'org.civicrm.volunteer'));
    CRM_Core_Session::setStatus($statusMsg, ts('Saved', array('domain' => 'org.civicrm.volunteer')), 'success');
  }

  /**
   * Gets completed rows (i.e., those with a contact ID)
   *
   * @param array $rows Rows submitted to the form
   * @return array
   */
  static function getCompletedRows (array $rows) {
    $completedRows = array();

    foreach ($rows as $key => $row) {
      if (!empty($row['contact_id'])) {
        $completedRows[$key] = $row;
      }
    }
    return $completedRows;
  }
}
