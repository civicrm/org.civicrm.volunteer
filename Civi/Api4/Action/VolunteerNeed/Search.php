<?php

namespace Civi\Api4\Action\VolunteerNeed;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class Search extends AbstractAction {

  /**
   * Filter by beneficiary contact.
   *
   * API4's runtime validator recognizes `array`, not PHPDoc's `int[]`.
   * Element values are normalized to positive integers by NeedSearch.
   *
   * @var int|array|null
   */
  protected $beneficiary;

  /**
   * Filter by project ID.
   *
   * @var int|null
   */
  protected $project;

  /**
   * Location filter.
   *
   * Address fields use ordinary matching unless a radius is supplied and the
   * optional Geocoder integration is available. Supported keys are
   * street_address, city, state_province_id, postal_code, country (or
   * country_id), radius and unit (km or miles). Numeric lat/lon remain accepted
   * for compatibility.
   *
   * @var array|null
   */
  protected $proximity;

  /**
   * Filter by volunteer role ID.
   *
   * API4's runtime validator recognizes `array`, not PHPDoc's `int[]`.
   * Element values are normalized to positive integers by NeedSearch.
   *
   * @var int|array|null
   */
  protected $roleId;

  /**
   * Filter needs starting on or after this date.
   *
   * @var string|null
   */
  protected $dateStart;

  /**
   * Filter needs starting on or before this date.
   *
   * @var string|null
   */
  protected $dateEnd;

  /**
   * Quick schedule filter.
   *
   * @var string
   * @options all,weekends,evenings,no_fixed_time
   */
  protected $timeFilter = 'all';

  /**
   * Limit to projects belonging to this campaign.
   *
   * @var int|string|null
   */
  protected $campaignId;

  public function _run(Result $result) {
    if ($this->getCheckPermissions()) {
      \CRM_Volunteer_Permission::assertProjectPerms(\CRM_Core_Action::VIEW);
    }
    $params = array_filter([
      'beneficiary' => $this->beneficiary,
      'project' => $this->project,
      'proximity' => $this->proximity,
      'role_id' => $this->roleId,
      'campaign_id' => $this->campaignId,
      'date_start' => $this->dateStart,
      'date_end' => $this->dateEnd,
      'time_filter' => $this->timeFilter,
    ], static function($value) {
      return $value !== NULL && $value !== '';
    });
    foreach (\CRM_Volunteer_BAO_NeedSearch::doSearch($params) as $row) {
      $result[] = $row;
    }
  }

}
