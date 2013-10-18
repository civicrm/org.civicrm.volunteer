CREATE TABLE IF NOT EXISTS civicrm_volunteer_project (
  id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Project Id',
  entity_table varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Entity table for entity_id (initially civicrm_event)',
  entity_id int(10) unsigned NOT NULL COMMENT 'Implicit FK project entity (initially eventID).',
  target_contact_id int(10) unsigned NULL COMMENT 'FK to civicrm_contact. The target (or beneficiary) of the volunteer activity',
  is_active tinyint(4) NOT NULL DEFAULT '1' COMMENT 'Is the project active. Enabling volunteering for an event or other project sets this TRUE.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_entity` (`entity_table`,`entity_id`),
  CONSTRAINT FK_civicrm_volunteer_project_target_contact_id FOREIGN KEY (`target_contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS civicrm_volunteer_need (
  id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Need Id',
  project_id int(10) unsigned COMMENT 'FK to civicrm_volunteer_project table which contains entity_table + entity for each volunteer project (initially civicrm_event + eventID).',
  start_time datetime DEFAULT NULL,
  duration int(11) DEFAULT NULL COMMENT 'Length in minutes of this volunteer time slot.',
  is_flexible tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Boolean indicating whether or not the time and role are flexible. Activities linked to a flexible need indicate that the volunteer is generally available.',
  quantity int(11) DEFAULT NULL COMMENT 'Number of volunteers required for this need.',
  visibility_id int(11) unsigned DEFAULT NULL COMMENT 'Implicit FK to option_value row in visibility option_group. Indicates whether this need is offered on public volunteer signup forms.',
  role_id int(11) DEFAULT NULL COMMENT 'Implicit FK to option_value row in volunteer_role option_group.',
  is_active tinyint(4) NOT NULL DEFAULT '1' COMMENT 'Is this need enabled?',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_id` (`id`),
  KEY `FK_civicrm_volunteer_need_project_id` (`project_id`),
  CONSTRAINT `FK_civicrm_volunteer_need_project_id` FOREIGN KEY (`project_id`) REFERENCES `civicrm_volunteer_project`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

SELECT @opGId := id FROM civicrm_option_group WHERE name = 'report_template';
SELECT @ovWeight := MAX(weight)+1 FROM civicrm_option_value WHERE option_group_id = @opGId;

INSERT IGNORE INTO `civicrm_option_value` (`option_group_id`, `label`, `value`, `name`, `grouping`, `filter`, `is_default`,`weight`,`description`, `is_optgroup`, `is_reserved`, `is_active`, `component_id`, `domain_id`, `visibility_id`) VALUES
(@opGId, 'Volunteer Report', 'volunteer', 'CRM_Volunteer_Form_VolunteerReport', NULL, 0, 0, @ovWeight, 'Volunteer Report', 0, 0, 1, NULL, NULL, NULL);

INSERT INTO `civicrm_report_instance` (`domain_id`, `title`, `report_id`, `name`, `args`, `description`, `permission`, `grouprole`, `form_values`, `is_active`, `email_subject`, `email_to`, `email_cc`, `header`, `footer`, `navigation_id`, `drilldown_id`, `is_reserved`) VALUES
(1, 'Volunteer Report', 'volunteer', NULL, NULL, 'Volunteer Report', 'access CiviReport', NULL, 'a:39:{s:6:"fields";a:10:{s:16:"contact_assignee";s:1:"1";s:14:"contact_target";s:1:"0";s:7:"project";s:1:"1";s:16:"activity_type_id";s:1:"1";s:16:"activity_subject";s:1:"1";s:18:"activity_date_time";s:1:"1";s:9:"status_id";s:1:"1";s:4:"role";s:1:"1";s:14:"time_scheduled";s:1:"1";s:14:"time_completed";s:1:"1";}s:17:"contact_source_op";s:3:"has";s:20:"contact_source_value";s:0:"";s:19:"contact_assignee_op";s:3:"has";s:22:"contact_assignee_value";s:0:"";s:17:"contact_target_op";s:3:"has";s:20:"contact_target_value";s:0:"";s:15:"current_user_op";s:2:"eq";s:18:"current_user_value";s:1:"0";s:27:"activity_date_time_relative";s:10:"this.month";s:23:"activity_date_time_from";s:0:"";s:21:"activity_date_time_to";s:0:"";s:19:"activity_subject_op";s:3:"has";s:22:"activity_subject_value";s:0:"";s:5:"id_op";s:2:"in";s:8:"id_value";a:0:{}s:12:"status_id_op";s:2:"in";s:15:"status_id_value";a:0:{}s:8:"tagid_op";s:2:"in";s:11:"tagid_value";a:0:{}s:6:"gid_op";s:2:"in";s:9:"gid_value";a:0:{}s:12:"custom_9_min";s:0:"";s:12:"custom_9_max";s:0:"";s:11:"custom_9_op";s:3:"lte";s:14:"custom_9_value";s:0:"";s:13:"custom_10_min";s:0:"";s:13:"custom_10_max";s:0:"";s:12:"custom_10_op";s:3:"lte";s:15:"custom_10_value";s:0:"";s:9:"order_bys";a:2:{i:1;a:2:{s:6:"column";s:5:"title";s:5:"order";s:3:"ASC";}i:2;a:2:{s:6:"column";s:9:"sort_name";s:5:"order";s:3:"ASC";}}s:11:"description";s:16:"Volunteer Report";s:13:"email_subject";s:0:"";s:8:"email_to";s:0:"";s:8:"email_cc";s:0:"";s:10:"permission";s:17:"access CiviReport";s:9:"parent_id";s:0:"";s:6:"groups";s:0:"";s:11:"instance_id";s:2:"36";}', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0);
