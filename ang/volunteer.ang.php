<?php
return [
  'basePages' => ['civicrm/vol'],
  'requires' => [
    'crmApp',
    'crmProfileUtils',
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
  'css' => ['ang/volunteer.css'],
  'partials' => ['ang/volunteer'],
  'settingsFactory' => ['CRM_Volunteer_Page_Angular', 'loadSettings'],
  'permissions' => array_keys(CRM_Volunteer_Permission::getVolunteerPermissions()),
];
