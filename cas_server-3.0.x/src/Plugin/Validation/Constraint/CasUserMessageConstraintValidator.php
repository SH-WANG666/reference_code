<?php

namespace Drupal\cas_server\Plugin\Validation\Constraint;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Validation\TypedDataAwareValidatorTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a CasUserMessageConstraint.
 */
class CasUserMessageConstraintValidator extends ConstraintValidator {

  use TypedDataAwareValidatorTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($string, Constraint $constraint) {
    $typed_data = $this->getTypedData();

    // An empty value is fine.
    if (!isset($string)) {
      return;
    }

    // Get the value with the proper datatype.
    if (!($typed_data instanceof PrimitiveInterface)) {
      throw new \LogicException('The data type must be a PrimitiveInterface at this point.');
    }
    $string = $typed_data->getCastedValue();

    // Strip out all tags and compare decoded output to ensure it is the same.
    // The result should be that the plain text string, with the exception of
    // html encoded entities will be confirmed as safe.
    if (Html::decodeEntities($string) != Html::decodeEntities(Xss::filter($string, []))) {
      $this->context->addViolation($constraint->hasInvalidCharacters);
    }
  }

}
