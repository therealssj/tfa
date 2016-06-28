<?php

namespace Drupal\tfa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tfa\TfaDataTrait;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * TFA Basic account setup overview page.
 */
class BasicOverview extends FormBase {
  use TfaDataTrait;

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * BasicOverview constructor.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data serivce.
   */
  public function __construct(UserDataInterface $user_data) {
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('user.data'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tfa_base_overview';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $output['info'] = array(
      '#type'   => 'markup',
      '#markup' => '<p>' . t('Two-factor authentication (TFA) provides additional security for your account. With TFA enabled, you log in to the site with a verification code in addition to your username and password.') . '</p>',
    );
    // $form_state['storage']['account'] = $user;.
    $configuration = $this->config('tfa.settings')->getRawData();
    $user_tfa      = $this->tfaGetTfaData($user->id(), $this->userData);
    $enabled       = isset($user_tfa['status']) && $user_tfa['status'] ? TRUE : FALSE;

    if (!empty($user_tfa)) {
      $date_formatter = \Drupal::service('date.formatter');
      if ($enabled && !empty($user_tfa['data']['plugins'])) {
        $status_text = t('Status: <strong>TFA enabled</strong>, set @time. <a href=":url">Disable TFA</a>', array(
          '@time' => $date_formatter->format($user_tfa['saved']),
          ':url'  => URL::fromRoute('tfa.disable', ['user' => $user->id()])->toString(),
        ));
      }
      else {
        $status_text = t('Status: <strong>TFA disabled</strong>, set @time.', array('@time' => $date_formatter->format($user_tfa['saved'])));
      }
      $output['status'] = array(
        '#type'   => 'markup',
        '#markup' => '<p>' . $status_text . '</p>',
      );
    }

    if ($configuration['enabled']) {
      // Validation plugin setup.
      $enabled_plugin = $configuration['validate_plugin'];
      $enabled_fallback_plugin = '';
      if (isset($configuration['fallback_plugins'][$enabled_plugin])) {
        $enabled_fallback_plugin = key($configuration['fallback_plugins'][$enabled_plugin]);
      }

      $output['app'] = $this->tfaPluginSetupFormOverview($enabled_plugin, $user, $user_tfa);

      if ($enabled_fallback_plugin) {
        // Fallback Setup.
        $output['recovery'] = $this->tfaPluginSetupFormOverview($enabled_fallback_plugin, $user, $user_tfa);
      }
    }
    else {
      $output['disabled'] = [
        '#type'   => 'markup',
        '#markup' => '<b>Currently there are no enabled plugins.</b>',
      ];
    }

    return $output;
  }

  /**
   * Get TFA basic setup action links for use on overview page.
   *
   * @param string $plugin
   *   The setup plugin.
   * @param object $account
   *   Current user account.
   * @param array $user_tfa
   *   Tfa data for current user.
   *
   * @return array
   *   Render array
   */
  protected function tfaPluginSetupFormOverview($plugin, $account, array $user_tfa) {
    $enabled = isset($user_tfa['status']) && $user_tfa['status'] ? TRUE : FALSE;

    $output = array();
    switch ($plugin) {
      case 'tfa_totp':
      case 'tfa_hotp':
        $output = array(
          'heading'     => array(
            '#type'  => 'html_tag',
            '#tag'   => 'h2',
            '#value' => t('TFA application'),
          ),
          'description' => array(
            '#type'  => 'html_tag',
            '#tag'   => 'p',
            '#value' => t('Generate verification codes from a mobile or desktop application.'),
          ),
          'link'        => array(
            '#theme' => 'links',
            '#links' => array(
              'admin' => array(
                'title' => !$enabled ? t('Set up application') : t('Reset application'),
                'url'   => Url::fromRoute(
                            'tfa.validation.setup',
                            [
                              'user'  => $account->id(),
                              'method' => $plugin,
                            ]
                ),
              ),
            ),
          ),
        );
        break;

      case 'tfa_basic_sms':
        break;

      case 'tfa_basic_trusted_browser':
        break;

      case 'tfa_recovery_code':
        $output = array(
          'heading'     => array(
            '#type'  => 'html_tag',
            '#tag'   => 'h2',
            '#value' => t('Fallback: Recovery Codes'),
          ),
          'description' => array(
            '#type'  => 'html_tag',
            '#tag'   => 'p',
            '#value' => t('Generate recovery codes to login when you can not do TFA.'),
          ),
        );

        if ($enabled) {
          $output['link'] = [
            '#theme' => 'links',
            '#links' => [
              'admin' => [
                'title' => t('Show Codes'),
                'url'   => Url::fromRoute(
                            'tfa.validation.setup',
                            [
                              'user'   => $account->id(),
                              'method' => $plugin,
                            ]
                ),
              ],
            ],
          ];
        }
        else {
          $output['disabled'] = [
            '#type'   => 'markup',
            '#markup' => '<b>You have not setup a TFA OTP method yet.</b>',
          ];
        }
        break;

    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
