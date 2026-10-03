<?php

/**
 * Small result-shape helpers for server-side API4 consumers.
 *
 * API4 intentionally returns rows rather than API3's getvalue/getsingle
 * variants. Keeping the two recurring conversions here makes the runtime
 * callers concise without recreating a generic API3 facade.
 */
class CRM_Volunteer_Api4 {

  /**
   * Read one CiviCRM setting value.
   *
   * Setting::get() takes setting names through `select`; it has no `where`
   * parameter, so the name must not be expressed as a filter.
   *
   * @return mixed|null
   */
  public static function getSetting(string $name) {
    $setting = \Civi\Api4\Setting::get(FALSE)
      ->addSelect($name)
      ->execute()
      ->first();
    return $setting['value'] ?? NULL;
  }

  /**
   * Strip APIv3 request plumbing from a parameter array.
   *
   * The deprecated APIv3 actions are adapters over API4, so the values they
   * forward must not carry APIv3's own request keys: `version`,
   * `check_permissions`, pagination/formatting options, or chained `api.*`
   * sub-requests. Chains are dropped rather than translated -- API4 expresses
   * them as joins, which the caller must request explicitly.
   *
   * @param array $params
   * @return array
   */
  public static function stripApi3Envelope(array $params) {
    $envelopeKeys = array(
      'version',
      'check_permissions',
      'checkPermissions',
      'debug',
      'sequential',
      'options',
      'return',
      'api.has_parent',
      'api_params',
      'entity',
      'action',
    );
    foreach ($envelopeKeys as $key) {
      unset($params[$key]);
    }
    return CRM_Volunteer_Permission::stripChainedApiParams($params);
  }

  /**
   * Store one or more CiviCRM settings in the active domain.
   */
  public static function setSettings(array $values): void {
    \Civi\Api4\Setting::set(FALSE)
      ->setValues($values)
      ->execute();
  }

}
