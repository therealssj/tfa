<?php

/**
 * @file
 * Contains Drupal\tfa\Form\EntryForm.
 */

namespace Drupal\tfa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

class EntryForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'tfa_entry_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = null) {
    $tfaManager = \Drupal::service('tfa.manager');
    $tfa = $tfaManager->getProcess($user);


    // Check flood tables.
    //@TODO Reimplement Flood Controls.
//    if (_tfa_hit_flood($tfa)) {
//      \Drupal::moduleHandler()->invokeAll('tfa_flood_hit', [$tfa->getContext()]);
//      return drupal_access_denied();
//    }
//

    // Get TFA plugins form.
    $form = $tfa->getForm($form, $form_state);

    //If there is a fallback method, set it.
    if ($tfa->hasFallback()) {
      $form['actions']['fallback'] = array(
        '#type' => 'submit',
        '#value' => t("Can't access your account?"),
        '#submit' => array('tfa_form_submit'),
        '#limit_validation_errors' => array(),
        '#weight' => 20,
      );
    }

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
    $user = $form_state->getValue('account');
    $tfaManager = \Drupal::service('tfa.manager');
    $tfa = $tfaManager->getProcess($user);
    $tfa->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $form_state->getValue('account');
    $tfaManager = \Drupal::service('tfa.manager');
    $tfa = $tfaManager->getProcess($user);
    if(!$tfa->submitForm($form, $form_state)) {
      // If fallback was triggered TFA process has been reset to new validate
      // plugin so run begin and store new context.

      $fallback = $form_state->getValue('fallback');
      if (isset($fallback) && $form_state->getValue('op') === $fallback) {
        $tfa->begin();
      }
      $context = $tfa->getContext();
      $tfaManager->setContext($user, $context);
      $form_state['rebuild'] = TRUE;
    }
    else {
      // TFA process is complete so finalize and authenticate user.
      $context = $tfaManager->getContext($user);
      $tfa->finalize();
      $tfaManager->login($user);
      // Set redirect based on query parameters, existing $form_state or context.
      //$form_state['redirect'] = _tfa_form_get_destination($context, $form_state, $user);
      $form_state->setRedirect('<front>');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tfa.settings'];
  }

}
