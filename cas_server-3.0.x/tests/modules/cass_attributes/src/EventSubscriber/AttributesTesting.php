<?php

declare(strict_types=1);

namespace Drupal\cass_attributes\EventSubscriber;

use Drupal\cas_server\Event\CASAttributesAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listen for CASAttributesAlterEvent and alter them to test limits.
 */
class AttributesTesting implements EventSubscriberInterface {

  /**
   * Alter the attributes.
   *
   * @param \Drupal\cas_server\Event\CASAttributesAlterEvent $event
   *   The event dispatched with user, ticket and attributes array.
   */
  public function alterAttributes(CASAttributesAlterEvent $event) {
    $attributes = $event->getAttributes();

    // Add an array.
    $attributes['test_array'] = [
      'first value',
      '2nd value',
    ];

    $event->setAttributes($attributes);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CASAttributesAlterEvent::CAS_ATTRIBUTES_ALTER_EVENT => [
        'alterAttributes',
      ],
    ];
  }

}
