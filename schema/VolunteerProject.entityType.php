<?php

use CRM_Volunteer_ExtensionUtil as E;

return [
  'name' => 'VolunteerProject',
  'table' => 'civicrm_volunteer_project',
  'class' => 'CRM_Volunteer_DAO_Project',
  'getInfo' => fn() => [
    'title' => E::ts('Volunteer Project'),
    'title_plural' => E::ts('Volunteer Projects'),
    'description' => E::ts('A project which offers volunteer opportunities.'),
    'label_field' => 'title',
    'search_fields' => ['title'],
    'log' => TRUE,
    'add' => '4.4',
  ],
  'getIndices' => fn() => [
    'index_volunteer_project_entity' => [
      'fields' => [
        'entity_table' => TRUE,
        'entity_id' => TRUE,
      ],
      'add' => '2.5',
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('Volunteer Project ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique volunteer project ID.'),
      'add' => '4.4',
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'title' => [
      'title' => E::ts('Title'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('The title of the volunteer project.'),
      'add' => '4.5',
    ],
    'description' => [
      'title' => E::ts('Description'),
      'sql_type' => 'text',
      'input_type' => 'RichTextEditor',
      'description' => E::ts('Full project description displayed on signup screens.'),
      'add' => '4.5',
      'input_attrs' => [
        'rows' => 8,
        'cols' => 60,
      ],
    ],
    // The pair is a dynamic foreign key. It had no metadata describing that,
    // unlike loc_block_id and campaign_id below, so API4 could not join a
    // project to its event, SearchKit could not traverse the relationship, and
    // entity_table offered no option list. Declaring it costs no schema change:
    // SqlGenerator only emits a real constraint for a *static*
    // entity_reference.entity, which a dynamic_entity does not have -- and must
    // not, since civicrm_event lives in an extension that can be switched off.
    'entity_table' => [
      'title' => E::ts('Entity Table'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Select',
      'description' => E::ts('Entity table associated with this project, such as civicrm_event.'),
      'add' => '4.4',
      'pseudoconstant' => [
        'callback' => ['CRM_Volunteer_BAO_Project', 'getEntityTables'],
      ],
    ],
    'entity_id' => [
      'title' => E::ts('Entity ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('ID of the entity associated with this project.'),
      'add' => '4.4',
      'entity_reference' => [
        'dynamic_entity' => 'entity_table',
        'key' => 'id',
      ],
    ],
    'is_active' => [
      'title' => E::ts('Enabled'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'description' => E::ts('Whether this project is active.'),
      'default' => TRUE,
      'add' => '4.4',
    ],
    'loc_block_id' => [
      'title' => E::ts('Location Block'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('Location block used by the project.'),
      'add' => '4.5',
      'entity_reference' => [
        'entity' => 'LocBlock',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'campaign_id' => [
      'title' => E::ts('Campaign'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('Campaign associated with the project.'),
      'add' => '4.5',
      'component' => 'CiviCampaign',
      'entity_reference' => [
        'entity' => 'Campaign',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
  ],
];
