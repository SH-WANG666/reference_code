<?php

namespace Drupal\Tests\cas_server\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\cas_server\Entity\CasServerService;

/**
 * Tests responses from the user login form.
 *
 * @group cas_server
 */
class UserLoginFormTest extends BrowserTestBase {

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
   * Configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Drupal\Core\TempStore\PrivateTempStoreFactory definition.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * CAS Service Service entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * A user with Anonymous permissions, but can log into any service.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $exampleUser;

  /**
   * A user with permission to log into test service.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $userCanAccess;

  /**
   * A user with Anonymous permission. Cannot access services.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $userCannotAccess;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');
    $this->ticketFactory = $this->container->get('cas_server.ticket_factory');
    $this->ticketStore = $this->container->get('cas_server.storage');
    $this->connection = $this->container->get('database');
    $this->tempStoreFactory = $this->container->get('tempstore.private');
    $this->entityStorage = $this->container->get('entity_type.manager')
      ->getStorage('cas_server_service');

    $test = CasServerService::create([
      'id' => 'test',
      'label' => 'Test Service',
      'service' => '*',
      'sso' => TRUE,
      'attributes' => [],
    ]);
    $test->save();

    $this->exampleUser = $this->drupalCreateUser(
      ['cas server login to any service'],
      'exampleUserName'
    );

    $this->userCanAccess = $this->drupalCreateUser(
      ['cas server login to test service'],
      'userCanAccess'
    );

    $this->userCannotAccess = $this->drupalCreateUser(
      [],
      'userCannotAccess'
    );

    // Remove default permission added during install so that permissions can
    // be tested with existing tests.
    user_role_revoke_permissions(
      'authenticated', [
        'cas server login to any service',
      ]
    );
  }

  /**
   * Test submitting with bad username/password.
   */
  public function testBadCredentials(): void {
    $this->drupalGet('cas/login');
    $edit = [
      'username' => $this->exampleUser->getAccountName(),
      'password' => $this->exampleUser->pass_raw . 'foadasd',
    ];
    $this->submitForm($edit, 'Submit');

    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertEmpty($cookie_cas_tgc);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('Invalid username or password.');
  }

