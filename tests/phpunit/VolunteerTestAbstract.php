<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Abstract class for Volunteer tests
 */
abstract class VolunteerTestAbstract extends CiviUnitTestCase {

  /**
   * Ensure that, if the database is repopulated, CiviVolunteer's install
   * operations are run, adding custom option group, activity fields, etc. to
   * the testing db.
   *
   * @param type $perClass
   * @param type $object
   * @return boolean
   */
  protected static function _populateDB($perClass = FALSE, &$object = NULL) {
    if (!parent::_populateDB($perClass, $object)) {
      return FALSE;
    }

    // code adapted from CRM_Volunteer_Upgrader::install().
    $upgrader = new CRM_Volunteer_Upgrader('org.civicrm.volunteer', dirname(__FILE__) . '/../../');

    $activityTypeId = $upgrader->createActivityType(CRM_Volunteer_Upgrader::customActivityTypeName);
    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign('volunteer_custom_activity_type_name', CRM_Volunteer_Upgrader::customActivityTypeName);
    $smarty->assign('volunteer_custom_group_name', CRM_Volunteer_Upgrader::customGroupName);
    $smarty->assign('volunteer_custom_option_group_name', CRM_Volunteer_Upgrader::customOptionGroupName);
    $smarty->assign('volunteer_activity_type_id', $activityTypeId);

    $customIDs = $upgrader->findCustomGroupValueIDs();
    $smarty->assign('customIDs', $customIDs);

    $upgrader->executeCustomDataTemplateFile('volunteer-customdata.xml.tpl');
    $upgrader->createVolunteerActivityStatus();
    return TRUE;
  }
}