<?php

/**
 * This file declares managed database records of type "ReportTemplate" and
 * "ReportInstance." The records will be automatically inserted, updated, or
 * deleted from the database as appropriate. For more details, see
 * "hook_civicrm_managed" (at http://wiki.civicrm.org/confluence/display/CRMDOC/Hook+Reference)
 * as well as "API and the Art of Installation" (at
 * https://civicrm.org/blogs/totten/api-and-art-installation).
 */
$labelVolunteerReport = ts('Volunteer Report', array('domain' => 'org.civicrm.volunteer'));

return array(
  array(
    'module' => 'org.civicrm.volunteer',
    'name' => 'CiviVolunteer - Volunteer Report Template',
    'entity' => 'ReportTemplate',
    'params' => array(
      'version' => 3,
      'label' => $labelVolunteerReport,
      'class_name' => 'CRM_Volunteer_Form_VolunteerReport',
      'description' => $labelVolunteerReport,
      'report_url' => 'volunteer',
    ),
  ),
  array(
    'module' => 'org.civicrm.volunteer',
    'name' => 'CiviVolunteer - Volunteer Report',
    'entity' => 'ReportInstance',
    'params' => array(
      'version' => 3,
      'title' => $labelVolunteerReport,
      'description' => $labelVolunteerReport,
      'report_id' => 'volunteer',
      'permissions' => 'access CiviReport',
      'form_values' => serialize(array(
        'fields' => array(
          'contact_assignee' => '1',
          'contact_target' => '0',
          'project' => '1',
          'activity_type_id' => '1',
          'activity_subject' => '1',
          'activity_date_time' => '1',
          'status_id' => '1',
          'role' => '1',
          'time_scheduled' => '1',
          'time_completed' => '1',
        ),
        'activity_date_time_relative' => 'this.month',
        'order_bys' => array(
          1 => array(
            'column' => 'sort_name',
            'order' => 'ASC',
          ),
        ),
      )),
    ),
  ),
);
