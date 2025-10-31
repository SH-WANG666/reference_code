<?php

namespace Drupal\cas_server;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an interface for defining a CasServerService.
 */
interface CasServerServiceInterface extends ConfigEntityInterface {

  /**
   * Get the machine name.
   */
  public function getId();

  /**
   * Set the machine name.
   *
   * @param string $machine_name
   *   The machine-readable name.
   */
  public function setId($machine_name);

  /**
   * Get the label.
   */
  public function getLabel();

  /**
   * Set the label.
   *
   * @param string $label
   *   The label.
   */
  public function setLabel($label);

  /**
   * Get the service definition pattern.
   */
  public function getService();

  /**
   * Set the service definition pattern.
   *
   * @param string $service
   *   A service string pattern.
   */
  public function setService($service);

  /**
   * Get the single sign on status.
   */
  public function getSso();

  /**
   * Set the single sign on status.
   *
   * @param bool $status
   *   Whether or not the service is SSO enabled.
   */
  public function setSso($status);

  /**
   * Get the released attribute names.
   */
  public function getAttributes();

  /**
   * Set the released attributes.
   *
   * @param array $attributes
   *   A list of user field machine names to release as attributes.
   */
  public function setAttributes($attributes);

  /**
   * Checks if account has permission to use this service.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   */
  public function accountPermitted(AccountInterface $account);

  /**
   * Check and return url parts if this service matches the given service url.
   *
   * @param string $service_url
   *   The service url passed as part of the request.
   *
   * @return array|bool
   *   An associative array containing, FALSE if fails:
   *   - path: The path component of $url. If $url is an external URL, this
   *     includes the scheme, authority, and path.
   *   - query: An array of query parameters from $url, if they exist.
   *   - fragment: The fragment component from $url, if it exists.
   */
  public function matches(string $service_url);

}
