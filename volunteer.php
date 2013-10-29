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
        'title' => ts('Volunteers'),
        'link' => $url,
        'valid' => TRUE,
        'active' => TRUE,
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
        'title' => ts('Volunteers'),
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
  $url = CRM_Utils_System::url('civicrm/event/manage', 'reset=1');
  CRM_Core_Session::setStatus(
    // as long as the message contains a link, the pop-up will not automatically close
    ts('<p>Thank you for installing the beta version of CiviVolunteer! Documentation is still a work in progress.</p><p>To get started, visit <a href="%1">Manage Events</a> and enable volunteer management from the new Volunteer tab on one of your events.</p>', array(1 => $url)),
    ts('Getting Started with CiviVolunteer'),
    'info'
  );
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
  if (CRM_Core_Permission::check('register to volunteer') && count($projects)) {
    $project = current($projects);
    $url = CRM_Utils_System::url('civicrm/volunteer/signup', array(
      'reset' => 1,
      'vid' => $project->id,
    ));
    $button_text = ts('Volunteer Now');

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

  // target only activity forms that contain the Volunteer Need ID field
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
}

/**
 * Implementation of hook_civicrm_permission.
 *
 * @param type $permissions Does not contain core perms -- only extension-defined perms.
 */
function volunteer_civicrm_permission(array &$permissions) {
  $prefix = ts('CiviVolunteer') . ': ';
  $permissions['register to volunteer'] = $prefix . 'register to volunteer';
}