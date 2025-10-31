<?php

namespace Drupal\Tests\cas_server\Kernel;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the CasUserMessage constraint.
 *
 * @group cas_server
 */
class CasUserMessageTest extends KernelTestBase {

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
   * Tests the CasUserMessage validation constraint validator.
   *
   * For testing we define string with a set of allowed values.
   */
  public function testValidation() {
    // Create a string definition with a CasUserMessage constraint.
    $definition = DataDefinition::create('string')
      ->addConstraint('CasUserMessage', []);

    // Test a valid string.
    $typed_data = $this->typedData->create(
      $definition,
      'This is boring plain text! With an & in it. International: Père Noël'
    );
    $violations = $typed_data->validate();
    $this->assertEquals(
      0,
      $violations->count(),
      'Validation passed for correct value.'
    );

    // Test the validation when an invalid value is passed.
    $typed_data = $this->typedData->create(
      $definition,
      '<i>This is not plain text! </i>'
    );
    $violations = $typed_data->validate();
    $this->assertEquals(
      1,
      $violations->count(),
      'Validation failed for incorrect value.'
    );

    // Make sure the information provided by a violation is correct.
    $violation = $violations[0];
    $this->assertEquals(
      'String must be plain text.',
      $violation->getMessage(),
      'The message for invalid value is correct.'
    );
    $this->assertEquals(
      $typed_data,
      $violation->getRoot(),
      'Violation root is correct.'
    );
    $this->assertEquals(
      '<i>This is not plain text! </i>',
      $violation->getInvalidValue(),
      'The invalid value is set correctly in the violation.'
    );

    // Test the validation when a value of an incorrect type is passed.
    $typed_data = $this->typedData->create($definition, 1);
    $violations = $typed_data->validate();
    $this->assertEquals(
      0,
      $violations->count(),
      'Value is coerced to the correct type and is valid.'
    );
  }

}
