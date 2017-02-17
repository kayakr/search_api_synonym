<?php

namespace Drupal\search_api_synonym\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Drupal\Console\Core\Command\Shared\ContainerAwareCommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;

/**
 * Drupal Console Command for export synonyms.
 *
 * @package Drupal\search_api_synonym
 */
class ExportCommand extends Command {

  use ContainerAwareCommandTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('searchapi:synonym:export')
      ->setDescription($this->trans('commands.searchapi.synonym.export.description'))
      ->addOption(
        'plugin',
        null,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.searchapi.synonym.export.options.plugin.description')
      )
      ->addOption(
        'langcode',
        null,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.searchapi.synonym.export.options.langcode.description')
      )
      ->addOption(
        'type',
        null,
        InputOption::VALUE_OPTIONAL,
        $this->trans('commands.searchapi.synonym.export.options.type.description'),
        'all'
      )
      ->addOption(
        'filter',
        null,
        InputOption::VALUE_OPTIONAL,
        $this->trans('commands.searchapi.synonym.export.options.filter.description')
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $pluginManager = \Drupal::service('plugin.manager.search_api_synonym.export');

    // Options
    $plugin = $input->getOption('plugin');
    $langcode = $input->getOption('langcode');
    $type = $input->getOption('type');
    $filter = $input->getOption('filter');

    // Validate plugin
    if (!$pluginManager->validatePlugin($plugin)) {

      var_dump($plugin);

    }

  }

}
