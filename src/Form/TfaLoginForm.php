<?php
/**
 * @file TfaLoginForm.php
 * Contains implementation of the TfaLoginForm class.
 */

namespace Drupal\tfa\Form;

use Drupal\tfa\TfaManager;
use Drupal\user\Form\UserLoginForm;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TfaLoginForm extends UserLoginForm {

  /**
   * @var \Drupal\tfa\TfaManager
   */
  protected $tfaManager;

  /**
   * Constructs a new UserLoginForm.
   *
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication object.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(FloodInterface $flood, UserStorageInterface $user_storage, UserAuthInterface $user_auth, RendererInterface $renderer, TfaManager $tfa_manager) {
    parent::__construct($flood, $user_storage, $user_auth, $renderer);
    $this->tfaManager = $tfa_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flood'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('user.auth'),
      $container->get('renderer'),
      $container->get('tfa.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#submit'][] = '::TfaLoginFormRedirect';

    return $form;
  }

  /**
   * Login submit handler to determine if TFA process is applicable. If not,
   * call the parent form submit.
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Similar to tfa_user_login() but not required to force user logout.

    if ($uid = $form_state->get('uid')) {
      $account = $this->userStorage->load($uid);
    }
    else {
      $account = user_load_by_name($form_state->get('name'));
    }

    if ($tfa = $this->tfaManager->getProcess($account)) {
      if ($account->hasPermission('require tfa') && !$this->tfaManager->loginComplete($account) && !$tfa->ready()) {
        drupal_set_message(t('Login disallowed. You are required to setup two-factor authentication. Please contact a site administrator.'), 'error');
        $form_state['redirect'] = 'user';
      }
      elseif (!$this->tfaManager->loginComplete($account) && $tfa->ready() && !$tfa->loginAllowed($account)) {

        // Restart flood levels, session context, and TFA process.
        //flood_clear_event('tfa_validate');
        //flood_register_event('tfa_begin');
//      $context = tfa_start_context($account);
//      $tfa = _tfa_get_process($account);

        // $query = drupal_get_query_parameters();
        if (!empty($form_state->redirect)) {
          // If there's an existing redirect set it in TFA context and
          // tfa_form_submit() will extract and set once process is complete.
          $context['redirect'] = $form_state['redirect'];
        }

        // Begin TFA and set process context.
        $tfa->begin();
        $context = $tfa->getContext();
        $this->tfaManager->setContext($account, $context);

        $login_hash = $this->tfaManager->getLoginHash($account);
        $form_state->setRedirect(
          'tfa.entry',
          ['user' => $account->id(),
            'hash' => $login_hash]
        //'tfa/' . $account->id() . '/' . $login_hash
        //array('query' => $query),
        );
      }
      else {
        return parent::submitForm($form, $form_state);
      }
    }
    else {
      drupal_set_message(t('Two-factor authentication is enabled but misconfigured. Please contact a site administrator.'), 'error');
      $form_state->setRedirect('user.page');
    }
  }

  /**
   * Login submit handler for TFA form redirection.
   *
   * Should be last invoked form submit handler for forms user_login and
   * user_login_block so that when the TFA process is applied the user will be
   * sent to the TFA form.
   *
   *
   * @param FormStateInterface $form_state
   */
  function TfaLoginFormRedirect($form, &$form_state){
    $route = $form_state->getValue('tfa_redirect');
    if (isset($route)) {
      $form_state->setRedirect($route);
    }
  }

}