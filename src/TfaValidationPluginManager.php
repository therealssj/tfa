<?php

namespace Drupal\tfa;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\user\UserDataInterface;

/**
 * The validation plugin manager.
 */
class TfaValidationPluginManager extends DefaultPluginManager {

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * TFA configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $tfaSettings;

  /**
   * Constructs a new TfaValidation plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, UserDataInterface $user_data) {
    parent::__construct('Plugin/TfaValidation', $namespaces, $module_handler, 'Drupal\tfa\Plugin\TfaValidationInterface', 'Drupal\tfa\Annotation\TfaValidation');
    $this->alterInfo('tfa_validation');
    $this->setCacheBackend($cache_backend, 'tfa_validation');
    $this->tfaSettings = $config_factory->get('tfa.settings');
    $this->userData = $user_data;
  }

  /**
   * Create an instance of a validation plugin.
   *
   * @param string $plugin_id
   *    The id of the setup plugin.
   * @param array $configuration
   *    Configuration data for the setup plugin.
   *
   * @return object
   *    Required validation plugin instance
   */
  public function createInstance($plugin_id, array $configuration = array()) {
    // @todo defining userdata as a parameter results in an error. why?
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition, $this->userData);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition, $this->userData);
    }
    return $plugin;
  }

  /**
   * Options here should be what we need to send in - ie. Account.
   * The plugin manager should handle determining what plugin is required.
   *
   * @param array $options
   *   The configuration for current validation.
   *
   * @return object
   *   The validation plugin instance.
   */
  public function getInstance(array $options) {
    $validate_plugin = $this->tfaSettings->get('validate_plugin');
    return $this->createInstance($validate_plugin, $options);
  }

}
