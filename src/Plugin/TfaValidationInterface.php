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
   * @param FormStateInterface $form_state
   *
   * @return array Form API array.
   */
  public function getForm(array $form, FormStateInterface $form_state);

  /**
   * Validate form.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return bool Whether form passes validation or not
   */
  public function validateForm(array $form, FormStateInterface $form_state);

  /**
   * @return string[]
   *   Returns a list of fallback methods available for the current validation
   */
  public function getFallbacks();

  /**
   * Store user specific information.
   *
   * @param string $module
   *   The name of the module the data is associated with.
   * @param array $data
   *   The value to store. Non-scalar values are serialized automatically.
   *
   * @return void
   */
  public function setUserData($module, array $data);

  /**
   * Returns data stored for the current validated user account.
   *
   * @param string $module
   *   The name of the module the data is associated with.
   * @param string $key
   *   The name of the data key.
   *
   * @return mixed|array
   *   the stored value is returned, or NULL if no value was found.
   */
  public function getUserData($key, $module);

  /**
   * Deletes data stored for the current validated user account.
   *
   * @param string $module
   *   The name of the module the data is associated with.
   * @param string $key
   *   The name of the data key.
   *
   * @return void
   */
  public function deleteUserData($key, $module);

}
