<?php

namespace Drupal\Tests\multiversion\Functional;

use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Entity\WorkspaceInterface;

/**
 * Test the workspace entity.
 *
 * @group multiversion
 */
class WorkspaceTest extends MultiversionFunctionalTestBase {

  protected $strictConfigSchema = FALSE;

  protected static $modules = ['multiversion', 'key_value'];

  public function testOperations() {
    $default = Workspace::load(1);
    $this->assertTrue(!empty($default), 'Default workspace was created when installing Multiversion module.');
    $machine_name = $this->randomMachineName();
    $entity = Workspace::create(['machine_name' => $machine_name, 'label' => $machine_name, 'type' => 'basic']);

    $this->assertTrue($entity instanceof WorkspaceInterface, 'Workspace entity was created.');

    $entity->save();
    $this->assertEqual($machine_name, $entity->get('machine_name')->value, 'Workspace entity was saved.');

    $entity = Workspace::load($entity->id());
    $this->assertEqual($machine_name, $entity->get('machine_name')->value, 'Workspace entity was loaded by ID.');
    $this->assertEqual($machine_name, $entity->label(), 'Label method returns the workspace name.');

    $created = $entity->getStartTime();
    $this->assertNotNull($created, "The value for 'created' field is not null.");

    $new_created_time = microtime(TRUE) * 1000000;
    $entity->setCreatedTime((int) $new_created_time);
    $this->assertEqual($new_created_time, $entity->getStartTime(), "Correct value for 'created' field.");

    //  Note that only lowercase characters (a-z), digits (0-9),
    // or any of the characters _, $, (, ), +, -, and / are allowed.
    $workspace1 = Workspace::create(['label' => 'Workspace 1', 'machine_name' => 'a0_$()+-/', 'type' => 'basic']);
    $violations1 = $workspace1->validate();
    $this->assertEqual($violations1->count(), 0, 'No violations');

    $workspace2 = Workspace::create(['label' => 'Workspace 2', 'machine_name' => 'A!"£%^&*{}#~@?', 'type' => 'basic']);
    $violations2 = $workspace2->validate();
    $this->assertEqual($violations2->count(), 1, 'One violation');
  }

  public function testActiveWorkspace() {
    $live = $this->workspaceManager->getActiveWorkspace();
    $this->assertEqual('live', $live->getMachineName());

    // Create a test workspaces.
    $test1 = Workspace::create(['machine_name' => 'test1', 'label' => 'test1', 'type' => 'basic']);
    $test1->save();
    $test2 = Workspace::create(['machine_name' => 'test2', 'label' => 'test2', 'type' => 'basic']);
    $test2->save();

    // Create test users.
    $user1 = $this->drupalCreateUser(['administer workspaces']);
    $user2 = $this->drupalCreateUser(['administer workspaces']);

    // Assert the workspace doesn't change when logging in.
    $this->drupalLogin($user1);
    $this->assertEqual('live', $this->workspaceManager->getActiveWorkspace()->getMachineName());

    // Switch and check that the manager return the correct workspace.
    $this->workspaceManager->setActiveWorkspace($test1);
    $this->assertEqual('test1', $this->workspaceManager->getActiveWorkspace()->getMachineName());

    // Log out and check that we go back to the default workspace and log back
    // in and check that the previous workspace has persisted for that user.
    $this->drupalLogout();
    $this->assertEqual('live', $this->workspaceManager->getActiveWorkspace()->getMachineName());
    $this->drupalLogin($user1);
    $this->assertEqual('test1', $this->workspaceManager->getActiveWorkspace()->getMachineName());
    $this->drupalLogout();

    // Login as a different user and set another workspace. Then check that the
    // last user still has the previous workspace persisted.
    $this->drupalLogin($user2);
    $this->workspaceManager->setActiveWorkspace($test2);
    $this->assertEqual('test2', $this->workspaceManager->getActiveWorkspace()->getMachineName());
    $this->drupalLogout();
    $this->drupalLogin($user1);
    $this->assertEqual('test1', $this->workspaceManager->getActiveWorkspace()->getMachineName());
  }

  public function testDeleteWorkspaceActiveForUser() {
    $cats = Workspace::create(['label' => 'Cats', 'machine_name' => 'cats', 'type' => 'basic']);
    $cats->save();
    $dogs = Workspace::create(['label' => 'Dogs', 'machine_name' => 'dogs', 'type' => 'basic']);
    $dogs->save();
    $dogs_id = $dogs->id();
    $alina = $this->drupalCreateUser(['administer workspaces']);
    $john = $this->drupalCreateUser(['administer workspaces']);

    // Login Alina and set the Cats workspace as active.
    $this->drupalLogin($alina);
    $this->workspaceManager->setActiveWorkspace($cats);
    $this->assertEqual('cats', $this->workspaceManager->getActiveWorkspace()->getMachineName());

    // Login John and set the Dogs workspace as active.
    $this->drupalLogin($john);
    $this->workspaceManager->setActiveWorkspace($dogs);
    $this->assertEqual('dogs', $this->workspaceManager->getActiveWorkspace()->getMachineName());

    // Login again Alina, the active workspace for this user should still be the
    // Cats workspace.
    $this->drupalLogin($alina);
    $this->assertEqual('cats', $this->workspaceManager->getActiveWorkspace()->getMachineName());

    // Alina is a cats person and decides to delete the Dogs workspace, without
    // taking into consideration that Dogs workspace can be active for another
    // user. This user has all the permissions to do that.
    $dogs->delete();
    // As workspaces are fully deleted on cron, run it.
    \Drupal::service('cron')->run();
    // After cron run the workspace should be deleted from the database.
    $this->assertEmpty(Workspace::load($dogs_id));

    // Now login John, this user would expect to see as active the Dogs
    // workspace, but if anyone else with the permissions to delete that
    // workspace, deleted it, then John should have as active the default
    // workspace. As Alina deleted the Dogs workspace, the active workspace
    // should be the Live workspace.
    $this->drupalLogin($john);
    $this->assertEqual('live', $this->workspaceManager->getActiveWorkspace()->getMachineName());
  }

}
