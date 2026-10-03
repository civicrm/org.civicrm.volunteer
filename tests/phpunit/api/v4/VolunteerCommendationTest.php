<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

use Civi\Api4\VolunteerCommendation;

/**
 * CRUD and authorization tests for the VolunteerCommendation API4 entity.
 *
 * Like assignments, commendations are Activity records carrying CiviVolunteer
 * custom fields, so this is a BasicEntity over the commendation service.
 *
 * @group headless
 */
class api_v4_VolunteerCommendationTest extends VolunteerTestAbstract {

  public function testTrustedCrudRoundTrip(): void {
    $project = $this->createProject(array(
      'title' => 'API4 commendation project',
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
    ));
    $volunteerId = $this->individualCreate();

    $created = VolunteerCommendation::create(FALSE)
      ->addValue('volunteer_project_id', $project['id'])
      ->addValue('volunteer_contact_id', $volunteerId)
      ->addValue('details', 'Outstanding work')
      ->execute()
      ->single();

    $commendationId = (int) $created['id'];
    $this->assertGreaterThan(0, $commendationId);
    $this->assertSame((int) $project['id'], (int) $created['volunteer_project_id']);
    $this->assertSame($volunteerId, (int) $created['volunteer_contact_id']);
    $this->assertStringContainsString('Outstanding work', (string) $created['details']);

    $read = VolunteerCommendation::get(FALSE)
      ->addWhere('volunteer_project_id', '=', $project['id'])
      ->execute();
    $this->assertCount(1, $read);
    $this->assertSame($commendationId, (int) $read->single()['id']);

    $updated = VolunteerCommendation::update(FALSE)
      ->addWhere('id', '=', $commendationId)
      ->addValue('details', 'Revised citation')
      ->execute()
      ->single();
    $this->assertStringContainsString('Revised citation', (string) $updated['details']);

    VolunteerCommendation::delete(FALSE)
      ->addWhere('id', '=', $commendationId)
      ->execute();
    $this->assertCount(
      0,
      VolunteerCommendation::get(FALSE)
        ->addWhere('volunteer_project_id', '=', $project['id'])
        ->execute()
    );
  }

  /**
   * The custom fields the entity advertises are the ones the service writes.
   */
  public function testAdvertisedFieldsMatchTheStoredCustomFields(): void {
    $fields = VolunteerCommendation::getFields(FALSE)->execute()->column('name');
    foreach (array('volunteer_project_id', 'volunteer_contact_id') as $expected) {
      $this->assertContains($expected, $fields);
    }

    $stored = array_keys(CRM_Volunteer_BAO_Commendation::getCustomFields());
    foreach ($stored as $name) {
      $this->assertContains(
        $name,
        $fields,
        "The commendation custom field $name is not advertised by API4."
      );
    }
  }

  /**
   * A commendation cannot be moved to another project or contact.
   */
  public function testCommendationCannotChangeOwner(): void {
    $projectA = $this->createProject(array('title' => 'Commendation owner A'));
    $projectB = $this->createProject(array('title' => 'Commendation owner B'));
    $volunteerId = $this->individualCreate();

    $created = VolunteerCommendation::create(FALSE)
      ->addValue('volunteer_project_id', $projectA['id'])
      ->addValue('volunteer_contact_id', $volunteerId)
      ->execute()
      ->single();

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('cannot be moved');
    VolunteerCommendation::update(FALSE)
      ->addWhere('id', '=', $created['id'])
      ->addValue('volunteer_project_id', $projectB['id'])
      ->execute();
  }

  /**
   * A read must name the project whose commendations are wanted.
   */
  public function testCheckedReadRequiresAProjectScope(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'edit all volunteer projects',
    );

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('project ID is required');
    VolunteerCommendation::get(TRUE)->execute();
  }

  public function testProjectOwnerCanCommendAndStrangerCannot(): void {
    $project = $this->createProject(array(
      'title' => 'Commendation permissions',
      'project_contacts' => array('volunteer_owner' => array($this->getMockedContactId())),
    ));
    $volunteerId = $this->individualCreate();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'view all contacts',
      'edit contacts',
      'edit own volunteer projects',
    );
    $created = VolunteerCommendation::create(TRUE)
      ->addValue('volunteer_project_id', $project['id'])
      ->addValue('volunteer_contact_id', $volunteerId)
      ->execute()
      ->single();
    $this->assertGreaterThan(0, (int) $created['id']);

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'register to volunteer',
    );
    $this->expectException(Exception::class);
    VolunteerCommendation::get(TRUE)
      ->addWhere('volunteer_project_id', '=', $project['id'])
      ->execute();
  }

  /**
   * A permission-checked delete authorizes each row against its owning
   * project, so the batch lookup must resolve volunteer_project_id alongside
   * the primary key. The inherited batch select returns only the id, which
   * used to fail authorization for every caller -- project editors included.
   */
  public function testProjectEditorCanDeleteCommendations(): void {
    $project = $this->createProject();
    $volunteerId = $this->individualCreate();

    $created = VolunteerCommendation::create(FALSE)
      ->addValue('volunteer_project_id', $project['id'])
      ->addValue('volunteer_contact_id', $volunteerId)
      ->execute()
      ->single();
    $commendationId = (int) $created['id'];

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'access CiviCRM',
      'view all contacts',
      'edit all volunteer projects',
    );

    VolunteerCommendation::delete(TRUE)
      ->addWhere('id', '=', $commendationId)
      ->execute();

    $this->assertCount(
      0,
      VolunteerCommendation::get(FALSE)
        ->addWhere('volunteer_project_id', '=', $project['id'])
        ->execute()
    );
  }

  public function testGenericWritesAreDenied(): void {
    $permissions = VolunteerCommendation::permissions();
    $this->assertSame(CRM_Core_Permission::ALWAYS_DENY_PERMISSION, $permissions['save']);
    $this->assertSame(CRM_Core_Permission::ALWAYS_DENY_PERMISSION, $permissions['replace']);
  }

}
