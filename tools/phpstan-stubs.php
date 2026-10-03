<?php
/**
 * Static-analysis stubs.
 *
 * CRM_Volunteer_DAO_Base does not exist as a file: the civix class loader
 * creates it at runtime with class_alias('CRM_Core_DAO_Base', ...) (see
 * volunteer.civix.php). PHPStan therefore cannot resolve it, and every DAO
 * subclass appears to extend an unknown class -- which makes all of
 * CRM_Core_DAO's methods and properties look undefined. Declaring the
 * relationship here restores analysis. This file is never loaded at runtime.
 */

// phpcs:disable
if (FALSE) {
  class CRM_Volunteer_DAO_Base extends CRM_Core_DAO_Base {
  }
}
