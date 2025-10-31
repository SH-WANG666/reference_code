<?php

namespace Drupal\cas_server;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Generate dynamic permissions for CAS services.
 */
class CasServerPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  // Remove need for static::create() method.
  use AutowireTrait;

  /**
   * Constructs a new CasServerPermissions object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Returns an array of permissions for sign in with available services.
   *
   * @return array
   *   The permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function servicePermissions() {
    $permissions = [];

    $services = $this->entityTypeManager
      ->getStorage('cas_server_service')
      ->loadMultiple();
    foreach ($services as $service) {
      $permissions["cas server login to {$service->id()} service"] = [
        'title' => $this->t('Allow CAS login to %service_name service', [
          '%service_name' => $service->label(),
        ]),
      ];
    }

    return $permissions;
  }

}
