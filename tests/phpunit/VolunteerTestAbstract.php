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

    $activityTypeId = $upgrader->createActivityType(CRM_Volunteer_BAO_Assignment::CUSTOM_ACTIVITY_TYPE);
    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign('volunteer_custom_activity_type_name', CRM_Volunteer_BAO_Assignment::CUSTOM_ACTIVITY_TYPE);
    $smarty->assign('volunteer_custom_group_name', CRM_Volunteer_BAO_Assignment::CUSTOM_GROUP_NAME);
    $smarty->assign('volunteer_custom_option_group_name', CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP);
    $smarty->assign('volunteer_activity_type_id', $activityTypeId);

    $customIDs = $upgrader->findCustomGroupValueIDs();
    $smarty->assign('customIDs', $customIDs);

    $upgrader->executeCustomDataTemplateFile('volunteer-customdata.xml.tpl');

    $upgrader->createVolunteerActivityStatus();

    $upgrader->createVolunteerContactType();
    $volContactTypeCustomGroupID = $upgrader->createVolunteerContactCustomGroup();
    $upgrader->createVolunteerContactCustomFields($volContactTypeCustomGroupID);

    $upgrader->installCommendationActivityType();

    $upgrader->installProjectRelationships();

    return TRUE;
  }
}