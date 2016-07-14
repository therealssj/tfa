<?php

namespace Drupal\tfa;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptService;
use Drupal\encrypt\EncryptServiceInterface;
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
   * Encryption profile manager.
   *
   * @var \Drupal\encrypt\EncryptionProfileManagerInterface
   */
  protected $encryptionProfileManager;

  /**
   * Encryption service.
   *
   * @var \Drupal\encrypt\EncryptService
   */
  protected $encryptService;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\user\UserDataInterface $user_data
   *   User data service.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryption_profile_manager
   *   Encryption profile manager.
   * @param \Drupal\encrypt\EncryptServiceInterface $encrypt_service
   *   Encryption service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service) {
    parent::__construct('Plugin/TfaValidation', $namespaces, $module_handler, 'Drupal\tfa\Plugin\TfaValidationInterface', 'Drupal\tfa\Annotation\TfaValidation');
    $this->alterInfo('tfa_validation');
    $this->setCacheBackend($cache_backend, 'tfa_validation');
    $this->tfaSettings = $config_factory->get('tfa.settings');
    $this->userData = $user_data;
    $this->encryptService = $encrypt_service;
    $this->encryptionProfileManager = $encryption_profile_manager;
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
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);

    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition, $this->userData, $this->encryptionProfileManager, $this->encryptService);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition, $this->userData, $this->encryptionProfileManager, $this->encryptService);
    }
    return $plugin;
  }

}
