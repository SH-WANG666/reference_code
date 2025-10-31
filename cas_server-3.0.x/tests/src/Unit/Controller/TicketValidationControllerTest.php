<?php

namespace Drupal\Tests\cas_server\Unit\Controller;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\cas_server\Unit\TestTicketValidationController;
use Drupal\cas_server\Ticket\ProxyTicket;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * TicketValidationController unit tests.
 *
 * @group cas_server
 *
 * @coversDefaultClass \Drupal\cas_server\Controller\TicketValidationController
 */
class TicketValidationControllerTest extends UnitTestCase {

  /**
   * The ticket factory.
   *
   * @var \Drupal\cas_server\Ticket\TicketFactory
   */
  protected $ticketFactory;

  /**
   * A proxy granting ticket.
   *
   * @var \Drupal\cas_server\Ticket\ProxyGrantingTicket
   */
  protected $pgt;

  /**
   * The ticket storage.
   *
   * @var \Drupal\cas_server\TicketStorageInterface
   */
  protected $ticketStore;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->ticketFactory = $this
      ->createMock('Drupal\cas_server\Ticket\TicketFactory');

    $this->pgt = $this
      ->createMock('Drupal\cas_server\Ticket\ProxyGrantingTicket');

    $this->ticketStore = $this
      ->createMock('Drupal\cas_server\TicketStorageInterface');
  }

  /**
   * Test the proxyCallback method through the test class.
   *
   * @param string $url
   *   The service url to test with.
   * @param string $ticket_class
   *   The ticket class to mock for testing.
   *
   * @covers ::proxyCallback
   *
   * @dataProvider proxyCallbackSuccessDataProvider
   */
  public function testProxyCallbackSuccess(string $url, string $ticket_class): void {
    $mock = new MockHandler([
      new Response(200),
      new Response(200),
    ]);
    $handler = HandlerStack::create($mock);
    $client = new Client(['handler' => $handler]);

    $this->ticketFactory->expects($this->once())
      ->method('createProxyGrantingTicket')
      ->willReturn($this->pgt);

    $this->pgt->expects($this->once())
      ->method('getId')
      ->willReturn('thisisapgtid');

    $ticket = $this->createMock($ticket_class);
    // Only mock the getProxyChain for ProxyTicket.
    if ($ticket instanceof ProxyTicket) {
      $ticket->expects($this->any())
        ->method('getProxyChain')
        ->willReturn(['foo']);
    }

    $controller = new TestTicketValidationController(
      $client,
      $this->ticketFactory,
      $this->ticketStore
    );
    $this->assertNotFalse($controller->callProxyCallback($url, $ticket));
  }

  /**
   * Data provider for testProxyCallbackSuccess.
   */
  public static function proxyCallbackSuccessDataProvider(): \Generator {

    $urls = ['https://example.com', 'https://example.com/bar?q=foo'];
    $ticket_classes = [
      'Drupal\cas_server\Ticket\ServiceTicket',
      'Drupal\cas_server\Ticket\ProxyTicket',
    ];

    foreach ($ticket_classes as $ticket_class) {
      foreach ($urls as $url) {
        yield [$url, $ticket_class];
      }
    }
  }

  /**
   * Test failure conditions for proxyCallback.
   *
   * @param string $url
   *   The service url to test with.
   * @param \GuzzleHttp\Client $client
   *   A guzzle client with a mocked request stack.
   *
   * @covers ::proxyCallback
   *
   * @dataProvider proxyCallbackFailureDataProvider
   */
  public function testProxyCallbackFailure(string $url, $client): void {

    $this->ticketFactory->expects($this->any())
      ->method('createProxyGrantingTicket')
      ->willReturn($this->pgt);

    $this->pgt->expects($this->any())
      ->method('getId')
      ->willReturn('thisisapgtid');

    $st = $this->createMock('Drupal\cas_server\Ticket\ServiceTicket');

    $controller = new TestTicketValidationController(
      $client,
      $this->ticketFactory,
      $this->ticketStore
    );
    $this->assertFalse($controller->callProxyCallback($url, $st));

  }

  /**
   * Data provider for testProxyCallbackFailure.
   */
  public static function proxyCallbackFailureDataProvider(): \Generator {
    $urls = ['http://example.com', 'https://example.com'];

    $mock = new MockHandler([
      new TransferException(),
      new Response(200),
    ]);
    $handler = HandlerStack::create($mock);
    $client1 = new Client(['handler' => $handler]);

    $mock = new MockHandler([
      new Response(200),
      new TransferException(),
    ]);
    $handler = HandlerStack::create($mock);
    $client2 = new Client(['handler' => $handler]);

    yield [$urls[0], $client1];
    yield [$urls[1], $client2];
    yield [$urls[1], $client1];
  }

}
