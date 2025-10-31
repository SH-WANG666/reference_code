<?php

namespace Drupal\cas_server\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\cas_server\ConfigHelper;
use Drupal\cas_server\Event\CASAttributesAlterEvent;
use Drupal\cas_server\Exception\TicketMissingException;
use Drupal\cas_server\Exception\TicketTypeException;
use Drupal\cas_server\Logger\DebugLogger;
use Drupal\cas_server\Ticket\ProxyTicket;
use Drupal\cas_server\Ticket\TicketFactory;
use Drupal\cas_server\TicketInterface;
use Drupal\cas_server\TicketStorageInterface;
use Drupal\user\UserInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Validate tickets and return a response.
 */
class TicketValidationController implements ContainerInjectionInterface {

  // Remove need for static::create() method.
  use AutowireTrait;

  /**
   * Cas protocol version 1 validation request.
   *
   * @var int
   */
  const CAS_PROTOCOL_1 = 0;

  /**
   * Cas protocol 2 service validation request.
   *
   * @var int
   */
  const CAS_PROTOCOL_2_SERVICE = 1;

  /**
   * Cas protocol 2 proxy validation request.
   *
   * @var int
   */
  const CAS_PROTOCOL_2_PROXY = 2;

  /**
   * Cas protocol 3 service validation request.
   *
   * @var int
   */
  const CAS_PROTOCOL_3_SERVICE = 3;

  /**
   * Cas protocol 3 proxy validation request.
   *
   * @var int
   */
  const CAS_PROTOCOL_3_PROXY = 4;

