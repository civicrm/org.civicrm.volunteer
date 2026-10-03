<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

/**
 * Volunteer commendations are activities, and belong to the project's campaign
 * for the same reason assignments do.
 *
 * @group headless
 */
class CRM_Volunteer_BAO_CommendationTest extends VolunteerTestAbstract {

  /**
   * Read the stored campaign through the DAO.
   *
   * Activity.campaign_id carries 'component' => 'CiviCampaign', so API4 omits
   * the field entirely on a site where the component is switched off -- which
   * the test database is. CRM_Volunteer_BAO_ProjectTest reads it the same way,
   * via findById(), for the same reason.
   */
  private function storedCampaignId(int $activityId) {
    return CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $activityId, 'campaign_id');
  }

  public function testCommendationInheritsProjectCampaign(): void {
    $campaign = CRM_Core_DAO::createTestObject('CRM_Campaign_BAO_Campaign');
    $this->assertObjectHasProperty('id', $campaign, 'Failed to prepopulate Campaign');
    // createTestObject() bypasses the API, so the cached option list backing
    // Activity.campaign_id validation is stale. @see ProjectTest.

    $project = $this->createProject(array('campaign_id' => $campaign->id));
    $contactId = $this->individualCreate();

    $commendation = CRM_Volunteer_BAO_Commendation::create(array(
      'check_permissions' => FALSE,
      'cid' => $contactId,
      'vid' => $project['id'],
      'details' => 'Exceptional.',
    ));

    $this->assertEquals(
      $campaign->id,
      $this->storedCampaignId((int) $commendation['id']),
      'A commendation did not inherit its project campaign, so it is invisible to campaign reporting.'
    );
  }

  /**
   * Whatever the project's campaign is -- including none -- the commendation
   * must agree with it, exactly as an assignment does.
   */
  public function testCommendationTracksProjectCampaign(): void {
    $project = $this->createProject();
    $projectCampaignId = CRM_Core_DAO::getFieldValue(
      'CRM_Volunteer_DAO_Project',
      $project['id'],
      'campaign_id'
    );
    $contactId = $this->individualCreate();

    $commendation = CRM_Volunteer_BAO_Commendation::create(array(
      'check_permissions' => FALSE,
      'cid' => $contactId,
      'vid' => $project['id'],
    ));

    $this->assertEquals(
      (string) $projectCampaignId,
      (string) $this->storedCampaignId((int) $commendation['id'])
    );
  }

}
