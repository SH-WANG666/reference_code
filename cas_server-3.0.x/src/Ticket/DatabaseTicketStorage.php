<?php

namespace Drupal\cas_server\Ticket;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\cas_server\Exception\TicketMissingException;
use Drupal\cas_server\Exception\TicketTypeException;
use Drupal\cas_server\TicketStorageInterface;

/**
 * Database storage for tickets used in CAS.
 */
class DatabaseTicketStorage implements TicketStorageInterface {

  /**
   * Constructs a new DatabaseTicketStorage object.
   */
  public function __construct(
    protected Connection $connection,
    protected TimeInterface $time,
  ) {
  }

  /**
   * Purge functions use the same formatted operand source from now.
   *
   * @return string
   *   The request time formatted to Y-m-d H:i:s.
   */
  protected function getExpirationOperand() {
    static $formatted_date = NULL;
    if ($formatted_date) {
      return $formatted_date;
    }

    $date = new \DateTime(
      '@' . $this->time->getRequestTime(),
      new \DateTimeZone('UTC')
    );
    return $formatted_date = $date->format('Y-m-d H:i:s');
  }

  /**
   * {@inheritdoc}
   */
  public function storeLoginTicket(LoginTicket $ticket) {
    $date = new \DateTime('now', new \DateTimeZone('UTC'));
    $date->setTimestamp($ticket->getExpirationTime());
    $this->connection->insert('cas_server_ticket_store')
      ->fields([
        'id',
        'expiration',
        'type',
        'session',
        'user',
      ],
      // Values.
      [
        $ticket->getId(),
        $date->format('Y-m-d H:i:s'),
        'login',
        $ticket->getSession(),
        $ticket->getUser(),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveLoginTicket($ticket_string) {
    $date = new \DateTime('now', new \DateTimeZone('UTC'));
    $result = $this->connection->select('cas_server_ticket_store', 'c')
      ->fields('c', [
        'id',
        'expiration',
        'type',
      ])
      ->condition('id', $ticket_string)
      ->condition('expiration', $date->format('Y-m-d H:i:s'), '>=')
      ->execute()
      ->fetch();
    if (!empty($result)) {
      if ($result->type == 'login') {
        $date = new \DateTime($result->expiration, new \DateTimeZone('UTC'));
        return new LoginTicket(
          $result->id,
          $date->getTimestamp()
        );
      }
      else {
        throw new TicketTypeException(
          'Expected ticket of type service; found ticket of type ' . $result->type
        );
      }
    }
    else {
      throw new TicketMissingException('Ticket was not found in ticket store.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLoginTicket(LoginTicket $ticket) {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('id', $ticket->getId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function purgeExpiredLoginTickets() {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('type', 'login')
      ->condition('expiration', $this->getExpirationOperand(), '<')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function storeServiceTicket(ServiceTicket $ticket) {
    $date = new \DateTime('now', new \DateTimeZone('UTC'));
    $date->setTimestamp($ticket->getExpirationTime());
    $this->connection->insert('cas_server_ticket_store')
      ->fields([
        'id',
        'expiration',
        'type',
        'session',
        'uid',
        'user',
        'service',
        'renew',
      ],
      // Values.
      [
        $ticket->getId(),
        $date->format('Y-m-d H:i:s'),
        'service',
        $ticket->getSession(),
        $ticket->getUid(),
        $ticket->getUser(),
        $ticket->getService(),
        $ticket->getRenew() ? 1 : 0,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveServiceTicket($ticket_string) {
    $result = $this->connection->select('cas_server_ticket_store', 'c')
      ->fields('c', [
        'id',
        'expiration',
        'type',
        'session',
        'uid',
        'user',
        'service',
        'renew',
      ])
      ->condition('id', $ticket_string)
      ->execute()
      ->fetch();
    if (!empty($result)) {
      if ($result->type == 'service') {
        $date = new \DateTime($result->expiration, new \DateTimeZone('UTC'));
        return new ServiceTicket(
          $result->id,
          $date->getTimestamp(),
          $result->session,
          $result->uid,
          $result->user,
          $result->service,
          $result->renew
        );
      }
      else {
        throw new TicketTypeException(
          'Expected ticket of type service; found ticket of type ' . $result->type
        );
      }
    }
    else {
      throw new TicketMissingException('Ticket was not found in ticket store.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteServiceTicket(ServiceTicket $ticket) {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('id', $ticket->getId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function purgeUnvalidatedServiceTickets() {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('type', 'service')
      ->condition('expiration', $this->getExpirationOperand(), '<')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function storeProxyTicket(ProxyTicket $ticket) {
    $date = new \DateTime('now', new \DateTimeZone('UTC'));
    $date->setTimestamp($ticket->getExpirationTime());
    $this->connection->insert('cas_server_ticket_store')
      ->fields([
        'id',
        'expiration',
        'type',
        'session',
        'uid',
        'user',
        'service',
        'renew',
        'proxy_chain',
      ],
      [
        $ticket->getId(),
        $date->format('Y-m-d H:i:s'),
        'proxy',
        $ticket->getSession(),
        $ticket->getUid(),
        $ticket->getUser(),
        $ticket->getService(),
        $ticket->getRenew() ? 1 : 0,
        serialize($ticket->getProxyChain()),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveProxyTicket($ticket_string) {
    $result = $this->connection->select('cas_server_ticket_store', 'c')
      ->fields('c')
      ->condition('id', $ticket_string)
      ->execute()
      ->fetch();
    if (!empty($result)) {
      if ($result->type == 'service') {
        $date = new \DateTime($result->expiration, new \DateTimeZone('UTC'));
        return new ServiceTicket(
          $result->id,
          $date->getTimestamp(),
          $result->session,
          $result->uid,
          $result->user,
          $result->service,
          $result->renew
        );
      }
      elseif ($result->type == 'proxy') {
        $date = new \DateTime($result->expiration, new \DateTimeZone('UTC'));
        return new ProxyTicket(
          $result->id,
          $date->getTimestamp(),
          $result->session,
          $result->uid,
          $result->user,
          $result->service,
          $result->renew,
          // @todo Convert this to using json; add update to convert existing.
          unserialize($result->proxy_chain, ['allowed_classes' => FALSE])
        );
      }
      else {
        throw new TicketTypeException(
          'Expected ticket of type service or proxy; found ticket of type ' . $result->type
        );
      }
    }
    else {
      throw new TicketMissingException('Ticket was not found in ticket store.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteProxyTicket(ProxyTicket $ticket) {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('id', $ticket->getId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function purgeUnvalidatedProxyTickets() {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('type', 'proxy')
      ->condition('expiration', $this->getExpirationOperand(), '<')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function storeProxyGrantingTicket(ProxyGrantingTicket $ticket) {
    // @todo Why does this create a DateTime and then throw away time.
    $date = new \DateTime('now', new \DateTimeZone('UTC'));
    $date->setTimestamp($ticket->getExpirationTime());
    $this->connection->insert('cas_server_ticket_store')
      ->fields([
        'id',
        'expiration',
        'type',
        'session',
        'uid',
        'user',
        'proxy_chain',
      ],
      [
        $ticket->getId(),
        $date->format('Y-m-d H:i:s'),
        'proxygranting',
        $ticket->getSession(),
        $ticket->getUid(),
        $ticket->getUser(),
        serialize($ticket->getProxyChain()),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveProxyGrantingTicket($ticket_string) {
    $result = $this->connection->select('cas_server_ticket_store', 'c')
      ->fields('c', [
        'id',
        'expiration',
        'type',
        'session',
        'uid',
        'user',
        'proxy_chain',
      ])
      ->condition('id', $ticket_string)
      ->execute()
      ->fetch();
    if (!empty($result)) {
      if ($result->type == 'proxygranting') {
        $date = new \DateTime($result->expiration, new \DateTimeZone('UTC'));
        return new ProxyGrantingTicket(
          $result->id,
          $date->getTimestamp(),
          $result->session,
          $result->uid,
          $result->user,
          // @todo Convert this to using json; add update to convert existing.
          unserialize($result->proxy_chain, ['allowed_classes' => FALSE])
        );
      }
      else {
        throw new TicketTypeException('Expected ticket of type proxygranting; found ticket of type ' . $result->type);
      }
    }
    else {
      throw new TicketMissingException('Ticket was not found in ticket store.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteProxyGrantingTicket(ProxyGrantingTicket $ticket) {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('id', $ticket->getId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function purgeExpiredProxyGrantingTickets() {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('type', 'proxygranting')
      ->condition('expiration', $this->getExpirationOperand(), '<')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function storeTicketGrantingTicket(TicketGrantingTicket $ticket) {
    $date = new \DateTime('now', new \DateTimeZone('UTC'));
    $date->setTimestamp($ticket->getExpirationTime());
    $this->connection->insert('cas_server_ticket_store')
      ->fields([
        'id',
        'expiration',
        'type',
        'session',
        'uid',
        'user',
      ],
      [
        $ticket->getId(),
        $date->format('Y-m-d H:i:s'),
        'ticketgranting',
        $ticket->getSession(),
        $ticket->getUid(),
        $ticket->getUser(),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveTicketGrantingTicket($ticket_string) {
    $result = $this->connection->select('cas_server_ticket_store', 'c')
      ->fields('c', ['id', 'expiration', 'type', 'session', 'uid', 'user'])
      ->condition('id', $ticket_string)
      ->execute()
      ->fetch();
    if (!empty($result)) {
      if ($result->type == 'ticketgranting') {
        $date = new \DateTime($result->expiration, new \DateTimeZone('UTC'));
        return new TicketGrantingTicket(
          $result->id,
          $date->getTimestamp(),
          $result->session,
          $result->uid,
          $result->user
        );
      }
      else {
        throw new TicketTypeException(
          'Expected ticket of type ticketgranting; found ticket of type ' . $result->type
        );
      }
    }
    else {
      throw new TicketMissingException('Ticket was not found in ticket store.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTicket(Ticket $ticket) {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('id', $ticket->getId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function purgeExpiredTicketGrantingTickets() {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('type', 'ticketgranting')
      ->condition('expiration', $this->getExpirationOperand(), '<')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTicketGrantingTicket(TicketGrantingTicket $ticket) {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('id', $ticket->getId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTicketsBySession($session) {
    $this->connection->delete('cas_server_ticket_store')
      ->condition('session', $session)
      ->execute();
  }

}
