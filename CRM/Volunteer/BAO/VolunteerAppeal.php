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

}
