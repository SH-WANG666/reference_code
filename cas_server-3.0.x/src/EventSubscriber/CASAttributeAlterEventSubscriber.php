<?php

namespace Drupal\cas_server\EventSubscriber;

use Drupal\cas_server\ConfigHelper;
use Drupal\cas_server\Event\CASAttributesAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listen for CASAttributesAlterEvent and build attributes array for it.
 */
class CASAttributeAlterEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new CASAttributeAlterEventSubscriber object.
   */
  public function __construct(protected ConfigHelper $configHelper) {
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents(): array {
    return [
      CASAttributesAlterEvent::CAS_ATTRIBUTES_ALTER_EVENT => [
        'onCasAttributeAlter',
        100,
      ],
    ];
  }

  /**
   * Build the attributes array used in successful authentication message.
   *
   * @param \Drupal\cas_server\Event\CASAttributesAlterEvent $event
   *   The event dispatched with user, ticket and attributes array.
   */
  public function onCasAttributeAlter(CASAttributesAlterEvent $event) {
    $eventAttributes = [];
    $attributes = $this->configHelper
      ->getAttributesForService($event->getTicket()->getService());

    if (!empty($attributes)) {
      foreach ($attributes as $attr) {
        foreach ($event->getUser()->get($attr)->getValue() as $value) {
          if (isset($value['value'])) {
            $eventAttributes[$attr] = $value['value'];
          }
          if (isset($value['target_id'])) {
            $eventAttributes[$attr][] = $value['target_id'];
          }
        }
      }
    }

    $event->setAttributes($eventAttributes);
  }

}
