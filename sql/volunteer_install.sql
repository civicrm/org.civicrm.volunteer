CREATE TABLE IF NOT EXISTS civicrm_volunteer_need (
  id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Need Id',
  start_time datetime DEFAULT NULL,
  duration decimal(20,2) DEFAULT NULL COMMENT 'Length in minutes of this volunteer time slot.',
  is_flexible tinyint(4) DEFAULT '0' COMMENT 'boolean indicating whether or not the time and role are flexible. Activities linked to a flexible need indicate that the volunteer is generally available.',
  quantity int(11) DEFAULT NULL COMMENT 'Number of volunteers required for this need.',
  visibility_id int(11) DEFAULT NULL COMMENT 'Implicit FK to option_value row in visibility option_group. Indicates whether this need is offered on public volunteer signup forms.',
  role_id int(11) DEFAULT NULL COMMENT 'Implicit FK to option_value row in volunteer_role option_group.',
  entity_id int(10) unsigned NOT NULL COMMENT 'Implicit FK to a CiviCRM entity which is the volunteer project (e.g. an event ID).',
  entity_table varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Entity table for entity_id',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
