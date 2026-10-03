<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * Installation-time integrity of the extension's managed option registries.
 *
 * @group headless
 */
class CRM_Volunteer_ManagedRegistryTest extends VolunteerTestAbstract {

  /**
   * The relationship options install with distinct, resolvable values.
   *
   * managed/Registry.mgd.php deliberately does not declare `value`, because
   * `update => unmodified` would rewrite it on the first reconcile after an
   * upgrade and silently repoint every existing
   * civicrm_volunteer_project_contact row at a different relationship type.
   * Nothing may therefore depend on a specific number -- only on the values
   * being distinct, positive, and resolvable by name.
   */
  public function testRelationshipOptionsAreResolvableByName(): void {
    $names = array('volunteer_owner', 'volunteer_manager', 'volunteer_beneficiary');
    $values = array();

    foreach ($names as $name) {
      $value = CRM_Core_PseudoConstant::getKey(
        'CRM_Volunteer_BAO_ProjectContact',
        'relationship_type_id',
        $name
      );
      $this->assertNotEmpty($value, "$name did not resolve to a stored value.");
      $this->assertGreaterThan(0, (int) $value, "$name resolved to a non-positive value.");
      $values[$name] = (int) $value;
    }

    $this->assertCount(
      3,
      array_unique($values),
      'Relationship option values collide: ' . json_encode($values)
    );
  }

  /**
   * The volunteer activity type and statuses exist after installation.
   *
   * These are managed records, and CiviCRM reconciles them before postInstall()
   * runs -- which is why the imperative creator for the statuses could be
   * removed. If that ordering ever changes, assignment creation breaks, so
   * assert it directly rather than relying on it implicitly.
   */
  public function testActivityRegistryIsInstalled(): void {
    $this->assertNotEmpty(
      CRM_Volunteer_BAO_Assignment::getActivityTypeId(),
      'The Volunteer activity type is missing after installation.'
    );
    foreach (array('Available', 'No_show') as $status) {
      $this->assertNotEmpty(
        $this->getOptionValue('activity_status', $status),
        "The $status activity status is missing after installation."
      );
    }
  }

  /**
   * Managed records are registered against this extension, so cleanup applies.
   */
  public function testRegistryRecordsAreManaged(): void {
    $managed = \Civi\Api4\Managed::get(FALSE)
      ->addSelect('name', 'entity_type')
      ->addWhere('module', '=', 'org.civicrm.volunteer')
      ->execute()
      ->column('name');

    $this->assertContains('CiviVolunteer Project Relationship Option Group', $managed);
    $this->assertContains('CiviVolunteer Project Relationship volunteer_owner', $managed);
    $this->assertContains('CiviVolunteer Registry activity_type Volunteer', $managed);
    $this->assertContains('CiviVolunteer Registry activity_status Available', $managed);
  }

}
