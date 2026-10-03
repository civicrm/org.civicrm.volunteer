# Hooks

CiviVolunteer provides the following hooks.

## hook_civicrm_volunteer_projectDefaultSettings

This hook is invoked when retrieving the default values for a volunteer project — for example when the project editor opens a brand-new project, or when a project is created from an event's Volunteers tab.

Definition:

```php
hook_civicrm_volunteer_projectDefaultSettings(array &$defaults)
```

Parameters:

* array `$defaults` - Reference to the array of default values, keyed by `CRM_Volunteer_DAO_Project` field name (e.g. `campaign_id`, `loc_block_id`, `is_active`).

Returns:

* `NULL` - The return value is ignored.

Example — default new projects to inactive and force a campaign:

```php
function myextension_civicrm_volunteer_projectDefaultSettings(array &$defaults) {
  $defaults['is_active'] = 0;
  if (empty($defaults['campaign_id'])) {
    $defaults['campaign_id'] = \Civi::settings()->get('myorg_default_campaign');
  }
}
```
