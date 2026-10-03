<?php

class CRM_Volunteer_Angular_Tab_Event extends CRM_Core_Page {

  /**
   * Initializes a placeholder volunteer project.
   *
   * Used in cases where no project yet exists for the event, to prepopulate the
   * "create" form.
   *
   * @return \CRM_Volunteer_BAO_Project
   */
  protected static function initializeProject($eventId) {
    $project = new CRM_Volunteer_BAO_Project();
    $project->id = 0;
    $project->entity_id = $eventId;
    $project->entity_table = CRM_Event_DAO_Event::getTableName();

    return $project;
  }

  /**
   * Sets the stage for the CiviVolunteer Angular app to be loaded in a tab.
   *
   * Called from hook_civicrm_tabset().
   *
   * @param int|string $eventId
   */
  public static function prepareTab($eventId) {
    CRM_Core_Region::instance('page-footer')->add(array(
      'template' => 'CRM/Volunteer/Page/Angular.tpl',
    ));

    // Memoized, and the same read hook_civicrm_tabset() has already performed
    // for this event.
    $project = CRM_Volunteer_BAO_Project::getEventProject($eventId);
    if (!$project) {
      $project = self::initializeProject($eventId);
    }

    CRM_Volunteer_Angular::load('/volunteer/manage/' . $project->id . '/details');

    $event = $project->getEntityAttributes();

    CRM_Core_Resources::singleton()
        // Only the tab's own stylesheet is added here. This hook fires from
        // CRM_Event_Form_ManageEvent::buildQuickForm(), i.e. on *every*
        // event-configuration screen (Info, Location, Fees, Registration,
        // Reminders), so anything added here is injected into all of them; the
        // workflow dialogs' chrome ships with the Angular bundle instead.
        ->addStyleFile('org.civicrm.volunteer', 'css/volunteer_events.css')
        ->addVars('org.civicrm.volunteer', array(
          'hash' => '#/volunteer/manage/' . $project->id . '/details',
          'projectId' => $project->id,
          'entityTable' => $project->entity_table,
          'entityId' => $project->entity_id,
          'entityTitle' => $event['title'],
          // Seeds a *new* project's campaign from the event's own; see
          // ang/volunteer/Project.js. An existing project keeps whatever it was
          // given.
          'entityCampaignId' => $event['campaign_id'] ?? NULL,
          'context' => 'eventTab',
    ));
  }

}
