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
   * Array of attributes on the related entity, translated to a common vocabulary.
   *
   * For example, an event's 'start_date' property is standardized to
   * 'start_time.'
   *
   * @see CRM_Volunteer_BAO_Project::getEntityAttributes()
   * @var array
   */
  private $entityAttributes = array();

  /**
   * The ID of the flexible Need for this Project. Accessible via __get method.
   *
   * @var int
   */
  private $flexible_need_id;

  /**
   * Array of associated Needs. Accessible via __get method.
   *
   * @var array
   */
  private $needs = array();

  /**
   * Array of profile IDs associated with the project.
   *
   * TODO: Should this property really be public?
   *
   * @var array
   */
  public $profileIds = array();

  /**
   * Array of associated Roles. Accessible via __get method.
   *
   * @var array Role labels keyed by IDs
   */
  private $roles = array();

  /**
   * Array of open needs. Open means:
   * <ol>
   *   <li>that the number of volunteer assignments associated with the need is
   *    fewer than quantity specified for the need</li>
   *   <li>that the need's start time or end time is in the future</li>
   *   <li>that the need is active</li>
   *   <li>that the need is visible</li>
   *   <li>that the need has a start_time (i.e., is not flexible)</li>
   * </ol>
   * Accessible via __get method.
   *
   * @var array Keyed by Need ID, with a subarray keyed by 'label' and 'role_id'
   */
  private $open_needs = array();

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
   * Gets related contacts of a specified type for a project.
   *
   * @param int $projectId
   * @param mixed $relationshipType
   *   Use either the value or the machine name for the optionValue
   * @return array
   *   Array of contact IDs
   */
  public static function getContactsByRelationship($projectId, $relationshipType) {
    $contactIds = array();

    $api = civicrm_api3('VolunteerProjectContact', 'get', array(
      'project_id' => $projectId,
      'relationship_type_id' => $relationshipType,
    ));
    foreach ($api['values'] as $rel) {
      $contactIds[] = $rel['contact_id'];
    }

    return $contactIds;
  }

  /**
   * Gets related contacts of a project nested by relationship type
   *
   * @param int $projectId
   * @return array
   *   Multidimensional array of contacts relationship_type_id => [contacts]
   */
  static function getContactsNestedbyRelationship($projectId) {
    $tmp = array("project_id" => $projectId);
    $result = _civicrm_api3_basic_get("CRM_Volunteer_BAO_ProjectContact", $tmp);
    $values = array();
    foreach($result['values'] as $relationship) {
      if(!array_key_exists($relationship['relationship_type_id'], $values)) {
        $values[$relationship['relationship_type_id']] = array();
      }
      $values[$relationship['relationship_type_id']][] = $relationship['contact_id'];
    }
    return $values;
  }

  /**
   * Create a Volunteer Project
   *
   * Takes an associative array and creates a Project object. This function is
   * invoked from within the web form layer and also from the API layer. Allows
   * the creation of project contacts, e.g.:
   *
   * $params['project_contacts'] = array(
   *   $relationship_type_name_or_id => $arr_contact_ids,
   * );
   *
   * @param array   $params      an assoc array of name/value pairs
   *
   * @return CRM_Volunteer_BAO_Project object
   * @access public
   * @static
   */
  static function create(array $params) {
    $projectId = CRM_Utils_Array::value('id', $params);
    $op = empty($projectId) ? CRM_Core_Action::ADD : CRM_Core_Action::UPDATE;

    if (!empty($params['check_permissions']) && !CRM_Volunteer_Permission::checkProjectPerms($op, $projectId)) {
      CRM_Utils_System::permissionDenied();

      // FIXME: If we don't return here, the script keeps executing. This is not
      // what I expect from CRM_Utils_System::permissionDenied().
      return FALSE;
    }

    // check required params
    if (!self::dataExists($params)) {
      CRM_Core_Error::fatal('Not enough data to create volunteer project object.');
    }

    // default to active unless explicitly turned off
    $params['is_active'] = CRM_Utils_Array::value('is_active', $params, TRUE);

    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);

    $project->save();

    $existingContacts = CRM_Volunteer_BAO_Project::getContactsNestedbyRelationship($project->id);
    $projectContacts = CRM_Utils_Array::value('project_contacts', $params, array());
    foreach ($projectContacts as $relationshipType => &$contactIds) {
      if(!is_array($contactIds)) {
        $contactIds = array($contactIds);
      }
      foreach ($contactIds as $id) {
        if(!array_key_exists($relationshipType, $existingContacts) || !in_array($id, $existingContacts[$relationshipType])) {
          civicrm_api3('VolunteerProjectContact', 'create', array(
            'contact_id' => $id,
            'project_id' => $project->id,
            'relationship_type_id' => $relationshipType,
          ));
        }
      }
    }

    $project->contacts = $projectContacts;

    $profiles = CRM_Utils_Array::value('profiles', $params, array());
    foreach ($profiles as $profile) {
      $profile['is_active'] = 1;
      $profile['module'] = "CiviVolunteer";
      $profile['entity_table'] = "civicrm_volunteer_project";
      $profile['entity_id'] = $project->id;
      $result = civicrm_api3('UFJoin', 'create', $profile);
      if ($result['is_error'] == 0) {
        $project->profileIds[] = $result['values'][0]['id'];
      }
    }

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
   * Get a list of Projects matching the params.
   *
   * This function is invoked from within the web form layer and also from the
   * API layer. Special params include:
   * <ol>
   *   <li>project_contacts (@see CRM_Volunteer_BAO_Project::create() and
   *     CRM_Volunteer_BAO_Project::buildContactJoin)</li>
   *   <li>proximity (@see CRM_Volunteer_BAO_Project::buildProximityWhere)</li>
   * </ol>
   *
   * NOTE: This method does not return data related to the special params
   * outlined above; however, these parameters can be used to filter the list
   * of Projects that is returned.
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_Project objects
   */
  public static function retrieve(array $params) {
    $result = array();

    $checkPerms = CRM_Utils_Array::value('check_permissions', $params);
    if($checkPerms && !CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::VIEW)) {
      CRM_Utils_System::permissionDenied();
      return;
    }

    $query = CRM_Utils_SQL_Select::from('`civicrm_volunteer_project` vp')
      ->select('DISTINCT vp.*');

    if (!empty($params['project_contacts'])) {
      $contactJoin = self::buildContactJoin($params['project_contacts']);
      if ($contactJoin) {
        $query->join('vpc', $contactJoin);
      }
    }

    if (!empty($params['proximity'])) {
      $query->join('loc', 'INNER JOIN `civicrm_loc_block` loc ON loc.id = vp.loc_block_id')
        ->join('civicrm_address', 'INNER JOIN `civicrm_address` ON civicrm_address.id = loc.address_id')
        ->where(self::buildProximityWhere($params['proximity']));
    }

    // This step is here to support both naming conventions for specifying params
    // (e.g., volunteer_project_id and id) while normalizing how we access them
    // (e.g., $project->id)
    $project = new CRM_Volunteer_BAO_Project();
    $project->copyValues($params);

    foreach ($project->fields() as $field) {
      $fieldName = $field['name'];

      if (!empty($project->$fieldName)) {
        $query->where('!column = @value', array(
          'column' => $fieldName,
          'value' => $project->$fieldName,
        ));
      }
    }

    $dao = self::executeQuery($query->toSQL());
    while ($dao->fetch()) {
      $fetchedProject = new CRM_Volunteer_BAO_Project();
      $fetchedProject->copyValues(clone $dao);
      $result[(int) $dao->id] = $fetchedProject;
    }
    $dao->free();

    return $result;
  }

  /**
   * Helper method to filter Projects by related contact.
   *
   * Conditionally invoked by CRM_Volunteer_BAO_Project::retrieve().
   *
   * @param array $projectContacts
   *   @see CRM_Volunteer_BAO_Project::create() for details on this parameter
   * @return mixed
   *   Boolean FALSE if no projects have the specified contact relationships;
   *   String SQL fragment otherwise
   */
  private static function buildContactJoin(array $projectContacts) {
    $result = FALSE;
    $onClauses = array();

    $relTypes = CRM_Core_OptionGroup::values(
      CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP,
      TRUE, FALSE, FALSE, NULL, 'name');

    foreach ($projectContacts as $relType => $contactIds) {
      if (!CRM_Utils_Type::validate($relType, 'Integer', FALSE)) {
        $relType = $relTypes[$relType];
      }
      $contactIds = implode(',', (array) $contactIds);

      $onClauses[] = "(vpc.contact_id IN ($contactIds) AND vpc.relationship_type_id = $relType)";
    }

    if (count($onClauses)) {
      $strOnClauses = implode(' OR ', $onClauses);
      $result = "INNER JOIN `civicrm_volunteer_project_contact` vpc
        ON vp.id = vpc.project_id AND ($strOnClauses)";
    }

    return $result;
  }

  /**
   * Helper method to filter Projects by location.
   *
   * @param array $params
   *   <ol>
   *     <li>string city - optional. Not used in this function, just passed along for geocoding.</li>
   *     <li>mixed country - required if lat/lon not provided. Can be country_id or string.</li>
   *     <li>float lat - required if country not provided</li>
   *     <li>float lon - required if country not provided</li>
   *     <li>string postal_code - optional. Not used in this function, just passed along for geocoding.</li>
   *     <li>float radius - required</li>
   *     <li>string street_address - optional. Not used in this function, just passed along for geocoding.</li>
   *     <li>string unit - optional, defaults to meters unless 'mile' is specified</li>
   *   </ol>
   * @return string
   *   SQL fragment (partial where clause)
   * @throws Exception
   */
  private static function buildProximityWhere(array $params) {
    $country = $lat = $lon = $radius = $unit = NULL;
    extract($params, EXTR_IF_EXISTS);

    // ensure that radius is a float
    if (!CRM_Utils_Rule::numeric($radius)) {
      throw new Exception(ts('Radius should exist and be numeric'));
    }

    if (!CRM_Utils_Rule::numeric($lat) || !CRM_Utils_Rule::numeric($lon)) {
      if (empty($country)) {
        throw new Exception(ts('Either Country or both Latitude and Longitude are required'));
      }

      // TODO: I think CRM_Utils_Geocode_*::format should be responsible for this
      // If/when CRM-17245 is closed, this if-block can be removed.
      if (CRM_Utils_Type::validate($country, 'Positive', FALSE)) {
        $country = civicrm_api3('Country', 'getvalue', array(
          'id' => $country,
          'return' => 'name',
        ));
      }

      // TODO: support other geocoders
      $geocodeSuccess = CRM_Utils_Geocode_Google::format($params);
      if (!$geocodeSuccess) {
        // this is intentionally a string; a query like "SELECT * FROM foo WHERE FALSE"
        // will return an empty set, which is what we should do if the provided address
        // can't be geocoded
        return 'FALSE';
      }
      // $params is passed to the geocoder by reference; on success, these values
      // will be available
      $lat = $params['geo_code_1'];
      $lon = $params['geo_code_2'];
    }

    $conversionFactor = ($unit == "mile") ? 1609.344 : 1000;
    //radius in meters
    $radius = $radius * $conversionFactor;

    return CRM_Contact_BAO_ProximityQuery::where($lat, $lon, $radius);
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
   * Check if there is absolute minimum of data to add the object.
   *
   * @param array $params
   *   An associatve array of name/value pairs
   * @return boolean
   */
  public static function dataExists($params) {
    return (CRM_Utils_Array::value('id', $params) || CRM_Utils_Array::value('title', $params));
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
   * Initialize this object with provided values. This override adds a little
   * data massaging prior to calling its parent.
   *
   * @param mixed $params
   *   An associative array of name/value pairs or a CRM_Core_DAO object
   *
   * @return boolean      did we copy all null values into the object
   * @access public
   */
  public function copyValues(&$params) {
    if (is_a($params, 'CRM_Core_DAO')) {
      $params = get_object_vars($params);
    }

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
   * Fetches attributes for the associated entity and puts them in
   * $this->entityAttributes, using a common vocabulary defined in $arrayKeys.
   *
   * @see CRM_Volunteer_BAO_Project::$entityAttributes
   * @return array
   */
  public function getEntityAttributes() {
    if (!$this->entityAttributes) {
      $arrayKeys = array('start_time', 'title');
      $this->entityAttributes = array_fill_keys($arrayKeys, NULL);

      if ($this->entity_table && $this->entity_id) {
        switch ($this->entity_table) {
          case 'civicrm_event' :
            $result = civicrm_api3('Event', 'getsingle', array(
              'id' => $this->entity_id,
            ));
            $this->entityAttributes['title'] = $result['title'];
            $this->entityAttributes['start_time'] = $result['start_date'];
            break;
        }
      }
    }
    return $this->entityAttributes;
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
      $flexibleNeed = civicrm_api('volunteer_need', 'getvalue', array(
        'is_active' => 1,
        'is_flexible' => 1,
        'project_id' => $project_id,
        'return' => 'id',
        'version' => 3,
      ));
      if (CRM_Utils_Array::value('is_error', $flexibleNeed) !== 1) {
        $result = (int) $flexibleNeed;
      }
    }

    return $result;
  }

  /**
   * Sets and returns the start date of the entity associated with this Project
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
   * Sets and returns the end date of the entity associated with this Project
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
        'options' => array(
          'sort' => 'start_time',
          'limit' => 0,
        ),
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
            CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP,
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
   * Sets and returns $this->open_needs. Delegate of __get().
   *
   * @return array Keyed by Need ID, with a subarray keyed by 'label' and 'role_id'
   */
  private function _get_open_needs() {
    if (empty($this->open_needs)) {

      if (empty($this->needs)) {
        $this->_get_needs();
      }

      $now = time();
      foreach ($this->needs as $id => $need) {
        if (
          !empty($need['start_time'])
          && ($need['quantity'] > $need['quantity_assigned'])
          && (
            strtotime($need['start_time']) >= $now
            || strtotime($need['end_time']) >= $now
          )
        ) {
          $this->open_needs[$id] = $need;
        }
      }
    }

    return $this->open_needs;
  }

  /**
   * Sets and returns $this->flexible_need_id. Delegate of __get().
   *
   * @return mixed Integer if project has a flexible need, else NULL
   */
  private function _get_flexible_need_id() {
    return self::getFlexibleNeedID($this->id);
  }
}
