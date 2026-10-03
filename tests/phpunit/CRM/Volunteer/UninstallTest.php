<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * Tests CRM_Volunteer_Upgrader::uninstall().
 *
 * Deliberately its own class with a single test: uninstall() deletes custom
 * groups, which drops their value tables -- DDL, which commits the per-test
 * transaction. Anything this test created would leak into the disposable
 * database, so the test also reinstalls the canonical objects through the
 * same install helpers the installer uses, and the phase-end full-suite run
 * is the proof that nothing poisoned later classes.
 *
 * @group headless
 */
class CRM_Volunteer_UninstallTest extends VolunteerTestAbstract {

  public function testUninstallRemovesExtensionArtifactsAndReinstalls(): void {
    // Preconditions: everything uninstall() claims to own exists.
    $this->assertNotNull($this->customGroupId('CiviVolunteer'));
    $this->assertNotNull($this->customGroupId('volunteer_commendation'));
    foreach (array('volunteer_project_relationship', 'msg_tpl_workflow_volunteer', 'volunteer_role', 'skill_level') as $optionGroupName) {
      $this->assertNotNull($this->optionGroupId($optionGroupName), "$optionGroupName should exist after installation.");
    }
    $workflowId = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('id')
      ->addWhere('option_group_id:name', '=', 'msg_tpl_workflow_volunteer')
      ->addWhere('name', '=', 'volunteer_registration')
      ->execute()
      ->first()['id'] ?? NULL;
    $this->assertNotNull($workflowId, 'The volunteer_registration workflow should exist after installation.');

    $upgrader = $this->createUpgrader();
    try {
      $upgrader->uninstall();

      // Every owned artifact is gone.
      $this->assertNull($this->customGroupId('CiviVolunteer'), 'The CiviVolunteer custom group must be removed.');
      $this->assertNull($this->customGroupId('volunteer_commendation'), 'The commendation custom group must be removed.');
      foreach (array('volunteer_project_relationship', 'msg_tpl_workflow_volunteer', 'volunteer_role', 'skill_level') as $optionGroupName) {
        $this->assertNull($this->optionGroupId($optionGroupName), "$optionGroupName must be removed.");
      }
      $this->assertCount(
        0,
        \Civi\Api4\MessageTemplate::get(FALSE)
          ->addWhere('workflow_id', '=', (int) $workflowId)
          ->execute(),
        'The registration message template must be removed with its workflow.'
      );
    }
    finally {
      // Always restore, even when an assertion above fails, because uninstall
      // issues DDL and therefore escapes the per-test transaction.
      $this->restoreCanonicalArtifacts($upgrader);
    }

    // Fail here, not in some later class, if the physical storage went
    // missing.
    foreach (array('CiviVolunteer', 'volunteer_commendation') as $groupName) {
      $table = CRM_Core_DAO::singleValueQuery(
        'SELECT table_name FROM civicrm_custom_group WHERE name = %1',
        array(1 => array($groupName, 'String'))
      );
      $this->assertNotNull($table, "The $groupName custom group must be restored.");
      $this->assertTrue(
        CRM_Core_DAO::checkTableExists((string) $table),
        "The $groupName value table ($table) must physically exist after the restore."
      );
    }


    // The restore must land on the same contracts the fresh-install test
    // asserts: one of each object, nothing duplicated.
    $this->assertNotNull($this->customGroupId('CiviVolunteer'), 'The CiviVolunteer custom group must be restored.');
    $this->assertCount(
      1,
      \Civi\Api4\CustomGroup::get(FALSE)->addWhere('name', '=', 'CiviVolunteer')->execute(),
      'The restore must not duplicate the custom group.'
    );
    $this->assertNotNull($this->optionGroupId('volunteer_project_relationship'));
    $relationshipValues = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('name')
      ->addWhere('option_group_id:name', '=', 'volunteer_project_relationship')
      ->execute()
      ->column('name');
    foreach (array('volunteer_owner', 'volunteer_manager', 'volunteer_beneficiary') as $relationship) {
      $this->assertContains($relationship, $relationshipValues, "$relationship must be restored.");
    }
    $restoredWorkflow = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('id')
      ->addWhere('option_group_id:name', '=', 'msg_tpl_workflow_volunteer')
      ->addWhere('name', '=', 'volunteer_registration')
      ->execute();
    $this->assertCount(1, $restoredWorkflow, 'The registration workflow must be restored exactly once.');
    $this->assertCount(
      1,
      \Civi\Api4\MessageTemplate::get(FALSE)
        ->addWhere('workflow_id', '=', (int) $restoredWorkflow->single()['id'])
        ->execute(),
      'The registration template must be restored exactly once.'
    );
  }

  private function customGroupId(string $name): ?int {
    $group = \Civi\Api4\CustomGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', $name)
      ->execute()
      ->first();
    return isset($group['id']) ? (int) $group['id'] : NULL;
  }

  private function optionGroupId(string $name): ?int {
    $group = \Civi\Api4\OptionGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', $name)
      ->execute()
      ->first();
    return isset($group['id']) ? (int) $group['id'] : NULL;
  }

  /**
   * Rebuild everything uninstall() removes and invalidate BAO schema caches.
   */
  private function restoreCanonicalArtifacts(CRM_Volunteer_Upgrader $upgrader): void {
    $upgrader->schemaUpgrade20();
    $upgrader->postInstall();
    \Civi\Api4\Managed::reconcile(FALSE)->execute();
    Civi::rebuild(array('metadata' => TRUE))->execute();

    foreach (array('CRM_Volunteer_BAO_Assignment', 'CRM_Volunteer_BAO_Commendation') as $bao) {
      foreach (array('customGroup', 'customFields') as $cache) {
        $property = new ReflectionProperty($bao, $cache);
        $property->setAccessible(TRUE);
        $property->setValue(NULL, array());
      }
    }
  }

  private function createUpgrader(): CRM_Volunteer_Upgrader {
    $upgrader = new CRM_Volunteer_Upgrader();
    $upgrader->init(array(
      'key' => 'org.civicrm.volunteer',
      'files' => 'CRM_Volunteer_Upgrader',
    ));
    $context = new CRM_Queue_TaskContext();
    $context->log = CRM_Core_Error::createDebugLogger();
    $ctxProperty = new ReflectionProperty('CRM_Extension_Upgrader_Base', 'ctx');
    $ctxProperty->setAccessible(TRUE);
    $ctxProperty->setValue($upgrader, $context);
    return $upgrader;
  }

}
