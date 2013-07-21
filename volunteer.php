<?php

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
        'valid' => 1,
        'active' => 1,
        'current' => false,
      );
      // If volunteer mngmt is enabled, add necessary UI elements
      if (CRM_Volunteer_BAO_Project::isActive($eventID, CRM_Event_DAO_Event::$_tableName)) {
        CRM_Volunteer_Form_Manage::addResources();
      }
    }
    else {
      $tab['volunteer'] = array(
        'title' => ts('Volunteers'),
        'url' => 'civicrm/event/manage/volunteer',
      );
    }
    // Insert this tab into position 4
    $tabs = array_merge(
      array_slice($tabs, 0, 4),
      $tab,
      array_slice($tabs, 4)
    );
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
  $f = '_volunteer_civicrm_pageRun_' . get_class($page);
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

  // show volunteer button only if this event has an active project
  if (count($projects)) {
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
 * Implementation of hook_civicrm_permission.
 *
 * @param type $permissions Does not contain core perms -- only extension-defined perms.
 */
function volunteer_civicrm_permission(array &$permissions) {
  $prefix = ts('CiviVolunteer') . ': ';
  $permissions['register to volunteer'] = $prefix . 'register to volunteer';
}