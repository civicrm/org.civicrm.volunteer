<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from org.civicrm.volunteer/xml/schema/CRM/Volunteer/Project.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:10f524cbe66b778694cc0184b796310c)
 */
use CRM_Volunteer_ExtensionUtil as E;

/**
 * Database access object for the Project entity.
 */
class CRM_Volunteer_DAO_Project extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '4.4';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_volunteer_project';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Project Id
   *
   * @var int
   */
  public $id;

  /**
   * The title of the Volunteer Project
   *
   * @var string
   */
  public $title;

  /**
   * Full description of the Volunteer Project. Text and HTML allowed. Displayed on sign-up screens.
   *
   * @var text
   */
  public $description;

  /**
   * Entity table for entity_id (initially civicrm_event)
   *
   * @var string
   */
  public $entity_table;

  /**
   * Implicit FK project entity (initially eventID).
   *
   * @var int
   */
  public $entity_id;

  /**
   * Is this need enabled?
   *
   * @var bool
   */
  public $is_active;

  /**
   * FK to Location Block ID
   *
   * @var int
   */
  public $loc_block_id;

  /**
   * The campaign associated with this Volunteer Project.
   *
   * @var int
   */
  public $campaign_id;
   /**
   * The type associated with this Volunteer Project. Implicit FK to option_value row in volunteer_project_type option_group.
   *
   * @var int unsigned
   */
  public $type_id;
  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_volunteer_project';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Volunteer Projects') : E::ts('Volunteer Project');
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'loc_block_id', 'civicrm_loc_block', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'campaign_id', 'civicrm_campaign', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('CiviVolunteer Project ID'),
          'description' => E::ts('Project Id'),
          'required' => TRUE,
          'where' => 'civicrm_volunteer_project.id',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => '4.4',
        ],
        'title' => [
          'name' => 'title',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Title'),
          'description' => E::ts('The title of the Volunteer Project'),
          'required' => TRUE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'where' => 'civicrm_volunteer_project.title',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'localizable' => 0,
          'add' => '4.5',
        ],
        'description' => [
          'name' => 'description',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => E::ts('Description'),
          'description' => E::ts('Full description of the Volunteer Project. Text and HTML allowed. Displayed on sign-up screens.'),
          'required' => FALSE,
          'rows' => 8,
          'cols' => 60,
          'where' => 'civicrm_volunteer_project.description',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'localizable' => 0,
          'html' => [
            'type' => 'RichTextEditor',
          ],
          'add' => '4.5',
        ],
        'entity_table' => [
          'name' => 'entity_table',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Entity Table'),
          'description' => E::ts('Entity table for entity_id (initially civicrm_event)'),
          'required' => TRUE,
          'maxlength' => 64,
          'size' => CRM_Utils_Type::BIG,
          'where' => 'civicrm_volunteer_project.entity_table',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'localizable' => 0,
          'add' => '4.4',
        ],
        'entity_id' => [
          'name' => 'entity_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('Implicit FK project entity (initially eventID).'),
          'required' => TRUE,
          'where' => 'civicrm_volunteer_project.entity_id',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'localizable' => 0,
          'add' => '4.4',
        ],
        'is_active' => [
          'name' => 'is_active',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => E::ts('Enabled'),
          'description' => E::ts('Is this need enabled?'),
          'required' => TRUE,
          'where' => 'civicrm_volunteer_project.is_active',
          'default' => '1',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'localizable' => 0,
          'add' => '4.4',
        ],
        'loc_block_id' => [
          'name' => 'loc_block_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Location Block ID'),
          'description' => E::ts('FK to Location Block ID'),
          'where' => 'civicrm_volunteer_project.loc_block_id',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'localizable' => 0,
          'FKClassName' => 'CRM_Core_DAO_LocBlock',
          'add' => '4.5',
        ],
        'campaign_id' => [
          'name' => 'campaign_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Campaign'),
          'description' => E::ts('The campaign associated with this Volunteer Project.'),
          'required' => FALSE,
          'where' => 'civicrm_volunteer_project.campaign_id',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'localizable' => 0,
          'FKClassName' => 'CRM_Campaign_DAO_Campaign',
          'component' => 'CiviCampaign',
          'html' => [
            'type' => 'EntityRef',
            'label' => E::ts("Campaign"),
          ],
          'pseudoconstant' => [
            'table' => 'civicrm_campaign',
            'keyColumn' => 'id',
            'labelColumn' => 'title',
            'prefetch' => 'FALSE',
          ],
          'add' => '4.5',
        ],
	'type_id' => [
          'name' => 'type_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => ts('Type', array('domain' => 'org.civicrm.volunteer')) ,
          'description' => 'Implicit FK to option_value row in volunteer_project_type option_group.',
          'default' => 'NULL',
          'table_name' => 'civicrm_volunteer_project',
          'entity' => 'Project',
          'bao' => 'CRM_Volunteer_DAO_Project',
          'pseudoconstant' => array(
            'optionGroupName' => 'volunteer_project_type',
            'optionEditPath' => 'civicrm/admin/options/volunteer_project_type',
          )
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'volunteer_project', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'volunteer_project', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
