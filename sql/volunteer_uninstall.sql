/* drop custom tables */
DROP TABLE IF EXISTS `civicrm_volunteer_need`;
DROP TABLE IF EXISTS `civicrm_volunteer_project_contact`;
DROP TABLE IF EXISTS `civicrm_volunteer_project`;

/* drop report-related records */
DELETE FROM `civicrm_report_instance` WHERE report_id = 'volunteer';

/* drop custom option group for roles (FK takes care of option values) */
DELETE FROM `civicrm_option_group` WHERE name = 'volunteer_role';

/* drop custom field group from Activities (FK takes care of fields) */
DELETE FROM `civicrm_custom_group` WHERE `name` = 'CiviVolunteer';

/* drop volunteer sign-up profile (FK takes care of profile fields) */
DELETE FROM `civicrm_uf_join` WHERE `module` = 'CiviVolunteer';
DELETE FROM `civicrm_uf_group` WHERE `name` = 'volunteer_sign_up';