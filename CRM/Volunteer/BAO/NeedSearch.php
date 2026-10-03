<?php

class CRM_Volunteer_BAO_NeedSearch {

  /**
   * @var array
   *   Holds project data for the Needs matched by the search. Keyed by project ID.
   */
  private $projects = array();

  /**
   * @var array
   *   See  getDefaultSearchParams() for format.
   */
  private $searchParams = array();

  /**
   * @var array
   *   An array of needs. The results of the search, which will ultimately be returned.
   */
  private $searchResults = array();

  /**
   * @param array $userSearchParams
   *   See setSearchParams();
   */
  public function __construct ($userSearchParams) {
    $this->searchParams = $this->getDefaultSearchParams();
    $this->setSearchParams($userSearchParams);
  }

  /**
   * Convenience static method for searching without instantiating the class.
   *
   * Invoked from the API layer.
   *
   * @param array $userSearchParams
   *   See setSearchParams();
   * @return array $this->searchResults
   */
  public static function doSearch ($userSearchParams) {
    $searcher = new self($userSearchParams);
    return $searcher->search();
  }

  /**
   * @return array
   *   Used as the starting point for $this->searchParams.
   */
  private function getDefaultSearchParams() {
    return array(
      'project' => array(
        'is_active' => 1,
      ),
      'need' => array(
        'role_id' => array(),
        'time_filter' => 'all',
      ),
    );
  }

  /**
   * Performs the search.
   *
   * Stashes the results in $this->searchResults.
   *
   * @return array $this->searchResults
   */
  public function search() {
    $projects = CRM_Volunteer_BAO_Project::retrieve($this->searchParams['project']);
    foreach ($projects as $project) {
      $results = array();

      $flexibleNeedId = $project->flexible_need_id;
      if ($flexibleNeedId) {
        $flexibleNeed = \Civi\Api4\VolunteerNeed::get(FALSE)
          ->addWhere('id', '=', $flexibleNeedId)
          ->execute()
          ->first();
        if (!empty($flexibleNeed['is_active'])
          && ($flexibleNeed['visibility_id'] ?? NULL) === CRM_Core_PseudoConstant::getKey('CRM_Volunteer_BAO_Need', 'visibility_id', 'public')) {
          $needId = $flexibleNeed['id'];
          $results[$needId] = $flexibleNeed;
        }
      }

      $openNeeds = $project->open_needs;
      foreach ($openNeeds as $key => $need) {
        if ($this->needFitsSearchCriteria($need)) {
          $results[$key] = $need;
        }
      }

      if (!empty($results)) {
        $this->projects[$project->id] = array();
      }

      $this->searchResults += $results;
    }

    $this->getSearchResultsProjectData();
    uasort($this->searchResults, array($this, "usortDateAscending"));
    return $this->searchResults;
  }

  /**
   * Returns TRUE if the need matches the dates in the search criteria, else FALSE.
   *
   * Assumptions:
   *   - Need start_time is never empty. (Only in exceptional cases should this
   *     assumption be false for non-flexible needs. Flexible needs are excluded
   *     from $project->open_needs.)
   *
   * @param array $need
   * @return boolean
   */
  private function needFitsDateCriteria(array $need) {
    $needStartTime = strtotime(($need['start_time'] ?? ''));
    $needEndTime = strtotime(($need['end_time'] ?? ''));

    // There are no date-related search criteria, so we're done here.
    if ($this->searchParams['need']['date_start'] === FALSE && $this->searchParams['need']['date_end'] === FALSE) {
      return TRUE;
    }

    // The search window has no end time. We need to verify only that the need
    // has dates after the start time.
    if ($this->searchParams['need']['date_end'] === FALSE) {
      return $needStartTime >= $this->searchParams['need']['date_start'] || $needEndTime >= $this->searchParams['need']['date_start'];
    }

    // The search window has no start time. We need to verify only that the need
    // starts before the end of the window.
    if ($this->searchParams['need']['date_start'] === FALSE) {
      return $needStartTime <= $this->searchParams['need']['date_end'];
    }

    // The need does not have fuzzy dates, and both ends of the search
    // window have been specified. We need to verify only that the need
    // starts in the search window.
    if ($needEndTime === FALSE) {
      return $needStartTime >= $this->searchParams['need']['date_start'] && $needStartTime <= $this->searchParams['need']['date_end'];
    }

    // The need has fuzzy dates, and both endpoints of the search window were
    // specified:
    return
      // Does the need start in the provided window...
      ($needStartTime >= $this->searchParams['need']['date_start'] && $needStartTime <= $this->searchParams['need']['date_end'])
      // or does the need end in the provided window...
      || ($needEndTime >= $this->searchParams['need']['date_start'] && $needEndTime <= $this->searchParams['need']['date_end'])
      // or are the endpoints of the need outside the provided window?
      || ($needStartTime <= $this->searchParams['need']['date_start'] && $needEndTime >= $this->searchParams['need']['date_end']);
  }

