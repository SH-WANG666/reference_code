<?php

namespace Drupal\cas_server\Event;

use Drupal\cas_server\Ticket\ServiceTicket;
use Drupal\user\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Build and alter attributes used during ticket validation success message.
 */
class CASAttributesAlterEvent extends Event {

  /**
   * Attributes alter Event ID.
   */
  const CAS_ATTRIBUTES_ALTER_EVENT = 'cas.attributes.alter';

  /**
   * Attributes to be used in validation success messaging.
   *
   * @var mixed
   */
  protected $attributes;

  /**
   * Constructs a new CASAttributesAlterEvent object.
   *
   * @param \Drupal\user\UserInterface $user
   *   Successfully validated user object.
   * @param \Drupal\cas_server\Ticket\ServiceTicket $ticket
   *   Ticket used in successful validation.
   */
  public function __construct(
    protected UserInterface $user,
    protected ServiceTicket $ticket,
  ) {
  }

  /**
   * Get the event Ticket associated with event.
   *
   * @return \Drupal\cas_server\Ticket\ServiceTicket
   *   Return the Event Ticket.
   */
  public function getTicket() {
    return $this->ticket;
  }

  /**
   * Get the user object associated with this ticket.
   *
   * @return \Drupal\user\UserInterface
   *   Return the Event User.
   */
  public function getUser() {
    return $this->user;
  }

  /**
   * Get the attributes associated with this event.
   *
   * @return mixed
   *   Return the attributes built
   */
  public function getAttributes() {
    return $this->attributes;
  }

  /**
   * Set the attributes to be used during success message.
   *
   * @param mixed $attributes
   *   The attributes to be used in success message.
   */
  public function setAttributes($attributes) {
    $this->attributes = $attributes;
  }

}
