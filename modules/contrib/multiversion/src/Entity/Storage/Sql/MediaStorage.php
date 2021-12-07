<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageTrait;

// Support Drupal 8.6.x which introduced a dedicated Media storage class.
if (class_exists('\Drupal\media\MediaStorage')) {
  class_alias('\Drupal\media\MediaStorage', '\CoreMediaStorage');
}
else {
  class_alias('\Drupal\Core\Entity\Sql\SqlContentEntityStorage', '\CoreMediaStorage');
}


/**
 * Storage handler for media entity.
 */
class MediaStorage extends \CoreMediaStorage implements ContentEntityStorageInterface {

  use ContentEntityStorageTrait;

}