  /**
   * Test submitting with correct values but no service, and no TGC enabled.
   */
  public function testCorrectNoServiceNoTicketGrantingCookie(): void {
    $this->drupalGet('cas/login');
    $edit = [
      'username' => $this->exampleUser->getAccountName(),
      'password' => $this->exampleUser->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('You are logged in to CAS single sign on.');

    // No cookie set because ticket_granting_ticket_auth defaults to false.
    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertEmpty($cookie_cas_tgc);
  }

  /**
   * Test submitting with correct values but no service, with TGC enabled.
   */
  public function testCorrectNoServiceWithTicketGrantingCookie(): void {
    // Change the configuration setting.
    $editable = $this->configFactory->getEditable('cas_server.settings');
    $editable->set('ticket.ticket_granting_ticket_auth', TRUE);
    $editable->save();

    $this->drupalGet('cas/login');
    $edit = [
      'username' => $this->exampleUser->getAccountName(),
      'password' => $this->exampleUser->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('You are logged in to CAS single sign on.');

    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertNotEmpty($cookie_cas_tgc);
  }

  /**
   * Test submitting without a valid login ticket.
   */
  public function testInvalidLoginTicket(): void {
    // LT is passed as a value in the form and is stored in the database.
    // Load the form to generate a new LT, and then change the value in the db.
    $this->drupalGet('cas/login');
    $this->connection->update('cas_server_ticket_store')
      ->fields([
        'id' => 'THIS_TICKET_IS_NOW_INVALID',
      ])
      ->condition('type', 'login')
      ->execute();

    $edit = [
      'username' => $this->exampleUser->getAccountName(),
      'password' => $this->exampleUser->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');

    $this->assertSession()->statusCodeEquals(200);
    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertEmpty($cookie_cas_tgc);
    $this->assertSession()
      ->pageTextContains('Login ticket invalid. Refresh page and try again.');

    // LT has an expiry time. Change the expiry to be in the past.
    $this->drupalGet('cas/login');
    $this->connection->update('cas_server_ticket_store')
      ->fields([
        // Drupal 1 launch date!
        'expiration' => '2001-01-15 11:22:33',
      ])
      ->condition('type', 'login')
      ->execute();

    $edit = [
      'username' => $this->exampleUser->getAccountName(),
      'password' => $this->exampleUser->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');

    $this->assertSession()->statusCodeEquals(200);
    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertEmpty($cookie_cas_tgc);
    $this->assertSession()
      ->pageTextContains('Login ticket invalid. Refresh page and try again.');
  }

  /**
   * Test submitting with correct values and a service.
   */
  public function testCorrectWithService(): void {
    // Change the configuration setting to allow the possibility of TGC.
    $editable = $this->configFactory->getEditable('cas_server.settings');
    $editable->set('ticket.ticket_granting_ticket_auth', TRUE);
    $editable->save();

    // Install the support module to help with testing.
    $this->assertTrue(
      \Drupal::service('module_installer')
        ->install(['cass_cookies_test']),
      'cass_cookies_test installed.'
    );

    // Confirm that extracting unique id without generate doesn't break.
    $this->drupalGet('cass_cookies_test/unique-id');
    $this->assertSession()->statusCodeEquals(200);
    $unique_id = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertEmpty($unique_id['unique_id']);

    $service = Url::fromRoute('cas_server.validate1');
    $service->setAbsolute();
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => $service->toString(),
      ],
    ]);
    $edit = [
      'username' => $this->exampleUser->getAccountName(),
      'password' => $this->exampleUser->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextNotContains('Your account does not have the required permissions to log in to this service');
    $this->assertSession()->pageTextMatches("/^no$/");

    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertNotEmpty($cookie_cas_tgc);

    // @phpcs:disable
    // @todo Left-over from WebTestBase::redirectCount. Find alternative.
    // $this->assertEquals($this->redirectCount, 2);
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
    $this->assertNotEmpty($ticket);
    $tid = $ticket->id;

    // Validate the ticket and confirm the response. No redirect expected.
    $this->drupalGet('cas/validate', [
      'query' => [
        'service' => $service->toString(),
        'ticket' => $tid,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('yes ' . $this->exampleUser->getAccountName());

    // Ticket was removed as part of that validation. Confirm that it no longer
    // works to validate.
    $this->drupalGet('cas/validate', [
      'query' => [
        'service' => $service->toString(),
        'ticket' => $tid,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextMatches("/^no$/");
  }

  /**
   * Test submitting with correct values and a service not configured for SSO.
   */
  public function testCorrectWithServiceNoSso(): void {
    // Change the configuration setting to allow the possibility of TGC.
    $editable = $this->configFactory->getEditable('cas_server.settings');
    $editable->set('ticket.ticket_granting_ticket_auth', TRUE);
    $editable->save();

    $test = $this->entityStorage->load('test');
    $test->setSso(FALSE);
    $test->save();

    // Install the support module to help with testing.
    $this->assertTrue(
      \Drupal::service('module_installer')
        ->install(['cass_cookies_test']),
      'cass_cookies_test installed.'
    );

    $service = Url::fromRoute('cas_server.validate1');
    $service->setAbsolute();
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => $service->toString(),
      ],
    ]);
    $edit = [
      'username' => $this->exampleUser->getAccountName(),
      'password' => $this->exampleUser->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextNotContains('Your account does not have the required permissions to log in to this service');
    $this->assertSession()->pageTextMatches("/^no$/");

    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertEmpty($cookie_cas_tgc);

    // @phpcs:disable
    // @todo Left-over from WebTestBase::redirectCount. Find alternative.
    // $this->assertEquals($this->redirectCount, 2);
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
    $this->assertNotEmpty($ticket);
    $tid = $ticket->id;

    // Validate the ticket and confirm the response. No redirect expected.
    $this->drupalGet('cas/validate', [
      'query' => [
        'service' => $service->toString(),
        'ticket' => $tid,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('yes ' . $this->exampleUser->getAccountName());

    // Ticket was removed as part of that validation. Confirm that it no longer
    // works to validate.
    $this->drupalGet('cas/validate', [
      'query' => [
        'service' => $service->toString(),
        'ticket' => $tid,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextMatches("/^no$/");
  }

  /**
   * Test submitting with correct values and a service.
   */
  public function testServicePermissions(): void {

    // Change the configuration setting to allow the possibility of TGC.
    $editable = $this->configFactory->getEditable('cas_server.settings');
    $editable->set('ticket.ticket_granting_ticket_auth', TRUE);
    $editable->save();

    // Use validate url as service.
    $service = Url::fromRoute('cas_server.validate1');
    $service->setAbsolute();
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => $service->toString(),
      ],
    ]);

    // User cannot access given service.
    $edit = [
      'username' => $this->userCannotAccess->getAccountName(),
      'password' => $this->userCannotAccess->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextContains('Your account does not have the required permissions to log in to this service');

    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertEmpty($cookie_cas_tgc);

    // User can access given service. Call cas/login for new LT.
    $this->drupalGet('cas/login', [
      'query' => [
        'service' => $service->toString(),
      ],
    ]);
    $edit = [
      'username' => $this->userCanAccess->getAccountName(),
      'password' => $this->userCanAccess->pass_raw,
    ];
    $this->submitForm($edit, 'Submit');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->pageTextNotContains('Your account does not have the required permissions to log in to this service');

    $cookie_cas_tgc = $this->getSession()->getCookie('cas_tgc');
    $this->assertNotEmpty($cookie_cas_tgc);
  }

}
