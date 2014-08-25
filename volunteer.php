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

/**
 * Implementation of hook_civicrm_config
 */
function volunteer_civicrm_config(&$config) {
  _volunteer_civix_civicrm_config($config);
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
 * Insert the "Volunteer" tab into the event edit workflow
 */
function volunteer_civicrm_tabset($tabsetName, &$tabs, $context) {
  if ($tabsetName == 'civicrm/event/manage') {
    if (!empty($context)) {
      $eventID = $context['event_id'];
      $url = CRM_Utils_System::url( 'civicrm/event/manage/volunteer',
        "reset=1&snippet=5&force=1&id=$eventID&action=update&component=event" );

      $tab['volunteer'] = array(
        'title' => ts('Volunteers', array('domain' => 'org.civicrm.volunteer')),
        'link' => $url,
        'valid' => TRUE,
        'active' => TRUE,
        'class' => 'livePage',
        'current' => false,
      );
      // If volunteer mngmt is enabled, add necessary UI elements
      if (CRM_Volunteer_BAO_Project::isActive($eventID, CRM_Event_DAO_Event::$_tableName)) {
        CRM_Volunteer_Form_Manage::addResources($eventID, CRM_Event_DAO_Event::$_tableName);
      } else {
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
  if ($tabsetName == 'civicrm/event/manage/rows' && CRM_Utils_Array::value('event_id', $context)) {
    $eventID = $context['event_id'];
    $tabs[$eventID]['is_volunteer'] = CRM_Volunteer_BAO_Project::isActive($eventID, CRM_Event_DAO_Event::$_tableName);
  }
}

/**
 * Implementation of hook_civicrm_install
 */
function volunteer_civicrm_install() {
  return _volunteer_civix_civicrm_install();
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
  $doc_url = 'https://github.com/civicrm/civivolunteer/blob/4.5-1.4/README.md';
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
  _volunteer_civicrm_check_resource_url();
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
    $url = CRM_Utils_System::url('civicrm/volunteer/signup',
      array('reset' => 1, 'vid' => $project->id),
      FALSE, // absolute?
      NULL, // fragment
      TRUE, // htmlize?
      TRUE // is frontend?
    );
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
}

/**
 * Callback for core Activity view and form
 *
 * Display user-friendly label for Need ID rather than an integer, or hide
 * the field altogether, depending on context.
 */
function _volunteer_civicrm_buildForm_CRM_Activity_Form_Activity($formName, &$form) {
  // determine name that the Volunteer Need ID field would be given in this form
  $params = array(
    'name' => CRM_Volunteer_Upgrader::customGroupName,
    'return' => 'id',
    'api.CustomField.getsingle' => array(
      'name' => 'Volunteer_Need_Id',
      'return' => 'id',
    ),
  );
  $result = civicrm_api3('CustomGroup', 'getsingle', $params);
  $group_id = $result['id'];
  $field_id = $result['api.CustomField.getsingle']['id'];

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
 * Displays an alert if the resource url is misconfigured
 * Proof is in the pudding - add a real js file to the page and see if it works.
 */
function _volunteer_civicrm_check_resource_url() {
  $message = json_encode(
    '<p>' . ts('Your extension resource url is not configured correctly. CiviVolunteer cannot work without this setting.', array('domain' => 'org.civicrm.volunteer')) .
    '</p><p>' . ts('Correct the problem at <a href="%1">Settings - Resource URLs</a>.', array(1 => CRM_Utils_System::url('civicrm/admin/setting/url', 'reset=1'), 'domain' => 'org.civicrm.volunteer')) . '</p>'
  );
  $title = json_encode(ts('Error'));
  CRM_Core_Resources::singleton()
    ->addScriptFile('org.civicrm.volunteer', 'js/checkResourceUrl.js')
    ->addScript("cj(function() {
      window.civiVolunteerResourceUrlIsOk || CRM.alert($message, $title, 'error');
    });");
}

/**
 * Implements hook_civicrm_alterAPIPermissions
 */
function volunteer_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
// note: unsetting the below would require the default ‘administer CiviCRM’ permission
  $permissions['volunteer_need']['default'] = array('access CiviEvent', 'edit all events');
  $permissions['volunteer_assignment']['default'] = array('access CiviEvent', 'edit all events');
}

/**
 * Implementation of hook_civicrm_alterTemplateFile
 *
 * @param type $formName
 * @param type $form
 * @param type $context
 * @param type $tplName
 */
function volunteer_civicrm_alterTemplateFile ($formName, &$form, $context, &$tplName) {
  $f = '_' . __FUNCTION__ . '_' . $formName;
  if (function_exists($f)) {
    $f($formName, $form, $context, $tplName);
  }
}

/**
 * Delegated implementation of hook_civicrm_alterTemplateFile
 *
 * Don't load the volunteer tab if Multiform prereq is missing.
 *
 * @param type $formName
 * @param type $form
 * @param type $context
 * @param string $tplName
 */
function _volunteer_civicrm_alterTemplateFile_CRM_Volunteer_Form_Volunteer ($formName, &$form, $context, &$tplName) {
  $unmet = CRM_Volunteer_Upgrader::checkExtensionDependencies();

  if (in_array('com.ginkgostreet.multiform', $unmet)) {
    $tplName = 'CRM/Volunteer/MissingDependency.tpl';
  }
}
