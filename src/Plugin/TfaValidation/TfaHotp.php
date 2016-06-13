<?php

/**
 * @file TfaHOTP class
 */

namespace Drupal\tfa\Plugin\TfaValidation;

use Base32\Base32;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Otp\GoogleAuthenticator;
use Otp\Otp;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;


/**
 * @TfaValidation(
 *   id = "tfa_hotp",
 *   label = @Translation("TFA Hotp"),
 *   description = @Translation("TFA Hotp Validation Plugin"),
 *   fallbacks = {
 *    "tfa_recovery_code"
 *   }
 * )
 */
class TfaHotp extends TfaBasePlugin implements TfaValidationInterface {
  use DependencySerializationTrait;

  /**
   * @var GoogleAuthenticator
   */
  protected $auth;

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * @var int
   */
  protected $timeSkew;

  /**
   * @var bool
   */
  protected $alreadyAccepted;

  /**
   * @copydoc TfaBasePlugin::__construct()
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->auth = new \StdClass();
    $this->auth->otp = new Otp();
    $this->auth->ga  = new GoogleAuthenticator();
    // Allow codes within tolerance range of 3 * 30 second units.
    $this->timeSkew = \Drupal::config('tfa.settings')->get('time_skew');
    // Recommended: set variable tfa_totp_secret_key in settings.php.
    $this->encryptionKey = \Drupal::config('tfa.settings')->get('secret_key');
    $this->alreadyAccepted = FALSE;

    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.data')
    );
  }

  /**
   * @copydoc TfaBasePlugin::ready()
   */
  public function ready() {
    return ($this->getSeed() !== FALSE);
  }

  /**
   * @copydoc TfaValidationPluginInterface::getForm()
   */
  public function getForm(array $form, FormStateInterface $form_state) {
    $form['code'] = array(
      '#type' => 'textfield',
      '#title' => t('Application verification code'),
      '#description' => t('Verification code is application generated and @length digits long.', array('@length' => $this->codeLength)),
      '#required' => TRUE,
      '#attributes' => array('autocomplete' => 'off'),
    );
    $form['actions']['#type'] = 'actions';
    $form['actions']['login'] = array(
      '#type' => 'submit',
      '#value' => t('Verify'),
    );

    return $form;
  }

  /**
   * @copydoc TfaValidationPluginInterface::validateForm()
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    //dpm($values);
    if (!$this->validate($values['code'])) {
      $form_state->setErrorByName('code', t('Invalid application code. Please try again.'));
      if ($this->alreadyAccepted) {
        $form_state->setErrorByName('code', t('Invalid code, it was recently used for a login. Please wait for the application to generate a new code.'));
      }
    }
    else {
      // Store accepted code to prevent replay attacks.
      $this->storeAcceptedCode($values['code']);
    }
  }

  /**
   * @copydoc TfaBasePlugin::validate()
   */
  protected function validate($code) {
    // Strip whitespace.
    $code = preg_replace('/\s+/', '', $code);
    if ($this->alreadyAcceptedCode($code)) {
      $this->isValid = FALSE;
    }
    else {
      // Get OTP seed.
      $seed = $this->getSeed();
      $counter = $this->getHOTPCounter();
      $this->isValid = ($seed && ($counter = $this->auth->otp->checkHotpResync(Base32::decode($seed), ++$counter, $code)));
      $this->setUserData('tfa', ['tfa_hotp_counter' => $counter]);
    }
    return $this->isValid;
  }

  /**
   * @param string $code
   */
  protected function storeAcceptedCode($code) {
    $code = preg_replace('/\s+/', '', $code);
    $hash = hash('sha1', Settings::getHashSalt() . $code);

    //Store the hash made using the code in users_data
    // @todo Use the request time to say something like 'code was requested at ..'?
    $store_data = ['tfa_accepted_code_' . $hash =>  REQUEST_TIME];
    $this->setUserData('tfa', $store_data);
  }

  /**
   * Whether code has recently been accepted.
   *
   * @param string $code
   * @return bool
   */
  protected function alreadyAcceptedCode($code) {
    $hash   = hash('sha1', Settings::getHashSalt() . $code);

    //Check if the code has already been used or not.
    $key = 'tfa_accepted_code_' . $hash;
    $result = $this->getUserData('tfa', $key);

    if (!empty($result)) {
      $this->alreadyAccepted = TRUE;
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get seed for this account.
   *
   * @return string Decrypted account OTP seed or FALSE if none exists.
   */
  protected function getSeed() {
    // Lookup seed for account and decrypt.
    $uid =  $this->configuration['uid'];
    $result = $this->getUserData('tfa', 'tfa_hotp_seed');

    if (!empty($result)) {
      $encrypted = Base32::decode($result['seed']);
      $seed = $this->decrypt($encrypted);
      if (!empty($seed)) {
        return $seed;
      }
    }
    return FALSE;
  }

  /**
   * Delete users seeds.
   *
   * @return int
   */
  public function deleteSeed() {
    $this->deleteUserData('tfa', 'tfa_hotp_seed');
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbacks(){
    return ($this->pluginDefinition['fallbacks']) ?: '';
  }

 /**
  * {@inheritdoc}
  */
  public function setUserData($module, array $data) {
    $this->userData->set(
      $module,
      $this->configuration['uid'],
      key($data),
      current($data)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getUserData($module, $key) {
    $result = $this->userData->get(
      $module,
      $this->configuration['uid'],
      $key
    );

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUserData($module, $key){
    $this->userData->delete(
      $module,
      $this->configuration['uid'],
      $key
    );
  }

  /**
   * @return int
   *   The current value of the HOTP counter, or 1 if no value was found
   */
  public function getHOTPCounter(){
    $result = ($this->getUserData('tfa', 'tfa_hotp_counter')) ?: 1;

    return $result;
  }

}
