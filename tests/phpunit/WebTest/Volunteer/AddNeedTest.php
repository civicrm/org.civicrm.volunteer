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
 | License along with this program; if not, contact CiviCRM LLC       |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

require_once 'CiviTest/CiviSeleniumTestCase.php';
require_once 'Webtest/Volunteer/VolunteerSeleniumTestCase.php';

/**
 * Class WebTest_Volunteer_AddNeedTest
 */
class WebTest_Volunteer_AddNeedTest extends Webtest_Volunteer_VolunteerSeleniumTestCase {

  protected function setUp() {
    parent::setUp();
  }

  function testAddNeed() {
    // Log in using webtestLogin() method
    $this->webtestLogin();

    $this->openCiviPage("event/add", "reset=1&action=add");

    $eventTitle = 'My Conference - ' . substr(sha1(rand()), 0, 7);
    $eventDescription = "Here is a description for this conference.";
    $this->_testAddEventInfo($eventTitle, $eventDescription);

    $streetAddress = "100 Main Street";
    $this->_testAddLocation($streetAddress);

    $eventInfoStrings = array($eventTitle, $eventDescription, $streetAddress);
    $eventId = $this->_testVerifyEventInfo($eventTitle, $eventInfoStrings);
    
    $this->webtestEnableVolunteerEvent($eventId);
  }


  /**
   * @param $eventTitle
   * @param $eventDescription
   */
  function _testAddEventInfo($eventTitle, $eventDescription) {
    $this->waitForElementPresent("_qf_EventInfo_upload-bottom");

    $this->select("event_type_id", "value=1");

    // Attendee role s/b selected now.
    $this->select("default_role_id", "value=1");

    // Enter Event Title, Summary and Description
    $this->type("title", $eventTitle);
    $this->type("summary", "This is a great conference. Sign up now!");

    // Type description in ckEditor (fieldname, text to type, editor)
    $this->fillRichTextField("description", $eventDescription, 'CKEditor');

    // Choose Start and End dates.
    // Using helper webtestFillDate function.
    $this->webtestFillDateTime("start_date", "+1 week");
    $this->webtestFillDateTime("end_date", "+1 week 1 day 8 hours ");

    $this->type("max_participants", "50");
    $this->click("is_map");
    $this->click("is_public");
    $this->click("_qf_EventInfo_upload-bottom");
    $this->waitForPageToLoad($this->getTimeoutMsec());
  }

  /**
   * @param $streetAddress
   */
  function _testAddLocation($streetAddress) {
    // Wait for Location tab form to load
    $this->waitForPageToLoad($this->getTimeoutMsec());
    $this->waitForElementPresent("_qf_Location_upload-bottom");

    // Fill in address fields
    $streetAddress = "100 Main Street";
    $this->type("address_1_street_address", $streetAddress);
    $this->type("address_1_city", "San Francisco");
    $this->type("address_1_postal_code", "94117");
    $this->select('address_1_country_id', 'United States');
    $this->select("address_1_state_province_id", "value=1004");
    $this->type("email_1_email", "info@civicrm.org");

    $this->click("_qf_Location_upload-bottom");

    // Wait for "saved" status msg
    $this->waitForText('crm-notification-container', "'Event Location' information has been saved.");
  }

  /**
   * @param $eventTitle
   * @param $eventInfoStrings
   * @param null $eventFees
   *
   * @return null
   */
  function _testVerifyEventInfo($eventTitle, $eventInfoStrings, $eventFees = NULL) {
    // verify event input on info page
    // start at Manage Events listing
    $this->openCiviPage("event/manage", "reset=1");
    $this->click("link=$eventTitle");
    $this->waitForElementPresent("css=div.crm-actionlinks-bottom");

    // Check for correct event info strings
    $this->assertStringsPresent($eventInfoStrings);

    // Optionally verify event fees (especially for discounts)
    if ($eventFees) {
      $this->assertStringsPresent($eventFees);

    }
    return $this->urlArg('id');
  }

}
