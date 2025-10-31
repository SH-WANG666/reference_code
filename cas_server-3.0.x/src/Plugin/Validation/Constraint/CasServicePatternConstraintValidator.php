<?php

namespace Drupal\cas_server\Plugin\Validation\Constraint;

use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Validation\TypedDataAwareValidatorTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a CasServicePatternConstraint.
 */
class CasServicePatternConstraintValidator extends ConstraintValidator {

  use TypedDataAwareValidatorTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($string, Constraint $constraint) {
    $typed_data = $this->getTypedData();

    // The value is required.
    if (empty($string)) {
      $this->context->addViolation($constraint->isRequired);
      return;
    }

    // Get the value with the proper datatype.
    if (!($typed_data instanceof PrimitiveInterface)) {
      throw new \LogicException('The data type must be a PrimitiveInterface at this point.');
    }
    $string = $typed_data->getCastedValue();

    // String needs to compile into a working regex.
    // @see \Drupal\cas_server\Entity\CasServerService::matches().
    $pattern = str_replace(
      '\*', '.*',
      '/^' . preg_quote($string, '/') . '$/'
    );

    // Check to see if it works for matching. Ignore E_WARNING issued from a
    // bad pattern.
    $result = @preg_match($pattern, 'https://example.com/');

    if ($result === FALSE) {
      $this->context->addViolation($constraint->patternInvalid, [
        '@error' => 'Call to preg_match failed.',
      ]);
    }
    elseif (preg_last_error() !== PREG_NO_ERROR) {
      $this->context->addViolation($constraint->patternInvalid, [
        '@error' => preg_last_error_msg(),
      ]);
    }
  }

}
