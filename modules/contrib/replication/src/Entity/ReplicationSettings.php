<?php

namespace Drupal\replication\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\replication\Entity\ReplicationSettingsInterface;

/**
 * Defines the replication settings entity.
 *
 * The replication settings are attached to a Workspace to define how that
 * Workspace should be replicated.
 *
 * @ConfigEntityType(
 *   id = "replication_settings",
 *   label = @Translation("Replication settings"),
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   list_cache_tags = { "rendered" },
 *   config_export = {
 *     "id",
 *     "label",
 *     "locked",
 *     "pattern",
 *   }
 * )
 */


class ReplicationSettings extends ConfigEntityBase implements ReplicationSettingsInterface {

  /**
   * An identifier for these replication settings.
   *
   * @var string
   */
  protected $id;

  /**
   * The human readable name for these replication settings.
   *
   * @var string
   */
  protected $label;

  /**
   * The plugin ID of a replication filter.
   *
   * @var string
   */
  protected $filter_id;

  /**
   * The replication filter parameters.
   *
   * @var array
   */
  protected $parameters;

  /**
   * {@inheritdoc}
   */
  public function getFilterId() {
    return $this->filter_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters() {
    return $this->parameters;
  }
}
