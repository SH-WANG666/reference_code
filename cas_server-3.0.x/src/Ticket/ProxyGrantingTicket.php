<?php

namespace Drupal\cas_server\Ticket;

/**
 * A proxy granting Ticket that hold the proxy chain used in responses.
 */
class ProxyGrantingTicket extends Ticket {

  /**
   * The chain of pgtUrls used to generate this ticket.
   *
   * @var array
   */
  protected $proxyChain;

  /**
   * Constructs a new ProxyGrantingTicket object.
   *
   * @param string $ticket_id
   *   The ticket id.
   * @param string $timestamp
   *   The expiration time of the ticket.
   * @param string $session_id
   *   The hashed session id.
   * @param string $uid
   *   The uid of the requestor.
   * @param string $username
   *   The username of requestor.
   * @param array $proxy_chain
   *   The array of pgturls used to generate this pgt.
   */
  public function __construct(
    $ticket_id,
    $timestamp,
    $session_id,
    $uid,
    $username,
    $proxy_chain,
  ) {
    $this->id = $ticket_id;
    $this->expirationTime = $timestamp;
    $this->session = $session_id;
    $this->uid = $uid;
    $this->user = $username;
    $this->proxyChain = $proxy_chain;
  }

  /**
   * Get the stored proxy chain for this proxy ticket.
   *
   * @return array
   *   The chain of pgtUrls used to generate this ticket.
   */
  public function getProxyChain() {
    return $this->proxyChain;
  }

}
