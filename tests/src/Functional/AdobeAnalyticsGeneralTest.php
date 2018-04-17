<?php

namespace Drupal\Tests\adobe_analytics\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the module's core logic.
 *
 * @group adobe_analytics
 */
class AdobeAnalyticsGeneralTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['adobe_analytics'];

  /**
   * @var \Drupal\Core\Session\AccountInterface
   *
   * The admin user account.
   */
  protected $adminUser;

  /**
   * Implementation of setUp().
   */
  function setUp() {
    parent::setUp();

    // Create an admin user with all the permissions needed to run tests.
    $this->adminUser = $this->drupalCreateUser(array('administer adobe analytics configuration', 'access administration pages'));
    $this->drupalLogin($this->adminUser);

    // Set some default settings.
    \Drupal::configFactory()->getEditable('adobe_analytics.settings')
      ->set('js_file_location', 'http://www.example.com/js/s_code_remote_h.js')
      ->set('image_file_location', 'http://examplecom.112.2O7.net/b/ss/examplecom/1/H.20.3--NS/0')
      ->set('version', 'H.20.3.')
      ->save();
  }

  function assertTrackingCode() {
    $config = \Drupal::config('adobe_analytics.settings');
    $this->assertSession()->responseContains('<!-- AdobeAnalytics code version: ');
    $this->assertSession()->responseContains($config->get('js_file_location'));
    $this->assertSession()->responseContains($config->get('image_file_location'));
    $this->assertSession()->responseContains($config->get('version'));
  }

  function assertNoTrackingCode() {
    $config = \Drupal::config('adobe_analytics.settings');
    $this->assertSession()->responseNotContains('<!-- AdobeAnalytics code version: ');
    $this->assertSession()->responseNotContains($config->get('js_file_location'));
    $this->assertSession()->responseNotContains($config->get('image_file_location'));
    $this->assertSession()->responseNotContains($config->get('version'));
  }

  function assertVar($name, $value, $message = '') {
    $edit = array(
      'variables[0][name]' => $name,
      'variables[0][value]' => $value,
    );
    $this->drupalPostForm('admin/config/system/adobeanalytics', $edit, 'Save configuration');
    $this->drupalGet('node');
    $this->assertSession()->responseContains($name . '="' . $value . '";');
  }

  function assertInvalidVar($name, $value, $message = '') {
    $edit = array(
      'variables[0][name]' => $name,
      'variables[0][value]' => $value,
    );
    $this->drupalPostForm('admin/config/system/adobeanalytics', $edit, 'Save configuration');
    $this->assertSession()->responseContains('This is not a valid variable name. It must start with a letter, $ or _ and cannot contain spaces.');
  }

  function testTrackingCode() {
    $this->drupalGet('<front>');
    $this->assertTrackingCode();
  }

  function testVariables() {
    // Test that variables with valid names are added properly.
    $valid_vars = array(
      $this->randomMachineName(8),
      $this->randomMachineName(8) . '7',
      '$' . $this->randomMachineName(8),
      '_' . $this->randomMachineName(8),
    );
    foreach ($valid_vars as $name) {
      $this->assertVar($name, $this->randomMachineName(8));
    }

    // Test that invalid variable names are not allowed.
    $invalid_vars = array(
      '7' . $this->randomMachineName(8),
      $this->randomMachineName(8) . ' ' . $this->randomMachineName(8),
      '#' . $this->randomMachineName(8),
    );
    foreach ($invalid_vars as $name) {
      $this->assertInvalidVar($name, $this->randomMachineName(8));
    }
  }

  function testSiteCatalystRolesTracking() {
    // Test that anonymous users can see the tracking code.
    \Drupal::configFactory()->getEditable('adobe_analytics.settings')
      ->set('track_roles', [
        'anonymous' => 'anonymous',
        'authenticated' => '0',
        'administrator' => '0',
      ])
      ->set('role_tracking_type', 'inclusive')
      ->save();

    $this->drupalGet('<front>');
    $this->assertTrackingCode();

    // Test that anonymous users cannot see the tracking code.
    \Drupal::configFactory()->getEditable('adobe_analytics.settings')
      ->set('role_tracking_type', 'exclusive')
      ->save();

    $this->drupalGet('<front>');
    $this->assertNoTrackingCode();
  }

}
