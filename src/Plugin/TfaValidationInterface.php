<?php

/**
 * @file
 * Contains \Drupal\tfa\TfaValidationInterface.
 */

namespace Drupal\tfa\Plugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface TfaValidationInterface
 *
 * Validation plugins interact with the Tfa form processes to provide code entry
 * and validate submitted codes.
 */
interface TfaValidationInterface {

  /**
   * Get TFA process form from plugin.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array Form API array.
   */
  public function getForm(array $form, FormStateInterface &$form_state);

  /**
   * Validate form.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return bool Whether form passes validation or not
   */
  public function validateForm(array $form, FormStateInterface &$form_state);
}
