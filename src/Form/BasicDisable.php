<?php

namespace Drupal\tfa\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tfa\Plugin\TfaValidation\TfaTotp;
use Drupal\tfa\TfaDataTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * TFA disable form router.
 */
class BasicDisable extends FormBase {
  use TfaDataTrait;
  /**
   * The plugin manager to fetch plugin information.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $manager;

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * BasicDisable constructor.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The plugin manager to fetch plugin information.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data object to store user information.
   */
  public function __construct(PluginManagerInterface $manager, UserDataInterface $user_data) {
    $this->manager = $manager;
    $this->userData = $user_data;
  }

  /**
   * Creates service objects for the class contructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to get the required services.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.tfa.validation'),
      $container->get('user.data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tfa_disable';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, User $user = NULL) {
    $account = User::load($this->currentUser()->id());

    $storage = $form_state->getStorage();
    $storage['account'] = $user;

    // @todo Check require permissions and give warning about being locked out.
    if ($account->id() != $user->id() && $account->hasPermission('administer users')) {
      $preamble_desc = t('Are you sure you want to disable TFA on account %name?', array('%name' => $user->getUsername()));
      $notice_desc = t('TFA settings and data will be lost. %name can re-enable TFA again from their profile.', array('%name' => $user->getUsername()));
    }
    else {
      $preamble_desc = t('Are you sure you want to disable your two-factor authentication setup?');
      $notice_desc = t("Your settings and data will be lost. You can re-enable two-factor authentication again from your profile.");
    }
    $form['preamble'] = array(
      '#prefix' => '<p class="preamble">',
      '#suffix' => '</p>',
      '#markup' => $preamble_desc,
    );
    $form['notice'] = array(
      '#prefix' => '<p class="preamble">',
      '#suffix' => '</p>',
      '#markup' => $notice_desc,
    );

    $form['account']['current_pass'] = array(
      '#type' => 'password',
      '#title' => t('Confirm your current password'),
      '#description_display' => 'before',
      '#size' => 25,
      '#weight' => -5,
      '#attributes' => array('autocomplete' => 'off'),
      '#required' => TRUE,
    );
    $form['account']['mail'] = array(
      '#type' => 'value',
      '#value' => $user->getEmail(),
    );
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Disable'),
    );
    $form['actions']['cancel'] = array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#limit_validation_errors' => array(),
    );

    $form_state->setStorage($storage);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user = User::load($this->currentUser()->id());
    $storage = $form_state->getStorage();
    $account = $storage['account'];
    // Allow administrators to disable TFA for another account.
    if ($account->id() != $user->id() && $user->hasPermission('administer users')) {
      $account = $user;
    }
    // Check password.
    $current_pass = \Drupal::service('password')->check(trim($form_state->getValue('current_pass')), $account->getPassword());
    if (!$current_pass) {
      $form_state->setErrorByName('current_pass', t("Incorrect password."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $storage = $form_state->getStorage();
    $values = $form_state->getValues();
    $account = $storage['account'];
    if ($values['op'] === $values['cancel']) {
      drupal_set_message(t('TFA disable canceled.'));
      $form_state->setRedirect('tfa.overview', ['user' => $account->id()]);
      return;
    }
    $this->tfaSaveTfaData($account->id(), $this->userData, array('status' => FALSE));

    // @todo Need to make this part generic
    // Delete OTP Seed.
    $validation_plugin = $this->manager->getInstance(['uid' => $account->id()]);
    $validation_plugin->deleteSeed();

    $fallbacks = $validation_plugin->getFallbacks();

    foreach ($fallbacks as $fallback) {
      $fallback_plugin = $this->manager->createInstance($fallback, ['uid' => $account->id()]);
      // @todo Need to make a generic function for purging user data.
      $fallback_plugin->deleteCodes();
    }

    \Drupal::logger('tfa')->notice('TFA disabled for user @name UID @uid', array(
      '@name' => $account->getUsername(),
      '@uid' => $account->id(),
    ));

    // @todo Not working, not sure why though.
    // E-mail account to inform user that it has been disabled.
    $params = array('account' => $account);
    \Drupal::service('plugin.manager.mail')->mail('tfa_basic', 'tfa_basic_disabled_configuration', $account->getEmail(), $account->getPreferredLangcode(), $params);

    drupal_set_message(t('TFA has been disabled.'));
    $form_state->setRedirect('tfa.overview', ['user' => $account->id()]);
  }

}
