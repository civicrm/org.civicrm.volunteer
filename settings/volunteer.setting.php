<?php

return array(
  'slider_widget_fields' => array(
    'group_name' => 'CiviVolunteer Configurations',
    'group' => 'org.civicrm.volunteer',
    'name' => 'slider_widget_fields',
    'type' => 'Array',
    'default' => NULL,
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Fields which are configured to use the slider widget',
    'help_text' => 'Which fields should use the slider widget?',
  ),

  'volunteer_project_default_profiles' => array(
    'group_name' => 'Default Project Settings',
    'group' => 'org.civicrm.volunteer',
    'name' => 'volunteer_project_default_profiles',
    'type' => 'Array',
    'default' => array(
      "primary" => array(civicrm_api3('UFGroup', 'getvalue', array(
        "name" => "volunteer_sign_up",
        "return" => "id"
      )))),
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Profiles for new Volunteer Projects',
    'help_text' => '',
  ),

  'volunteer_project_default_campaign' => array(
    'group_name' => 'Default Project Settings',
    'group' => 'org.civicrm.volunteer',
    'name' => 'volunteer_project_default_campaign',
    'type' => 'Int',
    'default' => '',
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Campaign for new Volunteer Projects',
    'help_text' => '',
  ),
  'volunteer_project_default_locblock' => array(
    'group_name' => 'Default Project Settings',
    'group' => 'org.civicrm.volunteer',
    'name' => 'volunteer_project_default_locblock',
    'type' => 'Int',
    'default' => '',
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Location for new Volunteer Projects',
    'help_text' => '',
  ),
  'volunteer_project_default_is_active' => array(
    'group_name' => 'Default Project Settings',
    'group' => 'org.civicrm.volunteer',
    'name' => 'volunteer_project_default_is_active',
    'type' => 'Int',
    'default' => 1,
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Active status for new Volunteer Projects',
    'help_text' => 'Should new Projects default to being active?',
  ),
  'volunteer_project_default_contacts' => array(
    'group_name' => 'Default Project Settings',
    'group' => 'org.civicrm.volunteer',
    'name' => 'volunteer_project_default_contacts',
    'type' => 'Array',
    'default' => array(
      "volunteer_owner" => array("mode" => "acting_contact", "value" => "1"),
      "volunteer_manager" => array("mode" => "acting_contact", "value" => "1"),
      "volunteer_beneficiary" => array("mode" => "relationship", "value" => "5_a"),
    ),
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => '',
    'help_text' => '',
  ),
  'volunteer_general_campaign_filter_type' => array(
    'group_name' => 'CiviVolunteer Global Settings',
    'group' => 'org.civicrm.volunteer',
    'name' => 'volunteer_general_campaign_filter_type',
    'type' => 'String',
    'default' => 'blacklist',
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Campaign Filter Whitelist/Blacklist',
    'help_text' => 'Whether Volunteer should use the campaign type list as a Blacklist or a Whitelist',
  ),
  'volunteer_general_campaign_filter_list' => array(
    'group_name' => 'CiviVolunteer Global Settings',
    'group' => 'org.civicrm.volunteer',
    'name' => 'volunteer_general_campaign_filter_list',
    'type' => 'Array',
    'default' => array(),
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Campaign Type(s)',
    'help_text' => 'Depending on the value of the Campaign Filter Whitelist/Blacklist setting, the campaign types in this list will either be shown or hidden from CiviVolunteer screens.',
  ),
  'volunteer_general_project_settings_help_text' => array(
    'group_name' => 'CiviVolunteer Global Settings',
    'group' => 'org.civicrm.volunteer',
    'name' => 'volunteer_general_project_settings_help_text',
    'type' => 'String',
    'default' => "<p>Use this screen to create a volunteer project.</p>
      <p>A project is a group of volunteer activities that will accomplish a
      specific goal. For example, to organize a fundraiser an organization may
      require volunteers for set up, greeting people, and clean up. In this
      case, the fundraiser project offers several distinct opportunities for
      volunteers to participate in. A project can represent a single event, a
      series of opportunities, or an ongoing opportunity at your organization.</p>
      <p>On this screen, you'll provide information about the project. Details
      about specific opportunities within the project can be provided on the
      following screen.</p>",
    'add' => '4.5',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Help Text for the Project Settings Screen',
  ),

);
