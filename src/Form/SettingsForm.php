<?php

namespace Drupal\tfa\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\tfa\TfaDataTrait;
use Drupal\tfa\TfaLoginPluginManager;
use Drupal\tfa\TfaSendPluginManager;
use Drupal\tfa\TfaSetupPluginManager;
use Drupal\tfa\TfaValidationPluginManager;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The admin configuration page.
 */
class SettingsForm extends ConfigFormBase {
  use TfaDataTrait;

  /**
   * The login plugin manager to fetch plugin information.
   *
   * @var \Drupal\tfa\TfaLoginPluginManager
   */
  protected $tfaLogin;

  /**
   * The send plugin manager to fetch plugin information.
   *
   * @var \Drupal\tfa\TfaSendPluginManager
   */
  protected $tfaSend;

  /**
   * The validation plugin manager to fetch plugin information.
   *
   * @var \Drupal\tfa\TfaValidationPluginManager
   */
  protected $tfaValidation;

  /**
   * The setup plugin manager to fetch plugin information.
   *
   * @var \Drupal\tfa\TfaSetupPluginManager
   */
  protected $tfaSetup;

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Encryption profile manager to fetch the existing encryption profiles.
   *
   * @var \Drupal\encrypt\EncryptionProfileManagerInterface
   */
  protected $encryptionProfileManager;

