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
    // Get volunteer_role_option_group_id of volunteer_role".
    $result = civicrm_api3('OptionGroup', 'get', [
      'sequential' => 1,
      'name' => "volunteer_role",
    ]);
    $volunteer_role_option_group_id = $result['id'];

    // Prepare select query for preparing fetch opportunity.
    // Join relevant table of need.
    $select = " SELECT project.id,project.title, project.description, project.is_active, project.loc_block_id, project.campaign_id, need.id as need_id, need.start_time, need.end_time, need.is_flexible, need.visibility_id, need.is_active as need_active,need.created as need_created,need.last_updated as need_last_updated,need.role_id as role_id, addr.street_address, addr.city, addr.postal_code, country.name as country, state.name as state_province, opt.label as role_lable, opt.description as role_description, campaign.title as campaign_title ";
    $from = " FROM civicrm_volunteer_project AS project";
    $join = " LEFT JOIN civicrm_volunteer_need AS need ON (need.project_id = project.id) ";
    $join .= " LEFT JOIN civicrm_loc_block AS loc ON (loc.id = project.loc_block_id) ";
    $join .= " LEFT JOIN civicrm_address AS addr ON (addr.id = loc.address_id) ";
    $join .= " LEFT JOIN civicrm_country AS country ON (country.id = addr.country_id) ";
    $join .= " LEFT JOIN civicrm_state_province AS state ON (state.id = addr.state_province_id) ";
    $join .= " LEFT JOIN civicrm_campaign AS campaign ON (campaign.id = project.campaign_id) ";
    // Get beneficiary_rel_no for volunteer_project_relationship type.
    $beneficiary_rel_no = CRM_Core_PseudoConstant::getKey("CRM_Volunteer_BAO_ProjectContact", 'relationship_type_id', 'volunteer_beneficiary');

    // Join Project Contact table for benificiary for specific $beneficiary_rel_no.
    $join .= " LEFT JOIN civicrm_volunteer_project_contact AS pc ON (pc.project_id = project.id And pc.relationship_type_id='".$beneficiary_rel_no."') ";
    // Join civicrm_option_value table for role details of need.
    $join .= " LEFT JOIN civicrm_option_value AS opt ON (opt.value = need.role_id And opt.option_group_id='".$volunteer_role_option_group_id."') ";
    // Join civicrm_contact table for contact details.
    $join .= " LEFT JOIN civicrm_contact AS cc ON (cc.id = pc.contact_id) ";
    $select .= ", GROUP_CONCAT( cc.id ) as beneficiary_id , GROUP_CONCAT( cc.display_name ) as beneficiary_display_name";

    $visibility_id = CRM_Volunteer_BAO_Project::getVisibilityId('name', "public");
    $where = " Where project.is_active = 1 AND need.visibility_id = ".$visibility_id;
    // Default Filter parameter of date start and date end field of need table.
    if(empty($this->searchParams['need']['date_start']) && empty($this->searchParams['need']['date_end'])) {
      $where .= " AND (
       (DATE_FORMAT(need.start_time,'%Y-%m-%d') <=   CURDATE() AND DATE_FORMAT(need.end_time,'%Y-%m-%d') >= CURDATE()) OR 
       (DATE_FORMAT(need.start_time,'%Y-%m-%d') >=  CURDATE()) OR (DATE_FORMAT(need.end_time,'%Y-%m-%d') >=  CURDATE()) OR
       (need.start_time Is NOT NULL && need.end_time IS NULL) OR 
       (need.start_time Is NULL && need.end_time IS NULL)
      )";
    }
    // Add date start and date end filter if passed in UI.
    if($this->searchParams['need']['date_start'] && $this->searchParams['need']['date_end']) {
      $start_time = date("Y-m-d", $this->searchParams['need']['date_start']);
      $end_time = date("Y-m-d", $this->searchParams['need']['date_end']);
      $where .= " AND (
        ( need.end_time IS NOT NULL AND need.start_time IS NOT NULL 
          AND (
            DATE_FORMAT(need.start_time,'%Y-%m-%d')<='".$end_time."' AND DATE_FORMAT(need.start_time,'%Y-%m-%d')>='".$start_time."'
            OR 
            DATE_FORMAT(need.end_time,'%Y-%m-%d') >= '".$start_time."' AND DATE_FORMAT(need.end_time,'%Y-%m-%d') <= '".$end_time."'
          )
        ) OR (
          need.end_time IS NULL AND DATE_FORMAT(need.start_time,'%Y-%m-%d')>='".$start_time."' AND DATE_FORMAT(need.start_time,'%Y-%m-%d')<='".$end_time."'
        ) 
      )";
    } else { // one but not the other supplied:
      if($this->searchParams['need']['date_start']) {
        $start_time = date("Y-m-d", $this->searchParams['need']['date_start']);
        $where .= " And (DATE_FORMAT(need.start_time,'%Y-%m-%d')>='".$start_time."')";
      }
      if($this->searchParams['need']['date_end']) {
        $end_time = date("Y-m-d", $this->searchParams['need']['date_end']);
        $where .= " And (DATE_FORMAT(need.end_time,'%Y-%m-%d')<='".$end_time."')";
      }
    }
    // Add role filter if passed in UI.
    if($this->searchParams['need']['role_id'] && is_array($this->searchParams['need']['role_id'])) {
      $role_id_string = implode(",", $this->searchParams['need']['role_id']);
      $where .= " And need.role_id IN (".$role_id_string.")";
    }
    // Add with(benificiary) filter if passed in UI.
    if($this->searchParams['project']['project_contacts']['volunteer_beneficiary']) {
      $beneficiary_id_string = implode(",", $this->searchParams['project']['project_contacts']['volunteer_beneficiary']);
      $where .= " And pc.contact_id IN (".$beneficiary_id_string.")";
    }
    // Add Location filter if passed in UI.
    if(isset($this->searchParams['project']["proximity"]) && !empty($this->searchParams['project']["proximity"])) {
      $proximityquery = CRM_Volunteer_BAO_Project::buildProximityWhere($this->searchParams['project']["proximity"]);
      $proximityquery = str_replace("civicrm_address", "addr", $proximityquery);
      $where .= " And ".$proximityquery;
    }
    // If Project Id is passed from URL- Query String.
    if(isset($this->searchParams['project']) && !empty($this->searchParams['project'])) {
      if(isset($this->searchParams['project']['is_active']) && isset($this->searchParams['project']['id'])) {
        $where .= " And project.id=".$this->searchParams['project']['id'];
      }
    }
    // Order by Logic.
    $orderByColumn = "project.id";
    $order = "ASC";
    $orderby = " group by need.id ORDER BY " . $orderByColumn . " " . $order;

    // Pagination Logic.
    $no_of_records_per_page = 10;
    if(isset($params['page_no']) && !empty($params['page_no'])) {
      $page_no = $params['page_no'];
    } else {
      $page_no = 1;
    }
    $offset = ($page_no-1) * $no_of_records_per_page;
    $limit = " LIMIT ".$offset.", ".$no_of_records_per_page;
    // Prepare whole sql query dynamic.
    //$sql = $select . $from . $join . $where . $orderby . $limit;
    $sql = $select . $from . $join . $where . $orderby;
    $dao = new CRM_Core_DAO();
    $dao->query($sql);
    $project_opportunities = [];
    $i=0;
    $config = CRM_Core_Config::singleton();
    $timeFormat = $config->dateformatDatetime;
    // Prepare array for need of projects.
    while ($dao->fetch()) {
      $project_opportunities[$i]['id'] = $dao->need_id;
      $project_opportunities[$i]['project_id'] = $dao->id;
      $project_opportunities[$i]['is_flexible'] = $dao->is_flexible;
      $project_opportunities[$i]['visibility_id'] = $dao->visibility_id;
      $project_opportunities[$i]['is_active'] = $dao->need_active;
      $project_opportunities[$i]['created'] = $dao->need_created;
      $project_opportunities[$i]['last_updated'] = $dao->need_last_updated;
      if(isset($dao->start_time) && !empty($dao->start_time)) {
        $start_time = CRM_Utils_Date::customFormat($dao->start_time, $timeFormat);
        if(isset($dao->end_time) && !empty($dao->end_time)) {
          $end_time = CRM_Utils_Date::customFormat($dao->end_time, $timeFormat);
          $project_opportunities[$i]['display_time'] = $start_time ." - ". $end_time;
        } else {
          $project_opportunities[$i]['display_time'] = $start_time;
        }
      } else {
        $project_opportunities[$i]['display_time'] = "Any";
      }
      $project_opportunities[$i]['role_id'] = $dao->role_id;
      if(empty($dao->role_lable)) {
        $project_opportunities[$i]['role_label'] = "Any";
      } else {
        $project_opportunities[$i]['role_label'] = $dao->role_lable;
      }
      $project_opportunities[$i]['role_description'] = $dao->role_description;
      $project_opportunities[$i]['project']['description'] =  $dao->description;
      $project_opportunities[$i]['project']['id'] =  $dao->id;
      $project_opportunities[$i]['project']['title'] =  $dao->title;
      $project_opportunities[$i]['project']['campaign_title'] = $dao->campaign_title;
      $project_opportunities[$i]['project']['location'] =  array(
        "city" => $dao->city,
        "country" => $dao->country,
        "postal_code" => $dao->postal_code,
        "state_province" => $dao->state_province,
        "street_address" => $dao->street_address
      );
      $beneficiary_display_name = explode(',', $dao->beneficiary_display_name);
      if(isset($beneficiary_display_name) && !empty($beneficiary_display_name) && is_array($beneficiary_display_name)) {
        $beneficiary_id_array = explode(',', $dao->beneficiary_id);
        foreach ($beneficiary_display_name as $key => $display_name) {
          $project_opportunities[$i]['project']['beneficiaries'][$key] = array(
            "id" => $beneficiary_id_array[$key],
            "display_name" => $display_name
          );
        }
      } else {
        $project_opportunities[$i]['project']['beneficiaries'] = $dao->beneficiary_display_name;
        $project_opportunities[$i]['project']['beneficiary_id'] = $dao->beneficiary_id;
      }
      $i++;
    }

    return $project_opportunities;
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