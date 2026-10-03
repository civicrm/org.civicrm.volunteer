<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */


require_once 'volunteer.civix.php';
require_once 'volunteer.slider.php';

use CRM_Volunteer_ExtensionUtil as E;

/**
 * Implementation of hook_civicrm_config
 */
function volunteer_civicrm_config(&$config) {
  _volunteer_civix_civicrm_config($config);
}

/**
 * Use the configured CiviCRM backend theme on CiviVolunteer screens.
 *
 * Public signup routes must remain marked public for access, URL-generation,
 * and CMS integration purposes. CiviCRM normally couples that flag to the
 * frontend theme, however, which makes public and administrative volunteer
 * screens look unrelated. Keep the public semantics while giving the complete
 * CiviVolunteer workflow the theme selected for CiviCRM's backend screens.
 *
 * The volunteer_use_backend_theme setting turns this off for sites whose
 * public CiviCRM pages must follow the frontend theme. An unknown value --
 * the settings metadata has not been reloaded since the upgrade -- keeps the
 * override on, which is the behaviour every earlier 2.5 build had.
 *
 * @param string $theme
 * @param array $context
 *
 * @see CRM_Utils_Hook::activeTheme()
 */
function volunteer_civicrm_activeTheme(&$theme, $context) {
  $path = trim((string) ($context['page'] ?? ''), '/');
  if (!preg_match('#^civicrm/(?:vol(?:/|$)|volunteer(?:/|$))#', $path)) {
    return;
  }

  $useBackendTheme = Civi::settings()->get('volunteer_use_backend_theme');
  if ($useBackendTheme !== NULL && !$useBackendTheme) {
    return;
  }

  $configuredTheme = Civi::settings()->get('theme_backend');
  $theme = (!$configuredTheme || $configuredTheme === 'default')
    ? \Civi\Core\Themes::DEFAULT_THEME
    : $configuredTheme;
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu/
 */
function volunteer_civicrm_navigationMenu(&$menu) {
  _volunteer_civix_insert_navigation_menu($menu, NULL, array(
    'label' => E::ts('Volunteers'),
    'name' => 'volunteer_volunteers',
    'url' => NULL,
    'permission' => 'register to volunteer,create volunteer projects,edit own volunteer projects,edit all volunteer projects,administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
    'icon' => 'crm-i fa-users',
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('New Volunteer Project'),
    'name' => 'volunteer_new_project',
    'url' => 'civicrm/volunteer/manage#/volunteer/manage/0',
    'permission' => 'create volunteer projects',
    'operator' => 'OR',
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Manage Volunteer Projects'),
    'name' => 'volunteer_manage_projects',
    'url' => 'civicrm/volunteer/manage#/volunteer/manage',
    'permission' => 'edit own volunteer projects,edit all volunteer projects',
    'operator' => 'OR',
    'separator' => 1,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Volunteer Hours Report'),
    'name' => 'volunteer_hours_report',
    'url' => 'civicrm/volunteer/hours-report',
    'permission' => 'edit all volunteer projects,view all contacts',
    'operator' => 'AND',
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Configure Roles'),
    'name' => 'volunteer_config_roles',
    'url' => 'civicrm/admin/options/volunteer_role?reset=1',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Configure Project Relationships'),
    'name' => 'volunteer_config_projrel',
    'url' => 'civicrm/admin/options/volunteer_project_relationship?reset=1',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Configure Volunteer Settings'),
    'name' => 'volunteer_config_settings',
    'url' => 'civicrm/admin/volunteer/settings',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 1,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Volunteer Interest Form'),
    'name' => 'volunteer_join',
    'url' => 'civicrm/volunteer/join',
    'permission' => 'register to volunteer',
    'operator' => 'OR',
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Search for Volunteer Opportunities'),
    'name' => 'volunteer_opp_search',
    'url' => 'civicrm/vol/#/volunteer/opportunities',
    'permission' => 'register to volunteer',
    'operator' => 'OR',
    'separator' => 0,
  ));

  _volunteer_civix_navigationMenu($menu);
}

/**
 * Implementation of hook_civicrm_tabset
 *
 * Insert the "Volunteer" tab into the event edit workflow and load Angular when
 * appropriate.
 */
function volunteer_civicrm_tabset($tabsetName, &$tabs, $context) {
  $eventId = $context['event_id'] ?? NULL;

  if (in_array($tabsetName, array('civicrm/event/manage', 'civicrm/event/manage/rows'), TRUE)
    && !_volunteer_can_manage_event_project($eventId)) {
    return;
  }

  if ($tabsetName == 'civicrm/event/manage') {
    if ($eventId) {
      // If in snippet mode, tab content is loading. Otherwise, the tabset is
      // loading. Angular should be loaded only once, and only in the latter case.
      // While it seems presumptuous to load Angular with the tabset, when we
      // don't yet know whether the user will visit the Volunteer tab, the
      // alternative is to load it after the full page has loaded, via an AJAX
      // call (with the tab content). The latter invites difficult-to-resolve
      // JavaScript scope conflicts with the CMS, so we avoid it.
      if (!CRM_Utils_Request::retrieve('snippet', 'String')) {
        CRM_Volunteer_Angular_Tab_Event::prepareTab($eventId);
      }

      $url = CRM_Utils_System::url( 'civicrm/event/manage/volunteer',
        "reset=1&snippet=5&force=1&id=$eventId&action=update&component=event");

      $tab['volunteer'] = array(
        'title' => ts('Volunteers', array('domain' => 'org.civicrm.volunteer')),
        'link' => $url,
        'valid' => TRUE,
        'active' => TRUE,
        'class' => 'livePage',
        'current' => false,
      );

      // getEventProject() rather than isActive(): the gate above has already
      // performed this lookup for this event, and the memo makes the second
      // read free.
      $eventProject = CRM_Volunteer_BAO_Project::getEventProject($eventId);
      if (!$eventProject || !$eventProject->is_active) {
        $tab['volunteer']['valid'] = FALSE;
      }
    }
    else {
      $tab['volunteer'] = array(
        'title' => ts('Volunteers', array('domain' => 'org.civicrm.volunteer')),
        'url'   => 'civicrm/event/manage/volunteer',
        'field' => 'is_volunteer',
      );
    }
    // Insert this tab into position 4
    $tabs = array_merge(
      array_slice($tabs, 0, 4),
      $tab,
      array_slice($tabs, 4)
    );
  }

  // on manage events listing screen, this section sets volunteer tab in configuration popup as enabled/disabled.
  // Core calls this once per row, so both reads here go through the memo.
  if ($tabsetName == 'civicrm/event/manage/rows' && $eventId) {
    $rowProject = CRM_Volunteer_BAO_Project::getEventProject($eventId);
    $tabs[$eventId]['is_volunteer'] = $rowProject ? $rowProject->is_active : NULL;
  }
}

/**
 * Check the project-level permission behind an event's Volunteer tab.
 *
 * @param int|null $eventId
 * @return bool
 */
function _volunteer_can_manage_event_project($eventId) {
  // The decision itself lives on CRM_Volunteer_Permission so the route behind
  // the tab can apply the same one; this remains the hook-facing name.
  return CRM_Volunteer_Permission::checkEventProjectManagement($eventId);
}

/**
 * Implementation of hook_civicrm_install
 */
function volunteer_civicrm_install() {
  return _volunteer_civix_civicrm_install();
}

/**
* Implements hook_civicrm_postInstall().
*
* @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
*/



/**
 * Implementation of hook_civicrm_enable
 */
function volunteer_civicrm_enable() {
  $doc_url = 'https://docs.civicrm.org/volunteer/en/latest/';
  $forum_url = 'http://forum.civicrm.org/index.php/board,84.0.html';
  $role_url = CRM_Utils_System::url('civicrm/admin/options/volunteer_role', 'group=volunteer_role&reset=1');
  $events_url = CRM_Utils_System::url('civicrm/event/manage', 'reset=1');
  $message = "<p>" . ts("Getting Started:") . "<p><ul>
    <li>" . ts('Read <a href="%1" target="_blank">documentation</a>', array(1 => $doc_url, 'domain' => 'org.civicrm.volunteer')) . "</li>
    <li>" . ts('Ask questions on the <a href="%1" target="_blank">forum</a>', array(1 => $forum_url, 'domain' => 'org.civicrm.volunteer')) . "</li>
    <li>" . ts('Configure <a href="%1" target="_blank">volunteer roles</a>', array(1 => $role_url, 'domain' => 'org.civicrm.volunteer')) . "</li>
    <li>" . ts('Enable volunteer management for one or more <a href="%1" target="_blank">events</a>', array(1 => $events_url, 'domain' => 'org.civicrm.volunteer')) . "</li></ul>";
  // As long as the message contains a link, the pop-up will not automatically close
  CRM_Core_Session::setStatus($message, ts('CiviVolunteer Installed', array('domain' => 'org.civicrm.volunteer')), 'success');
  _volunteer_civix_civicrm_enable();
  return TRUE;
}

/**
 * Implementation of hook_civicrm_pageRun
 *
 * Handler for pageRun hook.
 */
function volunteer_civicrm_pageRun(&$page) {
  $f = '_' . __FUNCTION__ . '_' . get_class($page);
  if (function_exists($f)) {
    $f($page);
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 *
 * Handler for postProcess hook.
 */
function volunteer_civicrm_postProcess($formName, &$form) {
  $f = '_' . __FUNCTION__ . '_' . $formName;
  if (function_exists($f)) {
    $f($formName, $form);
  }
}

/**
 * Callback for event info page
 *
 * Inserts "Volunteer Now" button via {crmRegion} if a project is associated
 * with the event.
 */
function _volunteer_civicrm_pageRun_CRM_Event_Page_EventInfo(&$page) {
  // Public event pages are rendered for visitors who may not be allowed to
  // register as volunteers. Leave the page untouched for them rather than
  // invoking a guarded project read which would raise an exception.
  if (!CRM_Volunteer_Permission::check('register to volunteer')) {
    return;
  }

  $params = array(
    'entity_id' => $page->getVar('_id'),
    'entity_table' => 'civicrm_event',
    'is_active' => 1,
  );
  $projects = CRM_Volunteer_BAO_Project::retrieve($params);

  // show volunteer button only if user has CiviVolunteer: register to volunteer AND this event has an active project
  if (count($projects)) {
    $project = current($projects);

    //VOL-189: Do not show the volunteer now button if there are not open needs.
    $openNeeds = \Civi\Api4\VolunteerNeed::search()
      ->setProject($project->id)
      ->execute()
      ->getArrayCopy();
    $openNeedCount = count($openNeeds);
    if ($openNeedCount > 0) {
      //VOL-191: Skip "shopping cart" if only one need
      if ($openNeedCount == 1) {
        $need = reset($openNeeds);
        $url = CRM_Utils_System::url('civicrm/volunteer/signup',
          "reset=1&needs[]={$need['id']}&dest=event", // query string
          FALSE, // absolute
          NULL, // fragment
          TRUE, // FrontEnd
          FALSE // Backend
        );
      } else {
        //VOL-190: Hide search pane in "shopping cart" for low role count projects
        $hideSearch = ($openNeedCount < 10) ? "hideSearch=always" : (($openNeedCount < 25) ? "hideSearch=1" : "hideSearch=0");
        $url = CRM_Utils_System::url('civicrm/vol/',
          NULL, // query string
          FALSE, // absolute?
          "/volunteer/opportunities?project={$project->id}&dest=event&{$hideSearch}", // fragment
          TRUE, // Frontend
          FALSE // Backend?
        );
      }


      $button_text = ts('Volunteer Now', array('domain' => 'org.civicrm.volunteer'));

      $snippet = array(
        'template' => 'CRM/Event/Page/volunteer-button.tpl',
        'button_text' => $button_text,
        'position' => 'top',
        'url' => $url,
        'weight' => -10,
      );
      CRM_Core_Region::instance('event-page-eventinfo-actionlinks-top')->add($snippet);

      $snippet['position'] = 'bottom';
      $snippet['weight'] = 10;
      CRM_Core_Region::instance('event-page-eventinfo-actionlinks-bottom')->add($snippet);

      CRM_Core_Resources::singleton()->addStyleFile('org.civicrm.volunteer',
        'templates/CRM/Event/Page/EventInfo.css'
      );
    }
  }
}

/**
 * Implementation of hook_civicrm_buildForm
 *
 * Handler for buildForm hook.
 */
function volunteer_civicrm_buildForm($formName, &$form) {
  $f = '_' . __FUNCTION__ . '_' . $formName;
  if (function_exists($f)) {
    $f($formName, $form);
  }
  _volunteer_addSliderWidget($form);
}

/**
 * Callback for core Activity view and form
 *
 * Display user-friendly label for Need ID rather than an integer, or hide
 * the field altogether, depending on context.
 */
function _volunteer_civicrm_buildForm_CRM_Activity_Form_Activity($formName, &$form) {
  // determine name that the Volunteer Need ID field would be given in this form
  $custom_group = CRM_Volunteer_BAO_Assignment::getCustomGroup();
  $custom_fields = CRM_Volunteer_BAO_Assignment::getCustomFields();
  $group_id = $custom_group['id'];
  $field_id = $custom_fields['volunteer_need_id']['id'];

  // element name varies depending on context
  $possible_element_names = array(
    'custom_' . $field_id . '_1',
    'custom_' . $field_id . '_-1',
  );
  $element_name = NULL;
  foreach ($possible_element_names as $name) {
    if ($form->elementExists($name)) {
      $element_name = $name;
      break;
    }
  }

  // If it contains the Volunteer Need ID field, this is an edit form
  if (isset($element_name)) {
    $field = $form->getElement($element_name);
    $form->removeElement($element_name);

    // If need_id isn't set, do not re-add need_id field as a dropdown.
    // See http://issues.civicrm.org/jira/browse/VOL-24?focusedCommentId=53836#comment-53836
    if (($need_id = $field->_attributes['value'])) {
      $need = CRM_Volunteer_Permission::withInternalBypass(function() use ($need_id) {
        return \Civi\Api4\VolunteerNeed::get(FALSE)
          ->addSelect('project_id')
          ->addWhere('id', '=', $need_id)
          ->execute()
          ->first();
      });
      if (!$need) {
        $form->add('static', $element_name, $field->_label, E::ts('Unavailable volunteer opportunity'));
        return;
      }
      // The core Activity form has already authorized access to this activity.
      // Read its static opportunity label in a trusted scope; project-level
      // authorization below still controls whether the field is editable.
      $Project = CRM_Volunteer_Permission::withInternalBypass(function() use ($need) {
        return CRM_Volunteer_BAO_Project::retrieveByID(
          $need['project_id'],
          array('check_permissions' => FALSE)
        );
      });

      $needs = array();
      foreach ($Project->needs as $key => $value) {
        $needs[$key] = $value['role_label'] . ': ' . $value['display_time'];
      }
      asort($needs);

      if (CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::UPDATE, $need['project_id'])) {
        $form->add(
          'select',               // field type
          $element_name,          // field name
          $field->_label,         // field label
          $needs,                 // list of options (value => label)
          TRUE                    // required
        );
      }
      else {
        // Core activity access permits viewing this value, but changing the
        // owning volunteer need requires project-level authority.
        $form->add('static', $element_name, $field->_label, $needs[$need_id] ?? (string) $need_id);
      }
    }
  }
  // In "View" mode
  elseif (isset($form->_activityTypeName) && $form->_activityTypeName == 'Volunteer') {
    $custom = $form->getTemplateVars('viewCustomData');
    if (!empty($custom[$group_id])) {
      $index = key($custom[$group_id]);
      if (!empty($custom[$group_id][$index]['fields'][$field_id]['field_value'])) {
        $value =& $custom[$group_id][$index]['fields'][$field_id]['field_value'];
        $need = CRM_Volunteer_Permission::withInternalBypass(function() use ($value) {
          return \Civi\Api4\VolunteerNeed::get(FALSE)
            ->addSelect('*')
            ->addWhere('id', '=', $value)
            ->execute()
            ->first();
        });
        if ($need) {
          $value = $need['role_label'] . ': ' . $need['display_time'];
        }
        $form->assign('viewCustomData', $custom);
      }
    }
  }
}

/**
 * Implementation of hook_civicrm_permission.
 *
 * @param array $permissions Does not contain core perms -- only extension-defined perms.
 */
/**
 * Implementation of hook_civicrm_copy
 *
 * Carry an event's volunteer setup onto a copy of that event. Core fires this
 * from CRM_Event_BAO_Event::copy() with the original's ID; without a listener,
 * duplicating an event produced one with no volunteer project and no warning.
 *
 * @param string $objectName
 * @param object $object
 *   The newly created copy.
 * @param int|null $original_id
 */
function volunteer_civicrm_copy($objectName, &$object, $original_id = NULL) {
  if ($objectName !== 'Event' || empty($object->id) || empty($original_id)) {
    return;
  }

  try {
    CRM_Volunteer_BAO_Project::copyForEvent((int) $original_id, (int) $object->id);
  }
  catch (Throwable $e) {
    // The event copy itself has already happened and is not ours to fail.
    // Surface the problem rather than aborting someone else's operation.
    \Civi::log()->error(sprintf(
      'Could not copy the volunteer project from event %d to event %d: %s',
      $original_id,
      $object->id,
      $e->getMessage()
    ));
  }
}

/**
 * Implementation of hook_civicrm_pre
 *
 * Unlink, rather than orphan, the volunteer project of an event being deleted.
 * @see CRM_Volunteer_BAO_Project::detachFromEvent()
 *
 * @param string $op
 * @param string $objectName
 * @param int|null $id
 * @param array $params
 */
function volunteer_civicrm_pre($op, $objectName, $id, &$params) {
  if ($objectName === 'Event' && $op === 'delete' && $id) {
    CRM_Volunteer_BAO_Project::detachFromEvent((int) $id);
  }
}

function volunteer_civicrm_permission(array &$permissions) {
  // VOL-71: Until the Joomla/Civi integration is fixed, don't declare new perms
  // for Joomla installs
  if (CRM_Core_Config::singleton()->userPermissionClass->isModulePermissionSupported()) {
    $permissions = array_merge($permissions, CRM_Volunteer_Permission::getVolunteerPermissions());
  }
}

/**
 * Implements hook_civicrm_alterAPIPermissions
 */
function volunteer_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  // Coarse API authorization limits action discovery. Domain methods still
  // perform project-level checks and never trust a request-supplied
  // check_permissions=FALSE value.
  $allow = array(CRM_Core_Permission::ALWAYS_ALLOW_PERMISSION);
  $projectView = array(array(
    'register to volunteer',
    'create volunteer projects',
    'edit own volunteer projects',
    'edit all volunteer projects',
  ));
  $projectEdit = array(array(
    'create volunteer projects',
    'edit own volunteer projects',
    'edit all volunteer projects',
  ));
  // APIv3's generic `setvalue` writes through CRM_Core_DAO::setFieldValue()
  // without ever reaching a BAO, so none of the project-level authorization in
  // this extension applies to it: anyone who satisfies the coarse map could
  // edit any row of any project. Core deprecated the action in favour of
  // `create` with an id -- which is guarded -- so refuse it outright rather
  // than leaving an unauthorized write path open.
  $deny = array(CRM_Core_Permission::ALWAYS_DENY_PERMISSION);

  $permissions['volunteer_need'] = array(
    'default' => $projectEdit,
    'get' => $projectView,
    'getsearchresult' => $projectView,
    'setvalue' => $deny,
  );
  // Assignment and project-contact reads have relationship-based access which
  // cannot be represented by the coarse permission map; their API methods do
  // the mandatory row check.
  $permissions['volunteer_assignment'] = array(
    'default' => $projectEdit,
    'get' => $allow,
  );
  $permissions['volunteer_commendation']['default'] = array(array(
    'edit own volunteer projects',
    'edit all volunteer projects',
  ));
  $permissions['volunteer_project'] = array(
    'default' => $projectEdit,
    'get' => $projectView,
    'delete' => array(array('delete own volunteer projects', 'delete all volunteer projects')),
    'removeprofile' => array('edit volunteer registration profiles'),
    'setvalue' => $deny,
  );
  $permissions['volunteer_util'] = array(
    'default' => $projectEdit,
    'getperms' => $allow,
    'getprofiles' => array('edit volunteer registration profiles'),
    'getsupportingdata' => $projectView,
    'getcountries' => $projectView,
  );
  $permissions['volunteer_project_contact'] = array(
    'default' => $projectEdit,
    'get' => $allow,
    'setvalue' => $deny,
  );
}

/**
 * This is an implementation of hook_civicrm_fieldOptions
 * It includes `civicrm_volunteer_project` in the whitelist
 * of tables allowed to have UFJoins
 *
 * @param $entity
 * @param $field
 * @param $options
 * @param $params
 */
function volunteer_civicrm_fieldOptions($entity, $field, &$options, $params) {
  if ($entity == 'UFJoin' && $field == 'entity_table') {
    if (($params['context'] ?? NULL) == 'validate') {
      $options[CRM_Volunteer_DAO_Project::getTableName()] = CRM_Volunteer_DAO_Project::getTableName();
    }
    else {
      $options[CRM_Volunteer_DAO_Project::getTableName()] = E::ts('Volunteer Project');
    }
  }
}
