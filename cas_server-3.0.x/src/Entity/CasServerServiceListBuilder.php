<?php

namespace Drupal\cas_server\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of CasServerServices.
 */
class CasServerServiceListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Service');
    $header['pattern'] = $this->t('Service URL pattern');
    $header['sso'] = $this->t('SSO');
    $header['attr'] = $this->t('Attributes');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['pattern'] = $entity->getService();
    $row['sso'] = $entity->getSso() ? $this->t('Yes') : $this->t('No');
    $attributes = $entity->getAttributes();
    $row['attr'] = !empty($attributes)
      ? implode(', ', $attributes)
      : '-';

    return $row + parent::buildRow($entity);
  }

}
