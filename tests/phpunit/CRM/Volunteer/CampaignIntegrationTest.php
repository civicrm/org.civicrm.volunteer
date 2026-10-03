<?php

require_once __DIR__ . '/../../VolunteerTestAbstract.php';

/**
 * CiviCampaign integration.
 *
 * The campaign paths were previously exercised only incidentally -- the harness
 * happens to give projects a campaign_id nobody asked for -- and never with the
 * component switched on, which is the only state in which API4 will even admit
 * that Activity.campaign_id and Event.campaign_id exist.
 *
 * @group headless
 */
class CRM_Volunteer_CampaignIntegrationTest extends VolunteerTestAbstract {

  /**
   * getManageOverview() returns its projects as a positional list.
   */
  private function overviewRow(array $overview, $projectId): ?array {
    foreach ($overview['projects'] as $row) {
      if ((int) $row['id'] === (int) $projectId) {
        return $row;
      }
    }
    return NULL;
  }

  public function setUp(): void {
    $this->quickCleanup(array('civicrm_volunteer_need', 'civicrm_volunteer_project'));
    parent::setUp();
    CRM_Volunteer_BAO_Project::flushEventProjectCache();
  }

  public function tearDown(): void {
    if (CRM_Core_Component::isEnabled('CiviCampaign')) {
      $this->disableCampaignComponent();
    }
    CRM_Volunteer_BAO_Project::flushEventProjectCache();
    parent::tearDown();
  }

  /**
   * The manage overview resolves a campaign label; the listing renders it.
   */
  public function testOverviewCarriesCampaignLabel(): void {
    $this->enableCampaignComponent();
    $title = 'Winter appeal';
    $campaignId = $this->createCampaign($title);
    $project = $this->createProject(array('campaign_id' => $campaignId, 'is_active' => 1));

    $overview = CRM_Volunteer_BAO_Project::getManageOverview(array('context' => 'edit'), FALSE);
    $row = $this->overviewRow($overview, $project['id']);

    $this->assertNotNull($row, 'The project is missing from the overview.');
    $this->assertSame($title, $row['campaign_label']);
  }

  /**
   * With the component off the label is simply absent, and reading the overview
   * must not reach \Civi\Api4\Campaign at all -- that class does not exist.
   */
  public function testOverviewSkipsCampaignLookupWhenComponentDisabled(): void {
    $this->assertFalse(CRM_Core_Component::isEnabled('CiviCampaign'));
    $project = $this->createProject(array('is_active' => 1));

    $overview = CRM_Volunteer_BAO_Project::getManageOverview(array('context' => 'edit'), FALSE);
    $row = $this->overviewRow($overview, $project['id']);

    $this->assertNotNull($row);
    $this->assertArrayHasKey('campaign_label', $row);
    $this->assertNull($row['campaign_label']);
  }

  /**
   * The overview also names the event a project belongs to, in one batched read.
   */
  public function testOverviewCarriesAssociatedEvent(): void {
    $event = $this->createEvent(array('title' => 'Spring fete'));
    $linked = $this->createProject(array(
      'is_active' => 1,
      'entity_table' => 'civicrm_event',
      'entity_id' => $event['id'],
    ));
    $standalone = $this->createProject(array('is_active' => 1));

    $overview = CRM_Volunteer_BAO_Project::getManageOverview(array('context' => 'edit'), FALSE);

    $linkedRow = $this->overviewRow($overview, $linked['id']);
    $this->assertSame(
      array(
        'entity_table' => 'civicrm_event',
        'entity_id' => (int) $event['id'],
        'title' => 'Spring fete',
      ),
      $linkedRow['associated_entity']
    );
    $this->assertNull($this->overviewRow($overview, $standalone['id'])['associated_entity']);
    $this->assertArrayNotHasKey(
      'entity_attributes',
      $linkedRow,
      'The overview must not fall back to the per-project entity lookup.'
    );
  }

  /**
   * Component availability reaches the client instead of being assumed there.
   */
  public function testSettingsFactoryPublishesComponentFlags(): void {
    $settings = CRM_Volunteer_Page_Angular::loadSettings();
    $this->assertArrayHasKey('isCampaignEnabled', $settings);
    $this->assertArrayHasKey('isEventEnabled', $settings);
    $this->assertFalse($settings['isCampaignEnabled']);
    $this->assertTrue($settings['isEventEnabled']);

    $this->enableCampaignComponent();
    $this->assertTrue(CRM_Volunteer_Page_Angular::loadSettings()['isCampaignEnabled']);
  }

