<?php

namespace Drupal\cas_server\Ticket;

use Drupal\cas_server\TicketInterface;

/**
 * Abstract Ticket class to be extended into the different Ticket types.
 */
abstract class Ticket implements TicketInterface {

  /**
   * The ticket identifier string.
   *
   * @var string
   */
  protected $id;

  /**
   * A unix timestamp representing the expiration time of the ticket.
   *
   * @var int
   */
  protected $expirationTime;

  /**
   * A hashed session ID for the session that requested ticket.
   *
   * @var string
   */
  protected $session;

  /**
   * The uid of the user who requested the ticket.
   *
   * @var int
   */
  protected $uid;

  /**
   * The username of the user who requested the ticket.
   *
   * @var string
   */
  protected $user;

  /**
   * Constructs a new Ticket object.
   *
   * @param string $ticket_id
   *   The ticket id.
   * @param string $timestamp
   *   The expiration time of the ticket.
   * @param string $session_id
   *   The hashed session id.
   * @param int $uid
   *   The uid of this ticket's user object.
   * @param string $username
   *   The username of requestor.
   */
  public function __construct(
    $ticket_id,
    $timestamp,
    $session_id,
    $uid,
    $username,
  ) {
    $this->id = $ticket_id;
    $this->expirationTime = $timestamp;
    $this->session = $session_id;
    $this->uid = $uid;
    $this->user = $username;
  }

  /**
   * Return the user.
   *
   * @return int
   *   The user id property.
   */
  public function getUid(): int {
    return $this->uid;
  }

  /**
   * Return the user.
   *
   * @return string
   *   The user property.
   */
  public function getUser(): string {
    return $this->user;
  }

  /**
   * Set the username for this ticket.
   *
   * @param string $username
   *   The username to be set.
   */
  public function setUser(string $username): void {
    $this->user = $username;
  }

  /**
   * Return the id of the ticket.
   *
   * @return string
   *   The id property.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Return the expiration time.
   *
   * @return int
   *   The expiration time.
   */
  public function getExpirationTime(): int {
    return $this->expirationTime;
  }

  /**
   * Return the session.
   *
   * @return string
   *   The session.
   */
  public function getSession(): string {
    return $this->session;
  }

}
