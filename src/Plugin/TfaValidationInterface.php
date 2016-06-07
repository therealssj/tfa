<?php

/**
 * @file
 * Contains \Drupal\tfa\TfaValidationInterface.
 */

namespace Drupal\tfa\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserDataInterface;

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
   * Returns a list of fallback methods available for this validation
   * @return string[]
   */
  public function getFallbacks();

  /**
   * Store user data in key value pairs
   *
   * @param string $module
   * @param array $data
   * @return void
   */
  public function setUserData($module, array $data);

  /**
   * Fetch user data using the key and module name
   *
   * @param string $key
   * @param string $module
   * @return array User Data array
   */
  public function getUserData($key, $module);

  /**
   * Fetch user data using the key and module name
   *
   * @param string $key
   * @param string $module
   * @return array User Data array
   */
  public function deleteUserData($key, $module);


}
