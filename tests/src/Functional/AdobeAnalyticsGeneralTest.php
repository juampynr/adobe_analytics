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
    $this->assertNoRaw("<!-- SiteCatalyst code version: ", 'The SiteCatalyst code was not found.');
    $this->assertNoRaw(variable_get("sitecatalyst_js_file_location"), 'The SiteCatalyst js file was properly omitted.');
    $this->assertNoRaw(variable_get("sitecatalyst_image_file_location"), 'The SiteCatalyst backup image was properly omitted.');
    $this->assertNoRaw(variable_get("sitecatalyst_version"), 'The SiteCatalyst version was omitted.');
  }

  function assertSiteCatalystVar($name, $value, $message = '') {
    $message = empty($message) ? 'The SiteCatalyst variable was correctly included.' : $message;

    $edit = array(
      'sitecatalyst_variables[0][name]' => $name,
      'sitecatalyst_variables[0][value]' => $value,
    );
    $this->drupalPost('admin/config/system/sitecatalyst', $edit, t('Save configuration'));
    $this->drupalGet('node');
    $this->assertRaw($name . '="' . $value . '";', $message);
  }

  function assertInvalidSiteCatalystVar($name, $value, $message = '') {
    $message = empty($message) ? 'The SiteCalalyst variable was correctly reported as invalid.' : $message;
    $edit = array(
      'sitecatalyst_variables[0][name]' => $name,
      'sitecatalyst_variables[0][value]' => $value,
    );
    $this->drupalPost('admin/config/system/sitecatalyst', $edit, t('Save configuration'));
    $this->assertText(t('This is not a valid variable name. It must start with a letter, $ or _ and cannot contain spaces.'), $message);
  }

  function testSiteCatalystTrackingCode() {
    $this->drupalGet('<front>');
    $this->assertTrackingCode();
  }

//  function testSiteCatalystVariables() {
//    // Test that variables with valid names are added properly.
//    $valid_vars = array(
//      $this->randomName(8),
//      $this->randomName(8) . '7',
//      '$' . $this->randomName(8),
//      '_' . $this->randomName(8),
//    );
//    foreach ($valid_vars as $name) {
//      $this->assertSiteCatalystVar($name, $this->randomName(8));
//    }
//
//    // Test that invalid variable names are not allowed.
//    $invalid_vars = array(
//      '7' . $this->randomName(8),
//      $this->randomName(8) . ' ' . $this->randomName(8),
//      '#' . $this->randomName(8),
//    );
//    foreach ($invalid_vars as $name) {
//      $this->assertInvalidSiteCatalystVar($name, $this->randomName(8));
//    }
//  }
//
//  function testSiteCatalystRolesTracking() {
//    variable_set('sitecatalyst_track_authenticated_user', 1);
//    variable_set('sitecatalyst_role_tracking_type', 'inclusive');
//
//    $this->drupalGet('<front>');
//    $this->assertTrackingCode();
//
//    variable_set('sitecatalyst_role_tracking_type', 'exclusive');
//    $this->drupalGet('<front>');
//    $this->assertNoTrackingCode();
//  }

}
