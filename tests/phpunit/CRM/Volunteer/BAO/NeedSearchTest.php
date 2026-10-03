<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

/**
 * Tests for the public opportunity search.
 *
 * @group headless
 */
class CRM_Volunteer_BAO_NeedSearchTest extends VolunteerTestAbstract {

  public function setUp(): void {
    $this->quickCleanup(array(
      'civicrm_volunteer_project_contact',
      'civicrm_volunteer_need',
      'civicrm_volunteer_project',
    ));
    parent::setUp();
  }

  /**
   * Build one active project with a beneficiary and one public dated need.
   *
   * @return array
   *   [projectId, needId, beneficiaryContactId, beneficiaryDisplayName]
   */
  private function createSearchableProject(): array {
    $beneficiaryId = $this->individualCreate(array(
      'first_name' => 'Bea',
      'last_name' => 'Neficiary',
    ));
    $project = $this->createProject(array(
      'title' => 'Searchable project',
      'is_active' => 1,
      'project_contacts' => array(
        'volunteer_owner' => array($this->getMockedContactId()),
        'volunteer_beneficiary' => array($beneficiaryId),
      ),
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('+30 days')),
      'duration' => 120,
      'is_flexible' => 0,
      'quantity' => 4,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));
    $displayName = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('display_name')
      ->addWhere('id', '=', $beneficiaryId)
      ->execute()
      ->first()['display_name'];

