<?php

namespace Drupal\cas_server\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\cas_server\ConfigHelper;
use Drupal\cas_server\Exception\TicketMissingException;
use Drupal\cas_server\Exception\TicketTypeException;
use Drupal\cas_server\Logger\DebugLogger;
use Drupal\cas_server\Ticket\TicketFactory;
use Drupal\cas_server\TicketStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Process and return proxy ticket responses.
 */
class ProxyController implements ContainerInjectionInterface {

  // Remove need for static::create() method.
  use AutowireTrait;

  /**
   * Constructs a new ProxyController object.
   */
  public function __construct(
    protected ConfigHelper $configHelper,
    protected DebugLogger $logger,
    protected TicketStorageInterface $ticketStore,
    protected TicketFactory $ticketFactory,
    protected TimeInterface $time,
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * Supply a proxy ticket to a request with a valid proxy-granting ticket.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The appropriate proxy Response object.
   */
  public function proxy(): Response {
    $request = $this->requestStack->getCurrentRequest();
    $format = 'xml';
    if ($request->query->has('format') && $request->query->get('format') == 'JSON') {
      $format = 'json';
    }

    if ($request->query->has('pgt') && $request->query->has('targetService')) {
      $service = urldecode($request->query->get('targetService'));
      if (!$this->configHelper->verifyServiceForSso($service)) {
        $this->logger->log(
          'Failed to proxy @service. Service is not authorized for SSO.', [
            '@service' => $service,
          ]
        );
        return $this->generateProxyRequestFailureResponse(
          $format,
          'UNAUTHORIZED_SERVICE_PROXY',
          "Not an authorized single sign on service"
        );
      }

      $pgt = $request->query->get('pgt');
      try {
        $ticket = $this->ticketStore->retrieveProxyGrantingTicket($pgt);
      }
      catch (TicketTypeException $e) {
        return $this->generateProxyRequestFailureResponse(
          $format,
          'INVALID_TICKET',
          $e->getMessage()
        );
      }
      catch (TicketMissingException $e) {
        return $this->generateProxyRequestFailureResponse(
          $format,
          'INVALID_TICKET',
          'Ticket not found'
        );
      }

      if ($this->time->getRequestTime() > $ticket->getExpirationTime()) {
        $this->logger->log(
          'Failed to validate ticket: @pgt. Ticket had expired.', [
            '@pgt' => $pgt,
          ]
        );
        return $this->generateProxyRequestFailureResponse(
          $format,
          'INVALID_TICKET',
          'Ticket has expired'
        );
      }

      $chain = $ticket->getProxyChain();
      $pt = $this->ticketFactory->createProxyTicket(
        $service,
        FALSE,
        $chain,
        $ticket->getSession(),
        $ticket->getUid(),
        $ticket->getUser()
      );

      return $this->generateProxySuccessRequestResponse($format, $pt->getId());
    }

    return $this->generateProxyRequestFailureResponse(
      $format,
      'INVALID_REQUEST',
      "'pgt' and 'targetService' parameters are both required"
    );
  }

  /**
   * Generate a proxy success request response.
   *
   * @param string $format
   *   XML or JSON.
   * @param string $ticket_string
   *   The ticket Id string.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response object with proxy ticket Id.
   */
  private function generateProxySuccessRequestResponse(
    string $format,
    string $ticket_string,
  ): Response {

    if ($format == 'json') {
      return new Response(
        Json::encode([
          'serviceResponse' => [
            'proxySuccess' => [
              'proxyTicket' => $ticket_string,
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
        "  <cas:proxySuccess>",
        "    <cas:proxyTicket>$ticket_string</cas:proxyTicket>",
        "  </cas:proxySuccess>",
        "</cas:serviceResponse>",
      ]),
      Response::HTTP_OK,
      ['content-type' => 'text/xml'],
    );
  }

  /**
   * Generate a properly structured failure message of the given format.
   *
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
  private function generateProxyRequestFailureResponse(
    string $format,
    string $code,
    string $message,
  ): Response {

    if ($format == 'json') {
      return new Response(
        Json::encode([
          'serviceResponse' => [
            'proxyFailure' => [
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
        "  <cas:proxyFailure code='$code'>",
        "    $message",
        "  </cas:proxyFailure>",
        "</cas:serviceResponse>",
      ]),
      Response::HTTP_OK,
      ['content-type' => 'text/xml'],
    );
  }

}
