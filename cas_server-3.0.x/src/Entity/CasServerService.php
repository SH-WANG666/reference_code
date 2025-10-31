<?php

namespace Drupal\cas_server\Entity;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\cas_server\CasServerServiceInterface;

/**
 * Defines a CasServerService entity.
 *
 * @ConfigEntityType(
 *   id = "cas_server_service",
 *   label = @Translation("Cas Server Service"),
 *   handlers = {
 *     "list_builder" = "Drupal\cas_server\Entity\CasServerServiceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\cas_server\Form\ServicesForm",
 *       "edit" = "Drupal\cas_server\Form\ServicesForm",
 *       "delete" = "Drupal\cas_server\Form\ServicesDeleteForm",
 *     }
 *   },
 *   config_prefix = "cas_server_service",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/people/cas_server/{cas_server_service}",
 *     "delete-form" = "/admin/config/people/cas_server/{cas_server_service}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "service",
 *     "sso",
 *     "attributes",
 *   }
 * )
 */
class CasServerService extends ConfigEntityBase implements CasServerServiceInterface {

  /**
   * The machine id.
   *
   * @var string
   */
  public $id;

  /**
   * The label.
   *
   * @var string
   */
  public $label;

  /**
   * The service URL pattern.
   *
   * @var string
   */
  public $service;

  /**
   * Single sign on status.
   *
   * @var bool
   */
  public $sso;

  /**
   * Attributes to release.
   *
   * @var array
   */
  public $attributes;

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function setId($new_id) {
    $this->id = $new_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($new_label) {
    $this->label = $new_label;
  }

  /**
   * {@inheritdoc}
   */
  public function getService() {
    return $this->service;
  }

  /**
   * {@inheritdoc}
   */
  public function setService($new_service) {
    $this->service = $new_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getSso() {
    return $this->sso;
  }

  /**
   * {@inheritdoc}
   */
  public function setSso($new_sso) {
    $this->sso = $new_sso;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes() {
    return $this->attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function setAttributes($new_attributes) {
    return $this->attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function accountPermitted(AccountInterface $account) {

    if ($account->hasPermission('cas server login to any service')) {
      return TRUE;
    }

    if ($account->hasPermission("cas server login to {$this->id()} service")) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function matches(string $service_url) {
    // Parse url into scheme, hostname and path for matching.
    $url = UrlHelper::parse($service_url);

    if (is_null($url['path'])) {
      return FALSE;
    }
    if (!UrlHelper::isValid($url['path'])) {
      return FALSE;
    }

    $pattern = str_replace(
      '\*', '.*',
      '/^' . preg_quote($this->service, '/') . '$/'
    );
    if (preg_match($pattern, $url['path'])) {
      return $url;
    }

    return FALSE;
  }

}
