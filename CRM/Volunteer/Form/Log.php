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
   * Batch informtaion
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
    $this->_batchInfo['item_count'] = 2;
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

    $this->assign('rowCount', $this->_batchInfo['item_count'] + 1);

    $volunteerRole = CRM_Volunteer_PseudoConstant::volunteerRole();
    $volunteerStatus = CRM_Core_PseudoConstant::activityStatus();
    $attributes = array(
      'size' => 6,
      'maxlength' => 14
    );
    for ($rowNumber = 1; $rowNumber <= $this->_batchInfo['item_count']; $rowNumber++) {
      CRM_Contact_Form_NewContact::buildQuickForm($this, $rowNumber, NULL, TRUE, 'primary_');
      
      $this->add('select', "volunteer_role[$rowNumber]", '', $volunteerRole);
      $this->add('select', "volunteer_status[$rowNumber]", '', $volunteerStatus);
      $this->addDateTime("field[$rowNumber][start_date]", '', FALSE, array('formatType' => 'activityDateTime'));

      $this->add('text', "scheduled_duration[$rowNumber]", '', $attributes);
      $this->add('text', "actual_duration[$rowNumber]", '', $attributes);
    }

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
    //return $defaults;
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    //$params = $this->controller->exportValues($this->_name);

  }

}

