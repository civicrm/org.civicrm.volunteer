<?php

use CRM_Volunteer_ExtensionUtil as E;

$hoursFormat = [
  \NumberFormatter::MIN_FRACTION_DIGITS => 2,
  \NumberFormatter::MAX_FRACTION_DIGITS => 2,
];

$records = [];

$records[] = [
  'name' => 'SavedSearch_Volunteer_Hours_Headline',
  'entity' => 'SavedSearch',
  'cleanup' => 'unused',
  'update' => 'unmodified',
  'params' => [
    'version' => 4,
    'values' => [
      'name' => 'Volunteer_Hours_Headline',
      'label' => E::ts('Volunteer Hours: Headline Totals'),
      'api_entity' => 'VolunteerHoursReport',
      'api_params' => [
        'version' => 4,
        'select' => [
          'COUNT(DISTINCT volunteer_contact_id) AS COUNT_volunteer_contact_id',
          'COUNT(activity_id) AS COUNT_activity_id',
          'SUM(scheduled_hours) AS SUM_scheduled_hours',
          'SUM(logged_hours_total) AS SUM_logged_hours_total',
          'SUM(awaiting_hours) AS SUM_awaiting_hours',
        ],
        'orderBy' => [],
        'where' => [],
        'groupBy' => [],
        'join' => [],
        'having' => [],
      ],
    ],
    'match' => ['name'],
  ],
];

$records[] = [
  'name' => 'SearchDisplay_Volunteer_Hours_Headline',
  'entity' => 'SearchDisplay',
  'cleanup' => 'unused',
  'update' => 'unmodified',
  'params' => [
    'version' => 4,
    'values' => [
      'name' => 'Volunteer_Hours_Headline',
      'label' => E::ts('Volunteer Hours Headline Totals'),
      'saved_search_id.name' => 'Volunteer_Hours_Headline',
      'type' => 'table',
      'settings' => [
        'description' => NULL,
        'sort' => [],
        'limit' => 1,
        // This aggregate search always returns exactly one headline row.
        'pager' => FALSE,
        'placeholder' => 1,
        'columns' => [
          [
            'type' => 'field',
            'key' => 'COUNT_volunteer_contact_id',
            'label' => E::ts('Volunteers'),
            'sortable' => FALSE,
            'alignment' => 'text-center',
          ],
          [
            'type' => 'field',
            'key' => 'COUNT_activity_id',
            'label' => E::ts('Assignments'),
            'sortable' => FALSE,
            'alignment' => 'text-center',
          ],
          [
            'type' => 'field',
            'key' => 'SUM_scheduled_hours',
            'label' => E::ts('Scheduled Hours'),
            'sortable' => FALSE,
            'alignment' => 'text-center',
            'format' => $hoursFormat,
          ],
          [
            'type' => 'field',
            'key' => 'SUM_logged_hours_total',
            'label' => E::ts('Logged Hours'),
            'sortable' => FALSE,
            'alignment' => 'text-center',
            'format' => $hoursFormat,
          ],
          [
            'type' => 'field',
            'key' => 'SUM_awaiting_hours',
            'label' => E::ts('Awaiting Hours'),
            'sortable' => FALSE,
            'alignment' => 'text-center',
          ],
        ],
        'actions' => FALSE,
        'classes' => [
          'table',
          'crm-vol-hours-kpi-table',
        ],
        'headerCount' => FALSE,
      ],
      'acl_bypass' => FALSE,
    ],
    'match' => ['saved_search_id', 'name'],
  ],
];

$records[] = [
  'name' => 'SavedSearch_Volunteer_Hours_Summary',
  'entity' => 'SavedSearch',
  'cleanup' => 'unused',
  'update' => 'unmodified',
  'params' => [
    'version' => 4,
    'values' => [
      'name' => 'Volunteer_Hours_Summary',
      'label' => E::ts('Volunteer Hours: Volunteer Summary'),
      'api_entity' => 'VolunteerHoursReport',
      'api_params' => [
        'version' => 4,
        'select' => [
          'volunteer_contact_id',
          'volunteer_display_name',
          'COUNT(activity_id) AS COUNT_activity_id',
          'SUM(is_attended) AS SUM_is_attended',
          'SUM(scheduled_hours) AS SUM_scheduled_hours',
          'SUM(logged_hours_total) AS SUM_logged_hours_total',
          'SUM(awaiting_hours) AS SUM_awaiting_hours',
        ],
        'orderBy' => [
          'volunteer_display_name' => 'ASC',
        ],
        'where' => [],
        'groupBy' => [
          'volunteer_contact_id',
          'volunteer_display_name',
        ],
        'join' => [],
        'having' => [],
      ],
    ],
    'match' => ['name'],
  ],
];

