<?php

namespace Drupal\Tests\cas_server\Unit;

use Drupal\cas_server\Controller\TicketValidationController;
use Drupal\cas_server\Ticket\Ticket;
use Drupal\cas_server\Ticket\TicketFactory;
use Drupal\cas_server\TicketStorageInterface;
use GuzzleHttp\Client;

/**
 * Provide a minimal controller to unit test the proxy callback procedure.
 */
class TestTicketValidationController extends TicketValidationController {

  /**
   * Constructs a new TestTicketValidationController object.
   *
   * Replace constructor with only the http client, ticket factory and store.
   */
  public function __construct(
    Client $http_client,
    TicketFactory $ticket_factory,
    TicketStorageInterface $ticket_store,
  ) {
    $this->httpClient = $http_client;
    $this->ticketFactory = $ticket_factory;
    $this->ticketStore = $ticket_store;
  }

  /**
   * Wrapper for the protected proxyCallback function.
   *
   * @see \Drupal\cas_server\Controller\TicketValidationController::proxyCallback()
   */
  public function callProxyCallback(string $pgtUrl, Ticket $ticket): bool {
    return $this->proxyCallback($pgtUrl, $ticket);
  }

}
