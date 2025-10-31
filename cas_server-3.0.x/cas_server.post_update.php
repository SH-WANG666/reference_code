<?php

/**
 * @file
 * The CAS Server module post_update functions file.
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\cas_server\CasServerPermissions;

/**
 * Grant "cas server login to any service" if update 8002 was missed.
 */
function cas_server_post_update_grant_service_permissions(&$sandbox) {
  // NB: This replaces the 8002 update which was most likely not triggered on
  // the 2.0.x branch releases due to the minimum core requirement of 9.1. Do
  // not grant permissions if admin has granted any in the mean time.
  $entity_type_manager = \Drupal::entityTypeManager();
  $perm_provider = new CasServerPermissions($entity_type_manager);
  $cas_service_permissions = array_keys($perm_provider->servicePermissions());

  $grant_any_service = TRUE;
  if (!empty($cas_service_permissions)) {
    // Add the wildcard permission.
    $cas_service_permissions[] = 'cas server login to any service';

    // Load all of the roles and check each to see if they have any cas perms.
    $roles = $entity_type_manager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role) {
      $role_permissions = $role->getPermissions();
      if (array_intersect($cas_service_permissions, $role_permissions)) {
        $grant_any_service = FALSE;
        break;
      }
    }
  }

  // If no permissions have been granted, grant login to any service to all.
  if ($grant_any_service) {
    user_role_grant_permissions(
      'authenticated', [
        'cas server login to any service',
      ]
    );

    return new TranslatableMarkup('CAS Server login to any service permission has been granted to all users. Review permissions as services can be restricted by role.');
  }

  return new TranslatableMarkup('Existing CAS Server permission grants left unchanged.');
}
