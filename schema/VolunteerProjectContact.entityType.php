<?php

use CRM_Volunteer_ExtensionUtil as E;

return [
  'name' => 'VolunteerProjectContact',
  'table' => 'civicrm_volunteer_project_contact',
  'class' => 'CRM_Volunteer_DAO_ProjectContact',
  'getInfo' => fn() => [
    'title' => E::ts('Volunteer Project Contact'),
    'title_plural' => E::ts('Volunteer Project Contacts'),
    'description' => E::ts('A contact related to a volunteer project as owner, manager, or beneficiary.'),
    'log' => TRUE,
    'add' => '4.5',
  ],
  'getIndices' => fn() => [
    'UI_project_contact_rel' => [
      'fields' => [
        'project_id' => TRUE,
        'contact_id' => TRUE,
        'relationship_type_id' => TRUE,
      ],
      'unique' => TRUE,
      'add' => '2.0',
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('Volunteer Project Contact ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique volunteer project contact row ID.'),
      'add' => '4.5',
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'project_id' => [
      'title' => E::ts('Volunteer Project'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('Volunteer project for this relationship.'),
      'add' => '4.5',
      'entity_reference' => [
        'entity' => 'VolunteerProject',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'contact_id' => [
      'title' => E::ts('Contact'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('Contact related to the volunteer project.'),
      'add' => '4.5',
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'relationship_type_id' => [
      'title' => E::ts('Relationship Type'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Select',
      'required' => TRUE,
      'description' => E::ts('Nature of the contact relationship to the project.'),
      'add' => '4.5',
      'pseudoconstant' => [
        'option_group_name' => 'volunteer_project_relationship',
      ],
    ],
  ],
];
