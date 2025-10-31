<?php

namespace Drupal\cas_server\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\user\RoleInterface;

/**
 * Add or Edit CasServerService entity.
 */
class ServicesForm extends EntityForm {

  // Remove need for static::create() method.
  use AutowireTrait;

  /**
   * Constructs a new ServicesForm object.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $service = $this->entity;

    // Form API stuff here.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $service->getLabel(),
      '#description' => $this->t('Label for the Service definition'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $service->getId(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
      '#disabled' => !$service->isNew(),
    ];

    $form['service'] = [
      '#type' => 'textfield',
      '#default_value' => $service->getService(),
      '#title' => $this->t('Service URL Pattern'),
      '#size' => 60,
      '#maxlength' => 255,
      '#required' => TRUE,
      '#description' => $this->t('Pattern to match service urls with. * is a wildcard.'),
    ];

    $form['sso'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Participate in single sign on?'),
      '#description' => $this->t('When enabled users will be issued a Ticket Granting Cookie (TGC) as part of their Single Sign On (SSO). The user will not need to provide credentials again while it is active.'),
      '#default_value' => $service->getSso(),
    ];

    $fields = array_keys($this->entityFieldManager->getFieldDefinitions('user', 'user'));
    $options = array_combine($fields, $fields);
    $form['attributes'] = [
      '#type' => 'select',
      '#title' => 'Released attributes',
      '#description' => $this->t('Fields to release as CAS attributes.'),
      '#multiple' => TRUE,
      '#default_value' => $service->getAttributes(),
      '#options' => $options,
    ];

    $form['restrict_roles'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Restrict login to roles'),
    ];

    // The current user must have 'administer permissions' on their role to be
    // able to set any of the permissions for a service.
    $can_set_roles = $this->currentUser
      ->hasPermission('administer permissions');

    // Do not show form inputs for users without adequate permissions.
    if (!$can_set_roles) {
      $form['restrict_roles']['info'] = [
        '#type' => 'markup',
        '#markup' => $this->t("Your account requires the 'administer permissions' permission to adjust these settings."),
      ];
      $form['restrict_roles']['#open'] = FALSE;
    }

    // Load all of the roles as they need to be checked for allow all and
    // also for form options. Remove the anonymous user.
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    unset($roles[RoleInterface::ANONYMOUS_ID]);

    // If there is an administrator role set on the site, remove that from the
    // options too as there's no point attempting to grant/revoke on that role
    // since it is locked to granted.
    $admin_note = NULL;
    $admin_roles = $this->entityTypeManager->getStorage('user_role')->getQuery()
      ->condition('is_admin', TRUE)
      ->execute();
    if ($admin_roles) {
      foreach ($admin_roles as $role_id) {
        unset($roles[$role_id]);
      }
      $admin_note = $this->t(
        'The administer role is automatically granted access: @admin_roles', [
          '@admin_roles' => implode(', ', $admin_roles),
        ]
      );
    }

    // Check if any roles have "accept all". And check and build role options.
    $any_service_roles = [];
    $role_options = [];
    $allowed_roles = [];
    foreach ($roles as $role_id => $role) {
      // Build options list.
      $role_options[$role_id] = $this->t('@role_label', [
        '@role_label' => $role->label(),
      ]);

      // Get list of roles which can login to any service.
      if ($role->hasPermission('cas server login to any service')) {
        $any_service_roles[$role_id] = $role_id;
      }

      // Get list of roles which can login to this service.
      if (!$service->isNew()) {
        if ($role->hasPermission("cas server login to {$service->id()} service")) {
          $allowed_roles[$role_id] = $role_id;
        }
      }
    }

    if ($can_set_roles) {
      // Display disabled choice for any service and link to the permissions to
      // change them.
      $form['restrict_roles']['accept_all'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Roles which can login to <em>any</em> service'),
        '#description' => $this->t(
          'This setting may be changed on the system @mod_permissions page.',
          [
            '@mod_permissions' => Link::fromTextAndUrl(
              'module permissions',
              Url::fromRoute('user.admin_permissions.module', [
                'modules' => 'cas_server',
              ])
            )->toString(),
          ]
        ),
        '#options' => $role_options,
        '#default_value' => $any_service_roles,
        '#disabled' => TRUE,
      ];

      // Choice of roles which can log into this service.
      $form['restrict_roles']['allowed_roles_existing'] = [
        '#type' => 'value',
        '#value' => $allowed_roles,
      ];
      $form['restrict_roles']['allowed_roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Roles to authenticate with this service'),
        '#description' => implode(' ', array_filter([
          $this->t('Users with a matching role will be permitted to log in using this service. The <em>login to any service</em> is checked before this permission.'),
          $admin_note,
        ])),
        '#options' => $role_options,
        '#default_value' => $allowed_roles,
        '#disabled' => !$can_set_roles,
      ];
    }
    // Provide the values to the submit handler without user intervention.
    else {
      $form['restrict_roles']['allowed_roles'] = [
        '#type' => 'value',
        '#value' => $allowed_roles,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $service = $this->entity;
    $status = $service->save();

    $op = NULL;
    switch ($status) {
      case SAVED_NEW:
        $op = $this->t('Saved');
        break;

      case SAVED_UPDATED:
        $op = $this->t('Updated');
        break;

      default:
        // Never reached as the storage service would die first.
        throw new \Exception('Failed to save Service entity');
    }

    // Set permissions for the service if they have changed.
    $existing = $form_state->getValue('allowed_roles_existing');
    $allowed = array_filter($form_state->getValue('allowed_roles'));
    if ($existing != $allowed) {
      $merged_roles = array_merge($existing, $allowed);
      foreach ($merged_roles as $role_id) {
        if (isset($existing[$role_id]) && !isset($allowed[$role_id])) {
          user_role_revoke_permissions(
            $role_id,
            ["cas server login to {$service->id()} service"]
          );
        }
        elseif (isset($allowed[$role_id]) && !isset($existing[$role_id])) {
          user_role_grant_permissions(
            $role_id,
            ["cas server login to {$service->id()} service"]
          );
        }
      }
    }

    $this->messenger()->addStatus($this->t('@result the %label Service.', [
      '@result' => $op,
      '%label' => $service->getLabel(),
    ]));

    $form_state->setRedirect('entity.cas_server_service.collection');

    return $status;
  }

  /**
   * Unique machine name callback.
   */
  public function exists($id) {
    $service_storage = $this->entityTypeManager
      ->getStorage('cas_server_service');
    $entity = $service_storage->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
    return (bool) $entity;
  }

}
