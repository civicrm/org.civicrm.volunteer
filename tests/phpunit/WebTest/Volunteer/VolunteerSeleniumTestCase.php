<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.5                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *  Base class for CiviVolunteer Selenium tests
 *
 *  Common functions for unit tests
 * @package CiviVolunteer
 */
class Webtest_Volunteer_VolunteerSeleniumTestCase extends CiviSeleniumTestCase {

  /**
   * Enable volunteer feature for a specific event
   *
   * @param $eventId: int
   */
  function webtestEnableVolunteerEvent($eventId) {
    $this->openCiviPage('event/manage/volunteer?reset=1&action=update&id=12', "reset=1&action=update&id={$eventId}", "_qf_Volunteer_cancel-bottom");
    $isChecked = $this->isChecked('is_active');
    if (!$isChecked) {
      $this->click("is_active");
    }
    $this->assertChecked("is_active");
    $this->click("_qf_Volunteer_upload-bottom");
    $this->waitForPageToLoad($this->getTimeoutMsec());
    $this->waitForTextPresent("Log Volunteer Hours");
  }
}
