<?php

namespace Drupal\cas_server;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Helper and utility class for retrieving and checking values against config.
 */
class ConfigHelper {

  /**
   * Stores settings object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * The service entity for this request.
   *
   * @var \Drupal\cas_server\CasServerServiceInterface
   */
  protected $serviceEntity = NULL;

  /**
   * The matched service URL for this request.
   *
   * @var array|null
   *   An associative array containing, NULL if not set:
   *   - path: The path component of $url. If $url is an external URL, this
   *     includes the scheme, authority, and path.
   *   - query: An array of query parameters from $url, if they exist.
   *   - fragment: The fragment component from $url, if it exists.
   */
  protected $serviceUrl = NULL;

  /**
   * The original URL provided on this request, or in a subsequent form.
   *
   * @var string
   */
  protected $serviceOriginalUrl = NULL;

  /**
   * Constructs a new ConfigHelper object.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->settings = $configFactory->get('cas_server.settings');
  }

  /**
   * Return the list of attributes to be released for a service.
   *
   * @param string $service_url
   *   A supplied service string.
   *
   * @return array
   *   An array of user field names to be released as attributes.
   */
  public function getAttributesForService($service_url) {
    if ($service = $this->loadServiceFromUri($service_url)) {
      return array_keys($service->getAttributes());
    }
    return [];
  }

  /**
   * Return the timeout for a proxy-granting ticket.
   *
   * @param int $now
   *   The expiry will be added to this to return expiry timestamp.
   *
   * @return int
   *   The number of seconds a proxy-granting ticket is valid.
   */
  public function getProxyGrantingTicketTimeout(int $now = -1) {
    $timeout = $this->settings->get('ticket.proxy_granting_ticket_timeout');
    if ($now > 0) {
      return $now + $timeout;
    }
    return $timeout;
  }

  /**
   * Return whether to use the ticket granting ticket or not.
   *
   * @return bool
   *   Whether to use the ticket granting cookie.
   */
  public function shouldUseTicketGrantingTicket() {
    return (bool) $this->settings->get('ticket.ticket_granting_ticket_auth');
  }

  /**
   * Return the timeout for a ticket-granting ticket.
   *
   * @param int $now
   *   The expiry will be added to this to return expiry timestamp.
   *
   * @return int
   *   The number of seconds a ticket-granting ticket is valid.
   */
  public function getTicketGrantingTicketTimeout(int $now = -1) {
    $timeout = $this->settings->get('ticket.ticket_granting_ticket_timeout');
    if ($now > 0) {
      return $now + $timeout;
    }
    return $timeout;
  }

  /**
   * Return the timeout for a proxy ticket.
   *
   * @param int $now
   *   The expiry will be added to this to return expiry timestamp.
   *
   * @return int
   *   The number of seconds a proxy ticket is valid.
   */
  public function getProxyTicketTimeout(int $now = -1) {
    $timeout = $this->settings->get('ticket.proxy_ticket_timeout');
    if ($now > 0) {
      return $now + $timeout;
    }
    return $timeout;
  }

  /**
   * Return the timeout for a login ticket.
   *
   * @param int $now
   *   The expiry will be added to this to return expiry timestamp.
   *
   * @return int
   *   The number of seconds a login ticket is valid.
   */
  public function getLoginTicketTimeout(int $now = -1) {
    $timeout = $this->settings->get('ticket.login_ticket_timeout') ?? 901;
    if ($now > 0) {
      return $now + $timeout;
    }
    return $timeout;
  }

  /**
   * Return the timeout for a service ticket.
   *
   * @return int
   *   The number of seconds a service ticket is valid.
   */
  public function getServiceTicketTimeout() {
    return $this->settings->get('ticket.service_ticket_timeout');
  }

  /**
   * The attribute to use for the username.
   *
   * @return string
   *   The username attribute.
   */
  public function getTicketUsernameAttribute() {
    $value = $this->settings->get('ticket.ticket_username_attribute');
    return $value ? $value : 'name';
  }

  /**
   * Return the custom not permitted message, or FALSE if not set.
   *
   * @return string|bool
   *   The configured service not permitted message.
   */
  public function getNotPermittedMessage() {
    if (!empty($m = $this->settings->get('messages.not_permitted'))) {
      return $m;
    }
    return FALSE;
  }

  /**
   * Check whether a service is configured for single sign on.
   *
   * @param string $service_url
   *   The service uri to check.
   *
   * @return bool
   *   Whether or not the service is authorized.
   */
  public function verifyServiceForSso($service_url) {
    if ($service = $this->loadServiceFromUri($service_url)) {
      return $service->getSso();
    }
    return FALSE;
  }

  /**
   * Return the custom invalid service message, or FALSE.
   *
   * @return string|bool
   *   The configured invalid service message.
   */
  public function getInvalidServiceMessage() {
    if (!empty($m = $this->settings->get('messages.invalid_service'))) {
      return $m;
    }
    return FALSE;
  }

  /**
   * Return the custom user logout message, or FALSE.
   *
   * @return string|bool
   *   The configured user logout message.
   */
  public function getUserLogoutMessage() {
    if (!empty($m = $this->settings->get('messages.user_logout'))) {
      return $m;
    }
    return FALSE;
  }

