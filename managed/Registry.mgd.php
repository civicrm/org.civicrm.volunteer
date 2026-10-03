<?php

use CRM_Volunteer_ExtensionUtil as E;

$records = array(
  array(
    'name' => 'CiviVolunteer Project Relationship Option Group',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => array(
      'version' => 4,
      'values' => array(
        'name' => 'volunteer_project_relationship',
        'title' => E::ts('Volunteer Project Relationship'),
        'description' => E::ts("Used to describe a contact's relationship to a volunteer project."),
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ),
      'match' => array('name'),
    ),
  ),
);

$relationships = array(
  'volunteer_owner' => array(E::ts('Owner'), E::ts('This contact owns the volunteer project.'), 1),
  'volunteer_manager' => array(E::ts('Manager'), E::ts('This contact manages volunteers and receives project notifications.'), 2),
  'volunteer_beneficiary' => array(E::ts('Beneficiary'), E::ts('This contact benefits from the volunteer project.'), 3),
);
foreach ($relationships as $name => $definition) {
  $records[] = array(
    'name' => 'CiviVolunteer Project Relationship ' . $name,
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => array(
      'version' => 4,
      'values' => array(
        'option_group_id.name' => 'volunteer_project_relationship',
        'name' => $name,
        'label' => $definition[0],
        'description' => $definition[1],
        // `value` is deliberately NOT declared. It is the key actually stored
        // in civicrm_volunteer_project_contact.relationship_type_id, and
        // `update => unmodified` writes declared fields over any record whose
        // entity_modified_date is NULL -- which is every record adopted from
        // the pre-2.5 imperative installer. Declaring it would let the first
        // reconcile after upgrade renumber these options and silently repoint
        // every existing project-contact row at the wrong relationship type,
        // breaking ownership checks. Values are resolved by name everywhere
        // (CRM_Core_PseudoConstant::getKey), so whatever the installer or a
        // fresh OptionValue.create assigns is fine. `weight` is omitted for the
        // same reason: it only affects display order and is the administrator's
        // to change.
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ),
      'match' => array('option_group_id', 'name'),
    ),
  );
}

$optionValues = array(
  array('activity_type', 'Volunteer', E::ts('Volunteer')),
  array('activity_type', 'volunteer_commendation', E::ts('Volunteer Commendation')),
  array('activity_status', 'Available', E::ts('Available')),
  array('activity_status', 'No_show', E::ts('No-show')),
);
foreach ($optionValues as $definition) {
  $records[] = array(
    'name' => 'CiviVolunteer Registry ' . $definition[0] . ' ' . $definition[1],
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => array(
      'version' => 4,
      'values' => array(
        'option_group_id.name' => $definition[0],
        'name' => $definition[1],
        'label' => $definition[2],
        'is_active' => TRUE,
      ),
      'match' => array('option_group_id', 'name'),
    ),
  );
}

return $records;
