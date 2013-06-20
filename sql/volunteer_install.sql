CREATE TABLE IF NOT EXISTS civicrm_volunteer_project (
  id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Project Id',
  entity_table varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Entity table for entity_id (initially civicrm_event)',
  entity_id int(10) unsigned NOT NULL COMMENT 'Implicit FK project entity (initially eventID).',
  is_active tinyint(4) DEFAULT '1' COMMENT 'Is the project active. Enabling volunteering for an event or other project sets this TRUE.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS civicrm_volunteer_need (
  id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Need Id',
  project_id int(10) unsigned NOT NULL COMMENT 'FK to civicrm_volunteer_project table which contains entity_table + entity for each volunteer project (initially civicrm_event + eventID).',
  start_time datetime DEFAULT NULL,
  duration int(11) DEFAULT NULL COMMENT 'Length in minutes of this volunteer time slot.',
  is_flexible tinyint(4) DEFAULT '0' COMMENT 'boolean indicating whether or not the time and role are flexible. Activities linked to a flexible need indicate that the volunteer is generally available.',
  quantity int(11) DEFAULT NULL COMMENT 'Number of volunteers required for this need.',
  visibility_id int(11) DEFAULT NULL COMMENT 'Implicit FK to option_value row in visibility option_group. Indicates whether this need is offered on public volunteer signup forms.',
  role_id int(11) DEFAULT NULL COMMENT 'Implicit FK to option_value row in volunteer_role option_group.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_id` (`id`),
  KEY `FK_civicrm_volunteer_need_project_id` (`project_id`),
  CONSTRAINT `FK_civicrm_volunteer_need_project_id` FOREIGN KEY (`project_id`) REFERENCES `civicrm_volunteer_project`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
  