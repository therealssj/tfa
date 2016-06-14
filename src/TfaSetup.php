<?php

namespace Drupal\tfa;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class TfaSetup.
 */
class TfaSetup {

  /**
   * @var TfaBasePlugin
   */
  protected $setupPlugin;

  /**
   * TFA Setup constructor.
   *
   * @param TfaSetup $plugin
   *   Plugins to instansiate.
   *
   *   Must include key:
   *
   *     - 'setup'
   *       Class name of TfaBasePlugin implementing TfaSetupPluginInterface.
   *
   * @param array $context
   *   Context of TFA process.
   *
   *   Must include key:
   *
   *     - 'uid'
   *       Account uid of user in TFA process.
   */
  public function __construct($plugin) {
    $this->setupPlugin = $plugin;
    // $this->context['plugins'] = $plugins;.
  }

  /**
   * Run any begin setup processes.
   */
  public function begin() {
    // Invoke begin method on setup plugin.
    if (method_exists($this->setupPlugin, 'begin')) {
      $this->setupPlugin->begin();
    }
  }

  /**
   * Get plugin form.
   *
   * @param array $form
   * @param array $form_state
   *
   * @return array
   */
  public function getForm(array $form, FormStateInterface &$form_state) {
    return $this->setupPlugin->getSetupForm($form, $form_state);
  }

  /**
   * Validate form.
   *
   * @param array $form
   * @param array $form_state
   *
   * @return bool
   */
  public function validateForm(array $form, FormStateInterface &$form_state) {
    return $this->setupPlugin->validateSetupForm($form, $form_state);
  }

  /**
   * Return process error messages.
   *
   * @return array
   */
  public function getErrorMessages() {
    return $this->setupPlugin->getErrorMessages();
  }

  /**
   *
   * @param array $form
   * @param array $form_state
   * @return bool
   */
  public function submitForm(array $form, FormStateInterface &$form_state) {
    return $this->setupPlugin->submitSetupForm($form, $form_state);
  }

}
