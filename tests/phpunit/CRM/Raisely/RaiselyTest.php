<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * RaiselyTest - Unit tests for the CiviCRM-Raisely web callback extension
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Raisely_RaiselyTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  // Define our test data for several tests
  private $test_data = [
    'data' => [
      'result' => [
        'metadata' => [
          'first_name' => 'Wonder',
          'last_name' => 'Woman',
          'email' => 'wonderwoman@justice.league',
          'street_address' => '1 Temple Way',
          'postal_code' => '2000',
          'city' => 'Themyscira',
          'state_province' => 'Paradise Islands',
          'country' => 'GR',
          'contact_type' => 'Individual',
          'profile_url' => 'https://themyscira.raisely.com/justiceleague',
          'public.donation_amount' => '50.00',
        ],
        'status' => 'succeeded',
        'method' => 'CREDIT_CARD',
        'failure_message' => null,
        'currency' => 'aud',
      ],
    ],

  /**
   * Test: _parseDonor returns a well-formed donor array
   */
  public function testSuccessfulParseDonor() {
    $expected = $test_data['data']['result']['metadata'];
    unset($expected['profile_url']);
    unset($expected['public.donation_amount']);
    $donor = CRM_Raisely_Page_Raisely::_parseDonor($test_data);
    $this->assertEquals($expected, $donor);
  }

  /**
   * Test: _parseDonor returns NULL when required fields are missing
   */
  public function testParseDonorMissingFields() {
    unset($test_data['data']['result']['metadata']['first_name'];
    $donor = CRM_Raisely_Page_Raisely::_parseDonor($test_data);
    $this->assertNull($donor);
  }

  /**
   * Test: _parseContribution returns a well-formed donor array
   */
  public function testSuccessfulParseContribution() {
    $expected = $test_data;
    unset($expected['metadata']);
    $expected['metadata']['public.donation_amount'] = '50.00';
    $contribution = CRM_Raisely_Page_Raisely::_parseContribution($test_data);
    $this->assertEquals($expected, $donor);
  }

  /**
   * Test: _parseContribution returns NULL when required fields are missing
   */
  public function testParseContributionMissingFields() {
    unset($test_data['data']['result']['metadata']['public.donation_amount']);
    $contrib = CRM_Raisely_Page_Raisely::_parseContribution($test_data);
    $this->assertNull($contrib);
  }

}
