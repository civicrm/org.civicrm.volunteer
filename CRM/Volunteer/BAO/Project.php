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

class CRM_Volunteer_BAO_Project extends CRM_Volunteer_DAO_Project {

  /**
   * The title of the Project, inherited from its associated entity
   *
   * @var string
   * @access public (via __get method)
   */
  private $title;

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
   * Unsets the Project's is_active flag in the database
   */
  public function disable() {
    $this->is_active = 0;
    $this->save();
  }

  /**
   * Sets the Project's is_active flag in the database
   */
  public function enable() {
    $this->is_active = 1;
    $this->save();
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
      $result[$project->id] = clone $project;
    }

    $project->free();

    return $result;
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
              'version' => 3,
              'id' => $this->entity_id,
              'return' => array('title'),
            );
            $result = civicrm_api('Event', 'get', $params);
            $this->title = $result['values'][$this->entity_id]['title'];
            break;
        }
      }
    }
    return $this->title;
  }
}
