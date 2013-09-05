DELETE FROM `civicrm_option_value` WHERE name = 'CRM_Volunteer_Form_VolunteerReport';

DELETE FROM `civicrm_report_instance` WHERE report_id = 'volunteer';
