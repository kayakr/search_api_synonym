<?php

namespace Drupal\search_api_synonym\Plugin\search_api_synonym\import;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\search_api_synonym\Import\ImportPluginBase;
use Drupal\search_api_synonym\Import\ImportPluginInterface;

/**
 * Import of JSON files.
 *
 * @SearchApiSynonymImport(
 *   id = "json",
 *   label = @Translation("JSON"),
 *   description = @Translation("Synonym import plugin from JSON file.")
 * )
 */
class JSON extends ImportPluginBase implements ImportPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function parseFile(File $file, array $settings) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function allowedExtensions() {
    return ['json'];
  }

}
