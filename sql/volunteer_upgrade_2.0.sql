CREATE TABLE IF NOT EXISTS `civicrm_volunteer_project_contact` (
  `id` INT(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` INT(10) unsigned NOT NULL COMMENT 'Foreign key to the Volunteer Project for this record',
  `contact_id` INT(10) unsigned NOT NULL COMMENT 'Foreign key to the Contact for this record',
  `relationship_type_id` INT(10) unsigned NOT NULL COMMENT 'Nature of the contact''s relationship to the Volunteer Project (e.g., Beneficiary). See option group volunteer_project_relationship.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_project_contact_rel` (`project_id`, `contact_id`, `relationship_type_id`),
  CONSTRAINT `FK_civicrm_volunteer_project_contact_project_id`
    FOREIGN KEY (`project_id`)
    REFERENCES `civicrm_volunteer_project`(`id`)
    ON DELETE CASCADE,
  CONSTRAINT `FK_civicrm_volunteer_project_contact_contact_id`
    FOREIGN KEY (`contact_id`)
    REFERENCES `civicrm_contact`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

/*
 * Migrate beneficiary (target_contact_id) from civicrm_volunteer_project to
 * civicrm_volunteer_project_contact.
 */
SELECT @beneficiary_reltype_id := `v`.`value`
FROM `civicrm_option_value` `v`
INNER JOIN `civicrm_option_group` `g`
ON `v`.`option_group_id` = `g`.`id`
WHERE `v`.`name` = 'volunteer_beneficiary'
AND `g`.`name` = 'volunteer_project_relationship';

INSERT INTO `civicrm_volunteer_project_contact` (`project_id`, `contact_id`, `relationship_type_id`)
SELECT `id`, `target_contact_id`, @beneficiary_reltype_id
FROM `civicrm_volunteer_project`
WHERE `target_contact_id` IS NOT NULL;


ALTER TABLE `civicrm_volunteer_project`
ADD `title` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL
  COMMENT 'The title of the Volunteer Project'
  AFTER  `id`,
ADD `description` TEXT COLLATE utf8_unicode_ci
  COMMENT 'Full description of the Volunteer Project. Text and HTML allowed. Displayed on sign-up screens.'
  AFTER `title`,
ADD `loc_block_id` INT(10) unsigned DEFAULT NULL
  COMMENT 'FK to Location Block ID'
  AFTER `is_active`,
ADD CONSTRAINT `FK_civicrm_volunteer_project_loc_block_id`
  FOREIGN KEY (`loc_block_id`)
  REFERENCES `civicrm_loc_block`(`id`)
  ON DELETE SET NULL,
ADD `campaign_id` INT(10) unsigned DEFAULT NULL
  COMMENT 'The campaign associated with this Volunteer Project.'
  AFTER `loc_block_id`,
ADD CONSTRAINT `FK_civicrm_volunteer_project_campaign_id`
  FOREIGN KEY (`campaign_id`)
  REFERENCES `civicrm_campaign`(`id`)
  ON DELETE SET NULL,
DROP FOREIGN KEY `FK_civicrm_volunteer_project_target_contact_id`,
DROP COLUMN `target_contact_id`,
DROP INDEX `unique_entity`,
MODIFY `entity_table` varchar(64) NULL,
MODIFY `entity_id` int(10) NULL;