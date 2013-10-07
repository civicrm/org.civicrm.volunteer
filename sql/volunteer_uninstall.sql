/* drop custom tables */
DROP TABLE `civicrm_volunteer_need`;
DROP TABLE `civicrm_volunteer_project`;

/* drop report-related records */
DELETE FROM `civicrm_option_value` WHERE name = 'CRM_Volunteer_Form_VolunteerReport';
DELETE FROM `civicrm_report_instance` WHERE report_id = 'volunteer';

/* drop custom option group for roles (FK takes care of option values) */
DELETE FROM `civicrm_option_group` WHERE name = 'volunteer_role';

/* drop custom field group from Activities (FK takes care of fields) */
DELETE FROM `civicrm_custom_group` WHERE `name` = 'CiviVolunteer';