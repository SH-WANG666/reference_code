<?php

namespace Drupal\cas_server\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\cas_server\ConfigHelper;
use Drupal\cas_server\EventSubscriber\CASResponseCookies;
use Drupal\cas_server\Exception\TicketMissingException;
use Drupal\cas_server\Exception\TicketTypeException;
use Drupal\cas_server\Logger\DebugLogger;
use Drupal\cas_server\RedirectResponse;
use Drupal\cas_server\Ticket\TicketFactory;
use Drupal\cas_server\TicketStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Process user input into actions.
 */
class UserActionController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  // Remove need for static::create() method.
  use AutowireTrait;

  /**
   * Constructs a new UserActionController object.
   */
  public function __construct(
    protected CASResponseCookies $responseCookies,
    protected ConfigHelper $configHelper,
    protected DebugLogger $logger,
    protected TicketStorageInterface $ticketStore,
    protected TicketFactory $ticketFactory,
    protected AccountProxyInterface $currentUser,
    protected TimeInterface $time,
    protected FormBuilderInterface $formBuilder,
    protected KillSwitch $killSwitch,
    protected RequestStack $requestStack,
    TranslationInterface $translation,
  ) {
    // Property is defined in StringTranslationTrait.
    $this->stringTranslation = $translation;
  }

  /**
   * Handles a page request for /cas/login.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|array
   *   A trusted redirect back to the service, a message page, or login form.
   */
  public function login(): TrustedRedirectResponse|array {
    $this->killSwitch->trigger();
    $request = $this->requestStack->getCurrentRequest();
    $service = $request->query->has('service') ? $request->query->get('service') : NULL;
    $service_entity = $this->configHelper->loadServiceFromUri($service);

    // If we have a ticket, it is because we've already processed the form and
    // need to be redirected back to the service.
    if ($request->query->has('ticket') && $service_entity !== FALSE) {
      $url = Url::fromUri($service, ['query' => ['ticket' => $request->query->get('ticket')]]);
      $response = new TrustedRedirectResponse($url->toString(), 302);
      $response
        ->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));
      return $response;
    }

    // Get special CAS parameters with their own special rules for values.
    $gateway = $this->getCasRequestParameter($request, 'gateway');
    $renew = $this->getCasRequestParameter($request, 'renew');

    // Setting both gateway and renew is undefined. SHOULD ignore gateway.
    if ($gateway && $renew) {
      $gateway = FALSE;
    }

    // If no service, need to either show the login form (if not logged in),
    // or a simple page to logged in users explaining their state.
    if (is_null($service)) {
      if (!$this->userHasSingleSignOnSession(NULL)) {
        return $this->formBuilder->getForm(
          '\Drupal\cas_server\Form\UserLogin',
          ''
        );
      }
      else {
        return $this->generateLoggedInMessage();
      }
    }

    // Check service url matches one of the cas services. If its not a valid
    // service, display a page to that effect.
    if (!$service_entity) {
      return $this->generateInvalidServiceMessage();
    }

    // Check if authenticated user is allowed to use this service. Anon user
    // can attempt to log into any service. A returning auth user will be
    // checked to see if they are permitted before granting cookie.
    if ($this->currentUser->isAuthenticated()) {
      if (!$service_entity->accountPermitted($this->currentUser)) {
        return $this->generateNotPermittedMessage();
      }
    }

    // If user has an active single sign on session and renew is not set,
    // generate a service ticket and redirect.
    if (!$renew && $this->userHasSingleSignOnSession($service)) {
      $st = $this->ticketFactory->createServiceTicket($service, FALSE);
      $url = Url::fromUri($service, ['query' => ['ticket' => $st->getId()]]);
      $response = new TrustedRedirectResponse($url->toString(), 302);
      $metadata = (new CacheableMetadata())->setCacheMaxAge(0);
      $response->addCacheableDependency($metadata);
      return $response;
    }

    // If gateway is set and user is not logged in, redirect them back to
    // service.
    if ($gateway && !$this->userHasSingleSignOnSession($service)) {
      $response = new TrustedRedirectResponse($service, 302);
      $metadata = (new CacheableMetadata())->setCacheMaxAge(0);
      $response->addCacheableDependency($metadata);
      return $response;
    }

    // Present the user with a login form.
    return $this->formBuilder->getForm(
      '\Drupal\cas_server\Form\UserLogin',
      $service
    );
  }

  /**
   * Special treatment of CAS request parameters retrieval for renew/gateway.
   *
   * The parameter only has to be "set" to be considered TRUE, but it is
   * RECOMMENDED that the value be "true". Due to existing code base the value
   * is considered FALSE if not set, and TRUE if set to any value except for
   * "false" which is also considered FALSE.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $param
   *   The parameter to check for and evaluate.
   *
   * @return bool
   *   If the parameter has been set and is true-ish.
   */
  private function getCasRequestParameter(Request $request, string $param) {

    if (!$request->query->has($param) || $request->query->get($param) == 'false') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Handles a page request for /cas/logout.
   *
   * @return \Drupal\Core\Routing\RedirectResponse|array
   *   Redirect the user logging out back to service, or display logged out.
   */
  public function logout(): RedirectResponse|array {
    $this->killSwitch->trigger();
    $request = $this->requestStack->getCurrentRequest();
    $service = $request->query->has('service')
      ? $request->query->get('service')
      : NULL;
    if ($request->cookies->has('cas_tgc')) {
      // Remove the ticket granting cookie.
      $this->responseCookies->clearCookie(
        'cas_tgc',
        Url::fromUri('internal:/cas')->toString()
      );
    }

    // Delete all tickets related to this session if id is available.
    if ($session_id = $this->ticketFactory->getUniqueId(FALSE)) {
      $this->ticketStore->deleteTicketsBySession($session_id);
    }

    $this->userLogout();

    // If we have a ticket, it is because we've already processed the form and
    // need to be redirected back to the service.
    if ($service) {
      return new RedirectResponse($service, 302);
    }

    return $this->generateUserLogoutPage();
  }

  /**
   * User has a valid single sign on session for a given service.
   *
   * @param string $service
   *   The service to check for.
   *
   * @return bool
   *   Return TRUE when a user has a valid SSO session.
   */
  private function userHasSingleSignOnSession($service): bool {
    if (!is_null($service) && !$this->configHelper->verifyServiceForSso($service)) {
      return FALSE;
    }

    $should_use_tgc = $this->configHelper->shouldUseTicketGrantingTicket();
    $request = $this->requestStack->getCurrentRequest();
    if ($should_use_tgc && $request->cookies->has('cas_tgc')) {
      $cas_tgc = urldecode($request->cookies->get('cas_tgc'));
      try {
        $tgt = $this->ticketStore->retrieveTicketGrantingTicket($cas_tgc);
      }
      catch (TicketTypeException $e) {
        $this->logger->log('Bad ticket type: @error_msg', [
          '@error_msg' => $e->getMessage(),
        ]);
        return FALSE;
      }
      catch (TicketMissingException $e) {
        $this->logger->log('Ticket not found: @ticket', [
          '@ticket' => $cas_tgc,
        ]);
        return FALSE;
      }

      if ($this->time->getRequestTime() > $tgt->getExpirationTime()) {
        $this->ticketStore->deleteTicketGrantingTicket($tgt);
        return FALSE;
      }

      if ($this->currentUser->id() != $tgt->getUid()) {
        return FALSE;
      }

      return TRUE;
    }
    elseif (!$should_use_tgc && !$this->currentUser->isAnonymous()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Markup for an invalid service message.
   *
   * @return array
   *   A renderable array.
   */
  private function generateInvalidServiceMessage(): array {
    $m = $this->configHelper->getInvalidServiceMessage();
    $message = $m ? $m : $this->t('You have not requested a valid service.');

    return $this->generateMessagePage(
      'cas-message-invalid-service',
      $message,
      $this->t('Invalid Service')
    );
  }

  /**
   * Markup for a not permitted message.
   *
   * @return array
   *   A renderable array.
   */
  private function generateNotPermittedMessage(): array {
    $m = $this->configHelper->getNotPermittedMessage();
    $message = $m ? $m : $this->t('Your account does not have the required permissions to log in to this service.');

    return $this->generateMessagePage(
      'cas-message-access-denied',
      $message,
      $this->t('Access denied')
    );
  }

  /**
   * Markup for logout message.
   *
   * @return array
   *   A renderable array.
   */
  protected function generateUserLogoutPage(): array {
    $m = $this->configHelper->getUserLogoutMessage();
    $message = $m ? $m : $this->t('You have been logged out');

    return $this->generateMessagePage('cas-message-logout', $message);
  }

  /**
   * Markup for logged in message.
   *
   * @return array
   *   A renderable array.
   */
  protected function generateLoggedInMessage(): array {
    $m = $this->configHelper->getLoggedInMessage();
    $message = $m ? $m : $this->t('You are logged in to CAS single sign on.');

    return $this->generateMessagePage('cas-message-login', $message);
  }

  /**
   * Common render tree output for message pages.
   *
   * @param string $class
   *   The unique class for this message type.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $message
   *   The message to display to the user.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $header
   *   The page h2 header to include.
   *
   * @return array
   *   A renderable array with message included.
   */
  protected function generateMessagePage(
    string $class,
    TranslatableMarkup|string $message,
    TranslatableMarkup|string|null $header = NULL,
  ): array {
    $return = [
      'wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['cas-message-box', $class],
        ],

        'header' => [
          '#prefix' => '<h2>',
          '#suffix' => '</h2>',
          '#markup' => $header,
        ],

        'message_wrapper' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['cas-message'],
          ],

          'message' => [
            '#markup' => $message,
          ],
        ],
      ],
    ];

    if (empty($header)) {
      unset($return['wrapper']['header']);
    }

    return $return;
  }

  /**
   * Encapsulates user_logout.
   */
  private function userLogout(): void {
    if ($this->currentUser->isAuthenticated()) {
      user_logout();
    }
  }

}
