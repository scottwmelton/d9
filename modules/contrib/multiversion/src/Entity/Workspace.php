<?php

namespace Drupal\multiversion\Entity;

use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;
use Drupal\workspace\Entity\Replication;
use Drupal\workspace\Entity\WorkspacePointer;

/**
 * The workspace entity class.
 *
 * @ContentEntityType(
 *   id = "workspace",
 *   label = @Translation("Workspace"),
 *   bundle_label = @Translation("Workspace type"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "views_data" = "Drupal\views\EntityViewsData"
 *   },
 *   admin_permission = "administer workspaces",
 *   base_table = "workspace",
 *   revision_table = "workspace_revision",
 *   data_table = "workspace_field_data",
 *   revision_data_table = "workspace_field_revision",
 *   bundle_entity_type = "workspace_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "machine_name" = "machine_name",
 *     "uid" = "uid",
 *     "created" = "created",
 *     "published" = "published"
 *   },
 *   multiversion = FALSE,
 *   local = TRUE
 * )
 */
class Workspace extends ContentEntityBase implements WorkspaceInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Workspace name'))
      ->setDescription(t('The workspace name.'))
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['machine_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Workspace ID'))
      ->setDescription(t('The workspace machine name.'))
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 128)
      ->setRequired(TRUE)
      ->addPropertyConstraints('value', ['Regex' => ['pattern' => '/^[\da-z_$()+-\/]*$/']]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The workspace owner.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\multiversion\Entity\Workspace::getCurrentUserId');

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The workspace type.'))
      ->setSetting('target_type', 'workspace_type')
      ->setReadOnly(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the workspace was last edited.'))
      ->setRevisionable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The UNIX timestamp of when the workspace has been created.'));

    $fields['published']->addConstraint('UnpublishWorkspace');

    $fields['queued_for_delete'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Queued for delete'))
      ->setDescription(t('A flag that specifies if the entity has been queued for delete on next cron run.'))
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setRequired(FALSE)
      ->setDefaultValue(FALSE)
      ->setInitialValue(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if (!$this->isNew()) {
      $workspace_id = $this->id();

      // Execute predelete action on workspace entity, this hook is needed to
      // be executed before the workspace is deleted on cron and before entity
      // predelete hooks provided by core.
      \Drupal::moduleHandler()->invokeAll('multiversion_workspace_predelete', [$this]);

      /** @var \Drupal\Core\Queue\QueueInterface $queue */
      $queue = \Drupal::queue('deleted_workspace_queue');
      $queue->createQueue();
      /** @var \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager */
      $multiversion_manager = \Drupal::service('multiversion.manager');
      $entity_type_manager = $this->entityTypeManager();
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_type */
      foreach ($multiversion_manager->getEnabledEntityTypes() as $entity_type) {
        // Load IDs for deleted entities.
        $deleted_entity_ids = $entity_type_manager
          ->getStorage($entity_type->id())
          ->getQuery()
          ->useWorkspace($workspace_id)
          ->isDeleted()
          ->execute();
        // Load IDs for non-deleted entities.
        $entity_ids = $entity_type_manager
          ->getStorage($entity_type->id())
          ->getQuery()
          ->useWorkspace($workspace_id)
          ->isNotDeleted()
          ->execute();
        foreach (array_merge($entity_ids, $deleted_entity_ids) as $entity_id) {
          $data = [
            'workspace_id' => $workspace_id,
            'entity_type_id' => $entity_type->id(),
            'entity_id' => $entity_id,
          ];
          $queue->createItem($data);
        }
      }
      // Add the workspace to the queue to be deleted.
      $data = [
        'entity_type_id' => 'workspace',
        'entity_id' => $workspace_id,
      ];
      $queue->createItem($data);
      $this->setQueuedForDelete()->save();

      if ($this->id() === $multiversion_manager->getActiveWorkspaceId()) {
        $multiversion_manager->setActiveWorkspaceId(\Drupal::getContainer()->getParameter('workspace.default'));
      }

      // Deleted workspace won't be active anymore for users that had it as
      // active, the default workspace will became active for them.
      $this->deleteWorkspaceActiveSessions($workspace_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateSeq() {
    return \Drupal::service('multiversion.entity_index.sequence')->useWorkspace($this->id())->getLastSequenceId();
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($created) {
    $this->set('created', (int) $created);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineName() {
    return $this->get('machine_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setQueuedForDelete($queued = TRUE) {
    $this->set('queued_for_delete', (bool) $queued);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueuedForDelete() {
    return $this->get('queued_for_delete')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultWorkspace() {
    return $this->id() == \Drupal::getContainer()->getParameter('workspace.default');
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Deletes from users private store entries where the workspace is active.
   *
   * This will lead to using the default active workspace as the active
   * workspace for users that had as active the deleted workspace.
   *
   * @param $workspace_id
   */
  protected function deleteWorkspaceActiveSessions($workspace_id) {
    // Get all collections for the workspace session negotiator and delete those
    // that have as active workspace the currently deleted workspace.
    // Manipulate directly with the database here becase for private tempstore
    // the access is restricted per current user.
    $session_negociator_collection = 'user.private_tempstore.workspace.negotiator.session';
    $values = \Drupal::database()
      ->select('key_value_expire', 'kve')
      ->fields('kve', ['name', 'value'])
      ->condition('collection', $session_negociator_collection)
      ->execute()
      ->fetchAll();

    foreach ($values as $value) {
      if (!empty($value->value) && !empty($value->name)) {
        $data = PhpSerialize::decode($value->value);
        if (!empty($data->data) && $data->data == $workspace_id) {
          \Drupal::database()
            ->delete('key_value_expire')
            ->condition('name', $value->name)
            ->execute();
        }
      }
    }
  }

}
