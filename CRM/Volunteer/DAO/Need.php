<?php

/**
 * DAOs provide an OOP-style facade for reading and writing database records.
 *
 * DAOs are a primary source for metadata in older versions of CiviCRM (<5.74)
 * and are required for some subsystems (such as APIv3).
 *
 * This stub provides compatibility. It is not intended to be modified in a
 * substantive way.
 *
 * The authoritative definition of this entity is schema/VolunteerNeed.entityType.php.
 * CRM_Volunteer_DAO_Base (a runtime alias for CRM_Core_DAO_Base) derives
 * fields(), indices(), keys() and getTableName() from it, so there is exactly
 * one source of truth. The property annotations below exist only so static
 * analysers and IDEs can see the columns that CRM_Core_DAO_Base populates
 * dynamically in its constructor.
 *
 * @property int|string $id
 * @property int|string $project_id
 * @property string $start_time
 * @property string $end_time
 * @property int|string $duration
 * @property string $is_flexible
 * @property int|string $quantity
 * @property int|string $visibility_id
 * @property int|string $role_id
 * @property string $is_active
 * @property string $created
 * @property string $last_updated
 */
class CRM_Volunteer_DAO_Need extends CRM_Volunteer_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civicrm_volunteer_need';

}
