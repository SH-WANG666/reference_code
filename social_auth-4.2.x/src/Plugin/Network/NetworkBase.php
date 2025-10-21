<?php

namespace Drupal\social_auth\Plugin\Network;

use Drupal\Core\Url;
use Drupal\social_api\Plugin\NetworkBase as SocialApiNetworkBase;
use Drupal\social_api\SocialApiException;
use Drupal\social_auth\Settings\SettingsInterface;

/**
 * Defines a Network Plugin for Social Auth.
 */
abstract class NetworkBase extends SocialApiNetworkBase implements NetworkInterface {

  /**
   * {@inheritdoc}
   */
  protected function initSdk(): mixed {
    /** @var \Drupal\social_auth\Plugin\Network\NetworkInterface $network */
    $network = $this->networkManager->createInstance($this->pluginId);
    $class_name = $network->getProviderClassName();
    if (!class_exists($class_name)) {
      throw new SocialApiException("Library class not found: {$class_name}");
    }

    // This default implementation assumes the network class extends from a
    // League abstract provided (accepting a single `$options` array).
    // Implementors using non-League network classes will need to override this
    // method.
    if ($this->validateConfig($network, $this->settings)) {
      $league_settings = [
        'clientId' => $this->settings->getClientId(),
        'redirectUri' => $this->getCallbackUrl()->setAbsolute()->toString(),
      ];
      if (!$network->usesPublicClientId()) {
        $league_settings['clientSecret'] = $this->settings->getClientSecret();
      }

      $league_settings += $this->getExtraSdkSettings();

      // Proxy configuration data for outward proxy.
      $proxyUrl = $this->siteSettings->get('http_client_config')['proxy']['http'] ?? NULL;
      if ($proxyUrl) {
        $league_settings['proxy'] = $proxyUrl;
      }
      return new $class_name($league_settings);
    }

    throw new SocialApiException('Module configuration validation failed -- verify client ID and secret settings.');
  }

  /**
   * Gets additional settings for the network class.
   *
   * Implementors can declare and use this method to augment the settings array
   * passed to constructors for libraries that extend from a League abstract
   * provider.
   *
   * @return array
   *   Key-value pairs for extra settings to pass to the provider class
   *   constructor.
   *
   * @see \Drupal\social_auth\Plugin\Network\NetworkBase::initSdk()
   */
  protected function getExtraSdkSettings(): array {
    return [];
  }

  /**
   * Checks that module is configured.
   *
   * @param \Drupal\social_auth\Plugin\Network\NetworkInterface $network
   *   Social Auth implementor.
   * @param \Drupal\social_auth\Settings\SettingsInterface $settings
   *   Social Auth implementor settings.
   *
   * @return bool
   *   True if module is configured. False otherwise.
   */
  protected function validateConfig(NetworkInterface $network, SettingsInterface $settings): bool {
    if (!$settings->getClientId()) {
      $this->loggerFactory
        ->get($this->pluginId)
        ->error('Define client ID in module settings.');
      return FALSE;
    }
    if (!$network->usesPublicClientId() && !$settings->getClientSecret()) {
      $this->loggerFactory
        ->get($this->pluginId)
        ->error('Define client secret in module settings.');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl(array $route_options = []): Url {
    return $this->getUrlFromDefaultRoute('redirect', $route_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getCallbackUrl(array $route_options = []): Url {
    return $this->getUrlFromDefaultRoute('callback', $route_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsFormUrl(array $route_options = []): Url {
    return $this->getUrlFromDefaultRoute('settings_form', $route_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderLogoPath(): string {
    $network = $this->networkManager->getDefinition($this->pluginId);
    $module_path = $this->networkManager->getModuleHandler()->getModule($network['provider'])->getPath();
    return base_path() . "$module_path/{$this->getImagePath()}";
  }

  /**
   * Gets a URL using the default routes provided by Social Auth.
   *
   * @param string $type
   *   Route type.
   * @param array $route_options
   *   Route options.
   *
   * @return \Drupal\Core\Url
   *   URL object for the requested route type.
   */
  private function getUrlFromDefaultRoute(string $type, array $route_options = []): Url {
    return Url::fromRoute(
      $this->getRoutes()[$type],
      ['network' => $this->getShortName()],
      $route_options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginDefinition()['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getShortName(): string {
    return $this->getPluginDefinition()['short_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSocialNetwork(): string {
    return $this->getPluginDefinition()['social_network'];
  }

  /**
   * {@inheritdoc}
   */
  public function getImagePath(): string {
    return $this->getPluginDefinition()['img_path'];
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->getPluginDefinition()['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderClassName(): string {
    return $this->getPluginDefinition()['class_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthManagerClassName(): string {
    return $this->getPluginDefinition()['auth_manager'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(): array {
    return $this->getPluginDefinition()['routes'];
  }

  /**
   * {@inheritdoc}
   */
  final public function getHandlers(): array {
    return $this->getPluginDefinition()['handlers'];
  }

  /**
   * {@inheritdoc}
   */
  public function usesPublicClientId(): bool {
    return $this->getPluginDefinition()['uses_public_client_id'] ?? FALSE;
  }

}
