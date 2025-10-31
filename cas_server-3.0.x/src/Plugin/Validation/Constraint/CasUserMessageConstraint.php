<?php

namespace Drupal\cas_server\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks the value provided is plain text.
 */
#[Constraint(
  id: 'CasUserMessage',
  label: new TranslatableMarkup(
    'CAS user message constraint',
    [],
    ['context' => 'Validation']
  ),
  type: 'string'
)]
class CasUserMessageConstraint extends SymfonyConstraint {

  /**
   * String doesn't meet restriction for a translatable string.
   *
   * @var string
   */
  public $hasInvalidCharacters = 'String must be plain text.';

}
