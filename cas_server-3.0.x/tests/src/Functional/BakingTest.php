<?php

namespace Drupal\Tests\cas_server\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\cass_cookies_test\Controller\CookieOven;

/**
 * Tests baking in and eating cookies in responses.
 *
 * @group cas_server
 */
class BakingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'cas_server',
    'cass_cookies_test',
  ];

  /**
   * Test setting and clearing a cookie using the event listener method.
   */
  public function testBakingEatingCookies(): void {

    // Assert cookie doesn't exist.
    $this->drupalGet('cass_cookies_test/cookie-tray');
    $this->assertSession()->statusCodeEquals(200);
    $cookie = $this->getSession()->getCookie(CookieOven::COOKIE_NAME);
    $this->assertEmpty($cookie);

    // Set a cookie.
    $this->drupalGet('cass_cookies_test/cookie-bake');
    $this->assertSession()->statusCodeEquals(200);
    $cookie = $this->getSession()->getCookie(CookieOven::COOKIE_NAME);
    $this->assertNotEmpty($cookie);

    // Confirm cookie is still present on next request.
    $this->drupalGet('cass_cookies_test/cookie-tray');
    $this->assertSession()->statusCodeEquals(200);
    $cookie = $this->getSession()->getCookie(CookieOven::COOKIE_NAME);
    $this->assertNotEmpty($cookie);

    // Clear cookie.
    $this->drupalGet('cass_cookies_test/cookie-eat');
    $this->assertSession()->statusCodeEquals(200);
    $cookie = $this->getSession()->getCookie(CookieOven::COOKIE_NAME);
    $this->assertEmpty($cookie);

    // Confirm cookie is still gone on next request.
    $this->drupalGet('cass_cookies_test/cookie-tray');
    $this->assertSession()->statusCodeEquals(200);
    $cookie = $this->getSession()->getCookie(CookieOven::COOKIE_NAME);
    $this->assertEmpty($cookie);
  }

}