$records[] = [
  'name' => 'SearchDisplay_Volunteer_Hours_Summary',
  'entity' => 'SearchDisplay',
  'cleanup' => 'unused',
  'update' => 'unmodified',
  'params' => [
    'version' => 4,
    'values' => [
      'name' => 'Volunteer_Hours_Summary',
      'label' => E::ts('Volunteer Summary'),
      'saved_search_id.name' => 'Volunteer_Hours_Summary',
      'type' => 'table',
      'settings' => [
        'description' => E::ts('One row per volunteer for the selected filters.'),
        'sort' => [
          ['volunteer_display_name', 'ASC'],
        ],
        'limit' => 25,
        'pager' => ['hide_single' => TRUE],
        'placeholder' => 5,
        'columns' => [
          [
            'type' => 'html',
            'key' => 'volunteer_display_name',
            'label' => E::ts('Volunteer'),
            'sortable' => TRUE,
            'rewrite' => "{capture assign=contactId}{\"[volunteer_contact_id]\"}{/capture}\n{capture assign=contactUrl}{crmURL p='civicrm/contact/view' q=\"reset=1&cid=`\$contactId`\"}{/capture}\n<a href=\"{\$contactUrl}\">[volunteer_display_name]</a>",
          ],
          [
            'type' => 'field',
            'key' => 'COUNT_activity_id',
            'label' => E::ts('Assignments'),
            'sortable' => TRUE,
            'tally' => ['fn' => 'SUM'],
          ],
          [
            'type' => 'field',
            'key' => 'SUM_is_attended',
            'label' => E::ts('Attended'),
            'sortable' => TRUE,
            'tally' => ['fn' => 'SUM'],
          ],
          [
            'type' => 'field',
            'key' => 'SUM_scheduled_hours',
            'label' => E::ts('Scheduled Hours'),
            'sortable' => TRUE,
            'format' => $hoursFormat,
            'tally' => ['fn' => 'SUM'],
          ],
          [
            'type' => 'field',
            'key' => 'SUM_logged_hours_total',
            'label' => E::ts('Logged Hours'),
            'sortable' => TRUE,
            'format' => $hoursFormat,
            'tally' => ['fn' => 'SUM'],
          ],
          [
            'type' => 'field',
            'key' => 'SUM_awaiting_hours',
            'label' => E::ts('Awaiting'),
            'sortable' => TRUE,
            'tally' => ['fn' => 'SUM'],
          ],
        ],
        'actions' => ['download'],
        // This report has one non-destructive task. Render it directly instead
        // of putting it in a one-item dropdown, which is also prone to escaping
        // the tab panel's positioning context in some CiviCRM themes.
        'actions_display_mode' => 'buttons',
        'classes' => ['table', 'table-striped'],
        'headerCount' => TRUE,
        'toggleColumns' => TRUE,
        'tally' => ['label' => E::ts('Total')],
      ],
      'acl_bypass' => FALSE,
    ],
    'match' => ['saved_search_id', 'name'],
  ],
];

$records[] = [
  'name' => 'SavedSearch_Volunteer_Hours_Detail',
  'entity' => 'SavedSearch',
  'cleanup' => 'unused',
  'update' => 'unmodified',
  'params' => [
    'version' => 4,
    'values' => [
      'name' => 'Volunteer_Hours_Detail',
      'label' => E::ts('Volunteer Hours: Assignment Detail'),
      'api_entity' => 'VolunteerHoursReport',
      'api_params' => [
        'version' => 4,
        'select' => [
          'activity_id',
          'volunteer_contact_id',
          'volunteer_display_name',
          'project_id',
          'project_title',
          'volunteer_need_id',
          'shift_start',
          'shift_end',
          'role_id:label',
          'status_id:label',
          'scheduled_hours',
          'logged_hours',
          'hours_entry_state:label',
          'details',
        ],
        'orderBy' => [
          'shift_start' => 'DESC',
          'volunteer_display_name' => 'ASC',
        ],
        'where' => [],
        'groupBy' => [],
        'join' => [],
        'having' => [],
      ],
    ],
    'match' => ['name'],
  ],
];

