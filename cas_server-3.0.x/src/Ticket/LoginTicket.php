<?php

namespace Drupal\cas_server\Ticket;

/**
 * Ticket used in CAS protocol to prevent credential replay attacks.
 */
class LoginTicket extends Ticket {

  /**
   * Create a LoginTicket.
   *
   * @param string $ticket_id
   *   The ticket id.
   * @param string $timestamp
   *   The expiration time of the ticket.
   *
   * @phpcs:disable Drupal.Functions.MultiLineFunctionDeclaration.MissingTrailingComma
   */
  public function __construct(
    $ticket_id,
    $timestamp
  ) {
    // phpcs:enable
    $this->id = $ticket_id;
    $this->expirationTime = $timestamp;

    // The following values are not used for this ticket type but are not null.
    $this->session = 'n/a';
    $this->user = 'n/a';
  }

}
