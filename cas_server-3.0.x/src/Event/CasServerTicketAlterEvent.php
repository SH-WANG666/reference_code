<?php

namespace Drupal\cas_server\Event;

use Drupal\cas_server\Ticket\Ticket;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event to allow modules to alter a Ticket during processing.
 */
class CasServerTicketAlterEvent extends Event {

  /**
   * Ticket alter Event ID.
   */
  const CAS_SERVER_TICKET_ALTER_EVENT = 'cas_server.ticket.alter';

  /**
   * Constructs a new CasServerTicketAlterEvent object.
   *
   * @param \Drupal\cas_server\Ticket\Ticket $ticket
   *   The ticket to be altered.
   */
  public function __construct(protected Ticket $ticket) {
  }

  /**
   * Get the Ticket from this event.
   *
   * @return \Drupal\cas_server\Ticket\Ticket
   *   Return the ticket after event processing.
   */
  public function getTicket() {
    return $this->ticket;
  }

}
