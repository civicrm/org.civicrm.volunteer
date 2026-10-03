<?php

class CRM_Volunteer_Page_Angular extends \CRM_Core_Page {

  public function run() {
    CRM_Core_Region::instance('page-footer')->add(array(
      'template' => 'CRM/common/notifications.tpl',
    ));
    // This route stays public so anonymous volunteers can reach it, but the
    // public-page status template adds `no-popup` to every session message.
    // The notification container above is present specifically so notices can
    // use CiviCRM's standard top-right bubbles; opt this host into that normal
    // rendering before the common page template consumes the session messages.
    $this->assign('urlIsPublic', FALSE);
    CRM_Volunteer_Angular::load($this->getDefaultRoute(), $this->getAngularBasePage());
    // Ensure this setting exists even if it's an empty object (https://github.com/civicrm/org.civicrm.volunteer/issues/613)
    Civi::resources()
      ->addVars('org.civicrm.volunteer', []);
    parent::run();
  }

  /**
   * Declare the shared Angular host template explicitly.
   *
   * CRM_Core_Page derives the template path from the class name, so every
   * subclass would otherwise need its own .tpl file. All of these pages render
   * the same Angular shell and differ only in their default route and access
   * check, so name the template here and let subclasses inherit it.
   *
   * @return string
   */
  public function getTemplateFileName() {
    return 'CRM/Volunteer/Page/Angular.tpl';
  }

  /**
   * Default route used when no hash route is supplied.
   *
   * @return string
   */
  protected function getDefaultRoute() {
    return '/volunteer/opportunities';
  }

  /**
   * CiviCRM page used as the Angular module host.
   *
   * @return string
   */
  protected function getAngularBasePage() {
    return 'civicrm/vol';
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

    // The BAOs guard their Campaign and Event reads on these; the client did
    // not, so with CiviCampaign switched off the project form still offered an
    // empty campaign picker and the listing still offered a campaign filter for
    // a column that could never be populated.
    $settings['isCampaignEnabled'] = CRM_Core_Component::isEnabled('CiviCampaign');
    $settings['isEventEnabled'] = CRM_Core_Component::isEnabled('CiviEvent');

    return $settings;
  }

}
