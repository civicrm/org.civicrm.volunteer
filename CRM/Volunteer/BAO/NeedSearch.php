<?php

class CRM_Volunteer_BAO_NeedSearch {

  /**
   * @var array
   *   Parameters that will be passed to api.VolunteerProject.get. (Chaining
   *   is supported.)
   */
  private $searchParams = array();

  /**
   * @var array
   *   The results of the search, which will ultimately be returned.
   */
  private $searchResults = array(
    'needs' => array(),
    'projects' => array(),
  );

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
      "is_active" => 1,
      "sequential" => 0,
      "options" => array("limit" => 0),
      "api.Campaign.getsingle" => array(),
      "api.LocBlock.getsingle" => array(
        "api.Address.getsingle" => array(),
      ),
      "api.VolunteerNeed.get" => array(
        "is_active" => 1,
        "options" => array("limit" => 0),
        "sequential" => 0,
        "visibility_id" => "public",
      ),
      "api.VolunteerProjectContact.get" => array(
        "options" => array("limit" => 0),
        "relationship_type_id" => "volunteer_beneficiary",
        "api.Contact.get" => array(),
      ),
    );
  }

  /**
   * Performs the search.
   *
   * Stashes the results in $this->searchResults. Delegates formatting the
   * results to formatSearchResults().
   *
   * @return array $this->searchResults
   */
  public function search() {
    $apiResult = civicrm_api3('VolunteerProject', 'get', $this->searchParams);
    foreach($apiResult['values'] as $project) {
      if ($project['api.VolunteerNeed.get']['count'] > 0) {
        $projectId = $project['id'];
        $this->searchResults['projects'][$projectId] = $project;
      }
    }

    $this->formatSearchResuts();
    return $this->searchResults;
  }

  /**
   * Sets the parameters for api.VolunteerProject.get, plus chained calls.
   *
   * @param array $userSearchParams
   *   Supported parameters:
   *     - beneficiary: mixed - an int-like string, a comma-separated list
   *         thereof, or an array representing one or more contact IDs
   *     - project: int-like string representing project ID
   *     - proximity: array - see CRM_Volunteer_BAO_Project::buildProximityWhere
   *     - role: mixed - an int-like string, a comma-separated list thereof, or
   *         an array representing one or more role IDs
   *     - date_start: See setSearchDateParams()
   *     - date_end: See setSearchDateParams()
   */
  private function setSearchParams($userSearchParams) {
    $this->setSearchDateParams($userSearchParams);

    $projectId = CRM_Utils_Array::value('project', $userSearchParams);
    if (CRM_Utils_Type::validate($projectId, 'Positive', FALSE)) {
      $this->searchParams['id'] = $projectId;
    }

    $proximity = CRM_Utils_Array::value('proximity', $userSearchParams);
    if (is_array($proximity)) {
      $this->searchParams['proximity'] = $proximity;
    }

    $beneficiary = CRM_Utils_Array::value('beneficiary', $userSearchParams);
    if ($beneficiary) {
      if (!array_key_exists('project_contacts', $this->searchParams)) {
        $this->searchParams['project_contacts'] = array();
      }
      $beneficiary = is_array($beneficiary) ? $beneficiary : explode(',', $beneficiary);
      $this->searchParams['project_contacts']['volunteer_beneficiary'] = $beneficiary;
    }

    $role = CRM_Utils_Array::value('role', $userSearchParams);
    if ($role) {
      $role = is_array($role) ? $role : explode(',', $role);
      $this->searchParams['api.VolunteerNeed.get']['role_id'] = array("IN" => $role);
    }
  }

  /**
   * Sets the date-related parameters for api.VolunteerProject.get.
   *
   * The dates actually apply to the Need entity, which is chained to
   * api.VolunteerProject.get. Used to filter needs by time, e.g., needs
   * between October 1 and October 31
   *
   * @param array $userSearchParams
   *   Supported parameters:
   *     - date_start: date
   *     - date_end: date
   */
  private function setSearchDateParams($userSearchParams) {
    $projectDateStart = CRM_Utils_Array::value('date_start', $userSearchParams);
    if (!$projectDateStart || !CRM_Utils_Type::validate($projectDateStart, 'Date', FALSE)) {
      $projectDateStart = NULL;
    }

    $projectDateEnd = CRM_Utils_Array::value('date_end', $userSearchParams);
    if (!$projectDateEnd || !CRM_Utils_Type::validate($projectDateEnd, 'Date', FALSE)) {
      $projectDateEnd = NULL;
    }

    if ($projectDateStart && $projectDateEnd) {
      $this->searchParams['api.VolunteerNeed.get']['start_time'] = array("BETWEEN" => array($projectDateStart, $projectDateEnd));
    } else if ($projectDateStart) {
      $this->searchParams['api.VolunteerNeed.get']['start_time'] = array(">" => $projectDateStart);
    } else if ($projectDateEnd) {
      $this->searchParams['api.VolunteerNeed.get']['start_time'] = array("<" => $projectDateEnd);
    }
  }

  /**
   * Formats search results for ease of use on the client side.
   *
   * Manipulates $this->searchResults.
   */
  private function formatSearchResuts() {
    foreach ($this->searchResults['projects'] as $projectId => $project) {
      if (!empty($project['api.Campaign.getsingle']['title'])) {
        $this->searchResults['projects'][$projectId]['campaign_title'] = $project['api.Campaign.getsingle']['title'];
      }
      unset($this->searchResults['projects'][$projectId]['api.Campaign.getsingle']);

      if (!empty($project['api.LocBlock.getsingle']['api.Address.getsingle'])) {
        // TODO: support state and country, which we get back as unfriendly IDs
        $this->searchResults['projects'][$projectId]['location'] = array(
          'city' => $project['api.LocBlock.getsingle']['api.Address.getsingle']['city'],
          'postalCode' => $project['api.LocBlock.getsingle']['api.Address.getsingle']['postal_code'],
          'streetAddress' => $project['api.LocBlock.getsingle']['api.Address.getsingle']['street_address'],
        );
      }
      unset($this->searchResults['projects'][$projectId]['api.LocBlock.getsingle']);

      foreach ($project['api.VolunteerNeed.get']['values'] as $need) {
        $needId = $need['id'];
        $this->searchResults['needs'][$needId] = $need;
      }
      unset($this->searchResults['projects'][$projectId]['api.VolunteerNeed.get']);

      foreach ($project['api.VolunteerProjectContact.get']['values'] as $projectContact) {
        if (!array_key_exists('beneficiaries', $this->searchResults['projects'][$projectId])) {
          $this->searchResults['projects'][$projectId]['beneficiaries'] = array();
        }

        $this->searchResults['projects'][$projectId]['beneficiaries'][] = array(
          'id' => $projectContact['contact_id'],
          'display_name' => $projectContact['api.Contact.get']['values'][0]['display_name'],
        );
        unset($this->searchResults['projects'][$projectId]['api.VolunteerProjectContact.get']);
      }
    }
  }

}
