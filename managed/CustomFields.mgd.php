<?php

/**
 * This file registers CiviVolunteer entities as extensible via custom fields.
 * Lifecycle events in this extension will cause these registry records to be
 * automatically inserted, updated, or deleted from the database as appropriate.
 * For more details, see "hook_civicrm_managed" (at
 * https://docs.civicrm.org/dev/en/master/hooks/hook_civicrm_managed/) as well
 * as "API and the Art of Installation" (at
 * https://civicrm.org/blogs/totten/api-and-art-installation).
 */

return array(
  array(
    'module' => 'org.civicrm.volunteer',
    'name' => 'CiviVolunteer - Volunteer Project Extensibility Registration',
    'entity' => 'OptionValue',
    'params' => array(
      'version' => 3,
      'option_group_id' => 'cg_extend_objects',
      'label' => 'Volunteer Project',
      'value' => 'VolunteerProject',
      'name' => 'civicrm_volunteer_project',
    ),
  ),
);
