<?php

namespace Drupal\cass_cookies_test\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Url;
use Drupal\cas_server\EventSubscriber\CASResponseCookies;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Testing helper module which does the set/clear of cookies on request.
 */
class CookieOven extends ControllerBase {

  // Remove need for static::create() method.
  use AutowireTrait;

  /**
   * Name used for test cookie.
   *
   * @var string
   */
  public const COOKIE_NAME = 'cass_cookies_test_name';

  /**
   * Value used for test cookie.
   *
   * @var string
   */
  public const COOKIE_VALUE = 'cass_cookies_test_value';

  /**
   * Constructs a new CookieOven object.
   */
  public function __construct(
    protected CASResponseCookies $responseCookies,
    protected TimeInterface $time,
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * Page listing current cookies.
   *
   * @param string $page
   *   Page title to show.
   *
   * @return array
   *   Render tree.
   */
  public function tray($page = 'Tray') {
    // Render tree to return.
    $return = [
      '#title' => $this->t('Cookies: @page', ['@page' => $page]),

      'table' => [
        '#type' => 'table',
        '#header' => [
          'test' => 'Cookie Name',
          'result' => 'Cookie Value',
        ],
        '#rows' => [],
      ],
    ];

    // Load current request cookies.
    $cookies = $this->requestStack->getCurrentRequest()->cookies->all();
    foreach ($cookies as $cookie_name => $cookie_value) {
      $return['table']['#rows'][] = [
        $cookie_name,
        $cookie_value,
      ];
    }

    return $return;
  }

  /**
   * Set a cookie and then show a page listing current cookies.
   *
   * @return array
   *   Render tree.
   */
  public function bake() {
    // Use the request time to generate expiry in 60 seconds.
    $expiry = $this->time->getRequestTime() + 60;

    // Add a cookie to the response.
    $this->responseCookies->setCookie(
      self::COOKIE_NAME,
      self::COOKIE_VALUE,
      $expiry,
      Url::fromUri('internal:/cass_cookies_test')->toString()
    );

    return self::tray('Bake');
  }

  /**
   * Clear a cookie and then show a page listing current cookies.
   *
   * @return array
   *   Render tree.
   */
  public function eat() {
    // Remove a cookie from the response.
    $this->responseCookies->clearCookie(
      self::COOKIE_NAME,
      Url::fromUri('internal:/cass_cookies_test')->toString()
    );

    return self::tray('Eat');
  }

}
