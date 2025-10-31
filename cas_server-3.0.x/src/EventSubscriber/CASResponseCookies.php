<?php

namespace Drupal\cas_server\EventSubscriber;

use Drupal\cas_server\Logger\DebugLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listen for Kernel Response and add cookies to it. Confirm added.
 */
class CASResponseCookies implements EventSubscriberInterface {

  /**
   * The cookies.
   *
   * @var \Symfony\Component\HttpFoundation\Cookie[]
   */
  protected $cookies = [];

  /**
   * Flag to signal if cookies were applied to this response.
   *
   * @var bool
   */
  protected $bakedCookies = FALSE;

  /**
   * Constructs a new CASResponseCookies object.
   */
  public function __construct(protected DebugLogger $logger) {
  }

  /**
   * Create a Cookie ready for inclusion in reply.
   *
   * @param string $name
   *   The name of the cookie.
   * @param string|null $value
   *   The value of the cookie.
   * @param int $expire
   *   The time the cookie expires.
   * @param string|null $path
   *   The path on the server in which the cookie will be available on.
   * @param string|null $domain
   *   The domain that the cookie is available to.
   * @param bool|null $secure
   *   Whether the client should send back the cookie only over HTTPS.
   * @param bool $httpOnly
   *   Whether the cookie will be made accessible only through the HTTP.
   * @param bool $raw
   *   Whether the cookie value should be sent with no url encoding.
   * @param string|null $sameSite
   *   Whether the cookie will be available for cross-site requests.
   *
   * @return \Symfony\Component\HttpFoundation\Cookie
   *   The cookie object created for response.
   *
   * @see https://github.com/symfony/symfony/blob/6.4/src/Symfony/Component/HttpFoundation/Cookie.php#L100
   */
  public function setCookie(
    string $name,
    ?string $value = NULL,
    int $expire = 0,
    ?string $path = '/',
    ?string $domain = NULL,
    ?bool $secure = NULL,
    bool $httpOnly = TRUE,
    bool $raw = FALSE,
    ?string $sameSite = Cookie::SAMESITE_LAX,
  ): Cookie {

    $hashCookie = $this->hashCookie($name, $path, $domain);
    return $this->cookies[$hashCookie] = Cookie::create(
      $name,
      $value,
      $expire,
      $path,
      $domain,
      $secure,
      $httpOnly,
      $raw,
      $sameSite
    );
  }

  /**
   * Clears the cookie from the requester's browser by setting to empty.
   *
   * Is effectively the same as setCookie with the value and expiry set to NULL
   * and 1.
   *
   * @param string $name
   *   The name of the cookie.
   * @param string|null $path
   *   The path on the server in which the cookie will be available on.
   * @param string|null $domain
   *   The domain that the cookie is available to.
   * @param bool|null $secure
   *   Whether the client should send back the cookie only over HTTPS.
   * @param bool $httpOnly
   *   Whether the cookie will be made accessible only through the HTTP.
   * @param string|null $sameSite
   *   Whether the cookie will be available for cross-site requests.
   *
   * @return \Symfony\Component\HttpFoundation\Cookie
   *   The cookie object created to clear the cookie in response.
   */
  public function clearCookie(
    string $name,
    ?string $path = '/',
    ?string $domain = NULL,
    bool $secure = FALSE,
    bool $httpOnly = TRUE,
    ?string $sameSite = NULL,
  ): Cookie {

    $hashCookie = $this->hashCookie($name, $path, $domain);
    return $this->cookies[$hashCookie] = Cookie::create(
      $name,
      NULL,
      1,
      $path,
      $domain,
      $secure,
      $httpOnly,
      FALSE,
      $sameSite
    );
  }

  /**
   * Retrieve a constructed cookie.
   *
   * @param string $name
   *   The name of the cookie.
   * @param string|null $path
   *   The path on the server in which the cookie will be available on.
   * @param string|null $domain
   *   The domain that the cookie is available to.
   *
   * @return \Symfony\Component\HttpFoundation\Cookie
   *   The Cookie from the response handler, or NULL if does not exist here.
   */
  public function getCookie(
    $name,
    ?string $path = '/',
    ?string $domain = NULL,
  ): ?Cookie {
    $hashCookie = $this->hashCookie($name, $path, $domain);
    return $this->cookies[$hashCookie] ?? NULL;
  }

  /**
   * Return all cookies set or cleared on this response.
   *
   * @return array
   *   The array of all cookies.
   */
  public function getCookies() {
    return $this->cookies;
  }

  /**
   * Add or clear cookies to be sent in response.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The Event to process.
   */
  public function bakeCookies(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    if (empty($this->cookies)) {
      return;
    }

    // Get the request from the event.
    $response = $event->getResponse();

    // Bake cookies into the response headers.
    foreach ($this->cookies as $cookie) {
      // This both bakes and eats cookies based on cookie ingredients.
      $this->logger->log('Adding cookie @name', ['@name' => $cookie->getName()]);
      $response->headers->setCookie($cookie);
    }

    // Add the response with new headers back into the event.
    $event->setResponse($response);

    // Set flag that cookies have been baked into response.
    $this->bakedCookies = TRUE;
  }

  /**
   * Hash the determining factors of the cookie into a has for an array key.
   *
   * @param string $name
   *   The name of the cookie.
   * @param string $path
   *   The path on the server in which the cookie will be available on.
   * @param string|null $domain
   *   The domain that the cookie is available to.
   *
   * @return string
   *   The sha256 hash.
   */
  protected function hashCookie(
    string $name,
    string $path = '/',
    ?string $domain = NULL,
  ): string {
    return hash('sha256', implode(':', [
      $name,
      $domain,
      $path,
    ]));
  }

  /**
   * Log instance where cookie dough exists but it has not been baked in.
   *
   * @param Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The Event to process.
   */
  public function checkForDough(TerminateEvent $event): void {
    if (!empty($this->cookies) && !$this->bakedCookies) {
      // This only happen at the very end of the process.
      $this->logger->log('Cookies not applied to response.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Add cookies to response.
      KernelEvents::RESPONSE => ['bakeCookies', 100],

      // Log circumstance where cookies were not baked into response.
      KernelEvents::TERMINATE => ['checkForDough'],
    ];
  }

}