  /**
   * The campaign whitelist/blacklist settings become an API filter.
   */
  public function testCampaignFilterReflectsTheWhitelistSetting(): void {
    Civi::settings()->set('volunteer_general_campaign_filter_type', 'blacklist');
    Civi::settings()->set('volunteer_general_campaign_filter_list', array(1, 2));
    $this->assertSame(
      array('campaign_type_id' => array('NOT IN' => array(1, 2))),
      CRM_Volunteer_Page_Angular::loadSettings()['campaignFilter']
    );

    Civi::settings()->set('volunteer_general_campaign_filter_type', 'whitelist');
    $this->assertSame(
      array('campaign_type_id' => array('IN' => array(1, 2))),
      CRM_Volunteer_Page_Angular::loadSettings()['campaignFilter']
    );

    Civi::settings()->set('volunteer_general_campaign_filter_list', array());
    $this->assertSame(
      array(),
      CRM_Volunteer_Page_Angular::loadSettings()['campaignFilter'],
      'An empty list must not narrow the picker to nothing.'
    );
  }

  /**
   * The public opportunity listing resolves a campaign title, and does not
   * reach for one that cannot exist.
   */
  public function testOpportunitySearchResolvesCampaignTitle(): void {
    $this->enableCampaignComponent();
    $title = 'Doorstep collection';
    $campaignId = $this->createCampaign($title);
    $project = $this->createProject(array('campaign_id' => $campaignId, 'is_active' => 1));
    $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 3,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));

    $results = \Civi\Api4\VolunteerNeed::search(FALSE)
      ->setProject($project['id'])
      ->execute()
      ->getArrayCopy();

    $this->assertNotEmpty($results);
    $need = reset($results);
    $this->assertSame($title, $need['project']['campaign_title']);
  }

  /**
   * Opportunities can be narrowed to one campaign, which is what a "volunteer
   * for this campaign" landing page needs.
   */
  public function testOpportunitySearchFiltersByCampaign(): void {
    $this->enableCampaignComponent();
    $wanted = $this->createCampaign('Wanted campaign');
    $other = $this->createCampaign('Other campaign');
    $publicVisibility = $this->getOptionValue('visibility', 'public');

    $needIds = array();
    foreach (array('wanted' => $wanted, 'other' => $other) as $key => $campaignId) {
      $project = $this->createProject(array('campaign_id' => $campaignId, 'is_active' => 1));
      $need = $this->createNeed(array(
        'project_id' => $project['id'],
        'start_time' => '2026-12-17 16:00:00',
        'duration' => 60,
        'is_flexible' => 0,
        'quantity' => 3,
        'visibility_id' => $publicVisibility,
        'is_active' => 1,
      ));
      $needIds[$key] = (int) $need['id'];
    }

    $filtered = \Civi\Api4\VolunteerNeed::search(FALSE)
      ->setCampaignId($wanted)
      ->execute()
      ->getArrayCopy();
    $returned = array_map('intval', array_column($filtered, 'id'));

    $this->assertContains($needIds['wanted'], $returned);
    $this->assertNotContains($needIds['other'], $returned);
  }

  /**
   * With the component off the filter is ignored rather than silently returning
   * nothing.
   */
  public function testOpportunityCampaignFilterIsIgnoredWhenComponentDisabled(): void {
    $this->assertFalse(CRM_Core_Component::isEnabled('CiviCampaign'));
    $project = $this->createProject(array('is_active' => 1));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => '2026-12-17 16:00:00',
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 3,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));

    $results = \Civi\Api4\VolunteerNeed::search(FALSE)
      ->setCampaignId(999999)
      ->execute()
      ->getArrayCopy();

    $this->assertContains(
      (int) $need['id'],
      array_map('intval', array_column($results, 'id'))
    );
  }

  /**
   * The volunteer report gains a campaign and an associated-event dimension,
   * each present only while its component is.
   */
  public function testReportDimensionsFollowComponentAvailability(): void {
    $columns = new ReflectionProperty('CRM_Report_Form', '_columns');
    $columns->setAccessible(TRUE);

    $report = new CRM_Volunteer_Form_VolunteerReport();
    $withoutCampaign = $columns->getValue($report);
    $this->assertArrayNotHasKey('civicrm_campaign', $withoutCampaign);
    $this->assertArrayHasKey('civicrm_event', $withoutCampaign);
    foreach (array('fields', 'filters', 'order_bys', 'group_bys') as $section) {
      $this->assertArrayHasKey(
        'event_title',
        $withoutCampaign['civicrm_event'][$section],
        "The event dimension is missing from $section."
      );
    }

    $this->enableCampaignComponent();
    $withCampaign = $columns->getValue(new CRM_Volunteer_Form_VolunteerReport());
    $this->assertArrayHasKey('civicrm_campaign', $withCampaign);
    foreach (array('fields', 'filters', 'order_bys', 'group_bys') as $section) {
      $this->assertArrayHasKey(
        'campaign_title',
        $withCampaign['civicrm_campaign'][$section],
        "The campaign dimension is missing from $section."
      );
    }
  }

}