$records[] = [
  'name' => 'SearchDisplay_Volunteer_Hours_Detail',
  'entity' => 'SearchDisplay',
  'cleanup' => 'unused',
  'update' => 'unmodified',
  'params' => [
    'version' => 4,
    'values' => [
      'name' => 'Volunteer_Hours_Detail',
      'label' => E::ts('Assignment Detail'),
      'saved_search_id.name' => 'Volunteer_Hours_Detail',
      'type' => 'table',
      'settings' => [
        'description' => E::ts('Every assigned shift, including cancelled and no-show assignments.'),
        'sort' => [
          ['shift_start', 'DESC'],
          ['volunteer_display_name', 'ASC'],
        ],
        'limit' => 50,
        'pager' => ['hide_single' => TRUE],
        'placeholder' => 5,
        'columns' => [
          [
            'type' => 'field',
            'key' => 'shift_start',
            'label' => E::ts('Shift Start'),
            'sortable' => TRUE,
          ],
          [
            'type' => 'html',
            'key' => 'volunteer_display_name',
            'label' => E::ts('Volunteer'),
            'sortable' => TRUE,
            'rewrite' => "{capture assign=contactId}{\"[volunteer_contact_id]\"}{/capture}\n{capture assign=contactUrl}{crmURL p='civicrm/contact/view' q=\"reset=1&cid=`\$contactId`\"}{/capture}\n<a href=\"{\$contactUrl}\">[volunteer_display_name]</a>",
          ],
          [
            'type' => 'html',
            'key' => 'project_title',
            'label' => E::ts('Project'),
            'sortable' => TRUE,
            'rewrite' => "{capture assign=projectBase}{crmURL p='civicrm/volunteer/manage'}{/capture}\n<a href=\"{\$projectBase}#/volunteer/manage/[project_id]/details\">[project_title]</a>",
          ],
          [
            'type' => 'field',
            'key' => 'role_id:label',
            'label' => E::ts('Role'),
            'sortable' => TRUE,
          ],
          [
            'type' => 'field',
            'key' => 'status_id:label',
            'label' => E::ts('Status'),
            'sortable' => TRUE,
          ],
          [
            'type' => 'field',
            'key' => 'scheduled_hours',
            'label' => E::ts('Scheduled Hours'),
            'sortable' => TRUE,
            'format' => $hoursFormat,
            'tally' => ['fn' => 'SUM'],
          ],
          [
            'type' => 'field',
            'key' => 'logged_hours',
            'label' => E::ts('Logged Hours'),
            'sortable' => TRUE,
            'format' => $hoursFormat,
            'tally' => ['fn' => 'SUM'],
          ],
          [
            'type' => 'field',
            'key' => 'hours_entry_state:label',
            'label' => E::ts('Hours Entry'),
            'sortable' => TRUE,
          ],
          [
            'type' => 'field',
            'key' => 'details',
            'label' => E::ts('Notes'),
            'sortable' => FALSE,
          ],
          [
            'type' => 'buttons',
            'label' => E::ts('View'),
            'alignment' => 'text-right',
            'size' => 'btn-xs',
            'links' => [
              [
                'path' => 'civicrm/activity/view?reset=1&id=[activity_id]&cid=[volunteer_contact_id]',
                'icon' => 'fa-eye',
                'text' => E::ts('View Assignment'),
                'style' => 'default',
                'target' => 'crm-popup',
                'conditions' => [[]],
              ],
            ],
          ],
        ],
        'actions' => ['download'],
        'actions_display_mode' => 'buttons',
        'classes' => ['table', 'table-striped'],
        'headerCount' => TRUE,
        'toggleColumns' => TRUE,
        'tally' => ['label' => E::ts('Total')],
      ],
      'acl_bypass' => FALSE,
    ],
    'match' => ['saved_search_id', 'name'],
  ],
];

return $records;
