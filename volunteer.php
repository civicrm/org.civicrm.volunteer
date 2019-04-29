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
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu/
 */
function volunteer_civicrm_navigationMenu(&$menu) {
  _volunteer_civix_insert_navigation_menu($menu, NULL, array(
    'label' => E::ts('Volunteers'),
    'name' => 'volunteer_volunteers',
    'url' => NULL,
    'permission' => NULL,
    'operator' => NULL,
    'separator' => 0,
    'icon' => 'crm-i fa-users',
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('New Volunteer Project'),
    'name' => 'volunteer_new_project',
    'url' => 'civicrm/vol/#/volunteer/manage/0',
    'permission' => NULL,
    'operator' => NULL,
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Manage Volunteer Projects'),
    'name' => 'volunteer_manage_projects',
    'url' => 'civicrm/vol/#/volunteer/manage',
    'permission' => NULL,
    'operator' => NULL,
    'separator' => 1,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Configure Roles'),
    'name' => 'volunteer_config_roles',
    'url' => 'civicrm/admin/options/volunteer_role?reset=1',
    'permission' => NULL,
    'operator' => NULL,
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Configure Project Relationships'),
    'name' => 'volunteer_config_projrel',
    'url' => 'civicrm/admin/options/volunteer_project_relationship?reset=1',
    'permission' => NULL,
    'operator' => NULL,
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Configure Volunteer Settings'),
    'name' => 'volunteer_config_settings',
    'url' => 'civicrm/admin/volunteer/settings',
    'permission' => NULL,
    'operator' => NULL,
    'separator' => 1,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Volunteer Interest Form'),
    'name' => 'volunteer_join',
    'url' => 'civicrm/volunteer/join',
    'permission' => NULL,
    'operator' => NULL,
    'separator' => 0,
  ));

  _volunteer_civix_insert_navigation_menu($menu, 'volunteer_volunteers', array(
    'label' => E::ts('Search for Volunteer Opportunities'),
    'name' => 'volunteer_opp_search',
    'url' => 'civicrm/vol/#/volunteer/opportunities',
    'permission' => NULL,
    'operator' => NULL,
    'separator' => 0,
  ));

  _volunteer_civix_navigationMenu($menu);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function volunteer_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _volunteer_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function volunteer_civicrm_xmlMenu(&$files) {
  _volunteer_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_tabset
 *
 * Insert the "Volunteer" tab into the event edit workflow and load Angular when
 * appropriate.
 */
function volunteer_civicrm_tabset($tabsetName, &$tabs, $context) {
  $eventId = CRM_Utils_Array::value('event_id', $context);

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
        'current' => FALSE,
      );

      if (!CRM_Volunteer_BAO_Project::isActive($eventId, CRM_Event_DAO_Event::$_tableName)) {
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
  if ($tabsetName == 'civicrm/event/manage/rows' && $eventId) {
    $tabs[$eventId]['is_volunteer'] = CRM_Volunteer_BAO_Project::isActive($eventId, CRM_Event_DAO_Event::$_tableName);
  }
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
function volunteer_civicrm_postInstall() {
  _volunteer_civix_civicrm_postInstall();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function volunteer_civicrm_uninstall() {
  return _volunteer_civix_civicrm_uninstall();
}

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
  return _volunteer_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function volunteer_civicrm_disable() {
  return _volunteer_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function volunteer_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _volunteer_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function volunteer_civicrm_managed(&$entities) {
  return _volunteer_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_entityTypes
 */
function volunteer_civicrm_entityTypes(&$entityTypes) {
  $entityTypes[] = array(
    'name'  => 'VolunteerNeed',
    'class' => 'CRM_Volunteer_DAO_Need',
    'table' => 'civicrm_volunteer_need',
  );
  $entityTypes[] = array(
    'name'  => 'VolunteerProject',
    'class' => 'CRM_Volunteer_DAO_Project',
    'table' => 'civicrm_volunteer_project',
  );
  $entityTypes[] = array(
    'name'  => 'VolunteerProjectContact',
    'class' => 'CRM_Volunteer_DAO_ProjectContact',
    'table' => 'civicrm_volunteer_project_contact',
  );
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
  _volunteer_periodicChecks();
}

function _volunteer_civicrm_pageRun_CRM_Admin_Page_Extensions(&$page) {
  _volunteer_prereqCheck();
}

function _volunteer_civicrm_pageRun_CRM_Volunteer_Page_Angular(&$page) {
  _volunteer_prereqCheck();
}

function _volunteer_prereqCheck() {
  $unmet = CRM_Volunteer_Upgrader::checkExtensionDependencies();
  CRM_Volunteer_Upgrader::displayDependencyErrors($unmet);
}

function _volunteer_periodicChecks() {
  $session = CRM_Core_Session::singleton();
  if (
    !CRM_Core_Permission::check('administer CiviCRM')
    || !$session->timer('check_CRM_Volunteer_Depends', CRM_Utils_Check::CHECK_TIMER)
  ) {
    return;
  }

  _volunteer_prereqCheck();
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
  $params = array(
    'entity_id' => $page->_id,
    'entity_table' => 'civicrm_event',
    'is_active' => 1,
  );
  $projects = CRM_Volunteer_BAO_Project::retrieve($params);

  // show volunteer button only if user has CiviVolunteer: register to volunteer AND this event has an active project
  if (CRM_Volunteer_Permission::check('register to volunteer') && count($projects)) {
    $project = current($projects);

    //VOL-189: Do not show the volunteer now button if there are not open needs.
    $openNeeds = civicrm_api3('VolunteerNeed', 'getsearchresult', array(
      'project' => $project->id,
      'sequential' => 1
    ));
    if($openNeeds['count'] > 0) {
      //VOL-191: Skip "shopping cart" if only one need
      if ($openNeeds['count'] == 1) {
        $need = $openNeeds['values'][0];
        $url = CRM_Utils_System::url('civicrm/volunteer/signup', "reset=1&needs[]={$need['id']}&dest=event");
      } else {
        //VOL-190: Hide search pane in "shopping cart" for low role count projects
        $hideSearch = ($openNeeds['count'] < 10) ? "hideSearch=always" : (($openNeeds['count'] < 25) ? "hideSearch=1" : "hideSearch=0");
        $url = CRM_Utils_System::url('civicrm/vol/',
          NULL, // query string
          FALSE, // absolute?
          "/volunteer/opportunities?project={$project->id}&dest=event&{$hideSearch}", // fragment
          TRUE, // htmlize?
          TRUE // is frontend?
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
      $need = civicrm_api3('VolunteerNeed', 'getsingle', array(
        'id' => $need_id,
        'return' => 'project_id'
      ));
      $Project = CRM_Volunteer_BAO_Project::retrieveByID($need['project_id']);

      $needs = array();
      foreach ($Project->needs as $key => $value) {
        $needs[$key] = $value['role_label'] . ': ' . $value['display_time'];
      }
      asort($needs);

      $form->add(
        'select',               // field type
        $element_name,          // field name
        $field->_label,         // field label
        $needs,                 // list of options (value => label)
        TRUE                    // required
      );
    }
  }
  // In "View" mode
  elseif (isset($form->_activityTypeName) && $form->_activityTypeName == 'Volunteer') {
    $custom = $form->get_template_vars('viewCustomData');
    if (!empty($custom[$group_id])) {
      $index = key($custom[$group_id]);
      if (!empty($custom[$group_id][$index]['fields'][$field_id]['field_value'])) {
        $value =& $custom[$group_id][$index]['fields'][$field_id]['field_value'];
        $need = civicrm_api3('VolunteerNeed', 'getsingle', array('id' => $value));
        $value = $need['role_label'] . ': ' . $need['display_time'];
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
// note: unsetting the below would require the default 'administer CiviCRM' permission
  $permissions['volunteer_need']['default'] = array('create volunteer projects');
  $permissions['volunteer_need']['getsearchresult'] = array('register to volunteer');
  $permissions['volunteer_assignment']['default'] = array('edit own volunteer projects');
  $permissions['volunteer_commendation']['default'] = array('edit own volunteer projects');
  $permissions['volunteer_project']['default'] = array('create volunteer projects');
  $permissions['volunteer_project']['get'] = array('register to volunteer');
  $permissions['volunteer_project']['getlocblockdata'] = array('edit own volunteer projects');
  $permissions['volunteer_util']['default'] = array('edit own volunteer projects');
  $permissions['volunteer_project_contact']['default'] = array('edit own volunteer projects');


  // allow fairly liberal access to the volunteer opp listing UI, which uses lots of API calls
  if (_volunteer_isVolListingApiCall($entity, $action) && CRM_Volunteer_Permission::checkProjectPerms(CRM_Core_Action::VIEW)) {
    $params['check_permissions'] = FALSE;
  }
}

/**
 * This is a helper function to volunteer_civicrm_alterAPIPermissions.
 *
 * It encapsulates the logic for determining whether or not the API calls in
 * question are of the type that the volunteer opportunities search/listing
 * depends on.
 *
 * @param string $entity
 *   The noun in an API call (e.g., volunteer_project)
 * @param string $action
 *   The verb in an API call (e.g., get)
 * @return boolean
 *   True if the API call is of the type that the vol opps UI depends on.
 */
function _volunteer_isVolListingApiCall($entity, $action) {
  $actions = array(
    'get',
    'getcountries',
    'getlist',
    'getsingle',
    'getsupportingdata',
    'getperms'
  );
  $entities = array('volunteer_project_contact', 'volunteer_need', 'volunteer_project', 'volunteer_util');

  return (in_array($entity, $entities) && in_array($action, $actions));
}

/**
 * Implementation of hook_civicrm_angularModules.
 *
 * @param array $angularModules
 *   An array containing a list of all Angular modules.
 */
function volunteer_civicrm_angularModules(&$angularModules) {
  $angularModules['volunteer'] = array(
    'ext' => 'org.civicrm.volunteer',
    'basePages' => array('civicrm/vol'),
    'requires' => array(
      'crmApp',
      'crmProfileUtils',
      'crmUi',
      'crmUtil',
      'ngRoute',
      'ngSanitize',
    ),
    'js' =>
      array (
        0 => 'ang/volunteer.js',
        1 => 'ang/volunteer/*.js',
        2 => 'ang/volunteer/*/*.js'
      ),
    'css' => array (0 => 'ang/volunteer.css'),
    'partials' => array (0 => 'ang/volunteer'),
    'settings' => array(),
  );

  // Perhaps the placement of this code is a little hackish; unless/until we
  // extend Civi\Angular\Page\Main, there doesn't appear to be a better
  // alternative. This populates CRM.permissions on the client side.
  CRM_Core_Resources::singleton()->addPermissions(array_keys(CRM_Volunteer_Permission::getVolunteerPermissions()))

    // Perhaps the placement of this code is a little hackish; unless/until we
    // extend Civi\Angular\Page\Main, there doesn't appear to be a better
    // alternative. This provides access to the current contact id on the
    // client side.
    ->addVars('org.civicrm.volunteer', array(
      'currentContactId' => CRM_Core_Session::singleton()->getLoggedInContactID()
    ));
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
  if ($entity == "UFJoin" && $field == "entity_table" && $params['context'] == "validate") {
    $options[CRM_Volunteer_DAO_Project::getTableName()] = CRM_Volunteer_DAO_Project::getTableName();
  }
}

