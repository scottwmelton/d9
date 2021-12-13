<?php

namespace Drupal\multiversion;

use Drupal\path\Plugin\Field\FieldType\PathFieldItemList;

class MultiversionFieldItemList extends PathFieldItemList {

  /**
   * @inheritDoc
   */

/* swm
  public function delete() {
    \Drupal::service('pathauto.alias_storage_helper')->deleteEntityPathAll($this->getEntity());
    if ($first = $this->first()) {
      $first->get('pathauto')->purge();
    }
  }
  */

}
