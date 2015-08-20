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
class CRM_Volunteer_Form_VolunteerReport extends CRM_Report_Form {

  protected $_customGroupExtends = array(
    'Activity'
  );

  function __construct() {
    $this->customGroup = CRM_Volunteer_BAO_Assignment::getCustomGroup();
    $this->customFields = CRM_Volunteer_BAO_Assignment::getCustomFields();
    $this->activityTypeID = CRM_Volunteer_BAO_Assignment::getActivityTypeId();
    $this->_columns = array(
      'project' => array(
        'fields' => array(
          'project' => array(
            'name' => 'title',
            'title' => ts('Project', array('domain' => 'org.civicrm.volunteer')),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
        ),
        'alias' => 'project',
        'order_bys' => array(
          'title' => array(
            'title' => ts('Project', array('domain' => 'org.civicrm.volunteer')),
            'default' => 1,
          ),
        ),
        'grouping' => 'project-fields',
      ),
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'contact_assignee' => array(
            'name' => 'sort_name',
            'title' => ts('Volunteer Name', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'civicrm_contact_assignee_civireport',
            'default' => TRUE,
            'required' => TRUE,
          ),
          'contact_source' => array(
            'name' => 'sort_name',
            'title' => ts('Source Contact Name', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'civicrm_contact_source',
            'no_repeat' => TRUE,
          ),
          'contact_target' => array(
            'name' => 'sort_name',
            'title' => ts('Target Contact Name', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'contact_civireport',
          ),
        ),
        'filters' => array(
          'contact_assignee' => array(
            'name' => 'sort_name',
            'alias' => 'civicrm_contact_assignee_civireport',
            'title' => ts('Volunteer Name', array('domain' => 'org.civicrm.volunteer')),
            'operator' => 'like',
            'type' => CRM_Report_Form::OP_STRING,
          ),
          'contact_source' => array(
            'name' => 'sort_name',
            'alias' => 'civicrm_contact_source',
            'title' => ts('Source Contact Name', array('domain' => 'org.civicrm.volunteer')),
            'operator' => 'like',
            'type' => CRM_Report_Form::OP_STRING,
          ),
          'contact_target' => array(
            'name' => 'sort_name',
            'alias' => 'contact_civireport',
            'title' => ts('Target Contact Name', array('domain' => 'org.civicrm.volunteer')),
            'operator' => 'like',
            'type' => CRM_Report_Form::OP_STRING,
          ),
          'current_user' => array(
            'name' => 'current_user',
            'title' => ts('Limit To Current User', array('domain' => 'org.civicrm.volunteer')),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => array('0' => ts('No', array('domain' => 'org.civicrm.volunteer')), '1' => ts('Yes', array('domain' => 'org.civicrm.volunteer'))),
          ),
        ),
        'grouping' => 'contact-fields',
        'order_bys' => array(
          'sort_name' => array(
            'title' => ts('Last Name, First Name', array('domain' => 'org.civicrm.volunteer')),
            'default' => '1'
          ),
        ),
        'alias' => 'civicrm_contact_assignee',
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array(
          'contact_assignee_email' => array(
            'name' => 'email',
            'title' => ts('Volunteer Email', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'civicrm_email_assignee',
          ),
          'contact_source_email' => array(
            'name' => 'email',
            'title' => ts('Source Contact Email', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'civicrm_email_source',
          ),
          'contact_target_email' => array(
            'name' => 'email',
            'title' => ts('Target Contact Email', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'civicrm_email_target',
          ),
        ),
      ),
      'civicrm_phone' => array(
        'dao' => 'CRM_Core_DAO_Phone',
        'fields' => array(
          'contact_assignee_phone' => array(
            'name' => 'phone',
            'title' => ts('Volunteer Phone', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'civicrm_phone_assignee',
          ),
          'contact_source_phone' => array(
            'name' => 'phone',
            'title' => ts('Source Contact Phone', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'civicrm_phone_source',
          ),
          'contact_target_phone' => array(
            'name' => 'phone',
            'title' => ts('Target Contact Phone', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'civicrm_phone_target',
          ),
        ),
      ),
      'civicrm_activity' => array(
        'dao' => 'CRM_Activity_DAO_Activity',
        'fields' => array(
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'source_record_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'activity_type_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'activity_subject' => array(
            'title' => ts('Subject', array('domain' => 'org.civicrm.volunteer')),
            'default' => TRUE,
          ),
          'activity_date_time' => array(
            'title' => ts('Scheduled Date/Time', array('domain' => 'org.civicrm.volunteer')),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'status_id' => array(
            'title' => ts('Activity Status', array('domain' => 'org.civicrm.volunteer')),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
        ),
        'filters' => array(
          'activity_date_time' => array(
            'default' => 'this.month',
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'activity_subject' => array(
            'title' => ts('Activity Subject', array('domain' => 'org.civicrm.volunteer')),
          ),
          'id' => array(
            'title' => ts('Project', array('domain' => 'org.civicrm.volunteer')),
            'operatorType' => CRM_Report_Form::OP_ENTITYREF,
            'type' => CRM_Utils_Type::T_INT,
            'attributes' => array(
              'entity' => 'VolunteerProject',
              'select' => array('minimumInputLength' => 0),
            ),
            'alias' => 'project_civireport',
          ),
          'status_id' => array(
            'title' => ts('Activity Status', array('domain' => 'org.civicrm.volunteer')),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_PseudoConstant::activityStatus(),
          ),
        ),
      ),
      'civicrm_activity_assignment' => array(
        'dao' => 'CRM_Activity_DAO_ActivityContact',
        'fields' => array(
          'contact_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'alias' => 'activity_assignment',
      ),
      'civicrm_activity_target' => array(
        'dao' => 'CRM_Activity_DAO_ActivityContact',
        'fields' => array(
          'contact_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'alias' => 'activity_target',
      ),
      'civicrm_activity_source' => array(
        'dao' => 'CRM_Activity_DAO_ActivityContact',
        'fields' => array(
          'contact_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'alias' => 'activity_source',
      ),
      'role' => array(
        'fields' => array(
          'role' => array(
            'name' => $this->customFields['volunteer_role_id']['column_name'],
            'title' => ts('Volunteer Role', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'cg',
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
        ),
        'order_bys' => array(
          'name' => array('title' => ts('Volunteer Role', array('domain' => 'org.civicrm.volunteer'))),
        ),
        'alias' => 'ov',
      ),
      'time_scheduled' => array(
        'fields' => array(
          'time_scheduled' => array(
            'name' => $this->customFields['time_scheduled_minutes']['column_name'],
            'title' => ts('Time Scheduled in Minutes', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'cg',
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
        ),
      ),
      'time_completed' => array(
        'fields' => array(
          'time_completed' => array(
            'name' => $this->customFields['time_completed_minutes']['column_name'],
            'title' => ts('Time Completed in Minutes', array('domain' => 'org.civicrm.volunteer')),
            'alias' => 'cg',
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
        ),
      ),
      'civicrm_case_activity' => array(
        'dao' => 'CRM_Case_DAO_CaseActivity',
        'fields' => array(
          'case_id' => array(
            'name' => 'case_id',
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'alias' => 'case_activity',
      ),
    );

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  function select() {
    $select = array();
    $seperator = CRM_CORE_DAO::VALUE_SEPARATOR;
    $this->_columnHeaders = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            if (isset($this->_params['group_bys']) &&
              !CRM_Utils_Array::value('activity_type_id', $this->_params['group_bys']) &&
              (in_array($fieldName, array(
                  'contact_assignee', 'assignee_contact_id')) ||
                in_array($fieldName, array('contact_target', 'target_contact_id'))
              )
            ) {
              $orderByRef = "activity_assignment_civireport.contact_id";
              if (in_array($fieldName, array(
                'contact_target', 'target_contact_id'))) {
                $orderByRef = "activity_target_civireport.contact_id";
              }
              $select[] = "GROUP_CONCAT(DISTINCT {$field['dbAlias']}  ORDER BY {$orderByRef} SEPARATOR '{$seperator}') as {$tableName}_{$fieldName}";
            }
            else {
              $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            }

            $alias = "{$tableName}_{$fieldName}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = CRM_Utils_Array::value('title', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['no_display'] = CRM_Utils_Array::value('no_display', $field);
            $this->_selectAliases[] = $alias;
          }
        }
      }
    }
    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  function from() {
    $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
    $roleID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_OptionGroup', 'volunteer_role', 'id','name');
    $assigneeID = CRM_Utils_Array::key('Activity Assignees', $activityContacts);
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContacts);
    $sourceID = CRM_Utils_Array::key('Activity Source', $activityContacts);
    $this->_from = "
        FROM civicrm_activity {$this->_aliases['civicrm_activity']}
             LEFT JOIN civicrm_activity_contact  {$this->_aliases['civicrm_activity_target']}
                    ON {$this->_aliases['civicrm_activity']}.id = {$this->_aliases['civicrm_activity_target']}.activity_id AND
                       {$this->_aliases['civicrm_activity_target']}.record_type_id = {$targetID}
             LEFT JOIN {$this->customGroup['table_name']} cg
                    ON {$this->_aliases['civicrm_activity']}.id = cg.entity_id
             LEFT JOIN civicrm_volunteer_need n
                    ON n.id = cg.{$this->customFields['volunteer_need_id']['column_name']}
             LEFT JOIN civicrm_option_value {$this->_aliases['role']} ON ( {$this->_aliases['role']}.value = cg.{$this->customFields['volunteer_role_id']['column_name']} AND {$this->_aliases['role']}.option_group_id = {$roleID} )
             LEFT JOIN civicrm_volunteer_project {$this->_aliases['project']}
                    ON {$this->_aliases['project']}.id = n.project_id
             LEFT JOIN civicrm_activity_contact {$this->_aliases['civicrm_activity_assignment']}
                    ON {$this->_aliases['civicrm_activity']}.id = {$this->_aliases['civicrm_activity_assignment']}.activity_id AND
                       {$this->_aliases['civicrm_activity_assignment']}.record_type_id = {$assigneeID}
             LEFT JOIN civicrm_activity_contact {$this->_aliases['civicrm_activity_source']}
                    ON {$this->_aliases['civicrm_activity']}.id = {$this->_aliases['civicrm_activity_source']}.activity_id AND
                       {$this->_aliases['civicrm_activity_source']}.record_type_id = {$sourceID}
             LEFT JOIN civicrm_contact contact_civireport
                    ON {$this->_aliases['civicrm_activity_target']}.contact_id = contact_civireport.id
             LEFT JOIN civicrm_contact civicrm_contact_assignee_civireport
                    ON {$this->_aliases['civicrm_activity_assignment']}.contact_id = civicrm_contact_assignee_civireport.id
             LEFT JOIN civicrm_contact civicrm_contact_source
                    ON {$this->_aliases['civicrm_activity_source']}.contact_id = civicrm_contact_source.id
             {$this->_aclFrom}
             LEFT JOIN civicrm_option_value
                    ON ( {$this->_aliases['civicrm_activity']}.activity_type_id = civicrm_option_value.value )
             LEFT JOIN civicrm_option_group
                    ON civicrm_option_group.id = civicrm_option_value.option_group_id
             LEFT JOIN civicrm_case_activity case_activity_civireport
                    ON case_activity_civireport.activity_id = {$this->_aliases['civicrm_activity']}.id
             LEFT JOIN civicrm_case
                    ON case_activity_civireport.case_id = civicrm_case.id
             LEFT JOIN civicrm_case_contact
                    ON civicrm_case_contact.case_id = civicrm_case.id ";

    if ($this->isTableSelected('civicrm_email')) {
      $this->_from .= "
            LEFT JOIN civicrm_email civicrm_email_source
                   ON {$this->_aliases['civicrm_activity_source']}.contact_id = civicrm_email_source.contact_id AND
                      civicrm_email_source.is_primary = 1

            LEFT JOIN civicrm_email civicrm_email_target
                   ON {$this->_aliases['civicrm_activity_target']}.contact_id = civicrm_email_target.contact_id AND
                      civicrm_email_target.is_primary = 1

            LEFT JOIN civicrm_email civicrm_email_assignee
                   ON {$this->_aliases['civicrm_activity_assignment']}.contact_id = civicrm_email_assignee.contact_id AND
                      civicrm_email_assignee.is_primary = 1 ";
    }
    if ($this->isTableSelected('civicrm_phone')) {
      $this->_from .= "
            LEFT JOIN civicrm_phone civicrm_phone_source
                   ON {$this->_aliases['civicrm_activity_source']}.contact_id = civicrm_phone_source.contact_id AND
                      civicrm_phone_source.is_primary = 1

            LEFT JOIN civicrm_phone civicrm_phone_target
                   ON {$this->_aliases['civicrm_activity_target']}.contact_id = civicrm_phone_target.contact_id AND
                      civicrm_phone_target.is_primary = 1

            LEFT JOIN civicrm_phone civicrm_phone_assignee
                   ON {$this->_aliases['civicrm_activity_assignment']}.contact_id = civicrm_phone_assignee.contact_id AND
                      civicrm_phone_assignee.is_primary = 1 ";
    }
  }

  function where() {
    $this->_where = " WHERE civicrm_option_group.name = 'activity_type' AND
                                {$this->_aliases['civicrm_activity']}.is_test = 0 AND
                                {$this->_aliases['civicrm_activity']}.is_deleted = 0 AND
                                {$this->_aliases['civicrm_activity']}.is_current_revision = 1 AND
                                {$this->_aliases['civicrm_activity']}.activity_type_id = {$this->activityTypeID}";

    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {

        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          if (CRM_Utils_Array::value('type', $field) & CRM_Utils_Type::T_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($field['name'], $relative, $from, $to, $field['type']);
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if ($field['name'] == 'current_user') {
            if (CRM_Utils_Array::value("{$fieldName}_value", $this->_params) == 1) {
              // get current user
              $session = CRM_Core_Session::singleton();
              if ($contactID = $session->get('userID')) {
                $clause = "( civicrm_contact_source.id = " . $contactID . " OR civicrm_contact_assignee.id = " . $contactID . " OR contact_civireport.id = " . $contactID . " )";
              }
              else {
                $clause = NULL;
              }
            }
            else {
              $clause = NULL;
            }
          }
          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }

    if (empty($clauses)) {
      $this->_where .= " ";
    }
    else {
      $this->_where .= " AND " . implode(' AND ', $clauses);
    }

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $totalAmount =  array();
    $count = 0;
    $select = "
      SELECT
      SUM( cg.{$this->customFields['time_scheduled_minutes']['column_name']} ) AS scheduled,
      SUM( cg.{$this->customFields['time_completed_minutes']['column_name']} ) AS completed";
    $sql = "{$select} {$this->_from} {$this->_where}";
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $scheduled = $dao->scheduled;
      $completed = $dao->completed;
    }
    $statistics['counts']['scheduled'] = array(
      'title' => ts('Total Time Scheduled in Minutes', array('domain' => 'org.civicrm.volunteer')),
      'value' => $scheduled,
      'type' => CRM_Utils_Type::T_STRING,
    );
    $statistics['counts']['completed'] = array(
      'title' => ts('Total Time Completed in Minutes', array('domain' => 'org.civicrm.volunteer')),
      'value' => $completed,
      'type' => CRM_Utils_Type::T_STRING,
    );

    return $statistics;
  }

  function groupBy() {
    $this->_groupBy = "GROUP BY {$this->_aliases['civicrm_activity']}.id";
  }

  function buildACLClause($tableAlias = 'contact_a') {
    // override for ACL( Since Contact may be source
    // contact/assignee or target also it may be null )

    if (CRM_Core_Permission::check('view all contacts')) {
      $this->_aclFrom = $this->_aclWhere = NULL;
      return;
    }

    $session = CRM_Core_Session::singleton();
    $contactID = $session->get('userID');
    if (!$contactID) {
      $contactID = 0;
    }
    $contactID = CRM_Utils_Type::escape($contactID, 'Integer');

    CRM_Contact_BAO_Contact_Permission::cache($contactID);
    $clauses = array();
    foreach ($tableAlias as $k => $alias) {
      $clauses[] = " INNER JOIN civicrm_acl_contact_cache aclContactCache_{$k} ON ( {$alias}.id = aclContactCache_{$k}.contact_id OR {$alias}.id IS NULL ) AND aclContactCache_{$k}.user_id = $contactID ";
    }

    $this->_aclFrom = implode(" ", $clauses);
    $this->_aclWhere = NULL;
  }

  function postProcess() {

    $this->buildACLClause(array('civicrm_contact_source', 'contact_civireport', 'civicrm_contact_assignee'));
    parent::postProcess();
  }

  function alterDisplay(&$rows) {
    // custom code to alter rows

    $entryFound     = FALSE;
    $activityType   = CRM_Core_PseudoConstant::activityType(TRUE, TRUE, FALSE, 'label', TRUE);
    $activityStatus = CRM_Core_PseudoConstant::activityStatus();
    $volunteerRoles = CRM_Volunteer_BAO_Need::buildOptions('role_id', 'create');
    $viewLinks      = FALSE;
    $seperator      = CRM_CORE_DAO::VALUE_SEPARATOR;
    $context        = CRM_Utils_Request::retrieve('context', 'String', $this, FALSE, 'report');

    if (CRM_Core_Permission::check('access CiviCRM')) {
      $viewLinks  = TRUE;
      $onHover    = ts('View Contact Summary for this Contact', array('domain' => 'org.civicrm.volunteer'));
      $onHoverAct = ts('View Activity Record', array('domain' => 'org.civicrm.volunteer'));
    }

    foreach ($rows as $rowNum => $row) {
      if (array_key_exists('civicrm_contact_contact_source', $row)) {
        if ($value = $row['civicrm_activity_assignment_contact_id']) {
          if ($viewLinks) {
            $url = CRM_Utils_System::url("civicrm/contact/view",
              'reset=1&cid=' . $value,
              $this->_absoluteUrl
            );
            $rows[$rowNum]['civicrm_contact_contact_source_link'] = $url;
            $rows[$rowNum]['civicrm_contact_contact_source_hover'] = $onHover;
          }
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_contact_contact_assignee', $row)) {
        $assigneeNames = explode($seperator, $row['civicrm_contact_contact_assignee']);
        if ($value = $row['civicrm_activity_assignment_contact_id']) {
          $assigneeContactIds = explode($seperator, $value);
          $link = array();
          if ($viewLinks) {
            foreach ($assigneeContactIds as $id => $value) {
              $url = CRM_Utils_System::url("civicrm/contact/view",
                'reset=1&cid=' . $value,
                $this->_absoluteUrl
              );
              $link[] = "<a title='" . $onHover . "' href='" . $url . "'>{$assigneeNames[$id]}</a>";
            }
            $rows[$rowNum]['civicrm_contact_contact_assignee'] = implode('; ', $link);
          }
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_contact_contact_target', $row)) {
        $targetNames = explode($seperator, $row['civicrm_contact_contact_target']);
        if ($value = $row['civicrm_activity_target_contact_id']) {
          $targetContactIds = explode($seperator, $value);
          $link = array();
          if ($viewLinks) {
            foreach ($targetContactIds as $id => $value) {
              $url = CRM_Utils_System::url("civicrm/contact/view",
                'reset=1&cid=' . $value,
                $this->_absoluteUrl
              );
              $link[] = "<a title='" . $onHover . "' href='" . $url . "'>{$targetNames[$id]}</a>";
            }
            $rows[$rowNum]['civicrm_contact_contact_target'] = implode('; ', $link);
          }
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_activity_activity_type_id', $row)) {
        if ($value = $row['civicrm_activity_activity_type_id']) {
          $rows[$rowNum]['civicrm_activity_activity_type_id'] = $activityType[$value];
          if ($viewLinks) {
            // Check for assignee contact id(s) (since they are the volunteer and use the first contact id in that list for view activity link if found,
            // else use source contact id
            if (!empty($rows[$rowNum]['civicrm_activity_assignment_contact_id'])) {
              $targets = explode($seperator, $rows[$rowNum]['civicrm_activity_assignment_contact_id']);
              $cid = $targets[0];
            }
            else {
              $cid = $rows[$rowNum]['civicrm_activity_source_contact_id'];
            }

            $actionLinks = CRM_Activity_Selector_Activity::actionLinks($row['civicrm_activity_activity_type_id'],
              CRM_Utils_Array::value('civicrm_activity_source_record_id', $rows[$rowNum]),
              FALSE,
              $rows[$rowNum]['civicrm_activity_id']
            );

            $linkValues = array(
              'id' => $rows[$rowNum]['civicrm_activity_id'],
              'cid' => $cid,
              'cxt' => $context,
            );
            $url = CRM_Utils_System::url($actionLinks[CRM_Core_Action::VIEW]['url'],
              CRM_Core_Action::replace($actionLinks[CRM_Core_Action::VIEW]['qs'], $linkValues), TRUE
            );
            $rows[$rowNum]['civicrm_activity_activity_type_id_link'] = $url;
            $rows[$rowNum]['civicrm_activity_activity_type_id_hover'] = $onHoverAct;
          }
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_activity_status_id', $row)) {
        if ($value = $row['civicrm_activity_status_id']) {
          $rows[$rowNum]['civicrm_activity_status_id'] = $activityStatus[$value];
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('role_role', $row)) {
        if ($value = $row['role_role']) {
          $rows[$rowNum]['role_role'] = $volunteerRoles[$value];
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_activity_activity_date_time', $row)) {
        $rows[$rowNum]['civicrm_activity_activity_date_time'] = CRM_Utils_Date::customFormat($row['civicrm_activity_activity_date_time']);
        // Display overdue marker
        if (array_key_exists('civicrm_activity_status_id', $row) &&
          CRM_Utils_Date::overdue($rows[$rowNum]['civicrm_activity_activity_date_time']) &&
          $activityStatus[$row['civicrm_activity_status_id']] != 'Completed'
        ) {
          $rows[$rowNum]['class'] = "status-overdue";
          $entryFound = TRUE;
        }
      }

      $entryFound = $this->alterDisplayAddressFields($row, $rows, $rowNum, 'activity', 'List all activities for this ') ? TRUE : $entryFound;

      if (!$entryFound) {
        break;
      }
    }
  }
}

