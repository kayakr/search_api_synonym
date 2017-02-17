<?php

namespace Drupal\search_api_synonym\Export;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;

/**
 * Provides an interface for search api synonym export plugins.
 *
 * @ingroup plugin_api
 */
interface ExportPluginInterface extends PluginFormInterface, ConfigurablePluginInterface {

}
