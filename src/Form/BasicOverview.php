<?php

namespace Drupal\tfa\Form;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * TFA Basic account setup overview page.
 */
class BasicOverview extends FormBase {



  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The expirable key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $keyValueExpirable;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * The permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $permissionHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('module_installer'),
      $container->get('keyvalue.expirable')->get('module_list'),
      $container->get('access_manager'),
      $container->get('current_user'),
      $container->get('user.permissions')
    );
  }

  /**
   * Constructs a ModulesListForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $key_value_expirable
   *   The key value expirable factory.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   Access manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ModuleInstallerInterface $module_installer, KeyValueStoreExpirableInterface $key_value_expirable, AccessManagerInterface $access_manager, AccountInterface $current_user, PermissionHandlerInterface $permission_handler) {
    $this->moduleHandler = $module_handler;
    $this->moduleInstaller = $module_installer;
    $this->keyValueExpirable = $key_value_expirable;
    $this->accessManager = $access_manager;
    $this->currentUser = $current_user;
    $this->permissionHandler = $permission_handler;
    $perms = $this->permissionHandler->getPermissions();
    $search_config_permissions = array_filter($perms, function($v) {
      return $v['provider'] == 'tfa';
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tfa_basic_base_overview';
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
    $configuration = \Drupal::config('tfa.settings')->getRawData();
    $user_tfa      = tfa_get_tfa_data($user->id());
    $enabled       = isset($user_tfa['status']) && $user_tfa['status'] ? TRUE : FALSE;

    if (!empty($user_tfa)) {
      $date_formatter = \Drupal::service('date.formatter');
      if ($enabled) {
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
      $enabled_plugin          = $configuration['validate_plugin'];
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
  public function tfaPluginSetupFormOverview($plugin, $account, array $user_tfa) {
    // No output if the plugin isn't enabled.
    /*if ($plugin !== variable_get('tfa_validate_plugin', '') &&
    !in_array($plugin, variable_get('tfa_fallback_plugins', array())) &&
    !in_array($plugin, variable_get('tfa_login_plugins', array()))) {
    return array();
    }*/

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
