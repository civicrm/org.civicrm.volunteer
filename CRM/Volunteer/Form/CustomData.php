<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2019                                |
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
 * @copyright CiviCRM LLC (c) 2004-2019
 */

/**
 * This class generates form components for custom data
 *
 * It delegates the work to lower level subclasses and integrates the changes
 * back in. It also uses a lot of functionality with the CRM API's, so any change
 * made here could potentially affect the API etc. Be careful, be aware, use unit tests.
 */
class CRM_Volunteer_Form_CustomData extends CRM_Core_Form {

  /**
   * Entity name (e.g. VolunteerProject).
   *
   * @var string
   */
  protected $_entityName;

  /**
   * The entity id, used when editing/creating custom data
   *
   * @var int
   */
  protected $_entityID;

  // /**
  //  * Entity sub type of the table id.
  //  *
  //  * @var string
  //  */
  // protected $_subTypeID;

  /**
   * Pre processing work done here.
   *
   * gets session variables for table name, id of entity in table, type of entity and stores them.
   */
  public function preProcess() {
    $this->_entityName = CRM_Utils_Request::retrieve('entityName', 'String', $this, TRUE);
    $this->_entityID = CRM_Utils_Request::retrieve('entityID', 'Positive', $this, TRUE);
    $this->_groupID = CRM_Utils_Request::retrieve('groupID', 'Positive', $this, FALSE);
    // $this->_subTypeID = CRM_Utils_Request::retrieve('subType', 'Positive', $this, TRUE);

    if (!in_array($this->_entityName, ['VolunteerProject'])) {
      $this->_entityName = 'VolunteerProject';
    }

    $groupTree = CRM_Core_BAO_CustomGroup::getTree($this->_entityName,
      NULL,
      $this->_entityID,
      $this->_groupID,
      NULL // $this->_subTypeID
    );
    // simplified formatted groupTree
    $groupTree = CRM_Core_BAO_CustomGroup::formatGroupTree($groupTree, 1, $this);
    // Array contains only one item
    foreach ($groupTree as $groupValues) {
      $this->_customTitle = $groupValues['title'];
      CRM_Utils_System::setTitle(ts('Edit %1', [1 => $groupValues['title']]));
    }

    $this->_defaults = [];
    CRM_Core_BAO_CustomGroup::setDefaults($groupTree, $this->_defaults);
    $this->setDefaults($this->_defaults);

    CRM_Core_BAO_CustomGroup::buildQuickForm($this, $groupTree);

    //need to assign custom data type and subtype to the template
    $this->assign('entityName', $this->_entityName);
    $this->assign('entityID', $this->_entityID);
    $this->assign('groupID', $this->_groupID);
    // $this->assign('subType', $this->_subTypeID);
  }

  /**
   * Build the form object.
   */
  public function buildQuickForm() {
    // make this form an upload since we dont know if the custom data injected dynamically
    // is of type file etc
    $this->addButtons([
      [
        'type' => 'upload',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);
  }

  /**
   * Process the user submitted custom data values.
   */
  public function postProcess() {
    $params = $this->controller->exportValues($this->_name);

    $transaction = new CRM_Core_Transaction();

    $entityTable = 'civicrm_volunteer_project';

    CRM_Core_BAO_CustomValueTable::postProcess($params,
      $entityTable,
      $this->_entityID,
      $this->_entityName
    );

    $session = CRM_Core_Session::singleton();
    $session->pushUserContext(CRM_Utils_System::url('civicrm/vol', "#/volunteer/manage/{$this->_entityID}"));

    $transaction->commit();
  }

}
