<?php

namespace Drupal\cass_cookies_test\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\cas_server\Ticket\TicketFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provide testing access to the internal unique id.
 */
class UniqueId implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * Creates a new UniqueId object.
   */
  public function __construct(protected TicketFactory $ticketFactory) {
  }

  /**
   * Show the user's internal unique identifier in machine consumable format.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The unique id as an array encoded in a JSON string.
   */
  public function show() {
    $unique_id = $this->ticketFactory->getUniqueId(FALSE);

    return new Response(
      Json::encode(['unique_id' => $unique_id]),
      Response::HTTP_OK,
      ['content-type' => 'text/json']
    );
  }

}
