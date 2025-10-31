<?php

namespace Drupal\Tests\cas_server\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\cas_server\Entity\CasServerService;

/**
 * Tests responses from the user action controller.
 *
 * @group cas_server
 */
class UserActionControllerTest extends BrowserTestBase {

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
   * Configuration factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The session handler storage.
   *
   * @var \Drupal\Core\Session\SessionHandlerInterface
   */
  protected $sessionHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->exampleUser = $this->drupalCreateUser(
      ['cas server login to any service'],
      'exampleUserName'
    );

    $this->ticketFactory = $this->container->get('cas_server.ticket_factory');
    $this->ticketStore = $this->container->get('cas_server.storage');
    $this->configFactory = $this->container->get('config.factory');
    $this->connection = $this->container->get('database');
    $this->sessionHandler = $this->container->get('session_handler');

    $test = CasServerService::create([
      'id' => 'test',
      'label' => 'Test Service',
      'service' => 'https://foo.example.com*',
      'sso' => TRUE,
      'attributes' => [],
    ]);
    $test->save();

    // Remove default permission added during install so that permissions can
    // be tested with existing tests.
    user_role_revoke_permissions(
      'authenticated', [
        'cas server login to any service',
      ]
    );
  }

  /**
   * Test the logout path.
   */
  public function testLogout() {
    // Change the configuration setting.
    $editable = $this->configFactory->getEditable('cas_server.settings');
    $editable->set('ticket.ticket_granting_ticket_auth', TRUE);
    $editable->save();

    // Install the support module to help with testing.
    $this->assertTrue(
      \Drupal::service('module_installer')
        ->install(['cass_cookies_test']),
      'cass_cookies_test installed.'
    );

    // Log into CAS.
    $this->drupalGet('cas/login');
    $edit = [
      'username' => $this->exampleUser->getAccountName(),
      'password' => $this->exampleUser->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('You are logged in to CAS single sign on.');

    // Make sure that drupalUserIsLoggedIn will work.
    $this->exampleUser->sessionId = $this->getSession()->getCookie(
      \Drupal::service('session_configuration')->getOptions(\Drupal::request())['name']
    );
    $this->assertTrue($this->drupalUserIsLoggedIn($this->exampleUser));

    // TGC will have been created as per the above changed config.
    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertNotEmpty($cookie_cas_tgc);

    // Get the signed in user's internal unique id.
    $this->drupalGet('cass_cookies_test/unique-id');
    $this->assertSession()->statusCodeEquals(200);
    $unique_id = Json::decode($this->getSession()->getPage()->getContent());

    // Proxy tickets take session ids in the constructor, so use those to test.
    $this->ticketFactory
      ->createProxyTicket('foo', FALSE, [], $unique_id['unique_id'], 0, 'bar');
    $this->ticketFactory
      ->createProxyTicket('baz', FALSE, [], $unique_id['unique_id'], 0, 'quux');

    // Confirm tickets created can be found.
    $tickets = $this->connection->select('cas_server_ticket_store', 'c')
      ->fields('c', ['id'])
      ->condition('session', $unique_id['unique_id'])
      ->execute()
      ->fetchAll();
    $this->assertNotEmpty($tickets);

    $this->drupalGet('cas/logout');
    $this->assertSession()->pageTextContains('You have been logged out');

    // Check user is not logged in. See previous check for details.
    $logged_in = (bool) $this->sessionHandler->read($this->exampleUser->sessionId);
    $this->assertFalse($logged_in);

    // TGC will have been removed.
    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertEmpty($cookie_cas_tgc);

    // Proxy tickets will have been removed.
    $tickets = $this->connection->select('cas_server_ticket_store', 'c')
      ->fields('c', ['id'])
      ->condition('session', $unique_id['unique_id'])
      ->execute()
      ->fetchAll();
    $this->assertEmpty($tickets);
  }

  /**
   * Test redirecting if presented with a service ticket.
   */
  public function testLoginRedirect() {
    // This test sets the service to our own internal service url and checks to
    // see if we get redirected there.
    $test_all = CasServerService::create([
      'id' => 'test_all',
      'label' => 'Allow All Test Services',
      'service' => '*',
      'sso' => FALSE,
      'attributes' => [],
    ]);
    $test_all->save();

    $service = Url::fromRoute('cas_server.validate1');
    $service->setAbsolute();
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => $service->toString(),
        'ticket' => 'foo',
      ],
    ]);
    $cas_validate_url = Url::fromUri('internal:/cas/validate', [
      'query' => [
        'ticket' => 'foo',
      ],
      'absolute' => TRUE,
    ])->toString();
    $this->assertSession()->addressEquals($cas_validate_url);

    // @todo Left-over from WebTestBase::redirectCount. Find alternative.
    // $this->assertEquals($this->redirectCount, 1);
  }

  /**
   * Check invalid service message.
   */
  public function testInvalidServiceMessage() {
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => 'https://bar.example.com',
      ],
    ]);
    $this->assertSession()
      ->pageTextContains('You have not requested a valid service');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test already logged in message.
   */
  public function testAlreadyLoggedIn() {
    $this->drupalLogin($this->exampleUser);
    $this->drupalGet('cas/login');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('You are logged in to CAS single sign on');
  }

  /**
   * Test receiving form with no service and not logged in.
   */
  public function testNoServiceNoSession() {
    $this->drupalGet('cas/login');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('username');
    $this->assertSession()->fieldExists('password');
    $this->assertSession()->hiddenFieldExists('lt');
  }

  /**
   * Test receiving form with a service and not logged in.
   */
  public function testServiceNoSession() {
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => 'https://foo.example.com',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('username');
    $this->assertSession()->fieldExists('password');
    $this->assertSession()->hiddenFieldExists('lt');
    $this->assertSession()->hiddenFieldValueEquals(
      'service',
      'https://foo.example.com'
    );
  }

  /**
   * Test gateway pass-through with no session.
   */
  public function testGatewayPassThrough() {
    $test_all = CasServerService::create([
      'id' => 'test_all',
      'label' => 'Allow All Test Services',
      'service' => '*',
      'sso' => FALSE,
      'attributes' => [],
    ]);
    $test_all->save();
    $service = Url::fromRoute('cas_server.validate1');
    $service->setAbsolute();
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => $service->toString(),
        'gateway' => 'true',
      ],
    ]);
    $this->assertSession()->addressEquals('cas/validate');

    // @todo Left-over from WebTestBase::redirectCount. Find alternative.
    // $this->assertEquals($this->redirectCount, 1);
  }

  /**
   * Test single sign on redirect.
   */
  public function testSingleSignOn() {
    $test_all = CasServerService::create([
      'id' => 'test_all',
      'label' => 'Allow All Test Services',
      'service' => '*',
      'sso' => TRUE,
      'attributes' => [],
    ]);
    $test_all->save();

    // Install the support module to help with testing.
    $this->assertTrue(
      \Drupal::service('module_installer')
        ->install(['cass_cookies_test']),
      'cass_cookies_test installed.'
    );

    $this->drupalLogin($this->exampleUser);
    $service = Url::fromRoute('cas_server.validate1');
    $service->setAbsolute();
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => $service->toString(),
      ],
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextNotContains('Your account does not have the required permissions to log in to this service');
    $post_login_url = $this->getSession()->getCurrentUrl();

    // @phpcs:disable
    // @todo Left-over from WebTestBase::redirectCount. Find alternative.
    // $this->assertEquals($this->redirectCount, 1);
    // @phpcs:enable

    // Get the signed in user's internal unique id.
    $this->drupalGet('cass_cookies_test/unique-id');
    $this->assertSession()->statusCodeEquals(200);
    $unique_id = Json::decode($this->getSession()->getPage()->getContent());

    $ticket = $this->connection->select('cas_server_ticket_store', 'c')
      ->fields('c', ['id'])
      ->condition('session', $unique_id['unique_id'])
      ->condition('type', 'service')
      ->execute()
      ->fetch();
    $tid = $ticket->id;

    $cas_validate_url = Url::fromUri('internal:/cas/validate', [
      'query' => [
        'ticket' => $tid,
      ],
      'absolute' => TRUE,
    ])->toString();
    $this->assertEquals($post_login_url, $cas_validate_url);
  }

  /**
   * Test permissions in combination SSO.
   */
  public function testSingleSignOnRestrictedServices() {

    // Create an all service to which user will be granted access.
    $test_too = CasServerService::create([
      'id' => 'test_too',
      'label' => 'Test Service Too',
      'service' => '*',
      'sso' => TRUE,
      'attributes' => [],
    ]);
    $test_too->save();

    // Create user with access to above service.
    $user = $this->drupalCreateUser(
      ['cas server login to test_too service'],
      'test_too_user'
    );

    // Change the configuration setting.
    $editable = $this->configFactory->getEditable('cas_server.settings');
    $editable->set('ticket.ticket_granting_ticket_auth', TRUE);
    $editable->save();

    // Log user in and assert it worked.
    $this->drupalLogin($user);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue($this->drupalUserIsLoggedIn($user));

    // Attempt to access service for which user does not have permissions.
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => 'https://foo.example.com/',
      ],
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('Your account does not have the required permissions to log in to this service');

    // Attempt to access service for which user does have access to. Use the
    // gateway to redirect on success.
    $service = Url::fromRoute('cas_server.validate1');
    $service->setAbsolute();
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => $service->toString(),
        'gateway' => 'true',
      ],
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextNotContains('Your account does not have the required permissions to log in to this service');
    $this->assertSession()->addressEquals($service->toString());
  }

}
