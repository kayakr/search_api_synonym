<?php

namespace Drupal\search_api_synonym\Export;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Base class for search api synonym export plugin managers.
 *
 * @ingroup plugin_api
 */
class ExportPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/search_api_synonym/export', $namespaces, $module_handler, 'Drupal\search_api_synonym\Export\ExportPluginInterface', 'Drupal\search_api_synonym\Annotation\Export');
    $this->alterInfo('search_api_synonym_export_info');
    $this->setCacheBackend($cache_backend, 'search_api_synonym_export_info_plugins');
  }

  /**
   * Gets a list of available export plugins.
   *
   * @return array
   *   An array with the plugin names as keys and the descriptions as values.
   */
  public function getAvailableExportPlgins() {
    // Use plugin system to get list of available export plugins.
    $plugins = $this->getDefinitions();

    $output = array();
    foreach ($plugins as $id => $definition) {
      $output[$id] = $definition;
    }

    return $output;
  }

  /**
   * Validate that a specific export plugin exists.
   *
   * @param string $plugin
   *   The plugin machine name.
   *
   * @return boolean
   *   True if the plugin exists.
   */
  public function validatePlugin($plugin) {
    if ($this->getDefinition($plugin, FALSE)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