    return array((int) $project['id'], (int) $need['id'], (int) $beneficiaryId, $displayName);
  }

  /**
   * Build a project whose public need can be located through its LocBlock.
   *
   * @return array
   *   [project row, need ID]
   */
  private function createLocatedProject(string $title, array $address): array {
    $project = $this->createProject(array(
      'title' => $title,
      'is_active' => 1,
      'location' => array('address' => $address),
    ));
    $need = $this->createNeed(array(
      'project_id' => $project['id'],
      'start_time' => date('Y-m-d H:i:s', strtotime('+45 days')),
      'duration' => 60,
      'is_flexible' => 0,
      'quantity' => 2,
      'visibility_id' => $this->getOptionValue('visibility', 'public'),
      'is_active' => 1,
    ));
    return array($project, (int) $need['id']);
  }

  /**
   * Return country and State/Province IDs used by location-search fixtures.
   */
  private function getLocationFixtureIds(): array {
    $us = \Civi\Api4\Country::get(FALSE)
      ->addSelect('id')
      ->addWhere('iso_code', '=', 'US')
      ->execute()
      ->single()['id'];
    $canada = \Civi\Api4\Country::get(FALSE)
      ->addSelect('id')
      ->addWhere('iso_code', '=', 'CA')
      ->execute()
      ->single()['id'];
    $colorado = \Civi\Api4\StateProvince::get(FALSE)
      ->addSelect('id')
      ->addWhere('country_id', '=', $us)
      ->addWhere('abbreviation', '=', 'CO')
      ->execute()
      ->single()['id'];
    $ontario = \Civi\Api4\StateProvince::get(FALSE)
      ->addSelect('id')
      ->addWhere('country_id', '=', $canada)
      ->addWhere('abbreviation', '=', 'ON')
      ->execute()
      ->single()['id'];

    return array_map('intval', array($us, $canada, $colorado, $ontario));
  }

  private function locationSearchIds(array $location): array {
    return array_map('intval', array_keys(
      CRM_Volunteer_BAO_NeedSearch::doSearch(array('proximity' => $location))
    ));
  }

  private function setProjectCoordinates(array $project, float $latitude, float $longitude): void {
    $location = \Civi\Api4\LocBlock::get(FALSE)
      ->addSelect('address_id')
      ->addWhere('id', '=', $project['loc_block_id'])
      ->execute()
      ->single();
    \Civi\Api4\Address::update(FALSE)
      ->addWhere('id', '=', $location['address_id'])
      ->addValue('geo_code_1', $latitude)
      ->addValue('geo_code_2', $longitude)
      ->addValue('manual_geo_code', TRUE)
      ->execute();
  }

  /**
   * Regression: a public searcher must still see project beneficiaries.
   *
   * The beneficiary names come from a chained VolunteerProjectContact.get that
   * carried check_permissions => FALSE on the *child*. APIv3's ChainSubscriber
   * overwrites each child's flag with the parent's, so that FALSE was discarded,
   * the extension's public branch then forced relationship_type_id to the
   * string 'volunteer_beneficiary' after pseudoconstant translation had already
   * run, and the comparison against an int column matched nothing -- so
   * beneficiaries silently vanished from the public search.
   */
  public function testPublicSearchReturnsProjectBeneficiaries(): void {
    [$projectId, $needId, $beneficiaryId, $displayName] = $this->createSearchableProject();

    CRM_Core_Config::singleton()->userPermissionClass->permissions = array(
      'register to volunteer',
    );

    $results = CRM_Volunteer_BAO_NeedSearch::doSearch(array('project' => $projectId));

    $this->assertArrayHasKey($needId, $results, 'The public need was not returned by the search.');
    $project = $results[$needId]['project'];

    $this->assertNotEmpty(
      $project['beneficiaries'] ?? array(),
      'Project beneficiaries were dropped from the public search result.'
    );
    $ids = array_map('intval', array_column($project['beneficiaries'], 'id'));
    $this->assertContains($beneficiaryId, $ids);
    $this->assertSame(
      array($displayName),
      array_column($project['beneficiaries'], 'display_name'),
      'Beneficiary display names were not resolved.'
    );
  }

  /**
   * The end of the search window includes the whole final day.
   */
  public function testSearchEndDateCoversTheEntireDay(): void {
    [$projectId, $needId] = $this->createSearchableProject();

    $startTime = CRM_Core_DAO::singleValueQuery(
      'SELECT start_time FROM civicrm_volunteer_need WHERE id = %1',
      array(1 => array($needId, 'Integer'))
    );
    $sameDay = date('Y-m-d', strtotime($startTime));

    $results = CRM_Volunteer_BAO_NeedSearch::doSearch(array(
      'project' => $projectId,
      'date_start' => $sameDay,
      'date_end' => $sameDay,
    ));

    $this->assertArrayHasKey(
      $needId,
      $results,
      'A need starting later in the day was excluded by the end-of-day boundary.'
    );
  }

  /**
   * Address fields remain useful without a radius and compose as SQL ANDs.
   */
  public function testOrdinaryLocationFilters(): void {
    [$us, $canada, $colorado, $ontario] = $this->getLocationFixtureIds();
    [, $denverNeed] = $this->createLocatedProject('Denver project', array(
      'street_address' => '123 Main Street',
      'city' => 'Denver',
      'postal_code' => '80202-1234',
      'state_province_id' => $colorado,
      'country_id' => $us,
    ));
    [, $boulderNeed] = $this->createLocatedProject('Boulder project', array(
      'street_address' => '900 Pearl Street',
      'city' => 'Boulder',
      'postal_code' => '80302',
      'state_province_id' => $colorado,
      'country_id' => $us,
    ));
    [, $torontoNeed] = $this->createLocatedProject('Toronto project', array(
      'street_address' => '100 Queen Street West',
      'city' => 'Toronto',
      'postal_code' => 'M5H 2N2',
      'state_province_id' => $ontario,
      'country_id' => $canada,
    ));

    $this->assertSame(array($denverNeed), $this->locationSearchIds(array('city' => 'dEnVeR')));
    $this->assertSame(array($denverNeed), $this->locationSearchIds(array('postal_code' => '802')));
    $this->assertSame(array($denverNeed), $this->locationSearchIds(array('street_address' => 'Main')));

    $coloradoIds = $this->locationSearchIds(array('state_province_id' => $colorado));
    $this->assertContains($denverNeed, $coloradoIds);
    $this->assertContains($boulderNeed, $coloradoIds);
    $this->assertNotContains($torontoNeed, $coloradoIds);

    $usIds = $this->locationSearchIds(array('country' => $us));
    $this->assertContains($denverNeed, $usIds);
    $this->assertContains($boulderNeed, $usIds);
    $this->assertNotContains($torontoNeed, $usIds);
    $this->assertSame($usIds, $this->locationSearchIds(array('country' => 'US')),
      'Legacy bookmarked ISO country values should resolve to the same numeric ID.');

    $this->assertSame(array(), $this->locationSearchIds(array(
      'city' => 'Denver',
      'postal_code' => '803',
    )), 'Populated location fields must be AND-ed rather than OR-ed.');
    $this->assertSame(array(), $this->locationSearchIds(array('city' => 'Den')),
      'City matching is case-insensitive but does not perform substring matching.');
  }

  /**
   * A stale distance in a bookmark cannot disable ordinary filtering.
   */
  public function testUnavailableProximityFallsBackToOrdinaryLocationFilters(): void {
    if (CRM_Volunteer_BAO_VolunteerUtil::isProximitySearchAvailable()) {
      $this->markTestSkipped('This fixture covers the no-Geocoder branch.');
    }
    [$us, , $colorado] = $this->getLocationFixtureIds();
    [, $denverNeed] = $this->createLocatedProject('Fallback Denver project', array(
      'city' => 'Denver',
      'postal_code' => '80202',
      'state_province_id' => $colorado,
      'country_id' => $us,
    ));
    [, $boulderNeed] = $this->createLocatedProject('Fallback Boulder project', array(
      'city' => 'Boulder',
      'postal_code' => '80302',
      'state_province_id' => $colorado,
      'country_id' => $us,
    ));

    $found = $this->locationSearchIds(array(
      'city' => 'Denver',
      'radius' => 25,
      'unit' => 'miles',
    ));
    $this->assertContains($denverNeed, $found);
    $this->assertNotContains($boulderNeed, $found);
  }

  /**
   * Core's proximity SQL observes both kilometre and mile conversions.
   */
  public function testProximityLocationFilterUsesStoredCoordinates(): void {
    if (!CRM_Volunteer_BAO_VolunteerUtil::isProximitySearchAvailable()) {
      $this->markTestSkipped('The optional Geocoder integration is not installed in this test environment.');
    }
    [$us, , $colorado] = $this->getLocationFixtureIds();
    [$denverProject, $denverNeed] = $this->createLocatedProject('Proximity Denver project', array(
      'city' => 'Denver',
      'state_province_id' => $colorado,
      'country_id' => $us,
    ));
    [$boulderProject, $boulderNeed] = $this->createLocatedProject('Proximity Boulder project', array(
      'city' => 'Boulder',
      'state_province_id' => $colorado,
      'country_id' => $us,
    ));
    $this->setProjectCoordinates($denverProject, 39.7392364, -104.9848620);
    $this->setProjectCoordinates($boulderProject, 40.0149856, -105.2705456);

    $center = array('lat' => 39.7392364, 'lon' => -104.9848620, 'radius' => 30);
    $kilometres = $this->locationSearchIds($center + array('unit' => 'km'));
    $this->assertContains($denverNeed, $kilometres);
    $this->assertNotContains($boulderNeed, $kilometres);

    $miles = $this->locationSearchIds($center + array('unit' => 'miles'));
    $this->assertContains($denverNeed, $miles);
    $this->assertContains($boulderNeed, $miles);
  }

}
