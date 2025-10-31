<?php

namespace Drupal\cas_server;

/**
 * Interface for the Ticket classes.
 */
interface TicketInterface {

  /**
   * Return the user.
   *
   * @return int
   *   The user id property.
   */
  public function getUid(): int;

  /**
   * Return the user.
   *
   * @return string
   *   The user property.
   */
  public function getUser(): string;

  /**
   * Set the username for this ticket.
   *
   * @param string $username
   *   The username to be set.
   */
  public function setUser(string $username): void;

  /**
   * Return the id of the ticket.
   *
   * @return string
   *   The id property.
   */
  public function getId(): string;

  /**
   * Return the expiration time.
   *
   * @return int
   *   The expiration time.
   */
  public function getExpirationTime(): int;

  /**
   * Return the session.
   *
   * @return string
   *   The session.
   */
  public function getSession(): string;

}
