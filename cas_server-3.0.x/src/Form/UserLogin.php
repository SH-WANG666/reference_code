<?php

namespace Drupal\cas_server\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\cas_server\ConfigHelper;
use Drupal\cas_server\EventSubscriber\CASResponseCookies;
use Drupal\cas_server\Exception\TicketMissingException;
use Drupal\cas_server\Exception\TicketTypeException;
use Drupal\cas_server\Logger\DebugLogger;
use Drupal\cas_server\Ticket\TicketFactory;
use Drupal\cas_server\TicketStorageInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the CAS user login form.
 */
class UserLogin extends FormBase implements TrustedCallbackInterface {

  // Remove need for static::create() method.
  use AutowireTrait;

  /**
   * Constructs a new \Drupal\cas_server\Form\UserLogin object.
   */
  public function __construct(
    protected CASResponseCookies $responseCookies,
    protected ConfigHelper $configHelper,
    protected DebugLogger $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    RequestStack $request_stack,
    protected TicketStorageInterface $ticketStore,
    protected TicketFactory $ticketFactory,
    protected TimeInterface $time,
    protected UserAuthInterface $authService,
  ) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['lazyLoginTicket'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cas_server_user_login';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $service = '') {

    // Identifying credential field. Can be configured to access username,
    // email address, or both.
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->configHelper->getAuthenticationSourceFieldTitle(),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'username',
      ],
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#size' => 60,
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'current-password',
      ],
    ];

    // Use placeholder and lazy builder to add login ticket value.
    // @see https://www.drupal.org/node/2562341
    // cspell:disable-next-line
    $lt_placeholder = 'login_ticket_placeholder_Zy5k74nHY2j4zRVPY1E_Y_z0zTusGF';
    $form['lt'] = [
      '#type' => 'hidden',
      '#value' => $lt_placeholder,
    ];
    $request = $this->requestStack->getCurrentRequest();
    if ($request->getMethod() != 'POST') {
      $form['#attached']['placeholders'][$lt_placeholder] = [
        '#lazy_builder' => [self::class . '::lazyLoginTicket', []],
      ];
    }

    $form['service'] = [
      '#type' => 'hidden',
      '#value' => $service,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    if ($this->configHelper->getShowResetPassword()) {
      $form['request_password'] = [
        '#theme' => 'item_list',
        '#items' => [
          [
            '#type' => 'link',
            '#title' => $this->t('Reset your password'),
            '#url' => Url::fromRoute('user.pass', [], [
              'attributes' => [
                'title' => $this->t('Send password reset instructions via email.'),
                'class' => ['request-password-link'],
              ],
            ]),
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $username = trim($form_state->getValue('username'));
    if ($this->configHelper->getAuthenticationSourceField() != 'name') {
      $users = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['mail' => $username]);
      if ($users) {
        $user = reset($users);
        $username = $user->getAccountName();

      }
      elseif ($this->configHelper->getAuthenticationSourceField() == 'mail') {
        $form_state->setErrorByName(
          'username',
          $this->t('Invalid username or password.')
        );
        return;
      }
    }
    $password = trim($form_state->getValue('password'));
    if (!($uid = $this->authService->authenticate($username, $password))) {
      $form_state->setErrorByName(
        'username',
        $this->t('Invalid username or password.')
      );
      return;
    }

    // Get the service entity. Service should be validated before use.
    $service = $form_state->getValue('service');
    if (!empty($service)) {
      // Get and validate the service.
      $serviceEntity = $this->configHelper->loadServiceFromUri($service);
      if (!$serviceEntity) {
        $form_state->setErrorByName(
          'service',
          $this->t('Service provided is invalid for authentication here.')
        );
        return;
      }

      // Check that a user with uid is permitted to authenticate with service.
      $account = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!$serviceEntity->accountPermitted($account)) {
        $form_state->setErrorByName(
          'username',
          $this->t('Access denied: Your account does not have the required permissions to log in to this service.')
        );
        return;
      }
    }

    // Pass validated values onto submit.
    $form_state->setValue('uid', $uid);
    $form_state->setValue('username', $username);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Validate the Login Ticket.
    $raw_user_input = $form_state->getUserInput();
    try {
      // Must use the raw input to avoid form sanitation reverting the field
      // to the placeholder value.
      $lt = $this->ticketStore->retrieveLoginTicket($raw_user_input['lt']);
    }
    catch (TicketTypeException | TicketMissingException $e) {
      $this->messenger()->addError(
        $this->t('Login ticket invalid. Refresh page and try again.')
      );
      $form_state->setRedirectUrl(Url::fromRoute('cas_server.login'));

      return;
    }
    // Ticket cannot be used again.
    $this->ticketStore->deleteLoginTicket($lt);

    $service = $form_state->getValue('service');
    if ($uid = $form_state->getValue('uid')) {
      $account = $this->entityTypeManager->getStorage('user')->load($uid);

      // This call initiates a session but from D10 onwards, after the release
      // of 10.3, the call to Session::getId() returns a NULL which is not the
      // greatest unique identifier. Use an unique value inside the session for
      // all tickets instead of the session id itself. This also guarantees it
      // will be started, and that we have a predictable user identifier again.
      // @see https://www.drupal.org/node/3006306
      user_login_finalize($account);
      $this->ticketFactory->getUniqueId();

      // Add TGC if configured to do so.
      if (empty($service) || $this->configHelper->verifyServiceForSso($service)) {
        if ($this->configHelper->shouldUseTicketGrantingTicket()) {
          $tgt = $this->ticketFactory->createTicketGrantingTicket();
          $this->responseCookies->setCookie(
            'cas_tgc',
            $tgt->getId(),
            $tgt->getExpirationTime(),
            Url::fromUri('internal:/cas')->toString()
          );
        }
      }

      // Redirect user to requested service.
      if (!empty($service)) {
        $st = $this->ticketFactory->createServiceTicket($service, TRUE);
        $url = Url::fromRoute('cas_server.login', [], [
          'query' => [
            'service' => $service,
            'ticket' => $st->getId(),
          ],
        ]);
        $form_state->setRedirectUrl($url);

        return;
      }

      // No service to redirect to, show them confirmed login.
      $form_state->setRedirectUrl(Url::fromRoute('cas_server.login'));
      return;
    }

    // User has failed to log in.
    $this->messenger()->addError(
      $this->t('Bad username/password combination given.')
    );
    $form_state->setRedirectUrl(Url::fromRoute('cas_server.login'));
  }

  /**
   * Callback to lazy build the LT.
   *
   * @return array
   *   A render array containing the LT string.
   */
  public static function lazyLoginTicket() {
    $lt = \Drupal::service('cas_server.ticket_factory')->createLoginTicket();
    return [
      '#type' => 'markup',
      '#markup' => $lt->getId(),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
