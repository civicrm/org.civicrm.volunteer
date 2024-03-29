-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from schema.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--
-- /*******************************************************
-- *
-- * Clean up the existing tables - this section generated from drop.tpl
-- *
-- *******************************************************/

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `civicrm_volunteer_need`;
DROP TABLE IF EXISTS `civicrm_volunteer_project_contact`;
DROP TABLE IF EXISTS `civicrm_volunteer_project`;

SET FOREIGN_KEY_CHECKS=1;
-- /*******************************************************
-- *
-- * Create new tables
-- *
-- *******************************************************/

-- /*******************************************************
-- *
-- * civicrm_volunteer_project
-- *
-- *******************************************************/
CREATE TABLE `civicrm_volunteer_project` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Project Id',
  `title` varchar(255) NOT NULL COMMENT 'The title of the Volunteer Project',
  `description` text NULL COMMENT 'Full description of the Volunteer Project. Text and HTML allowed. Displayed on sign-up screens.',
  `entity_table` varchar(64) NOT NULL COMMENT 'Entity table for entity_id (initially civicrm_event)',
  `entity_id` int unsigned NOT NULL COMMENT 'Implicit FK project entity (initially eventID).',
  `is_active` tinyint NOT NULL DEFAULT 1 COMMENT 'Is this need enabled?',
  `loc_block_id` int unsigned COMMENT 'FK to Location Block ID',
  `campaign_id` int unsigned NULL COMMENT 'The campaign associated with this Volunteer Project.',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civicrm_volunteer_project_loc_block_id FOREIGN KEY (`loc_block_id`) REFERENCES `civicrm_loc_block`(`id`) ON DELETE SET NULL,
  CONSTRAINT FK_civicrm_volunteer_project_campaign_id FOREIGN KEY (`campaign_id`) REFERENCES `civicrm_campaign`(`id`) ON DELETE SET NULL
)
ENGINE=InnoDB;

-- /*******************************************************
-- *
-- * civicrm_volunteer_project_contact
-- *
-- *******************************************************/
CREATE TABLE `civicrm_volunteer_project_contact` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int unsigned NOT NULL COMMENT 'Foreign key to the Volunteer Project for this record',
  `contact_id` int unsigned NOT NULL COMMENT 'Foreign key to the Contact for this record',
  `relationship_type_id` int unsigned NOT NULL COMMENT 'Nature of the contact\'s relationship to the Volunteer Project (e.g., Beneficiary). See option group volunteer_project_relationship.',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civicrm_volunteer_project_contact_project_id FOREIGN KEY (`project_id`) REFERENCES `civicrm_volunteer_project`(`id`) ON DELETE CASCADE,
  CONSTRAINT FK_civicrm_volunteer_project_contact_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE CASCADE
)
ENGINE=InnoDB;

-- /*******************************************************
-- *
-- * civicrm_volunteer_need
-- *
-- *******************************************************/
CREATE TABLE `civicrm_volunteer_need` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Need Id',
  `project_id` int unsigned NULL COMMENT 'FK to civicrm_volunteer_project table which contains entity_table + entity for each volunteer project (initially civicrm_event + eventID).',
  `start_time` datetime,
  `end_time` datetime COMMENT 'Used for specifying fuzzy dates, e.g., I have a need for 3 hours of volunteer work to be completed between 12/01/2015 and 12/31/2015.',
  `duration` int COMMENT 'Length in minutes of this volunteer time slot.',
  `is_flexible` tinyint NOT NULL DEFAULT 0 COMMENT 'Boolean indicating whether or not the time and role are flexible. Activities linked to a flexible need indicate that the volunteer is generally available.',
  `quantity` int DEFAULT NULL COMMENT 'The number of volunteers needed for this need.',
  `visibility_id` int unsigned DEFAULT NULL COMMENT ' Indicates whether this need is offered on public volunteer signup forms. Implicit FK to option_value row in visibility option_group.',
  `role_id` int unsigned DEFAULT NULL COMMENT 'The role associated with this need. Implicit FK to option_value row in volunteer_role option_group.',
  `is_active` tinyint NOT NULL DEFAULT 1 COMMENT 'Is this need enabled?',
  `created` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civicrm_volunteer_need_project_id FOREIGN KEY (`project_id`) REFERENCES `civicrm_volunteer_project`(`id`) ON DELETE SET NULL
)
ENGINE=InnoDB;
