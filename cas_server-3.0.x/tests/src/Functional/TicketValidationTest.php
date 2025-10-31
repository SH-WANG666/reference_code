<?php

declare(strict_types=1);

namespace Drupal\Tests\cas_server\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\cas_server\Entity\CasServerService;

/**
 * Tests responses from the ticket validation system.
 *
 * @group cas_server
 */
class TicketValidationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'cas_server',
  ];

  /**
   * An user with Anonymous permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $exampleUser;

  /**
   * The ticket factory.
   *
   * @var \Drupal\cas_server\Ticket\TicketFactory
   */
  protected $ticketFactory;

  /**
   * The ticket store.
   *
   * @var \Drupal\cas_server\TicketStorageInterface
   */
  protected $ticketStore;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->exampleUser = $this->drupalCreateUser([], 'exampleUserName');

    $this->ticketFactory = $this->container->get('cas_server.ticket_factory');
    $this->ticketStore = $this->container->get('cas_server.storage');
  }

  /**
   * Test failure with an invalid Pgt callback url.
   */
  public function testInvalidPgtCallback(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', 6000)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', 6000)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';
    $mangled_pgt_callback = 'h;ad;;//asdcx.otcz';

    // Protocol version 2.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'pgtUrl' => $mangled_pgt_callback,
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_PROXY_CALLBACK["\']>/'
    );

    // Protocol version 3.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/p3/serviceValidate', [
      'query' => [
        'pgtUrl' => $mangled_pgt_callback,
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_PROXY_CALLBACK["\']>/'
    );
  }

  /**
   * Test failure when renew is set but ticket doesn't comply.
   */
  public function testRenewMismatch(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', 6000)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', 6000)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';

    // Protocol version 1.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/validate', [
      'query' => [
        'renew' => 'true',
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('no');
    $this->assertSession()->responseNotContains('html');

    // Protocol version 2.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'renew' => 'true',
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_TICKET["\']>/'
    );
    $this->assertSession()->pageTextContains('renew');

    // Protocol version 3.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/p3/serviceValidate', [
      'query' => [
        'renew' => 'true',
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_TICKET["\']>/'
    );
    $this->assertSession()->pageTextContains('renew');
  }

  /**
   * Test failure when service doesn't match.
   */
  public function testServiceMismatch(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', 6000)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', 6000)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';

    // Protocol version 1.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/validate', [
      'query' => [
        'service' => $service . 'adfasd',
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('no');
    $this->assertSession()->responseNotContains('html');

    // Protocol version 2.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'service' => $service . 'adasdf',
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_SERVICE["\']>/'
    );

    // Protocol version 3.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/p3/serviceValidate', [
      'query' => [
        'service' => $service . 'adfasdf',
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_SERVICE["\']>/'
    );
  }

  /**
   * Test failure when ticket is expired.
   */
  public function testExpiredTicket(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', -20)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', -20)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';

    // Protocol version 1.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/validate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('no');
    $this->assertSession()->responseNotContains('html');

    // Protocol version 2.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_TICKET["\']>/'
    );
    $this->assertSession()->pageTextContains('expired');

    // Protocol version 3.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/p3/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_TICKET["\']>/'
    );
    $this->assertSession()->pageTextContains('expired');
  }

  /**
   * Test failure when ticket is missing from ticket store.
   */
  public function testMissingTicket(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', 6000)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', 6000)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';

    // Protocol version 1.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->ticketStore->deleteServiceTicket($st);
    $this->drupalGet('cas/validate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('no');
    $this->assertSession()->responseNotContains('html');

    // Protocol version 2.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->ticketStore->deleteServiceTicket($st);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_TICKET["\']>/'
    );

    // Protocol version 3.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->ticketStore->deleteServiceTicket($st);
    $this->drupalGet('cas/p3/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_TICKET["\']>/'
    );
  }

  /**
   * Test proxy validation.
   */
  public function testProxyValidation(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', 6000)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', 6000)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';
    $service_with_attributes = 'https://example.net';

    // Create matching cas service with attributes to release.
    $testServiceOne = CasServerService::create([
      'id' => 'test_service',
      'label' => 'Test Service',
      'service' => 'htt*://example.net*',
      'sso' => TRUE,
      'attributes' => [
        'uid' => 'uid',
        'mail' => 'mail',
      ],
    ]);
    $testServiceOne->save();

    // Protocol version 2.
    $st = $this->ticketFactory->createProxyTicket(
      $service, FALSE, [], 'foo',
      $this->exampleUser->id(),
      $this->ticketFactory->getUsernameAttribute($this->exampleUser)
    );
    $this->drupalGet('cas/proxyValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains(
      '<cas:authenticationSuccess>'
    );
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:proxies>');
    $this->assertSession()->responseNotContains('<cas:attributes>');

    // Protocol version 3; without attributes.
    $st = $this->ticketFactory->createProxyTicket(
      $service, FALSE, [], 'foo',
      $this->exampleUser->id(),
      $this->ticketFactory->getUsernameAttribute($this->exampleUser)
    );
    $this->drupalGet('cas/p3/proxyValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess');
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:proxies>');
    $this->assertSession()->responseNotContains('<cas:attributes>');

    // Protocol version 3; with attributes.
    $st = $this->ticketFactory->createProxyTicket(
      $service_with_attributes, FALSE, [], 'foo',
      $this->exampleUser->id(),
      $this->ticketFactory->getUsernameAttribute($this->exampleUser)
    );
    $this->drupalGet('cas/p3/proxyValidate', [
      'query' => [
        'service' => $service_with_attributes,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess');
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:proxies>');
    $this->assertSession()->responseContains('<cas:attributes>');
    $this->assertSession()
      ->responseContains('<cas:uid>' . $this->exampleUser->id() . '</cas:uid>');
    $this->assertSession()
      ->responseContains('<cas:mail>' . $this->exampleUser->getEmail() . '</cas:mail>');

    // Protocol version 2; with proxy chain.
    $st = $this->ticketFactory->createProxyTicket(
      $service, FALSE, [
        'https://c1.example.org',
        'https://c2.example.net',
      ], 'foo',
      $this->exampleUser->id(),
      $this->ticketFactory->getUsernameAttribute($this->exampleUser)
    );
    $this->drupalGet('cas/p3/proxyValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess');
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:attributes>');
    $this->assertSession()->responseContains('<cas:proxies>');
    $this->assertSession()
      ->responseMatches('/<cas:proxy>https:\/\/c1.example.org<\/cas:proxy>\s+<cas:proxy>https:\/\/c2.example.net<\/cas:proxy>/');
  }

  /**
   * Test failure when giving a proxy ticket to service validation.
   */
  public function testWrongTicketType(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', 6000)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', 6000)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';

    // Protocol version 2.
    $st = $this->ticketFactory->createProxyTicket(
      $service, FALSE, [], 'foo',
      $this->exampleUser->id(),
      $this->ticketFactory->getUsernameAttribute($this->exampleUser)
    );
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_TICKET_SPEC["\']>/'
    );

    // Protocol version 3.
    $st = $this->ticketFactory->createProxyTicket(
      $service, FALSE, [], 'foo',
      $this->exampleUser->id(),
      $this->ticketFactory->getUsernameAttribute($this->exampleUser)
    );
    $this->drupalGet('cas/p3/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_TICKET_SPEC["\']>/'
    );
  }

  /**
   * Test a simple valid request.
   */
  public function testSimpleSuccess(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', 6000)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', 6000)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';
    $service_with_attributes = 'https://example.net';

    // Create matching cas service with attributes to release.
    $testServiceOne = CasServerService::create([
      'id' => 'test_service',
      'label' => 'Test Service',
      'service' => 'htt*://example.net*',
      'sso' => TRUE,
      'attributes' => [
        'uid' => 'uid',
        'mail' => 'mail',
      ],
    ]);
    $testServiceOne->save();

    // Protocol version 1.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/validate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('yes');
    $this->assertSession()->pageTextContains(
      $this->ticketFactory->getUsernameAttribute($this->exampleUser)
    );
    $this->assertSession()->responseNotContains('html');

    // Protocol version 2; no attributes.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess>');
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:proxies>');
    $this->assertSession()->responseNotContains('<cas:attributes>');

    // Protocol version 2; with attributes.
    $st = $this->ticketFactory
      ->createServiceTicket($service_with_attributes, FALSE);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'service' => $service_with_attributes,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess>');
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:proxies>');
    $this->assertSession()->responseContains('<cas:attributes>');
    $this->assertSession()
      ->responseContains('<cas:uid>' . $this->exampleUser->id() . '</cas:uid>');
    $this->assertSession()
      ->responseContains('<cas:mail>' . $this->exampleUser->getEmail() . '</cas:mail>');

    // Protocol version 3.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/p3/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess>');
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:proxies>');
  }

  /**
   * Test attributes beyond the defaults.
   */
  public function testSuccessWithAttributes(): void {
    $this->config('cas_server.settings')
      ->set('ticket.service_ticket_timeout', 6000)
      ->save();
    $this->config('cas_server.settings')
      ->set('ticket.proxy_ticket_timeout', 6000)
      ->save();
    $this->drupalLogin($this->exampleUser);
    $service = 'https://example.com';
    $service_with_attributes = 'https://example.net';

    // Create matching cas service with attributes to release.
    $testServiceOne = CasServerService::create([
      'id' => 'test_service',
      'label' => 'Test Service',
      'service' => 'htt*://example.net*',
      'sso' => TRUE,
      'attributes' => [
        'uid' => 'uid',
        'mail' => 'mail',
      ],
    ]);
    $testServiceOne->save();

    // Protocol version 2; with attributes, but not altered.
    $st = $this->ticketFactory
      ->createServiceTicket($service_with_attributes, FALSE);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'service' => $service_with_attributes,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess>');
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:proxies>');
    $this->assertSession()->responseContains('<cas:attributes>');
    $this->assertSession()
      ->responseContains('<cas:uid>' . $this->exampleUser->id() . '</cas:uid>');
    $this->assertSession()
      ->responseContains('<cas:mail>' . $this->exampleUser->getEmail() . '</cas:mail>');
    // Check for additional attributes added by cass_attributes test module.
    $this->assertSession()
      ->responseNotContains('<cas:test_array>first value</cas:test_array>');
    $this->assertSession()
      ->responseNotContains('<cas:test_array>2nd value</cas:test_array>');

    // Install the attribute test module.
    $this->assertTrue(
      \Drupal::service('module_installer')
        ->install(['cass_attributes']),
      'Failed to install CASS Attribute module.'
    );

    // Protocol version 2; with altered attributes.
    $st = $this->ticketFactory
      ->createServiceTicket($service_with_attributes, FALSE);
    $this->drupalGet('cas/serviceValidate', [
      'query' => [
        'service' => $service_with_attributes,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess>');
    // Check for additional attributes added by cass_attributes test module.
    $this->assertSession()
      ->responseContains('<cas:test_array>first value</cas:test_array>');
    $this->assertSession()
      ->responseContains('<cas:test_array>2nd value</cas:test_array>');

    // Protocol version 3; Services without configured attributes, but they
    // will still be altered to include test_array.
    $st = $this->ticketFactory->createServiceTicket($service, FALSE);
    $this->drupalGet('cas/p3/serviceValidate', [
      'query' => [
        'service' => $service,
        'ticket' => $st->getId(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<cas:authenticationSuccess>');
    $this->assertSession()->responseContains(implode('', [
      '<cas:user>',
      $this->ticketFactory->getUsernameAttribute($this->exampleUser),
      '</cas:user>',
    ]));
    $this->assertSession()->responseNotContains('<cas:proxies>');
    $this->assertSession()->responseContains('<cas:attributes>');
    $this->assertSession()->responseNotContains('<cas:uid>');
    $this->assertSession()->responseNotContains('<cas:mail>');
    // Check for additional attributes added by cass_attributes test module.
    $this->assertSession()
      ->responseContains('<cas:test_array>first value</cas:test_array>');
    $this->assertSession()
      ->responseContains('<cas:test_array>2nd value</cas:test_array>');
  }

  /**
   * Test a simple request without the correct parameters.
   */
  public function testMissingParameters(): void {
    // Protocol version 1.
    $this->drupalGet('cas/validate');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('no');
    $this->assertSession()->responseNotContains('html');

    // Protocol version 2.
    $this->drupalGet('cas/serviceValidate');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_REQUEST["\']>/'
    );

    // Protocol version 3.
    $this->drupalGet('cas/p3/serviceValidate');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseMatches(
      '/<cas:authenticationFailure code=["\']INVALID_REQUEST["\']>/'
    );
  }

}
