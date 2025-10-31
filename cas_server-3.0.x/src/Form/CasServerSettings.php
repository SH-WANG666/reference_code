<?php

namespace Drupal\cas_server\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;

/**
 * Configuration form for CAS Server settings.
 */
class CasServerSettings extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * Maximum is equivalent to 32 bit PHP_MAX_INT. Or approx 68 years.
   *
   * @var integer
   */
  const CAS_SETTING_MAX_INT = 2147483647;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cas_server_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['ticket'] = [
      '#type' => 'details',
      '#title' => $this->t('Ticket settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['ticket']['login'] = [
      '#type' => 'number',
      '#title' => $this->t('Login ticket timeout'),
      '#description' => $this->t('Time in seconds for which a login ticket is valid, This determines how long a user has to submit the login form before it is invalid.'),
      '#size' => 30,
      '#min' => 1,
      '#max' => self::CAS_SETTING_MAX_INT,
      '#config_target' => 'cas_server.settings:ticket.login_ticket_timeout',
    ];
    $form['ticket']['service'] = [
      '#type' => 'number',
      '#title' => $this->t('Service ticket timeout'),
      '#description' => $this->t('Time in seconds for which a service ticket is valid.'),
      '#size' => 30,
      '#min' => 0,
      '#max' => self::CAS_SETTING_MAX_INT,
      '#config_target' => 'cas_server.settings:ticket.service_ticket_timeout',
    ];
    $form['ticket']['proxy'] = [
      '#type' => 'number',
      '#title' => $this->t('Proxy ticket timeout'),
      '#description' => $this->t('Time in seconds for which a proxy ticket is valid.'),
      '#size' => 30,
      '#min' => 0,
      '#max' => self::CAS_SETTING_MAX_INT,
      '#config_target' => 'cas_server.settings:ticket.proxy_ticket_timeout',
    ];
    $form['ticket']['proxy_granting'] = [
      '#type' => 'number',
      '#title' => $this->t('Proxy granting ticket timeout'),
      '#description' => $this->t('Time in seconds for which a proxy granting ticket is valid.'),
      '#size' => 30,
      '#min' => 0,
      '#max' => self::CAS_SETTING_MAX_INT,
      '#config_target' => 'cas_server.settings:ticket.proxy_granting_ticket_timeout',
    ];
    $form['ticket']['ticket_granting_auth'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use ticket granting ticket'),
      '#description' => $this->t('When checked, a user will be granted a ticket for login. If a user is logged in to Drupal, but does not have the ticket (or it has expired) then checking this will force them to enter their credentials again.'),
      '#size' => 30,
      '#config_target' => 'cas_server.settings:ticket.ticket_granting_ticket_auth',
    ];
    $form['ticket']['ticket_granting'] = [
      '#type' => 'number',
      '#title' => $this->t('Ticket granting ticket timeout'),
      '#description' => $this->t('Time in seconds for which a ticket granting ticket is valid.'),
      '#size' => 30,
      '#min' => 0,
      '#max' => self::CAS_SETTING_MAX_INT,
      '#config_target' => 'cas_server.settings:ticket.ticket_granting_ticket_timeout',
      '#states' => [
        'visible' => [
          ':input[name="ticket[ticket_granting_auth]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['ticket']['username'] = [
      '#type' => 'select',
      '#title' => $this->t('Username value'),
      '#description' => $this->t('Which value to use for the username to respond.'),
      '#options' => [
        'name' => $this->t('Username'),
        'mail' => $this->t('Email Address'),
        'uid' => $this->t('UID'),
      ],
      '#config_target' => 'cas_server.settings:ticket.ticket_username_attribute',
    ];

    $form['messages'] = [
      '#type' => 'details',
      '#title' => 'Custom Messages',
      '#open' => FALSE,
      '#tree' => TRUE,
    ];
    $form['messages']['invalid_service'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display message for Invalid Service'),
      '#description' => $this->t('Message to display to a user requesting an invalid service.'),
      '#size' => 60,
      '#config_target' => 'cas_server.settings:messages.invalid_service',
    ];
    $form['messages']['not_permitted'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display message for Not Permitted'),
      '#description' => $this->t("Message to display to a user requesting a service that they don't have permission to log in to."),
      '#size' => 60,
      '#config_target' => 'cas_server.settings:messages.not_permitted',
    ];
    $form['messages']['user_logout'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display message for User Logout'),
      '#description' => $this->t('Message to display to a user logged out of single sign on.'),
      '#size' => 60,
      '#config_target' => 'cas_server.settings:messages.user_logout',
    ];
    $form['messages']['logged_in'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display message for Logged In Users'),
      '#description' => $this->t('Message to display to a user already logged in to single sign on.'),
      '#size' => 60,
      '#config_target' => 'cas_server.settings:messages.logged_in',
    ];

    $form['debugging'] = [
      '#type' => 'details',
      '#title' => 'Debugging Options',
      '#open' => FALSE,
      '#tree' => TRUE,
    ];
    $form['debugging']['log'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging log'),
      '#description' => $this->t('Enable debugging output to the Drupal log service.'),
      '#config_target' => 'cas_server.settings:debugging.log',
    ];

    $form['login'] = [
      '#type' => 'details',
      '#title' => 'Login Options',
      '#open' => FALSE,
      '#tree' => TRUE,
    ];
    $form['login']['username'] = [
      '#type' => 'select',
      '#title' => $this->t('Username field'),
      '#description' => $this->t('Which field to use for user authentication.'),
      '#options' => [
        'name' => $this->t('Username'),
        'mail' => $this->t('Email Address'),
        'both' => $this->t('Username or email address'),
      ],
      '#config_target' => 'cas_server.settings:login.username_attribute',
    ];
    $form['login']['reset_password'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show reset password link on cas login form'),
      '#config_target' => 'cas_server.settings:login.reset_password',
    ];

    return parent::buildForm($form, $form_state);
  }

}
