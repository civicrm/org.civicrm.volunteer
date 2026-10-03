<?php

class CRM_Volunteer_Page_Router extends CRM_Core_Page {

  function run($args = NULL) {
    if (($args[0] ?? NULL) !== 'civicrm' || ($args[1] ?? NULL) !== 'volunteer') {
      throw new CRM_Core_Exception(ts('Invalid volunteer page callback configuration.', array('domain' => 'org.civicrm.volunteer')));
    }

    switch ($args[2] ?? NULL) {
      /**
       * This routes civicrm/volunteer/join to CiviVolunteer's reserved profile for volunteer interest.
       */
      case 'join':
        // The profile ID is controller state rather than caller input because
        // this route intentionally provides a stable, clean URL.
        $profileId = \Civi\Api4\UFGroup::get(FALSE)
          ->addSelect('id')
          ->addWhere('name', '=', 'volunteer_interest')
          ->execute()
          ->first()['id'] ?? NULL;
        if (!$profileId) {
          throw new CRM_Core_Exception(ts('The reserved volunteer interest profile is missing.', array('domain' => 'org.civicrm.volunteer')));
        }

        // if the user is logged in, serve edit mode profile; else serve create mode
        $contact_id = CRM_Core_Session::getLoggedInContactID();

        // set params for controller
        $class = 'CRM_Profile_Form_Edit';
        $title = NULL;
        $mode = isset($contact_id) ? CRM_Core_Action::UPDATE : CRM_Core_Action::ADD;
        $imageUpload = FALSE;
        $addSequence = FALSE;
        $ignoreKey = TRUE;
        $attachUpload = FALSE;

        $controller = new CRM_Core_Controller_Simple($class, $title, $mode, $imageUpload, $addSequence, $ignoreKey, $attachUpload);
        $controller->set('gid', $profileId);

        if (isset($contact_id)) {
          $controller->set('edit', 1);
        }

        $controller->process();
        return $controller->run();

      default:
        throw new CRM_Core_Exception(ts('Invalid volunteer page callback configuration.', array('domain' => 'org.civicrm.volunteer')));
    }
  }
}
