<?php

namespace Drupal\tfa\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Plugin\Block\UserLoginBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Tfa User login' block.
 *
 * @Block(
 *   id = "tfa_user_login_block",
 *   admin_label = @Translation("Tfa User login"),
 *   category = @Translation("Forms")
 * )
 */
class TfaUserLoginBlock extends UserLoginBlock {


  /**
   * @var TFA Configuration Settings
   */
  protected $tfaSettings;

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match);
    $this->tfaSettings = $config_factory->get('tfa.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $access = parent::blockAccess($account);
    $tfaAccess = $this->tfaSettings->get('enabled');

    $route_name = $this->routeMatch->getRouteName();
    $disabled_route = in_array($route_name, ['tfa.entry']);
    if ($access->isForbidden() || !$tfaAccess || $disabled_route) {
      return AccessResult::forbidden();
    }
    return $access;
  }

  /**
   * Fully override the UserLoginBlock build() method. Not doing so
   * does something bad when loading up the UserLoginForm.
   *
   * {@inheritdoc}
   */
  public function build() {
    // Get the default build info.
    $form = \Drupal::formBuilder()->getForm('Drupal\tfa\Form\TfaLoginForm');
    unset($form['name']['#attributes']['autofocus']);
    unset($form['name']['#description']);
    unset($form['pass']['#description']);
    $form['name']['#size'] = 15;
    $form['pass']['#size'] = 15;
    // $form['#action'] = $this->url('<current>', [], ['query' => $this->getDestinationArray(), 'external' => FALSE]);
    // Build action links.
    $items = [];
    if (\Drupal::config('user.settings')->get('register') != USER_REGISTER_ADMINISTRATORS_ONLY) {
      $items['create_account'] = \Drupal::l($this->t('Create new account'), new Url('user.register', [], [
        'attributes' => [
          'title' => $this->t('Create a new user account.'),
          'class' => ['create-account-link'],
        ],
      ]));
    }
    $items['request_password'] = \Drupal::l($this->t('Reset your password'), new Url('user.pass', [], [
      'attributes' => [
        'title' => $this->t('Send password reset instructions via e-mail.'),
        'class' => ['request-password-link'],
      ],
    ]));
    return [
      'user_login_form' => $form,
      'user_links' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

}
