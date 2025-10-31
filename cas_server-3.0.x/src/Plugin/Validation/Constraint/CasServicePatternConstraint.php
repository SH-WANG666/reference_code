<?php

namespace Drupal\cas_server\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks the value provided is plain text.
 */
#[Constraint(
  id: 'CasServicePattern',
  label: new TranslatableMarkup(
    'CAS service matching pattern constraint',
    [],
    ['context' => 'Validation']
  ),
  type: 'string'
)]
class CasServicePatternConstraint extends SymfonyConstraint {

  /**
   * Service pattern is required message.
   *
   * @var string
   */
  public $isRequired = 'A Service URL Pattern is required.';

  /**
   * Service pattern is invalid message.
   *
   * @var string
   */
  public $patternInvalid = 'Provided Service URL Pattern is invalid: @error';

}
