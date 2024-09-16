<?php

class CRM_Volunteer_Page_Angular extends \CRM_Core_Page {

  public function run() {
    CRM_Core_Region::instance('page-footer')->add(array(
      'template' => 'CRM/common/notifications.tpl',
    ));
    CRM_Volunteer_Angular::load('/volunteer/manage');
    // Ensure this setting exists even if it's an empty object (https://github.com/civicrm/org.civicrm.volunteer/issues/613)
    Civi::resources()
      ->addVars('org.civicrm.volunteer', []);
    parent::run();
  }

  /**
   * Settings factory function for the volunteer Angular module
   * @return array
   */
  public static function loadSettings() {
    $settings = [];
    $prefs = civicrm_api4('Setting', 'get', [
      'checkPermissions' => FALSE,
      'select' => [
        'volunteer_general_campaign_filter_type',
        'volunteer_general_campaign_filter_list',
      ],
    ], ['name' => 'value']);
    $campaignFilterOperator = $prefs['volunteer_general_campaign_filter_type'] === 'whitelist' ? 'IN' : 'NOT IN';
    $settings['campaignFilter'] = $prefs['volunteer_general_campaign_filter_list'] ?
      ['campaign_type_id' => [$campaignFilterOperator => $prefs['volunteer_general_campaign_filter_list']]] : [];

    return $settings;
  }

}
