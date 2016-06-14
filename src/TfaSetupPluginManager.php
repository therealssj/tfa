<?php

namespace Drupal\tfa;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\user\UserDataInterface;

/**
 * The setup plugin manager.
 */
class TfaSetupPluginManager extends DefaultPluginManager {

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs a new TfaSetup plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, UserDataInterface $user_data) {
    parent::__construct('Plugin/TfaSetup', $namespaces, $module_handler, 'Drupal\tfa\TfaSetupInterface', 'Drupal\tfa\Annotation\TfaSetup');
    $this->alterInfo('tfa_setup_info');
    $this->setCacheBackend($cache_backend, 'tfa_setup');
    $this->userData = $user_data;
  }

  /**
   * Create an instance of a setup plugin.
   *
   * @param string $plugin_id
   *    The id of the setup plugin.
   * @param array $configuration
   *    Configuration data for the setup plugin.
   *
   * @return object
   *    Require setup plugin instance
   */
  public function createInstance($plugin_id, array $configuration = array()) {
    // @todo defining userdata as a parameter results in an error. why?
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class      = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition, $this->userData);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition, $this->userData);
    }
    return $plugin;
  }

}
