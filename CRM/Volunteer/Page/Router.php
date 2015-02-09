<?php

class CRM_Volunteer_Page_Router extends CRM_Core_Page {

  function run($args = NULL) {
    if (CRM_Utils_Array::value(0, $args) !== 'civicrm' || CRM_Utils_Array::value(1, $args) !== 'volunteer') {
      CRM_Core_Error::fatal('Invalid page callback config.');
      return;
    }

    switch (CRM_Utils_Array::value(2, $args)) {
      /**
       * This routes civicrm/volunteer/join to CiviVolunteer's reserved profile for volunteer interest.
       */
      case 'join':
        // the profile expects the ID (and some other parameters) to be passed via URL; since we are providing
        // a nice clean URL, these parameters won't be there, so we fake it
        $_REQUEST['gid'] = civicrm_api3('UFGroup', 'getvalue', array(
          'sequential' => 1,
          'name' => "volunteer_interest",
          'return' => "id",
        ));
        $_REQUEST['force'] = '1';

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

        if (isset($contact_id)) {
          $controller->set('edit', 1);
        }

        $controller->process();
        return $controller->run();

      default:
        CRM_Core_Error::fatal('Invalid page callback config.');
        return;
    }
  }
}
