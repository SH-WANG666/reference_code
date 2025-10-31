<?php

namespace Drupal\Tests\cas_server\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\RoleInterface;

/**
 * Tests configuring a CAS service entity via UI.
 *
 * @group cas_server
 */
class ServiceConfigTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'cas_server',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The roles created for testing.
   *
   * @var \Drupal\user\RoleInterface[]
   */
  protected $roles;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Remove default permission added during install so that permissions can
    // be tested with existing tests.
    user_role_revoke_permissions(
      'authenticated', [
        'cas server login to any service',
      ]
    );

    // Add three roles to use in testing.
    $this->drupalCreateRole(
      ['access content'],
      'role_users'
    );
    $this->drupalCreateRole(
      ['access content'],
      'role_editor'
    );
    $this->drupalCreateRole(
      ['access content', 'administer users'],
      'role_admin',
      // Define a label that will fail in the event that a twig quoting issue.
      $this->randomString(6) . '{{'
    );

    // Load those roles and have the objects available for use.
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    $this->roles = $role_storage->loadMultiple();
    unset($this->roles[RoleInterface::ANONYMOUS_ID]);
  }

  /**
   * Test granting and revoking access to services via roles.
   */
  public function testGrantRevokeRole(): void {

    // Log in as administrator.
    $this->drupalLogin($this->rootUser);

    // Ensure the correct number of roles for later checkbox counts.
    $this->assertCount(4, $this->roles);

    // Entity add page for services.
    $this->drupalGet('admin/config/people/cas_server/services/add');
    $this->assertSession()->statusCodeEquals(200);

    // Check that all of the roles are present.
    foreach ($this->roles as $role) {
      $this->assertSession()->pageTextContains($role->label());
    }

    // Set an admin role for the site.
    $this->roles['role_admin']->setIsAdmin(TRUE)->save();

    // Confirm that the role is no longer present in the create form.
    $this->drupalGet('admin/config/people/cas_server/services/add');
    $this->assertSession()
      ->pageTextNotContains($this->roles['role_admin']->label());

    // Create a service via UI.
    $edit = [
      'id' => 'example_com',
      'label' => 'Example Com',
      'service' => 'https://example.com/*',
      'sso' => '1',
      'allowed_roles[role_users]' => 'role_users',
    ];
    $this->submitForm($edit, 'Save');

    // Load the service edit form and confirm that only users role is ticked.
    $this->drupalGet('admin/config/people/cas_server/services/example_com');
    $this->assertSession()->statusCodeEquals(200);
    $checkboxes = $this->xpath('//input[@type="checkbox"]');
    // One extra checkbox from from the SSO setting.
    $this->assertCount(7, $checkboxes, 'Incorrect number of checkboxes found.');
    $expected = [
      'accept_all[' . RoleInterface::AUTHENTICATED_ID . ']' => FALSE,
      'accept_all[role_users]' => FALSE,
      'accept_all[role_editor]' => FALSE,
      'allowed_roles[authenticated]' => FALSE,
      'allowed_roles[role_users]' => TRUE,
      'allowed_roles[role_editor]' => FALSE,
      // Not a target of this test but required here to avoid errors.
      'sso' => TRUE,
    ];
    foreach ($checkboxes as $checkbox) {
      $name = (string) $checkbox->getAttribute('name');
      $this->assertTrue(isset($expected[$name]));
      $checked = $checkbox->isChecked();
      $this->assertSame($checked, $expected[$name]);
    }

    // Untick users, tick editors.
    $edit = [
      'allowed_roles[role_users]' => '0',
      'allowed_roles[role_editor]' => 'role_editor',
    ];
    $this->submitForm($edit, 'Save');

    // Check that changes have been reflected.
    $this->drupalGet('admin/config/people/cas_server/services/example_com');
    $this->assertSession()->statusCodeEquals(200);
    $checkboxes = $this->xpath('//input[@type="checkbox"]');
    $expected = [
      'accept_all[' . RoleInterface::AUTHENTICATED_ID . ']' => FALSE,
      'accept_all[role_users]' => FALSE,
      'accept_all[role_editor]' => FALSE,
      'allowed_roles[authenticated]' => FALSE,
      'allowed_roles[role_users]' => FALSE,
      'allowed_roles[role_editor]' => TRUE,
      // Not a target of this test but required here to avoid errors.
      'sso' => TRUE,
    ];
    foreach ($checkboxes as $checkbox) {
      $name = (string) $checkbox->getAttribute('name');
      $this->assertTrue(isset($expected[$name]));
      $checked = $checkbox->isChecked();
      $this->assertSame($checked, $expected[$name]);
    }

    // Grant permissions to all services and confirm reflected.
    user_role_grant_permissions(
      RoleInterface::AUTHENTICATED_ID, [
        'cas server login to any service',
      ]
    );
    $this->drupalGet('admin/config/people/cas_server/services/example_com');
    $this->assertSession()->statusCodeEquals(200);
    $checkboxes = $this->xpath('//input[@type="checkbox"]');
    $expected = [
      'accept_all[' . RoleInterface::AUTHENTICATED_ID . ']' => TRUE,
      'accept_all[role_users]' => FALSE,
      'accept_all[role_editor]' => FALSE,
      'allowed_roles[authenticated]' => FALSE,
      'allowed_roles[role_users]' => FALSE,
      'allowed_roles[role_editor]' => TRUE,
      // Not a target of this test but required here to avoid errors.
      'sso' => TRUE,
    ];
    foreach ($checkboxes as $checkbox) {
      $name = (string) $checkbox->getAttribute('name');
      $this->assertTrue(isset($expected[$name]));
      $checked = $checkbox->isChecked();
      $this->assertSame($checked, $expected[$name]);
    }

    // Confirm that anonymous checkbox is disabled on module permissions page.
    $this->drupalGet('admin/people/permissions/module/cas_server');
    $this->assertSession()->statusCodeEquals(200);
    $checkbox_names = [
      'anonymous[cas server login to any service]',
      'anonymous[cas server login to example_com service]',
    ];
    foreach ($checkbox_names as $checkbox_name) {
      $checkbox = $this->xpath('//input[@name="' . $checkbox_name . '"]');
      $this->assertNotEmpty($checkbox);
      $disabled = $checkbox[0]->getAttribute('disabled');
      $this->assertSame($disabled, 'disabled');
    }
  }

}
