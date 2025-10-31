<?php

namespace Drupal\Tests\cas_server\Kernel;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the CasServicePattern constraint.
 *
 * @group cas_server
 */
class CasServicePatternTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'cas_server',
  ];

  /**
   * The typed data manager to use.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedData;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->typedData = $this->container->get('typed_data_manager');
  }

  /**
   * Tests the CasServicePattern validation constraint validator.
   *
   * For testing we define a string with a set of allowed values.
   */
  public function testValidation() {
    // Create a string definition with a CasUserMessage constraint.
    $definition = DataDefinition::create('string')
      ->addConstraint('CasServicePattern', []);

    // An empty value is invalid.
    $empty_values = [NULL, ''];
    foreach ($empty_values as $empty_value) {
      $typed_data = $this->typedData->create(
        $definition,
        $empty_value
      );
      $violations = $typed_data->validate();
      $this->assertEquals(
        1,
        $violations->count(),
        'An empty value is invalid.'
      );

      // Make sure the information provided by a violation is correct.
      $violation = $violations[0];
      $this->assertEquals(
        'A Service URL Pattern is required.',
        $violation->getMessage(),
        'The message for required value is correct.'
      );
      $this->assertEquals(
        $typed_data,
        $violation->getRoot(),
        'Violation root is correct.'
      );
      $this->assertEquals(
        $empty_value,
        $violation->getInvalidValue(),
        'The invalid value is set correctly in the violation.'
      );
    }

    // Test the validation when a value of an incorrect type is passed.
    $typed_data = $this->typedData->create($definition, 1);
    $violations = $typed_data->validate();
    $this->assertEquals(
      0,
      $violations->count(),
      'Value is coerced to the correct type and is valid.'
    );

    // Test a valid service strings.
    $valid_values = [
      'https://example.com/casservice*',
      'http*://*.edu/cas*',
      '*',
    ];
    foreach ($valid_values as $valid_value) {
      $typed_data = $this->typedData->create(
        $definition,
        $valid_value
      );
      $violations = $typed_data->validate();
      $this->assertEquals(
        0,
        $violations->count(),
        'Validation must pass for correct values.'
      );
    }

    // @todo Is there a possible invalid value?
    $this->assertEquals(1, 1, '1=1');
  }

}
