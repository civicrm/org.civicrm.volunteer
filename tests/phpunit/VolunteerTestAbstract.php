<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Abstract class for Volunteer tests
 */
abstract class VolunteerTestAbstract extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * The ID of a contact mocked as the acting user.
   *
   * Some volunteer code checks to see who the logged in user is -- e.g., to
   * fetch related contacts or owned projects. Tests will fail if
   * CRM_Core_Session::getLoggedInContactID() doesn't return a valid ID, so we
   * want to make sure to mock this.
   *
   * NOTE: To access this value, use getter getMockedContactId() instead of
   * accessing the property directly.
   *
   * NOTE: We expect that permissions management will be an unrelated process to
   * this mocking.
   *
   * @var int
   */
  protected $mockedContactId;
  protected $mockedContactParams = array(
    'contact_type' => 'Individual',
    'first_name' => 'Logged',
    'last_name' => 'In',
  );

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
            ->installMe(__DIR__)
            ->callback(function() {
              $mockedContact = \CRM_Core_DAO::createTestObject('CRM_Contact_DAO_Contact', $this->mockedContactParams);
              $this->mockedContactId = $mockedContact->id;
            }, 'mockContact')
            ->callback(function() {
              // This is a hack that we hope to remove once we figure out why most settings
              // are nonexistent following installation.
              $params = array();
              $extSettings = include(__DIR__ . '/../../settings/volunteer.setting.php');
              foreach ($extSettings as $setting) {
                $params[$setting['name']] = $setting['default'];
              }
              CRM_Core_BAO_Setting::setItems($params);
            }, 'importSettings')
            ->apply();
  }

  function setUp() {
    // Apparently actions performed in setUpHeadless take place in a different
    // session, so our mocked contact's ID needs to be added into the session
    // before each test.
    \CRM_Core_Session::singleton()->set('userID', $this->getMockedContactId());
  }

  function getMockedContactId() {
    if (empty($this->mockedContactId)) {
      $params = $this->mockedContactParams;
      $params['return'] = 'id';
      // cast as int for compatibility with \CRM_Core_DAO::createTestObject
      $this->mockedContactId = (int) civicrm_api3('Contact', 'getvalue', $params);
    }

    return $this->mockedContactId;
  }

}
