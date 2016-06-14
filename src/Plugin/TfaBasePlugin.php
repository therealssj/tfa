<?php

namespace Drupal\tfa\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserDataInterface;

/**
 * Base plugin class.
 */
abstract class TfaBasePlugin extends PluginBase {
  /**
   * The user submitted code to be validated.
   *
   * @var string
   */
  protected $code;

  /**
   * The allowed code length.
   *
   * @var int
   */
  protected $codeLength;

  /**
   * The error for the current validation.
   *
   * @var string[]
   */
  protected $errorMessages;

  /**
   * Whether the validation succeeded or not.
   *
   * @var bool
   */
  protected $isValid;

  /**
   * The user secret.
   *
   * @var string
   */
  protected $encryptionKey;

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs a new Tfa plugin object.
   *
   * @param array $configuration
   *    The plugin configuration.
   * @param string $plugin_id
   *    The plugin id.
   * @param mixed $plugin_definition
   *    The plugin definition.
   * @param \Drupal\user\UserDataInterface $user_data
   *    User data object to store user specific information.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Default code length is 6.
    $this->codeLength = 6;
    $this->isValid = FALSE;

    // User Data service to store user-based data in key value pairs.
    $this->userData = $user_data;
  }

  /**
   * Determine if the plugin can run for the current TFA context.
   *
   * @return bool
   *    True or False based on the checks performed.
   */
  abstract public function ready();

  /**
   * Get error messages suitable for form_set_error().
   *
   * @return array
   *   An array of error strings.
   */
  public function getErrorMessages() {
    return $this->errorMessages;
  }

  /**
   * Submit form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool Whether plugin form handling is complete.
   *   Plugins should return FALSE to invoke multi-step.
   */
  public function submitForm(array $form, FormStateInterface &$form_state) {
    return $this->isValid;
  }

  /**
   * Validate code.
   *
   * Note, plugins overriding validate() should be sure to set isValid property
   * correctly or else also override submitForm().
   *
   * @param string $code
   *   Code to be validated.
   *
   * @return bool
   *    Whether code is valid.
   */
  protected function validate($code) {
    if ((string) $code === (string) $this->code) {
      $this->isValid = TRUE;
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Generate a random string of characters of length $this->codeLength.
   *
   * @return string
   *   A random string.
   */
  protected function generate() {
    $characters = '123456789abcdefghijklmnpqrstuvwxyz';
    $string = '';
    $max = strlen($characters) - 1;
    for ($p = 0; $p < $this->codeLength; $p++) {
      $string .= $characters[mt_rand(0, $max)];
    }
    return $string;
  }

  /**
   * Encrypt a plaintext string.
   *
   * Should be used when writing codes to storage.
   *
   * @param string $text
   *   The string to be encrypted.
   *
   * @return string
   *   The enrcypted string.
   */
  protected function encrypt($text) {
    $key = $this->encryptionKey;

    $td = mcrypt_module_open('rijndael-128', '', 'ecb', '');
    $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);

    $key = substr($key, 0, mcrypt_enc_get_key_size($td));

    mcrypt_generic_init($td, $key, $iv);

    $data = mcrypt_generic($td, $text);

    mcrypt_generic_deinit($td);
    mcrypt_module_close($td);

    return $data;
  }

  /**
   * Decrypt a encrypted string.
   *
   * Should be used when reading codes from storage.
   *
   * @param string $data
   *   The string to be decrypted.
   *
   * @return string
   *   The decrypted string.
   */
  protected function decrypt($data) {
    $key = $this->encryptionKey;

    $td = mcrypt_module_open('rijndael-128', '', 'ecb', '');
    $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);

    $key = substr($key, 0, mcrypt_enc_get_key_size($td));

    mcrypt_generic_init($td, $key, $iv);

    $text = mdecrypt_generic($td, $data);

    mcrypt_generic_deinit($td);
    mcrypt_module_close($td);

    return $text;
  }

}
