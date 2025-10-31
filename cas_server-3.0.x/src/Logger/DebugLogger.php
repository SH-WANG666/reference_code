<?php

namespace Drupal\cas_server\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * Logger wrapper to only output log lines when enabled.
 */
class DebugLogger {

  /**
   * Stores settings object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Stores logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $loggerChannel;

  /**
   * Constructs a new DebugLogger object.
   */
  public function __construct(
    protected ConfigFactoryInterface $config_factory,
    protected LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->settings = $config_factory->get('cas_server.settings');
    $this->loggerChannel = $logger_factory->get('cas_server');
  }

  /**
   * Log information to the logger.
   *
   * Only log supplied information if module is configured to do so, otherwise
   * do nothing.
   *
   * @param string $message
   *   The message to log.
   * @param mixed $context
   *   Arguments used in the message.
   */
  public function log($message, $context = []) {
    if ($this->settings->get('debugging.log') == TRUE) {
      $this->loggerChannel->log(RfcLogLevel::DEBUG, $message, $context);
    }
  }

}
