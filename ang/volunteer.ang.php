<?php
return [
  'basePages' => [
    'civicrm/vol',
    'civicrm/volunteer/manage',
    'civicrm/volunteer/roster',
    'civicrm/volunteer/loghours',
  ],
  'requires' => [
    'api4',
    'crmApp',
    'crmDialog',
    'crmUi',
    'crmUtil',
    'ngRoute',
    'ngSanitize',
  ],
  'js' => [
    'ang/volunteer.js',
    'ang/volunteer/*.js',
    'ang/volunteer/*/*.js'
  ],
  'css' => ['css/volunteer-tokens.css', 'ang/volunteer.css', 'css/public_workflow.css'],
  'partials' => ['ang/volunteer'],
  'settingsFactory' => ['CRM_Volunteer_Page_Angular', 'loadSettings'],
  // The project Hours report requires this core permission in addition to the
  // extension's own `edit all volunteer projects` permission. Angular only
  // exposes permissions declared here to CRM.checkPerm(), so omitting it made
  // the server-authorized report tab appear unauthorized in the browser.
  'permissions' => array_merge(
    array_keys(CRM_Volunteer_Permission::getVolunteerPermissions()),
    ['view all contacts']
  ),
];
