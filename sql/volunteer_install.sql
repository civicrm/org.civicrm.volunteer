CREATE TABLE IF NOT EXISTS civicrm_civivol_need (
  id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Need Id',
  start_time datetime DEFAULT NULL,
  duration decimal(20,2) DEFAULT NULL COMMENT 'if times are flexible then duration is probably more appropriate than an end_time field',
  flexible tinyint(4) DEFAULT '0' COMMENT 'boolean indicating whether or not the time is flexible',
  quantity int(11) DEFAULT NULL COMMENT 'the number of volunteers needed for this need in each time slot',
  role_id varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Initially, this will be just a label (e.g., Lifeguard), but in future releases roles will have additional properties, such as qualifications.',
  entity_id int(10) unsigned NOT NULL COMMENT 'Artificial FK to other CiviCRM entity.',
  entity_table varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Entity table',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
