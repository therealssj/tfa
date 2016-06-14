<?php

namespace Drupal\tfa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tfa\TfaLoginPluginManager;
use Drupal\tfa\TfaValidationPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class EntryForm extends FormBase {

  /**
   * @var \Drupal\tfa\TfaManager
   */
  protected $tfaValidationManager;
  protected $tfaLoginManager;
  protected $tfaValidationPlugin;
  protected $tfaLoginPlugins;
  protected $tfaFallbackPlugin;

  /**
   *
   */
  public function __construct(TfaValidationPluginManager $tfa_validation_manager, TfaLoginPluginManager $tfa_login_manager) {
    $this->tfaValidationManager = $tfa_validation_manager;
    $this->tfaLoginManager = $tfa_login_manager;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.tfa.validation'),
      $container->get('plugin.manager.tfa.login')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'tfa_entry_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL) {
    // Check flood tables.
    // @TODO Reimplement Flood Controls.
    //    if (_tfa_hit_flood($tfa)) {
    //      \Drupal::moduleHandler()->invokeAll('tfa_flood_hit', [$tfa->getContext()]);
    //      return drupal_access_denied();
    //    }
    //
    // Get TFA plugins form.
    $this->tfaValidationPlugin = $this->tfaValidationManager->getInstance(['uid' => $user->id()]);
    $form = $this->tfaValidationPlugin->getForm($form, $form_state);

    if ($this->tfaLoginPlugins = $this->tfaLoginManager->getPlugins(['uid' => $user->id()])) {
      foreach ($this->tfaLoginPlugins as $login_plugin) {
        if (method_exists($login_plugin, 'getForm')) {
          $form = $login_plugin->getForm($form, $form_state);
        }
      }
    }

    // @TODO Add $fallback plugin capabilities.
    // If there is a fallback method, set it.
    //    if ($tfa->hasFallback()) {
    //      $form['actions']['fallback'] = array(
    //        '#type' => 'submit',
    //        '#value' => t("Can't access your account?"),
    //        '#submit' => array('tfa_form_submit'),
    //        '#limit_validation_errors' => array(),
    //        '#weight' => 20,
    //      );
    //    }
    // Set account element.
    $form['account'] = array(
      '#type' => 'value',
      '#value' => $user,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->tfaValidationPlugin->validateForm($form, $form_state);
    if (!empty($this->tfaLoginPlugins)) {
      foreach ($this->tfaLoginPlugins as $login_plugin) {
        if (method_exists($login_plugin, 'validateForm')) {
          $login_plugin->validateForm($form, $form_state);
        }
      }
    }
  }

  /**
   * For the time being, assume there is no fallback options available.
   * If the form is submitted and passes validation, the user should be able
   * to log in.
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $form_state->getValue('account');
    // If validation failed or fallback was requested.
    // if($form_state->hasAnyErrors()) {
    // If fallback was triggered TFA process has been reset to new validate
    // plugin so run begin and store new context.
    //      $fallback = $form_state->getValue('fallback');
    //      if (isset($fallback) && $form_state->getValue('op') === $fallback) {
    //        $tfa->begin();
    //      }
    // $context = $tfa->getContext();
    // $this->tfaManager->setContext($user, $context);
    // $form_state['rebuild'] = TRUE;
    // }
    // else {
    // TFA process is complete so finalize and authenticate user.
    // $context = $this->tfaManager->getContext($user);
    // TODO This could be improved with EventDispatcher.
    if (!empty($this->tfaLoginPlugins)) {
      foreach ($this->tfaLoginPlugins as $plugin) {
        if (method_exists($plugin, 'submitForm')) {
          $plugin->submitForm($form, $form_state);
        }
      }
    }

    user_login_finalize($user);

    // TODO Should finalize() be after user_login_finalize or before?!
    // TODO This could be improved with EventDispatcher.
    $this->finalize();

    // Set redirect based on query parameters, existing $form_state or context.
    // $form_state['redirect'] = _tfa_form_get_destination($context, $form_state, $user);.
    $form_state->setRedirect('<front>');
    // }.
  }

  /**
   * Run TFA process finalization.
   */
  public function finalize() {
    // Invoke plugin finalize.
    if (method_exists($this->tfaValidationPlugin, 'finalize')) {
      $this->tfaValidationPlugin->finalize();
    }
    // Allow login plugins to act during finalization.
    if (!empty($this->tfaLoginPlugins)) {
      foreach ($this->tfaLoginPlugins as $plugin) {
        if (method_exists($plugin, 'finalize')) {
          $plugin->finalize();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tfa.settings'];
  }

}
