<?php

namespace Drupal\cas_server;

use Drupal\cas_server\Ticket\LoginTicket;
use Drupal\cas_server\Ticket\ProxyGrantingTicket;
use Drupal\cas_server\Ticket\ProxyTicket;
use Drupal\cas_server\Ticket\ServiceTicket;
use Drupal\cas_server\Ticket\TicketGrantingTicket;

/**
 * Interface to manage the storage of Tickets.
 */
interface TicketStorageInterface {

  /**
   * Stores a login ticket.
   *
   * @param \Drupal\cas_server\Ticket\LoginTicket $ticket
   *   The ticket object to store.
   */
  public function storeLoginTicket(LoginTicket $ticket);

  /**
   * Retrieves login ticket information.
   *
   * @param string $ticket_string
   *   The ticket identifier to look up.
   *
   * @return \Drupal\cas_server\Ticket\LoginTicket
   *   A ticket object represented by the given identifier.
   */
  public function retrieveLoginTicket($ticket_string);

  /**
   * Deletes a login ticket.
   *
   * @param \Drupal\cas_server\Ticket\LoginTicket $ticket
   *   The ticket object to delete from storage.
   */
  public function deleteLoginTicket(LoginTicket $ticket);

  /**
   * Purge expired login tickets.
   */
  public function purgeExpiredLoginTickets();

  /**
   * Stores a service ticket.
   *
   * @param \Drupal\cas_server\Ticket\ServiceTicket $ticket
   *   The ticket object to store.
   */
  public function storeServiceTicket(ServiceTicket $ticket);

  /**
   * Retrieves service ticket information.
   *
   * @param string $ticket_string
   *   The ticket identifier to look up.
   *
   * @return \Drupal\cas_server\Ticket\ServiceTicket
   *   A ticket object represented by the given identifier.
   */
  public function retrieveServiceTicket($ticket_string);

  /**
   * Deletes a service ticket.
   *
   * @param \Drupal\cas_server\Ticket\ServiceTicket $ticket
   *   The ticket object to delete from storage.
   */
  public function deleteServiceTicket(ServiceTicket $ticket);

  /**
   * Purge unvalidated service tickets.
   */
  public function purgeUnvalidatedServiceTickets();

  /**
   * Stores a proxy ticket.
   *
   * @param \Drupal\cas_server\ProxyTicket $ticket
   *   The ticket object to store.
   */
  public function storeProxyTicket(ProxyTicket $ticket);

  /**
   * Retrieves proxy ticket information.
   *
   * @param string $ticket_string
   *   The ticket information to look up.
   *
   * @return \Drupal\cas_server\Ticket\ProxyTicket
   *   A ticket object represented by the given identifier.
   */
  public function retrieveProxyTicket($ticket_string);

  /**
   * Deletes a proxy ticket.
   *
   * @param \Drupal\cas_server\Ticket\ProxyTicket $ticket
   *   The ticket object to delete from storage.
   */
  public function deleteProxyTicket(ProxyTicket $ticket);

  /**
   * Purge unvalidated proxy tickets.
   */
  public function purgeUnvalidatedProxyTickets();

  /**
   * Stores a proxy-granting ticket.
   *
   * @param \Drupal\cas_server\Ticket\ProxyGrantingTicket $ticket
   *   The ticket object to store.
   */
  public function storeProxyGrantingTicket(ProxyGrantingTicket $ticket);

  /**
   * Retrieves proxy-granting ticket information.
   *
   * @param string $ticket_string
   *   The ticket information to look up.
   *
   * @return \Drupal\cas_server\Ticket\ProxyGrantingTicket
   *   A ticket object represented by the given identifier.
   */
  public function retrieveProxyGrantingTicket($ticket_string);

  /**
   * Deletes a proxy-granting ticket.
   *
   * @param \Drupal\cas_server\Ticket\ProxyGrantingTicket $ticket
   *   The ticket to delete from storage.
   */
  public function deleteProxyGrantingTicket(ProxyGrantingTicket $ticket);

  /**
   * Purge expired proxy-granting tickets.
   */
  public function purgeExpiredProxyGrantingTickets();

  /**
   * Stores a ticket-granting ticket.
   *
   * @param \Drupal\cas_server\Ticket\TicketGrantingTicket $ticket
   *   The ticket object to store.
   */
  public function storeTicketGrantingTicket(TicketGrantingTicket $ticket);

  /**
   * Retrieves ticket-granting ticket information.
   *
   * @param string $ticket_string
   *   The ticket information to look up.
   *
   * @return \Drupal\cas_server\Ticket\TicketGrantingTicket
   *   The ticket object represented by the given identifier.
   */
  public function retrieveTicketGrantingTicket($ticket_string);

  /**
   * Delete a ticket-granting ticket.
   *
   * @param \Drupal\cas_server\Ticket\TicketGrantingTicket $ticket
   *   The ticket to delete from storage.
   */
  public function deleteTicketGrantingTicket(TicketGrantingTicket $ticket);

  /**
   * Purge expired ticket-granting tickets.
   */
  public function purgeExpiredTicketGrantingTickets();

  /**
   * Delete all tickets associated with a given session.
   *
   * @param string $session
   *   A hashed session ID to look up.
   */
  public function deleteTicketsBySession($session);

}
