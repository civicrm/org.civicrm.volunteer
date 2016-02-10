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

      $flexibleNeed = civicrm_api3('VolunteerNeed', 'getsingle', array(
        'id' => $project->flexible_need_id,
      ));
      if ($flexibleNeed['visibility_id'] === CRM_Core_OptionGroup::getValue('visibility', 'public', 'name')) {
        $needId = $flexibleNeed['id'];
        $results[$needId] = $flexibleNeed;
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
    usort($this->searchResults, array($this, "usortDateAscending"));
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
    $needStartTime = strtotime(CRM_Utils_Array::value('start_time', $need));
    $needEndTime = strtotime(CRM_Utils_Array::value('end_time', $need));

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
      && (
        // Either no role was specified in the search...
        empty($this->searchParams['need']['role_id'])
        // or the need role is in the list of searched-by roles.
        || in_array($need['role_id'], $this->searchParams['need']['role_id'])
      );
  }

  /**
   * @param array $userSearchParams
   *   Supported parameters:
   *     - beneficiary: mixed - an int-like string, a comma-separated list
   *         thereof, or an array representing one or more contact IDs
   *     - project: int-like string representing project ID
   *     - proximity: array - see CRM_Volunteer_BAO_Project::buildProximityWhere
   *     - role_id: mixed - an int-like string, a comma-separated list thereof, or
   *         an array representing one or more role IDs
   *     - date_start: See setSearchDateParams()
   *     - date_end: See setSearchDateParams()
   */
  private function setSearchParams($userSearchParams) {
    $this->setSearchDateParams($userSearchParams);

    $projectId = CRM_Utils_Array::value('project', $userSearchParams);
    if (CRM_Utils_Type::validate($projectId, 'Positive', FALSE)) {
      $this->searchParams['project']['id'] = $projectId;
    }

    $proximity = CRM_Utils_Array::value('proximity', $userSearchParams);
    if (is_array($proximity)) {
      $this->searchParams['project']['proximity'] = $proximity;
    }

    $beneficiary = CRM_Utils_Array::value('beneficiary', $userSearchParams);
    if ($beneficiary) {
      if (!array_key_exists('project_contacts', $this->searchParams['project'])) {
        $this->searchParams['project']['project_contacts'] = array();
      }
      $beneficiary = is_array($beneficiary) ? $beneficiary : explode(',', $beneficiary);
      $this->searchParams['project']['project_contacts']['volunteer_beneficiary'] = $beneficiary;
    }

    $role = CRM_Utils_Array::value('role_id', $userSearchParams);
    if ($role) {
      $this->searchParams['need']['role_id'] = is_array($role) ? $role : explode(',', $role);
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
    $this->searchParams['need']['date_start'] = strtotime(CRM_Utils_Array::value('date_start', $userSearchParams));
    $this->searchParams['need']['date_end'] = strtotime(CRM_Utils_Array::value('date_end', $userSearchParams));
  }

  /**
   * Adds 'project' key to each need in $this->searchResults, containing data
   * related to the project, campaign, location, and project contacts.
   */
  private function getSearchResultsProjectData() {
    // api.VolunteerProject.get does not support the 'IN' operator, so we loop
    foreach ($this->projects as $id => &$project) {
      $api = civicrm_api3('VolunteerProject', 'getsingle', array(
        'id' => $id,
        'api.Campaign.getvalue' => array(
          'return' => 'title',
        ),
        'api.LocBlock.getsingle' => array(
          'api.Address.getsingle' => array(),
        ),
        'api.VolunteerProjectContact.get' => array(
          'options' => array('limit' => 0),
          'relationship_type_id' => 'volunteer_beneficiary',
          'api.Contact.get' => array(
            'options' => array('limit' => 0),
          ),
        ),
      ));

      $project['description'] = $api['description'];
      $project['id'] = $api['id'];
      $project['title'] = $api['title'];

      // Because of CRM-17327, the chained "get" may improperly report its result,
      // so we check the value we're chaining off of to decide whether or not
      // to trust the result.
      $project['campaign_title'] = empty($api['campaign_id']) ? NULL : $api['api.Campaign.getvalue'];

      // CRM-17327
      if (empty($api['loc_block_id']) || empty($api['api.LocBlock.getsingle']['address_id'])) {
        $project['location'] = array(
          'city' => NULL,
          'country' => NULL,
          'postal_code' => NULL,
          'state_provice' => NULL,
          'street_address' => NULL,
        );
      } else {
        $countryId = $api['api.LocBlock.getsingle']['api.Address.getsingle']['country_id'];
        $country = $countryId ? CRM_Core_PseudoConstant::country($countryId) : NULL;

        $stateProvinceId = $api['api.LocBlock.getsingle']['api.Address.getsingle']['state_province_id'];
        $stateProvince = $stateProvinceId ? CRM_Core_PseudoConstant::stateProvince($stateProvinceId) : NULL;

        $project['location'] = array(
          'city' => $api['api.LocBlock.getsingle']['api.Address.getsingle']['city'],
          'country' => $country,
          'postal_code' => $api['api.LocBlock.getsingle']['api.Address.getsingle']['postal_code'],
          'state_province' => $stateProvince,
          'street_address' => $api['api.LocBlock.getsingle']['api.Address.getsingle']['street_address'],
        );
      }

      foreach ($api['api.VolunteerProjectContact.get']['values'] as $projectContact) {
        if (!array_key_exists('beneficiaries', $project)) {
          $project['beneficiaries'] = array();
        }

        $project['beneficiaries'][] = array(
          'id' => $projectContact['contact_id'],
          'display_name' => $projectContact['api.Contact.get']['values'][0]['display_name'],
        );
      }
    }

    foreach ($this->searchResults as &$need) {
      $projectId = (int) $need['project_id'];
      $need['project'] = $this->projects[$projectId];
    }
  }

  /**
   * Callback for usort.
   */
  private static function usortDateAscending($a, $b) {
    $startTimeA = strtotime($a['start_time']);
    $startTimeB = strtotime($b['start_time']);

    if ($startTimeA === $startTimeB) {
      return 0;
    }
    return ($startTimeA < $startTimeB) ? -1 : 1;
  }

}
