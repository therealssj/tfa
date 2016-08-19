<?php

namespace Drupal\tfa\Tests;

use Base32\Base32;
use Drupal\simpletest\WebTestBase;
use Otp\GoogleAuthenticator;
use Otp\Otp;

/**
 * Tests the functionality of the Tfa plugins.
 *
 * @group Tfa
 */
class TfaValidationTest extends WebTestBase {

  /**
   * Object containing the external validation library.
   *
   * @var GoogleAuthenticator
   */
  protected $auth;

  /**
   * The validation plugin manager to fetch plugin information.
   *
   * @var \Drupal\tfa\TfaValidationPluginManager
   */
  protected $tfaValidationManager;

  /**
   * The secret.
   *
   * @var string
   */
  protected static $seed = "12345678901234567890";

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

  /**
   * {@inheritdoc}
   */
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
    $this->tfaValidationManager = \Drupal::service('plugin.manager.tfa.validation');
    $this->generateRoleKey();
    $this->generateEncryptionProfile();
  }

  /**
   * Test login with TOTP.
   */
  public function testTfaTotp() {
    // Setup validation plugin.
    $account = $this->drupalCreateUser(['require tfa', 'access content']);
    $plugin = 'tfa_totp';
    $this->config('tfa.settings')
         ->set('enabled', 1)
         ->set('validation_plugin', $plugin)
         ->set('encryption', 'test_encryption_profile')
         ->save();
    $validation_plugin = $this->tfaValidationManager->createInstance($plugin, ['uid' => $account->id()]);
    $validation_plugin->storeSeed(self::$seed);

    //Login.
    $edit = [
      'name' => $account->getUsername(),
      'pass' => $account->pass_raw,
    ];
    // Do not use drupalLogin as it does actual login.
    $this->drupalPostForm('user/login', $edit, t('Log in'));
    $this->assertText($this->uiStrings('app-desc'));
    // Get login hash. Could user tfa_login_hash() but would require reloading
    // account.
    $url_parts = explode('/', $this->url);
    $login_hash = array_pop($url_parts);

    // Try invalid code.
    $edit = [
      'code' => 112233,
    ];
    $this->drupalPostForm('tfa/' . $account->id() . '/' . $login_hash, $edit, t('Verify'));
    $this->assertText($this->uiStrings('invalid-code-retry'));

    // Try valid code.
    // Generate a code.
    $code = $this->auth->otp->totp(Base32::decode(self::$seed));
    $edit = [
      'code' => $code,
    ];

    $this->drupalPostForm('tfa/' . $account->id() . '/' . $login_hash, $edit, t('Verify'));
    $this->assertResponse(200);
    $this->assertText($account->getUsername());

    // Check for replay.
    $this->drupalLogout();
    $edit = [
      'name' => $account->getUsername(),
      'pass' => $account->pass_raw,
    ];

    // Do not use drupalLogin as it does actual login.
    $this->drupalPostForm('user/login', $edit, t('Log in'));
    $url_parts = explode('/', $this->url);
    $login_hash = array_pop($url_parts);

    $edit = [
      'code' => $code,
    ];

    $this->drupalPostForm('tfa/' . $account->id() . '/' . $login_hash, $edit, t('Verify'));
    $this->assertText($this->uiStrings('code-already-used'));
  }

  /**
   * Test login with HOTP.
   */
  public function testTfaHotp() {
    // Setup validation plugin.
    $account = $this->drupalCreateUser(['require tfa', 'access content']);
    $plugin = 'tfa_hotp';
    $this->config('tfa.settings')
         ->set('enabled', 1)
         ->set('validation_plugin', $plugin)
         ->set('encryption', 'test_encryption_profile')
         ->save();
    $validation_plugin = $this->tfaValidationManager->createInstance($plugin, ['uid' => $account->id()]);
    $validation_plugin->storeSeed(self::$seed);

    // Login.
    $edit = [
      'name' => $account->getUsername(),
      'pass' => $account->pass_raw,
    ];
    // Do not use drupalLogin as it does actual login.
    $this->drupalPostForm('user/login', $edit, t('Log in'));
    $this->assertText($this->uiStrings('app-desc'));
    // Get login hash. Could user tfa_login_hash() but would require reloading
    // account.
    $url_parts = explode('/', $this->url);
    $login_hash = array_pop($url_parts);
    //
    // Try invalid code.
    $edit = [
      'code' => 112233,
    ];
    $this->drupalPostForm('tfa/' . $account->id() . '/' . $login_hash, $edit, t('Verify'));
    $this->assertText($this->uiStrings('invalid-code-retry'));

    // Try valid code.
    // Generate a code.
    $code = $this->auth->otp->hotp(Base32::decode(self::$seed), 1);
    $edit = [
      'code' => $code,
    ];

    $this->drupalPostForm('tfa/' . $account->id() . '/' . $login_hash, $edit, t('Verify'));
    $this->assertResponse(200);
    $this->assertText($account->getUsername());

    // Check for replay.
    $this->drupalLogout();
    $edit = [
      'name' => $account->getUsername(),
      'pass' => $account->pass_raw,
    ];

    // Do not use drupalLogin as it does actual login.
    $this->drupalPostForm('user/login', $edit, t('Log in'));
    $url_parts = explode('/', $this->url);
    $login_hash = array_pop($url_parts);

    $edit = [
      'code' => $code,
    ];

    $this->drupalPostForm('tfa/' . $account->id() . '/' . $login_hash, $edit, t('Verify'));
    $this->assertText($this->uiStrings('code-already-used'));

  }

  /**
   * Test login with TOTP fallback method.
   */
  public function testFallback() {
    // Setup validation plugin.
    $account = $this->drupalCreateUser(['require tfa', 'access content']);
    $plugin = 'tfa_totp';
    $fallback_plugin = 'tfa_recovery_code';
    $fallback_plugin_config = [
      $plugin => [$fallback_plugin => ['enable' => 1, 'settings'=> ['recovery_codes_amount' => 1], 'weight' => -2]],
    ];
    $this->config('tfa.settings')
         ->set('enabled', 1)
         ->set('validation_plugin', $plugin)
         ->set('fallback_plugins', $fallback_plugin_config)
         ->set('encryption', 'test_encryption_profile')
         ->save();
    $validation_plugin = $this->tfaValidationManager->createInstance($fallback_plugin, ['uid' => $account->id()]);
    $validation_plugin->storeCodes(['222 333 444']);

    // Login.
    $edit = [
      'name' => $account->getUsername(),
      'pass' => $account->pass_raw,
    ];
    // Do not use drupalLogin as it does actual login.
    $this->drupalPostForm('user/login', $edit, t('Log in'));
    // Get login hash. Could user tfa_login_hash() but would require reloading
    // account.
    $url_parts = explode('/', $this->url);
    $login_hash = array_pop($url_parts);

    // Try invalid recovery code.
    $edit = [
      'code' => '111 222 333',
    ];
    $this->drupalPostForm('tfa/' . $account->id() . '/' . $login_hash, $edit, t('Verify'));
    $this->assertText($this->uiStrings('invalid-recovery-code'));

    // Try valid recovery code.
    $edit = [
      'code' => '222 333 444',
    ];
    $this->drupalPostForm('tfa/' . $account->id() . '/' . $login_hash, $edit, t('Verify'));
    $this->assertResponse(200);
    $this->assertText($account->getUsername());
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
      case 'invalid-recovery-code':
        return 'Invalid recovery code.';

      case 'app-desc':
        return 'Verification code is application generated and 6 digits long.';

      case 'invalid-code-retry':
        return 'Invalid application code. Please try again.';

      case 'code-already-used':
        return 'Invalid code, it was recently used for a login. Please try a new code.';
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
    $this->testKey = \Drupal::service('key.repository')->getKey('testing_key_128');
  }

  /**
   * Generate an Encryption profile for a Role key.
   */
  public function generateEncryptionProfile() {
    $values = [
      'id' => 'test_encryption_profile',
      'label' => 'Test encryption profile',
      'encryption_method' => 'test_encryption_method',
      'encryption_key' => $this->testKey->id()
    ];

    \Drupal::entityTypeManager()
      ->getStorage('encryption_profile')
      ->create($values)
      ->save();
  }

}
