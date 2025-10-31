<?php

namespace Drupal\Tests\cas_server\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\cas_server\Entity\CasServerService;

/**
 * Tests responses from the proxy ticket granting system.
 *
 * @group cas_server
 */
class ProxyControllerTest extends BrowserTestBase {

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

    $test_proxy = CasServerService::create([
      'id' => 'test_proxy',
      'label' => 'Proxyable Test Service',
      'service' => 'https://foo.example.com*',
      'sso' => TRUE,
      'attributes' => [],
    ]);
    $test_proxy->save();

    $test_no_proxy = CasServerService::create([
      'id' => 'test_no_proxy',
      'label' => 'Unproxyable Test Service',
      'service' => 'https://bar.example.com*',
      'sso' => FALSE,
      'attributes' => [],
    ]);
    $test_no_proxy->save();
  }

  /**
   * Tests an successful request.
   */
  public function testProxySuccessRequest(): void {
    $this->drupalLogin($this->exampleUser);
    $pgt = $this->ticketFactory->createProxyGrantingTicket([]);
    $this->drupalLogout();
    $service = 'https://foo.example.com';

    $this->drupalGet('cas/proxy', [
      'query' => [
        'pgt' => $pgt->getId(),
        'targetService' => $service,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains("<cas:proxySuccess>");
    $this->assertSession()->responseContains("<cas:proxyTicket>");
  }

  /**
   * Tests an invalid proxy request.
   */
  public function testInvalidProxyRequest(): void {
    $this->drupalGet('cas/proxy');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->responseContains("<cas:proxyFailure code='INVALID_REQUEST'>");
    $this->assertSession()
      ->responseContains("'pgt' and 'targetService' parameters are both required");
  }

  /**
   * Tests an unauthorized service request.
   */
  public function testUnauthorizedServiceRequest(): void {
    $this->drupalLogin($this->exampleUser);
    $pgt = $this->ticketFactory->createProxyGrantingTicket([]);
    $this->drupalLogout();
    $service = 'https://bar.example.com';

    $this->drupalGet('cas/proxy', [
      'query' => [
        'pgt' => $pgt->getId(),
        'targetService' => $service,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->responseContains("<cas:proxyFailure code='UNAUTHORIZED_SERVICE_PROXY'>");
  }

  /**
   * Tests an expired ticket request.
   */
  public function testExpiredTicketRequest(): void {
    $this->drupalLogin($this->exampleUser);
    $pgt = $this->ticketFactory->createProxyGrantingTicket([]);
    $this->drupalLogout();
    $service = 'https://foo.example.com';

    // Directly update the expiry date on the PGT so that it's in the past.
    $pgt_timeout = $this->config('cas_server.settings')
      ->get('ticket.proxy_granting_ticket_timeout');
    $date = new \DateTime('now', new \DateTimeZone('UTC'));
    $date->setTimestamp($pgt->getExpirationTime() - $pgt_timeout - 3600);
    \Drupal::database()->update('cas_server_ticket_store')
      ->fields([
        'expiration' => $date->format('Y-m-d H:i:s'),
      ])
      ->condition('id', $pgt->getId())
      ->execute();

    // Use the now expired ticket.
    $this->drupalGet('cas/proxy', [
      'query' => [
        'pgt' => $pgt->getId(),
        'targetService' => $service,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->responseContains("<cas:proxyFailure code='INVALID_TICKET'>");
  }

  /**
   * Tests a missing ticket request.
   */
  public function testMissingTicketRequest(): void {
    $this->drupalLogin($this->exampleUser);
    $pgt = $this->ticketFactory->createProxyGrantingTicket([]);
    $this->drupalLogout();
    $this->ticketStore->deleteProxyGrantingTicket($pgt);
    $service = 'https://foo.example.com';

    $this->drupalGet('cas/proxy', [
      'query' => [
        'pgt' => $pgt->getId(),
        'targetService' => $service,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->responseContains("<cas:proxyFailure code='INVALID_TICKET'>");
    $this->assertSession()->pageTextContains("Ticket not found");
  }

  /**
   * Tests a incorrect ticket type request.
   */
  public function testWrongTicketTypeRequest(): void {
    $this->drupalLogin($this->exampleUser);
    $pgt = $this->ticketFactory->createTicketGrantingTicket();
    $this->drupalLogout();
    $service = 'https://foo.example.com';

    $this->drupalGet('cas/proxy', [
      'query' => [
        'pgt' => $pgt->getId(),
        'targetService' => $service,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->responseContains("<cas:proxyFailure code='INVALID_TICKET'>");
    $this->assertSession()->pageTextNotContains("Ticket not found");
  }

}
