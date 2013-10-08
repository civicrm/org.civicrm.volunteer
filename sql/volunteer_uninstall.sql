/* get table name custom activity fields */
SELECT @customGroupId := max(id)
FROM civicrm_custom_group
WHERE name = 'CiviVolunteer';
SELECT @volunteerTable := CONCAT('civicrm_value_civivolunteer_', @customGroupId);

/* drop table for custom activity fields */
SET @drop_query = CONCAT('DROP TABLE ', @volunteerTable);
PREPARE dq FROM @drop_query;
EXECUTE dq;
DEALLOCATE PREPARE dq;

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

/* drop volunteer sign-up profile (FK takes care of profile fields) */
DELETE FROM `civicrm_uf_join` WHERE `module` = 'CiviVolunteer';
DELETE FROM `civicrm_uf_group` WHERE `name` = 'volunteer_sign_up';