<?php
/**
 * @file
 * Contains Drupal\tfa\TfaValidationPluginManager.
 */

namespace Drupal\tfa;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\AnnotatedClassDiscovery;


class TfaValidationPluginManager extends DefaultPluginManager {
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
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/TfaValidation', $namespaces, $module_handler, 'Drupal\tfa\Plugin\TfaValidationInterface', 'Drupal\tfa\Annotation\TfaValidation');
    $this->alterInfo('tfa_validation');
    $this->setCacheBackend($cache_backend, 'tfa_validation');

    // This is the essential line you have to use in your manager.
//    $this->discovery = new AnnotatedClassDiscovery('Plugin/TfaValidation', $namespaces, 'Drupal\tfa\Annotation\TfaValidation');
//    dpm($this);
  }

}
