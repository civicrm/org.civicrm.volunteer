<?php
/**
 * Domain layer for the VolunteerUtil API4 actions.
 *
 * The former api/v3/VolunteerUtil.php contained the business logic inline.
 * It lives here so the API4 entity owns the implementation and the API3
 * wrappers are thin compatibility adapters. Except where noted, permission
 * enforcement lives in the API4 entity/action, not in these methods, so
 * trusted server-side callers may use them directly.
 */
class CRM_Volunteer_BAO_VolunteerUtil {

  /**
   * The permissions defined by the volunteer extension.
   *
   * @return array
   */
  public static function getPermissions(): array {
    $results = array();

    foreach (CRM_Volunteer_Permission::getVolunteerPermissions() as $k => $v) {
      $results[] = array(
        'description' => $v['description'],
        'label' => $v['label'],
        'name' => $k,
        'safe_name' => strtolower(str_replace(array(' ', '-'), '_', $k)),
      );
    }

    return $results;
  }

  /**
   * The limited profile metadata required by the project editor.
   *
   * Exposes only profile titles and field summaries, allowing project editors
   * to select profiles without granting broad API access to UFGroup/UFField.
   *
   * @param array $selectedIds
   *   Profile IDs which must be returned even when inactive or missing.
   * @return array
   *   Keys: profiles, can_manage, create_url.
   */
  public static function getProfiles(array $selectedIds = array()): array {
    $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds))));
    // The create/edit/preview links target civicrm/admin/uf/group/*, which
    // inherit civicrm/admin's access_arguments -- and a comma-separated list
    // there means AND (CRM_Core_Menu::fillMenuValues). 'profile listings and
    // forms' is the front-end profile-use permission and grants none of it, so
    // gating the links on it offered users links to a page they would be
    // denied.
    $canManage = CRM_Core_Permission::check('administer CiviCRM system')
      && CRM_Core_Permission::check('administer CiviCRM data')
      && CRM_Core_Permission::check('access CiviCRM');

    $groups = \Civi\Api4\UFGroup::get(FALSE)
      ->addSelect('id', 'title', 'is_active', 'group_type')
      ->addOrderBy('title', 'ASC')
      ->execute();

    $profiles = array();
    $profileIds = array();
    foreach ($groups as $group) {
      $id = (int) $group['id'];
      if (empty($group['is_active']) && !in_array($id, $selectedIds, TRUE)) {
        continue;
      }
      $profileIds[] = $id;
      $profiles[$id] = array(
        'id' => $id,
        'title' => $group['title'],
        'display_title' => empty($group['is_active'])
          ? ts('%1 (Inactive)', array(1 => $group['title'], 'domain' => 'org.civicrm.volunteer'))
          : $group['title'],
        'is_active' => !empty($group['is_active']),
        'is_missing' => FALSE,
        'group_type' => $group['group_type'] ?? NULL,
        'fields' => array(),
        'edit_url' => $canManage
          ? CRM_Utils_System::url('civicrm/admin/uf/group/field', "reset=1&action=browse&gid={$id}", FALSE, NULL, FALSE)
          : NULL,
        'preview_url' => $canManage
          ? CRM_Utils_System::url('civicrm/admin/uf/group/preview', "reset=1&gid={$id}", FALSE, NULL, FALSE)
          : NULL,
      );
    }

    // Fields are returned for every listed profile, not only the selected
    // ones. Narrowing to the selection would be tighter, but the editor
    // renders the field summary for whichever profile the user picks in the
    // dropdown and only refetches on window focus, so scoping here would
    // blank the summary until the next refresh. What is exposed is
    // configuration metadata -- profile titles and field labels -- behind
    // 'edit volunteer registration profiles', the permission whose whole
    // purpose is choosing among these profiles.
    if ($profileIds) {
      $fields = \Civi\Api4\UFField::get(FALSE)
        ->addSelect('id', 'uf_group_id', 'field_name', 'label', 'is_required', 'weight')
        ->addWhere('uf_group_id', 'IN', $profileIds)
        ->addWhere('is_active', '=', TRUE)
        ->addOrderBy('weight', 'ASC')
        ->execute();
      foreach ($fields as $field) {
        $profileId = (int) $field['uf_group_id'];
        if (isset($profiles[$profileId])) {
          $profiles[$profileId]['fields'][] = array(
            'id' => (int) $field['id'],
            'field_name' => $field['field_name'],
            'label' => $field['label'],
            'is_required' => !empty($field['is_required']),
            'weight' => (int) $field['weight'],
          );
        }
      }
    }

    foreach (array_diff($selectedIds, $profileIds) as $missingId) {
      $profiles[$missingId] = array(
        'id' => $missingId,
        'title' => ts('Missing profile #%1', array(1 => $missingId, 'domain' => 'org.civicrm.volunteer')),
        'display_title' => ts('Missing profile #%1', array(1 => $missingId, 'domain' => 'org.civicrm.volunteer')),
        'is_active' => FALSE,
        'is_missing' => TRUE,
        'group_type' => NULL,
        'fields' => array(),
        'edit_url' => NULL,
        'preview_url' => NULL,
      );
    }

    uasort($profiles, function($a, $b) {
      return strcasecmp($a['display_title'], $b['display_title']);
    });

    return array(
      'profiles' => array_values($profiles),
      'can_manage' => $canManage,
      'create_url' => $canManage
        ? CRM_Utils_System::url('civicrm/admin/uf/group/add', 'reset=1&action=add', FALSE, NULL, FALSE)
        : NULL,
    );
  }

  /**
   * Supporting data for the named JavaScript interface.
   *
   * @param string $controller
   * @param bool $checkPermissions
   *   Permission enforcement is controller-dependent and runs here rather
   *   than in the entity's static permission metadata.
   * @return array
   * @throws \CRM_Core_Exception
   */
  public static function getSupportingData(string $controller, bool $checkPermissions = TRUE): array {
    $results = array();

    if ($controller === 'VolunteerProject') {
      if ($checkPermissions && !CRM_Volunteer_Permission::checkProjectManagement()) {
        throw new CRM_Core_Exception(ts('You do not have permission to manage volunteer projects.', array('domain' => 'org.civicrm.volunteer')), 403);
      }
      $relTypes = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('*')
        ->addWhere('option_group_id.name', '=', CRM_Volunteer_BAO_ProjectContact::RELATIONSHIP_OPTION_GROUP)
        ->execute();
      $results['relationship_types'] = array();
      foreach ($relTypes as $relType) {
        // API3 keyed rows by id and quoted scalars; preserve that shape for
        // the consuming Angular templates.
        $results['relationship_types'][(string) $relType['id']] = array_map(
          static function($value) {
            return is_bool($value) ? (string) (int) $value : (string) $value;
          },
          $relType
        );
      }

      $results['phone_types'] = CRM_Core_OptionGroup::values("phone_type", FALSE, FALSE, TRUE);
      $results['volunteer_general_project_settings_help_text'] = CRM_Volunteer_Api4::getSetting('volunteer_general_project_settings_help_text');

      //Fetch the Defaults from saved settings.
      $defaults = CRM_Volunteer_BAO_Project::composeDefaultSettingsArray();

      //Allow other extensions to modify the defaults
      CRM_Volunteer_Hook::projectDefaultSettings($defaults);

      $results['defaults'] = $defaults;
    }

    if ($controller === 'VolOppsCtrl') {
      if ($checkPermissions) {
        CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::VIEW);
      }
      $results['roles'] = CRM_Core_OptionGroup::values('volunteer_role', FALSE, FALSE, TRUE);
      $results['proximity_available'] = self::isProximitySearchAvailable();
    }

    if ($controller === 'VolunteerWorkflow') {
      if ($checkPermissions && !CRM_Volunteer_Permission::checkProjectManagement()) {
        throw new CRM_Core_Exception(ts('You do not have permission to manage volunteer projects.', array('domain' => 'org.civicrm.volunteer')), 403);
      }

      $results['roles'] = array();
      $roles = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('id', 'value', 'name', 'label', 'description', 'is_active', 'weight')
        ->addWhere('option_group_id.name', '=', CRM_Volunteer_BAO_Assignment::ROLE_OPTION_GROUP)
        ->addWhere('is_active', '=', TRUE)
        ->addOrderBy('weight', 'ASC')
        ->execute();
      foreach ($roles as $role) {
        $results['roles'][] = array(
          'id' => (int) $role['value'],
          'name' => $role['name'],
          'label' => $role['label'],
          'description' => CRM_Utils_String::purifyHTML($role['description'] ?? ''),
        );
      }

      $visibilityOptions = \Civi::entity('VolunteerNeed')->getOptions('visibility_id', array(), TRUE) ?? array();
      $results['visibility'] = array_column($visibilityOptions, 'id', 'name');
      $results['statuses'] = array();
      $statusOptions = \Civi::entity('Activity')->getOptions('status_id', array(), TRUE) ?? array();
      foreach ($statusOptions as $status) {
        $results['statuses'][] = array(
          'id' => (int) $status['id'],
          'name' => $status['name'],
          'label' => $status['name'] === 'Completed'
            ? ts('Attended', array('domain' => 'org.civicrm.volunteer'))
            : $status['label'],
        );
      }
      // The roster offers per-volunteer Email and SMS actions. Both need the
      // activity type id for their core form, and SMS is only usable when a
      // provider is configured and the user may send.
      $activityTypes = array_column(
        \Civi::entity('Activity')->getOptions('activity_type_id', array(), TRUE) ?? array(),
        'id',
        'name'
      );
      $results['email_activity_type_id'] = isset($activityTypes['Email']) ? (int) $activityTypes['Email'] : NULL;
      $results['sms'] = array(
        'activity_type_id' => isset($activityTypes['SMS']) ? (int) $activityTypes['SMS'] : NULL,
        'enabled' => (bool) CRM_SMS_BAO_SmsProvider::activeProviderCount()
          && CRM_Core_Permission::check('send SMS'),
      );
      $results['can_send_email'] = (bool) CRM_Utils_Mail::validOutBoundMail();
      $results['manage_roles_url'] = CRM_Core_Permission::check('administer CiviCRM')
        ? CRM_Utils_System::url('civicrm/admin/options/volunteer_role', 'reset=1', FALSE, NULL, FALSE)
        : NULL;
      $results['shift_filter_presets'] = self::getShiftFilterPresets();
    }

    if (!in_array($controller, array('VolunteerProject', 'VolOppsCtrl', 'VolunteerWorkflow'), TRUE)) {
      throw new CRM_Core_Exception(ts('Unsupported volunteer interface.', array('domain' => 'org.civicrm.volunteer')));
    }

    $results['profile_audience_types'] = CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes();

    return $results;
  }

  /**
   * Site-local boundaries used by the Shifts page and its shared dialog.
   *
   * Use CiviCRM's testable clock, the CMS timezone, and the domain's
   * weekBegins setting. Sending complete boundaries avoids having the browser
   * reinterpret site-local need timestamps in its own timezone.
   */
  private static function getShiftFilterPresets(): array {
    $timezone = new DateTimeZone((string) CRM_Core_Config::singleton()->userSystem->getTimeZoneString());
    $now = (new DateTimeImmutable('@' . CRM_Utils_Time::time()))->setTimezone($timezone);
    $today = $now->setTime(0, 0, 0);
    $weekBegins = (int) Civi::settings()->get('weekBegins');
    if ($weekBegins < 0 || $weekBegins > 6) {
      $weekBegins = 0;
    }
    $daysSinceWeekStart = ((int) $today->format('w') - $weekBegins + 7) % 7;
    $thisWeek = $today->sub(new DateInterval('P' . $daysSinceWeekStart . 'D'));

    $range = static function(DateTimeImmutable $from): array {
      return array(
        'from' => $from->format('Y-m-d 00:00:00'),
        'to' => $from->add(new DateInterval('P6D'))->format('Y-m-d 23:59:59'),
      );
    };

    return array(
      'now' => $now->format('Y-m-d H:i:s'),
      'today' => array(
        'from' => $today->format('Y-m-d 00:00:00'),
        'to' => $today->format('Y-m-d 23:59:59'),
      ),
      'this_week' => $range($thisWeek),
      'next_week' => $range($thisWeek->add(new DateInterval('P7D'))),
      'last_week' => $range($thisWeek->sub(new DateInterval('P7D'))),
    );
  }

  /**
   * Whether the optional Geocoder extension can service a proximity search.
   *
   * Checking the configured provider as well as the extension status avoids
   * advertising a distance control which core cannot actually execute (for
   * example, after the extension was disabled but geoProvider still contains
   * its old value).
   */
  public static function isProximitySearchAvailable(): bool {
    try {
      return CRM_Extension_System::singleton()->getManager()->isEnabled('org.wikimedia.geocoder')
        && CRM_Utils_GeocodeProvider::getUsableClassName() === 'CRM_Utils_Geocode_Geocoder';
    }
    catch (Throwable $e) {
      // This is an optional integration. A missing extension, classloader, or
      // provider configuration disables distance search without affecting the
      // ordinary address filters.
      return FALSE;
    }
  }

  /**
   * The enabled countries in CiviCRM, flagged with the default country.
   *
   * Permission enforcement is contextual (project view vs. project edit)
   * and stays in the API4 action.
   *
   * @return array
   *   Country rows keyed by ID, with stringified is_default for template
   *   compatibility with the former API3 shape.
   */
  public static function getCountries(): array {
    $countryLimit = CRM_Volunteer_Api4::getSetting('countryLimit');
    $defaultContactCountry = CRM_Volunteer_Api4::getSetting('defaultContactCountry');

    $countries = \Civi\Api4\Country::get(FALSE)
      ->addSelect('id', 'name', 'iso_code', 'is_active');
    if (!empty($countryLimit)) {
      $countries->addWhere('id', 'IN', array_map('intval', (array) $countryLimit));
    }
    $results = array();
    foreach ($countries->execute() as $country) {
      $id = (int) $country['id'];
      // The former API3-based implementation provided even boolean data as
      // quoted strings; keep doing so because templates compare against "1".
      $results[$id] = array(
        'id' => $id,
        'name' => $country['name'],
        'iso_code' => $country['iso_code'],
        'is_active' => (string) (int) ($country['is_active'] ?? 0),
        'is_default' => ($defaultContactCountry && $id === (int) $defaultContactCountry) ? "1" : "0",
      );
    }

    return $results;
  }

  /**
   * The active, searchable custom fields in the Volunteer_Information group.
   *
   * @return array
   */
  public static function getCustomFields(): array {
    $allowedCustomFieldTypes = array('Autocomplete-Select',
      'CheckBox', 'Multi-Select', 'Radio', 'Select', 'Text');

    $customFields = array();
    foreach (\Civi\Api4\CustomField::get(FALSE)
      ->addSelect('*', 'custom_group_id.name')
      ->addWhere('custom_group_id.name', '=', 'Volunteer_Information')
      ->addWhere('custom_group_id.extends', '=', 'Individual')
      ->addWhere('html_type', 'IN', $allowedCustomFieldTypes)
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('is_searchable', '=', TRUE)
      ->execute() as $customField) {
      $customFields[] = $customField;
    }

    $optionListIDs = array();
    foreach ($customFields as $field) {
      if (!empty($field['option_group_id'])) {
        $optionListIDs[] = (int) $field['option_group_id'];
      }
    }
    $optionListIDs = array_unique($optionListIDs);

    $optionData = array();
    if ($optionListIDs) {
      $optionValues = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('*')
        ->addWhere('is_active', '=', TRUE)
        ->addWhere('option_group_id', 'IN', $optionListIDs)
        ->addOrderBy('weight', 'ASC')
        ->execute();
      foreach ($optionValues as $optionValue) {
        $optionData[(int) $optionValue['option_group_id']][] = $optionValue;
      }
    }

    foreach ($customFields as &$field) {
      $optionGroupId = $field['option_group_id'] ?? NULL;
      if ($optionGroupId) {
        $field['options'] = $optionData[(int) $optionGroupId] ?? array();
      }
      elseif ($field['data_type'] === 'Boolean' && $field['html_type'] === 'Radio') {
        // Boolean fields don't use option groups, so supply one.
        $field['options'] = array(
          array(
            'is_active' => 1,
            'is_default' => 1,
            'label' => ts("Yes", array('domain' => 'org.civicrm.volunteer')),
            'value' => 1,
            'weight' => 1,
          ),
          array(
            'is_active' => 1,
            'is_default' => 0,
            'label' => ts("No", array('domain' => 'org.civicrm.volunteer')),
            'value' => 0,
            'weight' => 2,
          ),
        );
      }
    }
    unset($field);

    return $customFields;
  }

}
