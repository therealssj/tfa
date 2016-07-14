<?php

namespace Drupal\tfa\Plugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface TfaValidationInterface.
 *
 * Validation plugins interact with the Tfa form processes to provide code entry
 * and validate submitted codes.
 */
interface TfaValidationInterface {

  /**
   * Get TFA process form from plugin.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form API array.
   */
  public function getForm(array $form, FormStateInterface $form_state);

  /**
   * Validate form.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Whether form passes validation or not
   */
  public function validateForm(array $form, FormStateInterface $form_state);

  /**
   * Get validation plugin fallbacks.
   *
   * @return string[]
   *   Returns a list of fallback methods available for the current validation
   */
  public function getFallbacks();

  /**
   * Purge all the plugin related data.
   */
  public function purge();

}
