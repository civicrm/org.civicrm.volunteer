<?php
use CRM_Volunteer_ExtensionUtil as E;

class CRM_Volunteer_BAO_VolunteerAppeal extends CRM_Volunteer_DAO_VolunteerAppeal {

  /**
   * Create a new VolunteerAppeal based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Volunteer_DAO_VolunteerAppeal|NULL
   */
  public static function create($params) {
    $className = 'CRM_Volunteer_DAO_VolunteerAppeal';
    $entityName = 'VolunteerAppeal';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } 

  /**
   * Get a list of Project Appeal matching the params.
   *
   * This function is invoked from within the web form layer and also from the
   * API layer. Special params include:
   * 
   *
   * NOTE: This method does not return data related to the special params
   * outlined above; however, these parameters can be used to filter the list
   * of Projects appeal that is returned.
   *
   * @param array $params
   * @return array of CRM_Volunteer_BAO_VolunteerAppeal objects
   */
  public static function retrieve(array $params) {
    $result = array();
	
	$query = CRM_Utils_SQL_Select::from('`civicrm_volunteer_appeal` vp')
      ->select('*');
    $appeal = new CRM_Volunteer_BAO_VolunteerAppeal();

    $appeal->copyValues($params);
    
    foreach ($appeal->fields() as $field) { 
      $fieldName = $field['name'];

      if (!empty($appeal->$fieldName)) {
        $query->where('!column = @value', array(
          'column' => $fieldName,
          'value' => $appeal->$fieldName,
        ));
      }
    }

    $dao = self::executeQuery($query->toSQL()); 
    while ($dao->fetch()) {
      $fetchedAppeal = new CRM_Volunteer_BAO_VolunteerAppeal();  
      $daoClone = clone $dao; 
      $fetchedAppeal->copyValues($daoClone);  
      $result[(int) $dao->id] = $fetchedAppeal;
      
    }
	
    $dao->free();
   
    return $result;
  }



/**
   * @inheritDoc This override adds a little data massaging prior to calling its
   * parent.
   *
   * @deprecated since version 4.7.21-2.3.0
   *   Internal core methods should not be extended by third-party code.
   */
  public function copyValues(&$params, $serializeArrays = FALSE) {
    if (is_a($params, 'CRM_Core_DAO')) {
      $params = get_object_vars($params);
    }

    if (array_key_exists('is_active', $params)) {
      /*
       * don't force is_active to have a value if none was set, to allow searches
       * where the is_active state of appeal is irrelevant
       */
      $params['is_active'] = CRM_Volunteer_BAO_VolunteerAppeal::isOff($params['is_active']) ? 0 : 1;
    }
    return parent::copyValues($params, $serializeArrays);
  }
  
  /**
   * Invoked from the API layer.
   *
   * Fetch appeal based on search parameter.
   * @param array $params
   * @return array $appeals
   */
  public static function doSearch($params) {
    
	$show_beneficiary_at_front = 1;
	
	$search_appeal = $params['search_appeal'];
	$search_appeal = trim($search_appeal);
	$select = " SELECT appeal.*, addr.street_address, addr.city, addr.postal_code";
    $from = " FROM civicrm_volunteer_appeal AS appeal ";
	$join = " LEFT JOIN civicrm_volunteer_project AS p ON (p.id = appeal.project_id) ";
	$join .= " LEFT JOIN civicrm_loc_block AS loc ON (loc.id = p.loc_block_id) ";
	$join .= " LEFT JOIN civicrm_address AS addr ON (addr.id = loc.address_id) ";
	
	if($show_beneficiary_at_front == 1) {
		$join .= " LEFT JOIN civicrm_volunteer_project_contact AS pc ON (pc.project_id = p.id) ";
		$join .= " LEFT JOIN civicrm_contact AS cc ON (cc.id = pc.contact_id) ";
		$select .= " , GROUP_CONCAT(DISTINCT cc.display_name ) as beneficiary_display_name";
    }
	$where = " Where p.is_active = 1";
	if(isset($search_appeal) && !empty($search_appeal)) {
		$where .= " And appeal.title Like '%".$search_appeal."%' OR appeal.appeal_description Like '%".$search_appeal."%'";
	}
	// Order by Logic.
	$orderByColumn = "appeal.id";
	$order = "ASC";
	if(!empty($params["orderby"])) {
		$orderByColumn = $params["orderby"];
	}
	if(!empty($params["order"])) {
		$order = $params["order"];
	}
	
	$orderby = " GROUP By appeal.id ORDER BY " . $orderByColumn . " " . $order;
	
	// Pagination Logic.
	$no_of_records_per_page = 10;
	if(isset($params['page_no']) && !empty($params['page_no'])) {
		$page_no = $params['page_no'];
	} else {
		$page_no = 1;
	}
	$offset = ($page_no-1) * $no_of_records_per_page;
	$limit = " LIMIT ".$offset.", ".$no_of_records_per_page;
	$sql = $select . $from . $join . $where . $orderby . $limit;
	$dao = new CRM_Core_DAO();
    $dao->query($sql);
	
	$sql2 = $select . $from . $join . $where . $orderby;
	$dao2 = new CRM_Core_DAO();
    $dao2->query($sql);
	$total_appeals = count($dao2->fetchAll());
	$appeals = array();
	$appeals['appeal'] = array();
    $appeal = [];
	while ($dao->fetch()) {
		$appeal['id'] = $dao->id;
		$appeal['project_id'] = $dao->project_id;
		$appeal['title'] = $dao->title;
		$appeal['image'] = $dao->image;
		$appeal['appeal_teaser'] = $dao->appeal_teaser;
		$appeal['appeal_description'] = $dao->appeal_description;
		$appeal['location_done_anywhere'] = $dao->location_done_anywhere;
		$appeal['is_appeal_active'] = $dao->is_appeal_active;
		$appeal['active_fromdate'] = $dao->active_fromdate;
		$appeal['active_todate'] = $dao->active_todate;
		$appeal['display_volunteer_shift'] = $dao->display_volunteer_shift;
		$appeal['hide_appeal_volunteer_button'] = $dao->hide_appeal_volunteer_button;
		$appeal['beneficiary_display_name'] = $dao->beneficiary_display_name;
		
		$address = "";
		if ($dao->street_address) {
			$address .= " ".$dao->street_address;
		}
		if ($dao->street_address && ($dao->city || $dao->postal_code)) {
			$address .= ' <br /> ';
		}
		if ($dao->city) {
			$address .= " ".$dao->city;
		}
		if ($dao->city && $dao->postal_code) {
			$address .= ', '.$dao->postal_code;
		} else if ($dao->postal_code) {
			$address .= $dao->postal_code;
		}
		$appeal['location'] =  $address;
		$appeals['appeal'][] = $appeal;
	}
	$appeals['total_appeal'] = $total_appeals;
    
	return $appeals;
  }

}
