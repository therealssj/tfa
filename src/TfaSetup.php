<?php

namespace Drupal\tfa;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tfa\Plugin\TfaSetupInterface;

/**
 * Class TfaSetup.
 */
class TfaSetup {
  // @todo This class is somewhat useless. Deprecate this later.
  /**
   * Current setup plugin.
   *
   * @var $setupPlugin
   */
  protected $setupPlugin;

  /**
   * TFA Setup constructor.
   *
   * @param TfaSetupInterface $plugin
   *   Plugins to instansiate.
   */
  public function __construct(TfaSetupInterface $plugin) {
    $this->setupPlugin = $plugin;
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
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form API array.
   */
  public function getForm(array $form, FormStateInterface &$form_state, $reset = 0) {
    return $this->setupPlugin->getSetupForm($form, $form_state, $reset);
  }

  /**
   * Validate form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if setup completed otherwise FALSE.
   */
  public function validateForm(array $form, FormStateInterface &$form_state) {
    return $this->setupPlugin->validateSetupForm($form, $form_state);
  }

  /**
   * Return process error messages.
   *
   * @return array
   *   An array containing the setup errors.
   */
  public function getErrorMessages() {
    return $this->setupPlugin->getErrorMessages();
  }

  /**
   * Submit the setup form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if no errors occur when saving the data.
   */
  public function submitForm(array $form, FormStateInterface &$form_state) {
    return $this->setupPlugin->submitSetupForm($form, $form_state);
  }

  /**
   * Returns a list of messages for plugin step.
   *
   * @return string[]
   *   An array containing messages to be used during plugin setup.
   */
  public function getSetupMessages() {
    return $this->setupPlugin->getSetupMessages();
  }

}
