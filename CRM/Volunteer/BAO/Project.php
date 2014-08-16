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

class CRM_Volunteer_BAO_Project extends CRM_Volunteer_DAO_Project {

  /**
   * The title of the Project, inherited from its associated entity
   *
   * @var string
   * @access public (via __get method)
   */
  private $title;

  /**
   * The ID of the flexible Need for this Project
   *
   * @var int
   */
  // TODO: populate this based on result from getFlexibleNeedID()
  private $flexible_need_id;

  /**
   * Array of associated Needs. Accessible via __get method.
   *
   * @var array
   */
  private $needs = array();

  /**
   * Array of associated Roles. Accessible via __get method.
   *
   * @var array Role labels keyed by IDs
   */
  private $roles = array();

  /**
   * Array of associated Shifts. Accessible via __get method.
   *
   * @var array Keyed by Need ID, with a subarray keyed by 'label' and 'role_id'
   */
  private $shifts = array();

  /**
   * The start_date of the Project, inherited from its associated entity
   *
   * @var string
   * @access public (via __get method)
   */
  private $start_date;

  /**
   * The end_date of the Project, inherited from its associated entity
   *
   * @var string
   * @access public (via __get method)
   */
  private $end_date;

  /**
   * class constructor
   */
  function __construct() {
    parent::__construct();
  }

  /**
   * Implementation of PHP's magic __get() function.
   *
   * @param string $name The inaccessible property
   * @return mixed Result of fetcher method
   */
  function __get($name) {
    $f = "_get_$name";
    if (method_exists($this, $f)) {
      return $this->$f();
    }
  }

  /**
   * Implementation of PHP's magic __isset() function.
   *
   * @param string $name The inaccessible property
   * @return boolean
   */
  function __isset($name) {
    $result = FALSE;
    $f = "_get_$name";
    if (method_exists($this, $f)) {
      $v = $this->$f();
      $result = !empty($v);
    }
    return $result;
  }

  /**
   * Function to create a Volunteer Project
   * takes an associative array and creates a Project object
   *
   * This function is invoked from within the web form layer and also from the api layer
   *
   * @param array   $params      (reference ) an assoc array of name/value pairs
   *
   * @return object CRM_Volunteer_BAO_Project object
   * @access public
   * @static
   */
  static function create(array $params) {

    // check required params
    if (!self::dataExists($params)) {
      CRM_Core_Error::fatal('Not enough data to create volunteer project object.');
    }

    // default to active unless explicitly turned off
    $params['is_active'] = CRM_Utils_Array::value('is_active', $params, TRUE);

    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);

    $project->save();