  /**
   * The admin configuraiton form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory object.
   * @param \Drupal\tfa\TfaLoginPluginManager $tfa_login
   *   The login plugin manager.
   * @param \Drupal\tfa\TfaSendPluginManager $tfa_send
   *   The send plugin manager.
   * @param \Drupal\tfa\TfaValidationPluginManager $tfa_validation
   *   The validation plugin manager.
   * @param \Drupal\tfa\TfaSetupPluginManager $tfa_setup
   *   The setup plugin manager.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryption_profile_manager
   *   Encrypt profile manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TfaLoginPluginManager $tfa_login, TfaSendPluginManager $tfa_send, TfaValidationPluginManager $tfa_validation, TfaSetupPluginManager $tfa_setup, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager) {
    parent::__construct($config_factory);
    $this->tfaLogin = $tfa_login;
    $this->tfaSend = $tfa_send;
    $this->tfaSetup = $tfa_setup;
    $this->tfaValidation = $tfa_validation;
    $this->encryptionProfileManager = $encryption_profile_manager;
    // User Data service to store user-based data in key value pairs.
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
    return new static($container->get('config.factory'), $container->get('plugin.manager.tfa.login'), $container->get('plugin.manager.tfa.send'), $container->get('plugin.manager.tfa.validation'), $container->get('plugin.manager.tfa.setup'), $container->get('user.data'), $container->get('encrypt.encryption_profile.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tfa_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('tfa.settings');
    $form = array();

    // Get Login Plugins.
    $login_plugins = $this->tfaLogin->getDefinitions();

    // Get Send Plugins.
    $send_plugins = $this->tfaSend->getDefinitions();

    // Get Validation Plugins.
    $validate_plugins = $this->tfaValidation->getDefinitions();

    // Get validation plugin labels and their fallbacks.
    $validate_plugins_labels = [];
    $validate_plugins_fallbacks = [];
    foreach ($validate_plugins as $plugin) {
      $validate_plugins_labels[$plugin['id']] = $plugin['label']->render();
      if (isset($plugin['fallbacks'])) {
        $validate_plugins_fallbacks[$plugin['id']] = $plugin['fallbacks'];
      }
    }

    // Get Setup Plugins.
    $setup_plugins = $this->tfaSetup->getDefinitions();
    // Fetching all available encrpytion profiles.
    $encryption_profiles = $this->encryptionProfileManager->getAllEncryptionProfiles();

    $plugins_empty = $this->dataEmptyCheck($validate_plugins, 'No plugins available for validation. See the TFA help documentation for setup.');
    $encryption_profiles_empty = $this->dataEmptyCheck($encryption_profiles, 'No Encryption profiles available. Please set one up.');

    if ($plugins_empty || $encryption_profiles_empty) {
      $form_state->cleanValues();
      // Return form instead of parent::BuildForm to avoid the save button.
      return $form;
    }

    // Enable TFA checkbox.
    $form['tfa_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable TFA'),
      '#default_value' => $config->get('enabled') && !empty($encryption_profiles),
      '#description' => t('Enable TFA for account authentication.'),
      '#disabled' => empty($encryption_profiles),
    );

    $enabled_state = array(
      'visible' => array(
        ':input[name="tfa_enabled"]' => array('checked' => TRUE),
      ),
    );

    if (count($validate_plugins)) {
      $form['tfa_validate'] = array(
        '#type' => 'select',
        '#title' => t('Validation plugin'),
        '#options' => $validate_plugins_labels,
        '#default_value' => $config->get('validate_plugin') ?: 'tfa_totp',
        '#description' => t('Plugin that will be used as the default TFA process.'),
        // Show only when TFA is enabled.
        '#states' => $enabled_state,
        '#required' => TRUE,
      );
    }
    else {
      $form['no_validate'] = array(
        '#value' => 'markup',
        '#markup' => t('No available validation plugins available. TFA process will not occur.'),
      );
    }

    if (count($validate_plugins_fallbacks)) {
      $form['tfa_fallback'] = array(
        '#type' => 'fieldset',
        '#title' => t('Validation fallback plugins'),
        '#description' => t('Fallback plugins and order. Note, if a fallback plugin is not setup for an account it will not be active in the TFA form.'),
        '#states' => $enabled_state,
        '#tree' => TRUE,
      );

      $enabled_fallback_plugins = $config->get('fallback_plugins');
      foreach ($validate_plugins_fallbacks as $plugin => $fallbacks) {
        $fallback_state = array(
          'visible' => array(
            ':input[name="tfa_validate"]' => array('value' => $plugin),
          ),
        );
        if (count($fallbacks)) {
          foreach ($fallbacks as $fallback) {
            $order = (@$enabled_fallback_plugins[$plugin][$fallback]['weight']) ?: -2;
            $fallback_value = (@$enabled_fallback_plugins[$plugin][$fallback]['enable']) ?: 1;
            $form['tfa_fallback'][$plugin][$fallback] = array(
              'enable' => array(
                '#title' => $validate_plugins_labels[$fallback],
                '#type' => 'checkbox',
                '#default_value' => $fallback_value,
                '#states' => $fallback_state,
              ),
              'weight' => array(
                '#type' => 'weight',
                '#title' => t('Order'),
                '#delta' => 2,
                '#default_value' => $order,
                '#title_display' => 'invisible',
                '#states' => $fallback_state,
              ),
            );
          }
        }
        else {
          $form['tfa_fallback'][$plugin] = array(
            '#type' => 'item',
            '#description' => t('No fallback plugins available.'),
            '#states' => $fallback_state,
          );
        }
      }
    }

    $totp_enabled_state = [
      'visible' => [
        ':input[name="tfa_enabled"]' => array('checked' => TRUE),
        'select[name="tfa_validate"]' => ['value' => 'tfa_totp'],
      ],
    ];

    $hotp_enabled_state = [
      'visible' => [
        ':input[name="tfa_enabled"]' => array('checked' => TRUE),
        'select[name="tfa_validate"]' => ['value' => 'tfa_hotp'],
      ],
    ];

    $form['extra_settings']['tfa_totp'] = [
      '#type' => 'fieldset',
      '#title' => t('Extra Settings'),
      '#descrption' => t('Extra plugin settings.'),
      '#states' => $totp_enabled_state,
    ];

    $form['extra_settings']['tfa_totp']['time_skew'] = [
      '#type' => 'textfield',
      '#title' => t('Time Skew'),
      '#default_value' => ($config->get('time_skew')) ?: 30,
      '#description' => 'Number of 30 second chunks to allow TOTP keys between.',
      '#size' => 2,
      '#required' => TRUE,
    ];

    $form['extra_settings']['tfa_hotp'] = [
      '#type' => 'fieldset',
      '#title' => t('Extra Settings'),
      '#descrption' => t('Extra plugin settings.'),
      '#states' => $hotp_enabled_state,
    ];

    $form['extra_settings']['tfa_hotp']['counter_window'] = [
      '#type' => 'textfield',
      '#title' => t('Counter Window'),
      '#default_value' => ($config->get('counter_window')) ?: 5,
      '#description' => 'How far ahead from current counter should we check the code.',
      '#size' => 2,
      '#required' => TRUE,
    ];

    // The encryption profiles select box.
    $encryption_profile_labels = [];
    foreach ($encryption_profiles as $encryption_profile) {
      $encryption_profile_labels[$encryption_profile->id()] = $encryption_profile->label();
    }
    $form['encryption_profile'] = [
      '#type' => 'select',
      '#title' => t('Encryption Profile'),
      '#options' => $encryption_profile_labels,
      '#description' => 'Encryption profiles to encrypt the secret',
      '#default_value' => $config->get('encryption'),
      '#states' => $enabled_state,
      '#required' => TRUE,
    ];

    $form['recovery_codes_amount'] = [
      '#type' => 'textfield',
      '#title' => t('Recovery Codes Amount'),
      '#default_value' => ($config->get('recovery_codes_amount')) ?: 10,
      '#description' => 'Number of Recovery Codes To Generate.',
      '#states' => $enabled_state,
      '#size' => 2,
      '#required' => TRUE,
    ];

    $form['validation_skip'] = [
      '#type' => 'textfield',
      '#title' => t('Skip Validation'),
      '#default_value' => ($config->get('validation_skip')) ?: 2,
      '#description' => 'No. of times a user without having setup tfa validation can login.',
      '#size' => 2,
      '#states' => $enabled_state,
      '#required' => TRUE,
    ];

    $form['name_prefix'] = [
      '#type' => 'textfield',
      '#title' => t('OTP QR Code Prefix'),
      '#default_value' => ($config->get('name_prefix')) ?: 'tfa',
      '#description' => 'Prefix for OTP QR code names. Suffix is account username.',
      '#size' => 15,
      '#states' => $enabled_state,
      '#required' => TRUE,
    ];

    // Enable login plugins.
    if (count($login_plugins)) {
      $login_form_array = array();

      foreach ($login_plugins as $login_plugin) {
        $id = $login_plugin['id'];
        $title = $login_plugin['label']->render();
        $login_form_array[$id] = (string) $title;
      }

      $form['tfa_login'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Login plugins'),
        '#options' => $login_form_array,
        '#default_value' => ($config->get('login_plugins')) ? $config->get('login_plugins') : array(),
        '#description' => t('Plugins that can allow a user to skip the TFA process. If any plugin returns true the user will not be required to follow TFA. <strong>Use with caution.</strong>'),
      );
    }

    // Enable send plugins.
    if (count($send_plugins)) {
      $send_form_array = array();

      foreach ($send_plugins as $send_plugin) {
        $id = $send_plugin['id'];
        $title = $send_plugin['label']->render();
        $send_form_array[$id] = (string) $title;
      }

      $form['tfa_send'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Send plugins'),
        '#options' => $send_form_array,
        '#default_value' => ($config->get('send_plugins')) ? $config->get('send_plugins') : array(),
        // TODO - Fill in description.
        '#description' => t('Not sure what this is'),
      );
    }

    // Enable setup plugins.
    if (count($setup_plugins) >= 1) {
      $setup_form_array = array();

      foreach ($setup_plugins as $setup_plugin) {
        $id = $setup_plugin['id'];
        $title = $setup_plugin['label']->render();
        $setup_form_array[$id] = $title;
      }

      $form['tfa_setup'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Setup plugins'),
        '#options' => $setup_form_array,
        '#default_value' => ($config->get('setup_plugins')) ? $config->get('setup_plugins') : array(),
        '#description' => t('Not sure what this is'),
      );

    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    );

    // By default, render the form using theme_system_config_form().
    $form['#theme'] = 'system_config_form';

    $form['actions']['reset'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => array('::resetForm'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $validate_plugin = $form_state->getValue('tfa_validate');
    $fallback_plugins = $form_state->getValue('tfa_fallback');

    // Delete tfa data if plugin is disabled.
    if ($this->config('tfa.settings')->get('enabled') && !$form_state->getValue('tfa_enabled')) {
      $this->userData->delete('tfa');
    }

    $setup_plugins = $form_state->getValue('tfa_setup') ?: [];
    $send_plugins = $form_state->getValue('tfa_send') ?: [];
    $login_plugins = $form_state->getValue('tfa_login') ?: [];
    $encryption_profile = $form_state->getValue('encryption');
    $this->config('tfa.settings')->set('enabled', $form_state->getValue('tfa_enabled'))->set('time_skew', $form_state->getValue('time_skew'))->set('counter_window', $form_state->getValue('counter_window'))->set('recovery_codes_amount', $form_state->getValue('recovery_codes_amount'))->set('name_prefix', $form_state->getValue('name_prefix'))->set('setup_plugins', array_filter($setup_plugins))->set('send_plugins', array_filter($send_plugins))->set('login_plugins', array_filter($login_plugins))->set('validate_plugin', $validate_plugin)->set('fallback_plugins', $fallback_plugins)->set('validation_skip', $form_state->getValue('validation_skip'))->set('encryption', $form_state->getValue('encryption_profile'))->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tfa.settings'];
  }

  /**
   * Check whether the given data is empty and set appropritate message.
   *
   * @param array $data
   *   Data to be checked.
   * @param string $message
   *   Message to show if data is empty.
   *
   * @return bool
   *   TRUE if data is empty otherwise FALSE.
   */
  protected function dataEmptyCheck($data, $message) {
    if (empty($data)) {
      drupal_set_message(t($message), 'error');
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Resets the filter selections.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('tfa.settings.reset');
  }

}
