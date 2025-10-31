<?php

namespace Drupal\Tests\cas_server\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\cas_server\CasServerServiceInterface;
use Drupal\cas_server\Entity\CasServerService;

/**
 * Tests the services definition behavior.
 *
 * @group cas_server
 */
class ConfigHelperTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'cas_server',
  ];

  /**
   * The configuration helper.
   *
   * @var \Drupal\cas_server\ConfigHelper
   */
  protected $configHelper;

  /**
   * Test service one.
   *
   * @var \Drupal\cas_server\Entity\CasServerService
   */
  protected $testServiceOne;

  /**
   * Test service two.
   *
   * @var \Drupal\cas_server\Entity\CasServerService
   */
  protected $testServiceTwo;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['cas_server']);

    $this->testServiceOne = CasServerService::create([
      'id' => 'test_service',
      'label' => 'Test Service',
      'service' => 'htt*://foo.example.com*',
      'sso' => TRUE,
      'attributes' => [
        'field_test_attributes' => 'field_test_attributes',
        'mail' => 'mail',
      ],
    ]);
    $this->testServiceOne->save();

    $this->testServiceTwo = CasServerService::create([
      'id' => 'test_service_too',
      'label' => 'Test Service Too',
      'service' => 'http://local.he.com/casservice',
      'sso' => TRUE,
      'attributes' => [],
    ]);
    $this->testServiceTwo->save();

    $this->configHelper = $this->container->get('cas_server.config_helper');
  }

  /**
   * Tests the service pattern matching.
   */
  public function testPatternMatchSuccess(): void {
    $service = $this->configHelper
      ->loadServiceFromUri('https://foo.example.com/bar?q=baz#quux');

    $this->assertNotEmpty($service);
  }

  /**
   * Tests the service pattern matching.
   */
  public function testPatternMatchFailure(): void {
    $service = $this->configHelper
      ->loadServiceFromUri('http://bar.example.com');

    $this->assertEmpty($service);
  }

  /**
   * Tests that the correct attributes are returned from a matched service.
   */
  public function testGetServiceAttributes(): void {
    $attributes = $this->configHelper
      ->getAttributesForService('https://foo.example.com');

    $this->assertEquals(['field_test_attributes', 'mail'], $attributes);
  }

  /**
   * Test extracting the service uri with additional parameters.
   *
   * @param string $service_url
   *   The service URI to run tests on.
   * @param array|bool $url_parts
   *   The expected url parts after parsing, or FALSE on failure.
   * @param string|bool $service_entity_id
   *   The expected service machine id, or FALSE if not found.
   *
   * @dataProvider serviceFromUrlDataProvider
   */
  public function testServiceFromUrl(
    ?string $service_url,
    array|bool $url_parts,
    string|bool $service_entity_id,
  ): void {

    $returned_service_entity = $this->configHelper
      ->loadServiceFromUri($service_url);

    if ($returned_service_entity instanceof CasServerServiceInterface) {
      $this->assertEquals($returned_service_entity->id(), $service_entity_id);

      $got_service_entity = $this->configHelper->getServiceEntity();
      $this->assertEquals(
        $returned_service_entity->id(),
        $got_service_entity->id()
      );

      $got_service_parts = $this->configHelper->getServiceUrl();
      $this->assertEquals($got_service_parts, $url_parts);
    }
    else {
      $this->assertFalse($returned_service_entity);
      $this->assertFalse($this->configHelper->getServiceEntity());
      $this->assertFalse($this->configHelper->getServiceUrl());
    }
  }

  /**
   * Data provider for testServiceFromUrl.
   *
   * @return array
   *   Testing parameters: url, url parts, service id.
   */
  public static function serviceFromUrlDataProvider(): \Generator {
    yield [NULL, FALSE, FALSE];
    yield [
      'https://foo.example.com',
      [
        'path' => 'https://foo.example.com',
        'query' => [],
        'fragment' => '',
      ],
      'test_service',
    ];
    yield [
      'https://foo.example.com/bar?q=baz#quux',
      [
        'path' => 'https://foo.example.com/bar',
        'query' => [
          'q' => 'baz',
        ],
        'fragment' => 'quux',
      ],
      'test_service',
    ];
    yield [
      'http%3A//local.he.com/casservice%3Freturnto%3D/exclusives/changing-subsea-boosting-application-landscape-177304',
      [
        'path' => 'http://local.he.com/casservice',
        'query' => [
          'returnto' => '/exclusives/changing-subsea-boosting-application-landscape-177304',
        ],
        'fragment' => '',
      ],
      'test_service_too',
    ];
    yield ['http://not-a-service.example.org/pickles', FALSE, FALSE];
  }

}
