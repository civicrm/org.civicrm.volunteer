<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Abstract class for Volunteer tests
 */
abstract class VolunteerTestAbstract extends CiviUnitTestCase {

  /**
   * Ensure that, if the database is repopulated, CiviVolunteer's install
   * operations are run, adding custom option group, activity fields, etc. to
   * the testing DB. NOTE: Installation/alteration of tables not managed by
   * core (e.g., civicrm_volunteer_project) should not be reproduced here.
   *
   * @param type $perClass
   * @param type $object
   * @return boolean
   */
  protected static function _populateDB($perClass = FALSE, &$object = NULL) {
    if (!parent::_populateDB($perClass, $object)) {
      return FALSE;
    }

    // Code adapted from CRM_Volunteer_Upgrader::install().
    $upgrader = new CRM_Volunteer_Upgrader('org.civicrm.volunteer', dirname(__FILE__) . '/../../');

    $upgrader->install();

    return TRUE;
  }
}
