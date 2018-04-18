<?php

namespace Drupal\adobe_analytics;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\system\Entity\Menu;

/**
 * Class to provide helpful function.
 */
class AdobeAnalyticsHelper {

  // To allow tracking by the AdobeAnalytics package.
  const ADOBEANALYTICS_TOKEN_CACHE = 'adobe_analytics:tag_token_results';

  /**
   * The CurrentRouteMatch service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The ModuleHandler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Token replacement object.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Array of variables.
   *
   * @var array
   */
  protected $variables;

  /**
   * Context array.
   *
   * @var array
   */
  protected $context;

  /**
   * Current user object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Adobe config settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs an AdobeAnalyticsHelper object.
   */
  public function __construct(CurrentRouteMatch $currentRouteMatch, ModuleHandlerInterface $moduleHandler, Token $token, AccountProxyInterface $current_user, ConfigFactory $config_factory) {
    $this->currentRouteMatch = $currentRouteMatch;
    $this->moduleHandler = $moduleHandler;
    $this->token = $token;
    $this->currentUser = $current_user;
    $this->config = $config_factory->get('adobe_analytics.settings');
  }

  /**
   * Get the context.
   */
  public function adobeAnalyticsGetTokenContext() {

    if (is_null($this->context)) {
      $this->context['node'] = $this->currentRouteMatch->getParameter('node');
      $this->context['term'] = ($this->currentRouteMatch->getParameter('taxonomy_term')) ? $this->currentRouteMatch->getParameter('taxonomy_term') : 2;
      $this->context['menu'] = Menu::load('main-menu');
    }

    return $this->context;
  }

  /**
   * Replace tokens.
   */
  public function adobeAnalyticsTokenReplace($text, $data = [], array $options = []) {

    // Short-circuit the degenerate case, just like token_replace() does.
    $text_tokens = $this->token->replace($text);
    if (!empty($text_tokens)) {
      return $text_tokens;
    }
  }

  /**
   * Format the variables like key value pair;.
   */
  public function adobeAnalyticsFormatVariables($variables = []) {

    $extra_variables = $this->getVariables();

    // Create context data to be used by token.
    $variables_formatted = '';
    foreach ($variables as $key => $value) {
      if (is_array($value)) {
        // Use the last element.
        $value = end($value);
      }

      if (isset($extra_variables[$key])) {
        $value = $extra_variables[$key];
      }

      $key = htmlspecialchars($key, ENT_NOQUOTES, 'UTF-8');
      $value = $this->adobeAnalyticsTokenReplace($value);
      $variables_formatted .= "{$key}=\"{$value}\";\n";
    }
    return $variables_formatted;
  }

  /**
   * Used to replace the value.
   *
   * AdobeAnalytics variables the variables need to be
   * defined with hook_adobe_analytics_variables().
   *
   * @param string $name
   *   Extra variable name.
   * @param string $value
   *   Value of the the name variable.
   */
  public function setVariable($name = NULL, $value = NULL) {
    if (!empty($name)) {
      $this->variables[$name] = $value;
    }
  }

  /**
   * Return variables.
   *
   * @return array
   *   The array of variables.
   */
  public function getVariables() {
    return $this->variables;
  }

  /**
   * Lazy builder callback to render markup.
   *
   * @return array
   *   Build array.
   */
  public function renderMarkup() {
    if ($this->skipTracking()) {
      return [];
    }

    $js_file_location = $this->config->get('js_file_location');
    $codesnippet = $this->config->get('codesnippet');
    $version = $this->config->get("version");
    $nojs = !empty($this->config->get("image_file_location")) ? $this->config->get("image_file_location") : NULL;

    // Format and combine variables in the "right" order
    // Right order is the code file (list likely to be maintained)
    // Then admin settings with codesnippet first and finally taxonomy->vars.
    $formatted_vars = '';
    $adobe_analytics_hooked_vars = \Drupal::moduleHandler()->invokeAll('adobe_analytics_variables', []);

    if (!empty($adobe_analytics_hooked_vars['header'])) {
      $formatted_vars = $this->adobeAnalyticsFormatVariables($adobe_analytics_hooked_vars['header']);
    }

    if (!empty($codesnippet)) {
      // Add any custom code snippets if specified and replace any tokens.
      $context = $this->adobeAnalyticsGetTokenContext();
      $formatted_vars .= $this->adobeAnalyticsTokenReplace(
          $this->config->get('codesnippet'), $context, [
            'clear' => TRUE,
            'sanitize' => TRUE,
          ]
        ) . "\n";
    }

    if (!empty($adobe_analytics_hooked_vars['variables'])) {
      $formatted_vars .= $this->adobeAnalyticsFormatVariables($adobe_analytics_hooked_vars['variables']);
    }

    if (!empty($adobe_analytics_hooked_vars['footer'])) {
      $formatted_vars .= $this->adobeAnalyticsFormatVariables($adobe_analytics_hooked_vars['footer']);
    }

    $build = [
      '#theme' => 'analytics_code',
      '#js_file_location' => $js_file_location,
      '#version' => $version,
      '#image_location' => $nojs,
      '#formatted_vars' => $formatted_vars,
    ];

    return $build;
  }

  /**
   * Determines whether or not to skip adding analytics code.
   */
  public function skipTracking() {
    // Check if we should track the currently active user's role.
    $track_user = TRUE;
    $get_roles = [];
    $tracking_type = $this->config->get('role_tracking_type');
    $stored_roles = $this->config->get('track_roles');
    if ($stored_roles) {
      $get_roles = [];
      foreach ($stored_roles as $key => $value) {
        if ($value) {
          // Get all the selected roles.
          $get_roles[$key] = $key;
        }
      }
    }

    // Compare the roles with current user.
    if (is_array($this->currentUser->getRoles())) {
      foreach ($this->currentUser->getRoles() as $role) {
        if (array_key_exists($role, $get_roles)) {
          if ($tracking_type == 'inclusive') {
            $track_user = TRUE;
          }
          if ($tracking_type == 'exclusive') {
            $track_user = FALSE;
          }
          break;
        }
      }
    }

    // Don't track page views in the admin sections, or for certain roles.
    $is_admin = \Drupal::service('router.admin_context')->isAdminRoute();
    if ($is_admin || $track_user == FALSE) {
      return TRUE;
    }

    return FALSE;
  }

}
