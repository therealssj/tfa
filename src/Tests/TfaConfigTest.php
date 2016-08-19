<?php

namespace Drupal\tfa\Tests;

use Drupal\simpletest\WebTestBase;
use Otp\GoogleAuthenticator;
use Otp\Otp;

/**
 * Tests the Tfa UI.
 *
 * @group Tfa
 */
class TfaConfigTest extends WebTestBase {
  /**
   * Object containing the external validation library.
   *
   * @var GoogleAuthenticator
   */
  protected $auth;

  /**
   * User doing the TFA Validation.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * Administrator to handle configurations.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * Test key for encryption
   *
   * @var \Drupal\key\Entity\Key
   */
  protected $testKey;

  /**
   * Exempt from strict schema checking.
   *
   * @see \Drupal\Core\Config\Testing\ConfigSchemaChecker
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  public static $modules = ['tfa', 'node', 'encrypt', 'encrypt_test', 'key', 'ga_login'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    // Enable TFA module and the test module.
    parent::setUp();

    // OTP class to do GA Login validation.
    $this->auth = new \StdClass();
    $this->auth->otp = new Otp();
    $this->auth->ga  = new GoogleAuthenticator();
    $this->webUser = $this->drupalCreateUser(['setup own tfa']);
    $this->adminUser = $this->drupalCreateUser(['administer users', 'administer site configuration']);
    $this->generateRoleKey();
    $this->generateEncryptionProfile();
  }

  /**
   * Test to check if configurations are working as desired.
   */
  public function testTfaConfig() {
    // Check that config form is restricted for users.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('admin/config/people/tfa');
    $this->assertResponse(403);

    // Check that config form is accessible to admins.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/people/tfa');
    $this->assertResponse(200);
    $this->assertText($this->uiStrings('config-form'));

    $edit = [
      'tfa_enabled' => TRUE,
      'tfa_validate' => 'tfa_hotp',
      'tfa_login[tfa_trusted_browser]' => 'tfa_trusted_browser',
    ];

    $this->drupalPostForm(NULL, $edit, t('Save configuration'));
    $this->assertText($this->uiStrings('config-saved'));
    $this->assertOptionSelected('edit-tfa-validate', 'tfa_hotp', t('Plugin selected'));
    $this->assertFieldChecked('edit-tfa-fallback-tfa-hotp-tfa-recovery-code-enable', t('Fallback selected'));
  }

  /**
   * TFA module user interface strings.
   *
   * @param string $id
   *   ID of string.
   *
   * @return string
   *   UI message for corresponding id.
   */
  protected function uiStrings($id) {
    switch ($id) {
      case 'config-form':
        return 'TFA Settings';

      case 'config-saved':
        return 'The configuration options have been saved.';
    }
  }

  /**
   * Generate a Role key.
   */
  public function generateRoleKey() {
    // Generate a key; at this stage the key hasn't been configured completely.
    $values = [
      'id' => 'testing_key_128',
      'label' => 'Testing Key 128 bit',
      'key_type' => "encryption",
      'key_type_settings' => ['key_size' => '128'],
      'key_provider' => 'config',
      'key_input' => 'none',
      // This is actually 16bytes but oh well..
      'key_provider_settings' => ['key_value' => 'mustbesixteenbit', 'base64_encoded' => FALSE],
    ];
    \Drupal::entityTypeManager()
      ->getStorage('key')
      ->create($values)
      ->save();
  }

  /**
   * Generate an Encryption profile for a Role key.
   */
  public function generateEncryptionProfile() {
    $values = [
     'id' => 'test_encryption_profile',
     'label' => 'Test encryption profile',
     'encryption_method' => 'test_encryption_method',
     'encryption_key' => 'testing_key_128'
    ];

    \Drupal::entityTypeManager()
      ->getStorage('encryption_profile')
      ->create($values)
      ->save();
  }
}
