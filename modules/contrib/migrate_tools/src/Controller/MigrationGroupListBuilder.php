<?php

namespace Drupal\migrate_tools\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of migration group entities.
 *
 * @package Drupal\migrate_tools\Controller
 *
 * @ingroup migrate_tools
 */
class MigrationGroupListBuilder extends ConfigEntityListBuilder {

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   A render array structure of header strings.
   *
   * @see Drupal\Core\Entity\EntityListController::render()
   */
  public function buildHeader() {
    error_log('test1');
    $header['label'] = $this->t('Migration Group');
    $header['machine_name'] = $this->t('Machine Name');
    $header['description'] = $this->t('Description');
    $header['source_type'] = $this->t('Source Type');
    return $header + parent::buildHeader();
  }

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to build the row.
   *
   * @return array
   *   A render array of the table row for displaying the entity.
   *
   * @see \Drupal\Core\Entity\EntityListController::render()
   */
  public function buildRow(EntityInterface $entity) {
    error_log('test2');
    $row['label'] = $entity->label();
    $row['machine_name'] = $entity->id();
    $row['description'] = $entity->get('description');
    $row['source_type'] = $entity->get('source_type');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $operations['list'] = [
      'title' => $this->t('List migrations'),
      'weight' => 0,
      'url' => Url::fromRoute('entity.migration.list', ['migration_group' => $entity->id()]),
    ];

    return $operations;
  }

}