  /**
   * Constructs a new TicketValidationController object.
   */
  public function __construct(
    protected ConfigHelper $configHelper,
    protected DebugLogger $logger,
    protected TicketStorageInterface $ticketStore,
    protected TicketFactory $ticketFactory,
    protected TimeInterface $time,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected ClientInterface $httpClient,
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * Global handler for validation requests.
   *
   * This function handles the top-level requirements of a validation request
   * and then delegates out to the relevant protocol-specific handler to
   * generate responses. This is done to avoid duplication of code.
   *
   * @param int $validation_type
   *   An integer representing which type of validation request this is.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The appropriate CAS Response.
   */
  public function validate(int $validation_type): Response {
    $request = $this->requestStack->getCurrentRequest();
    $format = 'xml';
    if ($request->query->has('format') && $request->query->get('format') == 'JSON') {
      $format = 'json';
    }

    if ($request->query->has('ticket') && $request->query->has('service')) {
      $ticket_string = $request->query->get('ticket');
      $service_string = $request->query->get('service');
      $renew = $request->query->has('renew') ? TRUE : FALSE;

      // Load the ticket. If it doesn't exist or is the wrong type, return the
      // appropriate failure response.
      $ticket = NULL;
      try {
        switch ($validation_type) {
          case self::CAS_PROTOCOL_1:
          case self::CAS_PROTOCOL_2_SERVICE:
          case self::CAS_PROTOCOL_3_SERVICE:
            $ticket = $this->ticketStore->retrieveServiceTicket($ticket_string);
            break;

          case self::CAS_PROTOCOL_2_PROXY:
          case self::CAS_PROTOCOL_3_PROXY:
            $ticket = $this->ticketStore->retrieveProxyTicket($ticket_string);
            break;
        }
      }
      catch (TicketTypeException $e) {
        $this->logger->log(
          'Failed to validate ticket: @ticket_string. @error_msg', [
            '@ticket_string' => $ticket_string,
            '@error_msg' => $e->getMessage(),
          ]
        );
        return $this->generateRequestFailureResponse(
          $validation_type,
          $format,
          'INVALID_TICKET_SPEC',
          'Ticket was of the incorrect type'
        );
      }
      catch (TicketMissingException $e) {
        $this->logger->log(
          'Failed to validate ticket: @ticket_string. @error_msg', [
            '@ticket_string' => $ticket_string,
            '@error_msg' => $e->getMessage(),
          ]
        );
        return $this->generateRequestFailureResponse(
          $validation_type,
          $format,
          'INVALID_TICKET',
          'Ticket not present in ticket store'
        );
      }

      // Check expiration time against request time.
      if ($this->time->getRequestTime() > $ticket->getExpirationTime()) {
        $this->logger->log(
          'Failed to validate ticket: @ticket_string. Ticket had expired.', [
            '@ticket_string' => $ticket_string,
          ]
        );
        return $this->generateRequestFailureResponse(
          $validation_type,
          $format,
          'INVALID_TICKET',
          'Ticket is expired'
        );
      }

      // Check for a service mismatch.
      if ($service_string != $ticket->getService()) {
        $this->logger->log(
          'Failed to validate ticket: @ticket_string. Supplied service @service_string did not match ticket service @ticket_service', [
            '@ticket_string' => $ticket_string,
            '@service_string' => $service_string,
            '@ticket_service' => $ticket->getService(),
          ]
        );

        // Have to delete the ticket.
        $this->ticketStore->deleteServiceTicket($ticket);

        return $this->generateRequestFailureResponse(
          $validation_type,
          $format,
          'INVALID_SERVICE',
          'Provided service did not match ticket service'
        );
      }

      // Check against renew parameter.
      if ($renew && !$ticket->getRenew()) {
        $this->logger->log(
          "Failed to validate ticket: @ticket_string. Supplied service required direct presentation of credentials.", [
            '@ticket_string' => $ticket_string,
          ]
        );
        return $this->generateRequestFailureResponse(
          $validation_type,
          $format,
          'INVALID_TICKET',
          'Ticket did not come from initial login and renew was set'
        );
      }

      // Handle proxy callback procedure.
      $pgtIou = FALSE;
      if ($request->query->has('pgtUrl')) {
        $pgtIou = $this->proxyCallback($request->query->get('pgtUrl'), $ticket);
        if ($pgtIou === FALSE) {
          return $this->generateRequestFailureResponse(
            $validation_type,
            $format,
            'INVALID_PROXY_CALLBACK',
            'The credentials specified for proxy authentication do not meet security requirements.'
          );
        }
      }

      // Validation success, first delete the ticket.
      $this->ticketStore->deleteServiceTicket($ticket);

      return $this->generateSuccessResponse(
        $validation_type,
        $format,
        $ticket,
        $pgtIou
      );
    }

    $this->logger->log('Validation failed due to missing vital parameters.');
    return $this->generateRequestFailureResponse(
      $validation_type,
      $format,
      'INVALID_REQUEST',
      'Missing required request parameters'
    );
  }

  /**
   * Generate the generic Cas protocol version 1 not valid response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A Response object with the failure.
   */
  private function generateVersion1Failure(): Response {
    return new Response(
      "no\n",
      Response::HTTP_OK,
      ['content-type' => 'text/plain']
    );
  }

  /**
   * Generate a properly structured failure message of the given format.
   *
   * @param int $protocol_version
   *   The protocol version to respond with.
   * @param string $format
   *   XML or JSON.
   * @param string $code
   *   The proxy failure code.
   * @param string $message
   *   The additional detailed message.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response object with failure message.
   */
  private function generateRequestFailureResponse(
    int $protocol_version,
    string $format,
    string $code,
    string $message,
  ): Response {

    if ($protocol_version == self::CAS_PROTOCOL_1) {
      return $this->generateVersion1Failure();
    }

    if ($format == 'json') {
      return new Response(
        Json::encode([
          'serviceResponse' => [
            'authenticationFailure' => [
              'code' => $code,
              'description' => $message,
            ],
          ],
        ]),
        Response::HTTP_OK,
        ['content-type' => 'application/json']
      );
    }

    return new Response(
      implode("\n", [
        "<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>",
        "  <cas:authenticationFailure code='$code'>",
        "    $message",
        "  </cas:authenticationFailure>",
        "</cas:serviceResponse>",
      ]),
      Response::HTTP_OK,
      ['content-type' => 'text/xml'],
    );
  }

  /**
   * Generate a ticket validation success message of the given format.
   *
   * @param int $protocol_version
   *   The protocol version to respond with.
   * @param string $format
   *   XML or JSON.
   * @param \Drupal\cas_server\TicketInterface $ticket
   *   The ticket that was validated.
   * @param string $pgtIou
   *   The pgtIou, if applicable.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object with the success message, with optional attribute blocks.
   */
  private function generateSuccessResponse(
    int $protocol_version,
    string $format,
    TicketInterface $ticket,
    string $pgtIou,
  ): Response {
    $proxy_chain = ($ticket instanceof ProxyTicket) ? $ticket->getProxyChain() : [];
    if (empty($proxy_chain) && $protocol_version == self::CAS_PROTOCOL_1) {
      return $this->generateVersion1Success($ticket);
    }

    $account = $this->loadUser($ticket->getUid());
    $event = new CASAttributesAlterEvent($account, $ticket);
    $this->eventDispatcher->dispatch(
      $event,
      CASAttributesAlterEvent::CAS_ATTRIBUTES_ALTER_EVENT
    );
    $attributes = $event->getAttributes();

    if ($format == 'json') {
      $response = [
        'serviceResponse' => [
          'authenticationSuccess' => [
            'user' => $ticket->getUser(),
          ],
        ],
      ];
      $auth_success = &$response['serviceResponse']['authenticationSuccess'];

      if (!empty($attributes)) {
        $auth_success['attributes'] = $attributes;
      }

      if ($pgtIou) {
        $auth_success['proxyGrantingTicket'] = $pgtIou;
      }

      if (!empty($proxy_chain)) {
        $auth_success['proxies'] = $proxy_chain;
      }

      return new Response(
        Json::encode($response),
        Response::HTTP_OK,
        ['content-type' => 'application/json']
      );
    }

    $response = [
      "<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>",
      "  <cas:authenticationSuccess>",
      "    <cas:user>" . $ticket->getUser() . "</cas:user>",
    ];

    if (!empty($attributes)) {
      $response[] = "    <cas:attributes>";
      foreach ($attributes as $key => $value) {
        $value = is_array($value) ? $value : [$value];
        foreach ($value as $array_value) {
          $response[] = sprintf("      <cas:%s>%s</cas:%s>",
            $key,
            $array_value,
            $key
          );
        }
      }
      $response[] = "    </cas:attributes>";
    }

    if ($pgtIou) {
      $response[] = "    <cas:proxyGrantingTicket>$pgtIou</cas:proxyGrantingTicket>";
    }

    if ($ticket instanceof ProxyTicket) {
      $proxy_chain = $ticket->getProxyChain();
      if (!empty($proxy_chain)) {
        $response[] = "    <cas:proxies>";
        foreach ($proxy_chain as $pgt_url) {
          $response[] = "      <cas:proxy>$pgt_url</cas:proxy>";
        }
        $response[] = "    </cas:proxies>";
      }
    }

    $response[] = "  </cas:authenticationSuccess>";
    $response[] = "</cas:serviceResponse>";

    return new Response(
      implode("\n", $response),
      Response::HTTP_OK,
      ['content-type' => 'text/xml'],
    );
  }

  /**
   * Verify the proxy callback url and order a proxy granting ticket issued.
   *
   * @param string $pgtUrl
   *   The supplied callback url to be verified.
   * @param \Drupal\cas_server\TicketInterface $ticket
   *   The ticket that was used for this request.
   *
   * @return string|bool
   *   A pgtIou string to pass along in the response, or FALSE on failure.
   */
  protected function proxyCallback(
    string $pgtUrl,
    TicketInterface $ticket,
  ): string|bool {

    $url = urldecode($pgtUrl);
    if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
      return FALSE;
    }
    // Verify identity of callback url.
    try {
      $this->httpClient->get($url, ['verify' => TRUE]);
    }
    catch (TransferException $e) {
      return FALSE;
    }
    // Order a proxy granting ticket.
    if ($ticket instanceof ProxyTicket) {
      $proxy_chain = $ticket->getProxyChain();
      array_unshift($proxy_chain, $url);
    }
    else {
      $proxy_chain = [$url];
    }

    $pgtIou = 'PGTIOU-';
    $pgtIou .= Crypt::randomBytesBase64(32);

    $pgt = $this->ticketFactory->createProxyGrantingTicket($proxy_chain);
    $pgtId = $pgt->getId();

    // Send a GET request with pgtId and pgtIou. Verify response code.
    if (!empty(parse_url($url, PHP_URL_QUERY))) {
      $full_url = $url .= "&pgtIou=$pgtIou&pgtId=$pgtId";
    }
    else {
      $full_url = $url .= "?pgtIou=$pgtIou&pgtId=$pgtId";
    }
    try {
      $this->httpClient->get($full_url, ['verify' => TRUE]);
    }
    catch (TransferException $e) {
      // If verification failed, delete proxy granting ticket.
      $this->ticketStore->deleteProxyGrantingTicket($pgt);
      return FALSE;
    }

    return $pgtIou;
  }

  /**
   * Generate the generic Cas protocol version 1 valid response.
   *
   * @param \Drupal\cas_server\TicketInterface $ticket
   *   The ticket for this request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A Response object with the success and username.
   */
  private function generateVersion1Success(TicketInterface $ticket): Response {
    return new Response(
      "yes\n" . $ticket->getUser() . "\n",
      Response::HTTP_OK,
      ['content-type' => 'text/plain']
    );
  }

  /**
   * Load a user by uid.
   *
   * @param string $uid
   *   The uid to load.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user object or null if they do not exist.
   */
  private function loadUser(string $uid): UserInterface|null {
    return $this->entityTypeManager->getStorage('user')->load($uid);
  }

}
