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
    $project->entity_table = CRM_Event_DAO_Event::$_tableName;

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

    $project = current(CRM_Volunteer_BAO_Project::retrieve(array(
          'entity_id' => $eventId,
          'entity_table' => CRM_Event_DAO_Event::$_tableName,
    )));
    if (!$project) {
      $project = self::initializeProject($eventId);
    }

    CRM_Volunteer_Angular::load('/volunteer/manage/' . $project->id);

    $event = $project->getEntityAttributes();
    $entityTitle = $event['title'];

    CRM_UF_Page_ProfileEditor::registerProfileScripts();
    extract(CRM_Volunteer_Form_IncludeProfile::getProfileSelectorTypes());
    CRM_UF_Page_ProfileEditor::registerSchemas(CRM_Utils_Array::collect('entity_type', $profileEntities));
    CRM_Core_Resources::singleton()
        ->addStyleFile('org.civicrm.volunteer', 'css/volunteer_app.css')
        ->addStyleFile('org.civicrm.volunteer', 'css/volunteer_events.css')
        ->addVars('org.civicrm.volunteer', array(
          'hash' => '#/volunteer/manage/' . $project->id,
          'projectId' => $project->id,
          'entityTable' => $project->entity_table,
          'entityId' => $project->entity_id,
          'entityTitle' => $entityTitle,
          'context' => 'eventTab',
          'dataGroupType' => CRM_Core_BAO_UFGroup::encodeGroupType($allowCoreTypes, $allowSubTypes, ';;'),
          'dataEntities' => json_encode($profileEntities),
          'dataDefault' => FALSE,
          'dataUsedFor' => json_encode($usedFor),

    ));
  }

}