  /**
   * @param array $need
   * @return boolean
   */
  private function needFitsSearchCriteria(array $need) {
    return
      $this->needFitsDateCriteria($need)
      && $this->needFitsTimeCriteria($need)
      && (
        // Either no role was specified in the search...
        empty($this->searchParams['need']['role_id'])
        // or the need role is in the list of searched-by roles.
        || in_array($need['role_id'], $this->searchParams['need']['role_id'])
      );
  }

  /**
   * Apply the public browser's mutually-exclusive schedule shortcuts.
   *
   * Need timestamps are stored as site-local wall time. CiviCRM initializes
   * PHP with the CMS timezone, and using it explicitly here keeps weekday and
   * 5:00 PM boundary decisions stable on hosts whose process default differs.
   */
  private function needFitsTimeCriteria(array $need) {
    $filter = $this->searchParams['need']['time_filter'];
    if ($filter === 'all') {
      return TRUE;
    }
    if ($filter === 'no_fixed_time') {
      return !empty($need['end_time'])
        || (empty($need['end_time']) && empty($need['duration']));
    }
    if (empty($need['start_time'])) {
      return FALSE;
    }
    try {
      $start = new DateTimeImmutable(
        $need['start_time'],
        new DateTimeZone(date_default_timezone_get())
      );
    }
    catch (Throwable $e) {
      return FALSE;
    }
    if ($filter === 'weekends') {
      return (int) $start->format('N') >= 6;
    }
    if ($filter === 'evenings') {
      return (int) $start->format('H') >= 17;
    }
    return TRUE;
  }

  /**
   * @param array $userSearchParams
   *   Supported parameters:
   *     - beneficiary: mixed - an int-like string, a comma-separated list
   *         thereof, or an array representing one or more contact IDs
   *     - project: int-like string representing project ID
   *     - proximity: array - address fields plus optional radius/unit
   *     - role_id: mixed - an int-like string, a comma-separated list thereof, or
   *         an array representing one or more role IDs
   *     - date_start: See setSearchDateParams()
   *     - date_end: See setSearchDateParams()
   */
  private function setSearchParams($userSearchParams) {
    $this->setSearchDateParams($userSearchParams);

    $projectId = $userSearchParams['project'] ?? NULL;
    if (CRM_Utils_Type::validate($projectId, 'Positive', FALSE)) {
      $this->searchParams['project']['id'] = $projectId;
    }

    $proximity = $userSearchParams['proximity'] ?? NULL;
    if (is_array($proximity)) {
      $this->searchParams['project']['proximity'] = $proximity;
    }

    $beneficiary = $userSearchParams['beneficiary'] ?? NULL;
    if ($beneficiary) {
      if (!array_key_exists('project_contacts', $this->searchParams['project'])) {
        $this->searchParams['project']['project_contacts'] = array();
      }
      $beneficiary = is_array($beneficiary) ? $beneficiary : explode(',', $beneficiary);
      $this->searchParams['project']['project_contacts']['volunteer_beneficiary'] = $beneficiary;
    }

    // The listing already resolves a campaign title for every project it
    // returns, but there was no way to search on one -- so a "volunteer for
    // this campaign" page could not be built.
    // One campaign, not a list: these params are handed to
    // CRM_Volunteer_BAO_Project::retrieve(), whose generic field filter emits a
    // scalar equality per DAO field. An array there would build invalid SQL.
    $campaign = $userSearchParams['campaign_id'] ?? NULL;
    if ($campaign && CRM_Core_Component::isEnabled('CiviCampaign')
      && CRM_Utils_Type::validate($campaign, 'Positive', FALSE)) {
      $this->searchParams['project']['campaign_id'] = (int) $campaign;
    }

    $role = $userSearchParams['role_id'] ?? NULL;
    if ($role) {
      $roles = is_array($role) ? $role : explode(',', $role);
      $this->searchParams['need']['role_id'] = array_values(array_unique(array_filter(array_map('intval', $roles))));
    }

    $timeFilter = (string) ($userSearchParams['time_filter'] ?? 'all');
    if (in_array($timeFilter, array('all', 'weekends', 'evenings', 'no_fixed_time'), TRUE)) {
      $this->searchParams['need']['time_filter'] = $timeFilter;
    }
  }

  /**
   * Sets date_start and date_need in $this->searchParams to a timestamp or to
   * boolean FALSE if invalid values were supplied.
   *
   * @param array $userSearchParams
   *   Supported parameters:
   *     - date_start: date
   *     - date_end: date
   */
  private function setSearchDateParams($userSearchParams) {
    $this->searchParams['need']['date_start'] = strtotime(($userSearchParams['date_start'] ?? ''));
    $dateEndInput = trim((string) ($userSearchParams['date_end'] ?? ''));
    $dateEnd = FALSE;
    if ($dateEndInput !== '') {
      try {
        // Set a calendar end-of-day in the configured timezone. Adding a fixed
        // number of seconds is incorrect on daylight-saving transition days.
        $dateEnd = (new DateTimeImmutable($dateEndInput))
          ->setTime(23, 59, 59)
          ->getTimestamp();
      }
      catch (Exception $e) {
        $dateEnd = FALSE;
      }
    }
    $this->searchParams['need']['date_end'] = $dateEnd;
  }