  /**
   * Return the custom logged in message, or FALSE.
   *
   * @return string|bool
   *   The configured log in message.
   */
  public function getLoggedInMessage() {
    if (!empty($m = $this->settings->get('messages.logged_in'))) {
      return $m;
    }
    return FALSE;
  }

  /**
   * The attribute to use for authentication.
   *
   * @return string
   *   The username attribute.
   */
  public function getAuthenticationSourceField() {
    $value = $this->settings->get('login.username_attribute');
    return $value ? $value : 'name';
  }

  /**
   * The title of the login field on UserLogin form.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated title.
   */
  public function getAuthenticationSourceFieldTitle() {
    $title = NULL;

    $username_field = $this->getAuthenticationSourceField();
    switch ($username_field) {
      case 'both':
        $title = new TranslatableMarkup('Username or email address');
        break;

      case 'mail':
        $title = new TranslatableMarkup('Email address');
        break;

      default:
        $title = new TranslatableMarkup('Username');
    }

    return $title;
  }

  /**
   * Show reset password link on login form.
   *
   * @return bool
   *   Whether or not to show the reset password link.
   */
  public function getShowResetPassword() {
    $value = $this->settings->get('login.reset_password');
    return (bool) $value;
  }

  /**
   * Get the service entity given the service parameter.
   *
   * The method sets the internal values for serviceEntity, serviceUrl and
   * serviceOriginalUrl, used while processing requests.
   *
   * @param ?string $service_url
   *   The provided service string.
   * @param bool $reset
   *   If the stored internal value should be re-evaluated from service url.
   *
   * @return \Drupal\cas_server\CasServerServiceInterface|bool
   *   A matching CasServerService object or FALSE if no match.
   */
  public function loadServiceFromUri(
    ?string $service_url,
    bool $reset = FALSE,
  ): CasServerServiceInterface|bool {

    if (empty($service_url)) {
      // Clear internal state too.
      $this->clearServiceVariables();
      return FALSE;
    }

    if (!$reset && !is_null($this->serviceEntity)) {
      return $this->serviceEntity;
    }

    $entity_manager = $this->entityTypeManager
      ->getStorage('cas_server_service');
    $service_ids = $entity_manager->getQuery()->accessCheck(FALSE)->execute();

    $service_entities = $entity_manager->loadMultiple($service_ids);
    foreach ($service_entities as $service) {
      if ($matched_url = $service->matches($service_url)) {

        $this->serviceUrl = $matched_url;
        $this->serviceOriginalUrl = $service;
        return $this->serviceEntity = $service;
      }
    }

    $this->serviceUrl = FALSE;
    $this->serviceOriginalUrl = FALSE;
    return $this->serviceEntity = FALSE;
  }

  /**
   * Get the matched service entity.
   *
   * @throws \Exception
   *   If called called prior to loadServiceFromUri call to set variables.
   *
   * @return \Drupal\cas_server\CasServerServiceInterface|bool
   *   The service entity matched from the service url.
   */
  public function getServiceEntity(): CasServerServiceInterface|bool {
    if (!is_null($this->serviceEntity)) {
      return $this->serviceEntity;
    }

    return FALSE;
  }

  /**
   * Get all or part of the parse service url.
   *
   * @param ?string $part
   *   The part to return, or NULL to return all.
   *
   * @return array|bool
   *   An associative array containing, FALSE if fails:
   *   - path: The path component of $url. If $url is an external URL, this
   *     includes the scheme, authority, and path.
   *   - query: An array of query parameters from $url, if they exist.
   *   - fragment: The fragment component from $url, if it exists.
   */
  public function getServiceUrl(?string $part = NULL): array|bool {
    if (!is_null($this->serviceUrl)) {
      if (!is_null($part)) {
        if (in_array($part, ['path', 'query', 'fragment'])) {
          return $this->serviceUrl[$part];
        }

        throw new \InvalidArgumentException('Not valid url parse part.');
      }

      return $this->serviceUrl;
    }

    return FALSE;
  }

  /**
   * Clear internal service variables for this request.
   */
  protected function clearServiceVariables(): void {
    $this->serviceEntity = $this->serviceUrl = $this->serviceOriginalUrl = NULL;
  }

  /**
   * Validate that the given value is a valid attribute name.
   *
   * @param array|string|null $value
   *   The array to validate.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validation execution context.
   */
  public static function validateAttributesConfig(
    array|string|null $value,
    ExecutionContextInterface $context,
  ): void {
    if ($value === NULL) {
      return;
    }

    // Get the list of valid values.
    static $fields = FALSE;
    if ($fields === FALSE) {
      $entityFieldManager = \Drupal::service('entity_field.manager');
      $fields = array_keys($entityFieldManager->getFieldDefinitions('user', 'user'));
    }

    // Convert string to array.
    if (!is_array($value)) {
      $value = [$value];
    }

    // Check each of the values to ensure they are valid.
    foreach ($value as $item) {
      if (!in_array($item, $fields)) {
        $context->addViolation(
          'The attribute %attribute is not valid option.', [
            '%attribute' => $item,
          ]
        );
      }
    }
  }

}