    return $project;
  }

  /**
   * Find out if a project is active
   *
   * @param $entityId
   * @param $entityTable
   * @return boolean|null Boolean if project exists, null otherwise
   */
  static function isActive($entityId, $entityTable) {
    $params['entity_id'] = $entityId;
    $params['entity_table'] = $entityTable;
    $projects = self::retrieve($params);

    if (count($projects) === 1) {
      $p = current($projects);
      return $p->is_active;
    }
    return NULL;
  }

  /**
   * Get a list of Projects matching the params, where params keys are column
   * names of civicrm_volunteer_project.
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  static function retrieve(array $params) {
    $result = array();

    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);
    $project->find();

    while ($project->fetch()) {
      $result[(int) $project->id] = clone $project;
    }

    $project->free();

    return $result;
  }

  /**
   * Wrapper method for retrieve
   *
   * @param mixed $id Int or int-like string representing project ID
   * @return CRM_Volunteer_BAO_Project
   */
  static function retrieveByID($id) {
    $id = (int) CRM_Utils_Type::validate($id, 'Integer');

    $projects = self::retrieve(array('id' => $id));

    if (!array_key_exists($id, $projects)) {
      CRM_Core_Error::fatal("No project with ID $id exists.");
    }

    return $projects[$id];
  }

  /**
   * Check if there is absolute minimum of data to add the object
   *
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return boolean
   * @access public
   */
  public static function dataExists($params) {
    if (
      CRM_Utils_Array::value('id', $params) || (
        CRM_Utils_Array::value('entity_table', $params) &&
        CRM_Utils_Array::value('entity_id', $params)
      )
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns TRUE if value represents an "off" value, FALSE otherwise
   *
   * @param type $value
   * @return boolean
   * @access public
   */
  public static function isOff($value) {
    if (in_array($value, array(FALSE, 0, '0'), TRUE)) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * Given an associative array of name/value pairs, extract all the values
   * that belong to this object and initialize the object with said values. This
   * override adds a little data massaging prior to calling its parent.
   *
   * @param array $params (reference ) associative array of name/value pairs
   *
   * @return boolean      did we copy all null values into the object
   * @access public
   */
  public function copyValues(&$params) {
    if (array_key_exists('is_active', $params)) {
      /*
       * don't force is_active to have a value if none was set, to allow searches
       * where the is_active state of Projects is irrelevant
       */
      $params['is_active'] = CRM_Volunteer_BAO_Project::isOff($params['is_active']) ? 0 : 1;
    }
    return parent::copyValues($params);
  }

  /**
   * Sets and returns name of the entity associated with this Project
   *
   * @access private
   */
  private function _get_title() {
    if (!$this->title) {
      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $params = array(
              'id' => $this->entity_id,
              'return' => array('title'),
            );
            $result = civicrm_api3('Event', 'get', $params);
            $this->title = $result['values'][$this->entity_id]['title'];
            break;
        }
      }
    }
    return $this->title;
  }

  /**
   * Given project_id, return ID of flexible Need
   *
   * @param int $project_id
   * @return mixed Integer on success, else NULL
   */
  public static function getFlexibleNeedID ($project_id) {
    $result = NULL;

    if (is_int($project_id) || ctype_digit($project_id)) {
      $flexibleNeed = civicrm_api3('volunteer_need', 'getsingle', array(
        'is_active' => 1,
        'is_flexible' => 1,
        'project_id' => $project_id,
      ));
      $result = (int) $flexibleNeed['id'];
    }

    return $result;
  }

  /**
   * Sets and returns name of the entity associated with this Project
   *
   * @access private
   */
  private function _get_start_date() {
    if (!$this->start_date) {
      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $params = array(
              'id' => $this->entity_id,
              'return' => array('start_date'),
            );
            $result = civicrm_api3('Event', 'get', $params);
            $this->start_date = $result['values'][$this->entity_id]['start_date'];
            break;
        }
      }
    }
    return $this->start_date;
  }

  /**
   * Sets and returns name of the entity associated with this Project
   *
   * @access private
   */
  private function _get_end_date() {
    if (!$this->end_date) {
      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $params = array(
              'id' => $this->entity_id,
              'return' => array('end_date'),
            );
            $result = civicrm_api3('Event', 'get', $params);
            $this->end_date = CRM_Utils_Array::value('end_date', $result['values'][$this->entity_id]);
            break;
        }
      }
    }
    return $this->end_date;
  }

  /**
   * Sets $this->needs and returns the Needs associated with this Project. Delegate of __get().
   * Note: only active, visible needs are returned.
   *
   * @return array Needs as returned by API
   */
  private function _get_needs() {
    if (empty($this->needs)) {
      $result = civicrm_api3('VolunteerNeed', 'get', array(
        'is_active' => '1',
        'project_id' => $this->id,
        'visibility_id' => CRM_Core_OptionGroup::getValue('visibility', 'public', 'name'),
        'options' => array('sort' => 'start_time'),
      ));
      $this->needs = $result['values'];
      foreach (array_keys($this->needs) as $need_id) {
        $this->needs[$need_id]['quantity_assigned'] = CRM_Volunteer_BAO_Need::getAssignmentCount($need_id);
      }
    }

    return $this->needs;
  }

  /**
   * Sets $this->roles and returns the Roles associated with this Project. Delegate of __get().
   * Note: only roles for active, visible needs are returned.
   *
   * @return array Roles, labels keyed by IDs
   */
  private function _get_roles() {
    if (empty($this->roles)) {
      $roles = array();

      if (empty($this->needs)) {
        $this->_get_needs();
      }

      foreach ($this->needs as $need) {
        if (CRM_Utils_Array::value('is_flexible', $need) == '1') {
          $roles[CRM_Volunteer_BAO_Need::FLEXIBLE_ROLE_ID] = CRM_Volunteer_BAO_Need::getFlexibleRoleLabel();
        } else {
          $role_id = CRM_Utils_Array::value('role_id', $need);
          $roles[$role_id] = CRM_Core_OptionGroup::getLabel(
            CRM_Volunteer_Upgrader::customOptionGroupName,
            $role_id
          );
        }
      }
      asort($roles);
      $this->roles = $roles;
    }

    return $this->roles;
  }

  /**
   * Sets $this->shifts and returns the shifts associated with this Project. Delegate of __get().
   * Note: only shifts for active, visible needs are returned.
   *
   * @return array Shifts array is keyed by Need ID, with a subarray keyed by 'label' and 'role_id'
   */
  private function _get_shifts() {
    if (empty($this->shifts)) {
      $shifts = array();

      if (empty($this->needs)) {
        $this->_get_needs();
      }

      foreach ($this->needs as $id => $need) {
        if (!empty($need['start_time'])) {
          $shifts[$id] = array(
            'label' => CRM_Volunteer_BAO_Need::getTimes($need['start_time'], CRM_Utils_Array::value('duration', $need)),
            'role_id' => $need['role_id'],
          );
        }
      }

      $this->shifts = $shifts;
    }

    return $this->shifts;
  }
}