  /**
   * Adds 'project' key to each need in $this->searchResults, containing data
   * related to the project, campaign, location, and project contacts.
   */
  private function getSearchResultsProjectData() {
    $beneficiaryIds = array();
    foreach ($this->projects as $id => &$project) {
      $api = \Civi\Api4\VolunteerProject::get(FALSE)
        ->addSelect('id', 'title', 'description', 'campaign_id', 'loc_block_id')
        ->addWhere('id', '=', $id)
        ->execute()
        ->single();

      $project['description'] = CRM_Utils_String::purifyHTML($api['description'] ?? '');
      $project['id'] = $api['id'];
      $project['title'] = $api['title'];

      // Civi\Api4\Campaign lives in the civi_campaign extension. Without this
      // guard a project holding a campaign_id fatals the public opportunity
      // listing on any site where CiviCampaign is switched off.
      $campaignReadable = !empty($api['campaign_id'])
        && CRM_Core_Component::isEnabled('CiviCampaign');
      $campaign = !$campaignReadable ? NULL : \Civi\Api4\Campaign::get(FALSE)
        ->addSelect('title')
        ->addWhere('id', '=', $api['campaign_id'])
        ->execute()
        ->first();
      $project['campaign_title'] = $campaign['title'] ?? NULL;

      $location = empty($api['loc_block_id']) ? NULL : \Civi\Api4\LocBlock::get(FALSE)
        ->addSelect(
          'address_id', 'address_id.city', 'address_id.country_id',
          'address_id.postal_code', 'address_id.state_province_id',
          'address_id.street_address'
        )
        ->addWhere('id', '=', $api['loc_block_id'])
        ->execute()
        ->first();
      if (empty($location['address_id'])) {
        $project['location'] = array(
          'city' => NULL,
          'country' => NULL,
          'postal_code' => NULL,
          'state_province' => NULL,
          'street_address' => NULL,
        );
      } else {
        $countryId = $location['address_id.country_id'] ?? NULL;
        $country = $countryId ? CRM_Core_PseudoConstant::country($countryId) : NULL;

        $stateProvince = NULL;
        if (isset($location['address_id.state_province_id'])) {
          $stateProvinceId = $location['address_id.state_province_id'];
          $stateProvince = CRM_Core_PseudoConstant::stateProvince($stateProvinceId);
        }
        

        $project['location'] = array(
          'city' => $location['address_id.city'] ?? NULL,
          'country' => $country,
          'postal_code' => $location['address_id.postal_code'] ?? NULL,
          'state_province' => $stateProvince,
          'street_address' => $location['address_id.street_address'] ?? NULL,
        );
      }

      $projectContacts = \Civi\Api4\VolunteerProjectContact::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('project_id', '=', $id)
        ->addWhere('relationship_type_id:name', '=', 'volunteer_beneficiary')
        ->execute();
      foreach ($projectContacts as $projectContact) {
        if (!array_key_exists('beneficiaries', $project)) {
          $project['beneficiaries'] = array();
        }

        $contactId = (int) $projectContact['contact_id'];
        $beneficiaryIds[$contactId] = $contactId;
        $project['beneficiaries'][] = array('id' => $contactId, 'display_name' => '');
      }
    }
    unset($project);

    // The public result needs only beneficiary display names. Fetch them in a
    // single internal query after project-contact authorization has constrained
    // the IDs to active-project beneficiaries.
    $beneficiaryNames = array();
    if ($beneficiaryIds) {
      $contacts = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id', 'display_name')
        ->addWhere('id', 'IN', array_values($beneficiaryIds))
        ->execute();
      foreach ($contacts as $contact) {
        $beneficiaryNames[(int) $contact['id']] = $contact['display_name'];
      }
    }
    foreach ($this->projects as &$project) {
      // Iterate the array itself, not `$project['beneficiaries'] ?? array()`:
      // a null-coalescing expression is not a variable, so foreach-by-reference
      // would bind to a temporary and silently discard every write.
      if (empty($project['beneficiaries'])) {
        continue;
      }
      foreach ($project['beneficiaries'] as &$beneficiary) {
        $beneficiary['display_name'] = $beneficiaryNames[(int) $beneficiary['id']] ?? '';
      }
      unset($beneficiary);
    }
    unset($project);

    foreach ($this->searchResults as &$need) {
      $projectId = (int) $need['project_id'];
      $need['project'] = $this->projects[$projectId];
    }
  }

  /**
   * Callback for usort.
   */
  private static function usortDateAscending($a, $b) {
    // A flexible need has no start time, and PHP 8.1 deprecates strtotime(NULL).
    $startTimeA = strtotime($a['start_time'] ?? '');
    $startTimeB = strtotime($b['start_time'] ?? '');

    if ($startTimeA === $startTimeB) {
      return 0;
    }
    return ($startTimeA < $startTimeB) ? -1 : 1;
  }

}
