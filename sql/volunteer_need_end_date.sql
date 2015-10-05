ALTER TABLE `civicrm_volunteer_need`
ADD `end_time` datetime DEFAULT NULL
  COMMENT 'Used for specifying fuzzy dates, e.g., I have a need for 3 hours of volunteer work to be completed between 12/01/2015 and 12/31/2015.'
  AFTER  `start_time`;