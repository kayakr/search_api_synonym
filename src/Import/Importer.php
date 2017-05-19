<?php

namespace Drupal\search_api_synonym\Import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api_synonym\Entity\Synonym;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Importer class.
 *
 * Process and import synonyms data.
 *
 * @package Drupal\search_api_synonym
 */
class Importer {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The database connection used to check the IP against.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs Importer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection which will be used to store / get the nid / source guid mapping.
   */
  public function __construct() {
    $this->entityManager = \Drupal::service('entity.manager');
    $this->entityRepository = \Drupal::service('entity.repository');
    $this->moduleHandler = \Drupal::service('module_handler');
    $this->connection = \Drupal::service('database');
  }

  /**
   * Execute the import of an array with synonyms.
   *
   * @param array $items
   *   Raw synonyms data.
   * @param array $settings
   *   Import settings.
   *
   * @return array
   *   Array with info about the result.
   */
  public function execute(array $items, array $settings) {
    // Prepare items.
    $items = $this->prepare($items);

    // Create synonyms.
    $results = $this->createSynonyms($items, $settings);

    return $results;
  }

  /**
   * Prepare and validate the data.
   *
   * @param array $data
   *  Raw synonyms data.
   */
  private function prepare(array $items) {
    $prepared = [];

    foreach ($items as $item) {
      $prepared[$item['word']][] = $item['synonym'];
    }

    return $prepared;
  }

  /**
   * Create synonyms.
   *
   * Start batch if more than 50 synonyms should be added.
   *
   * @param array $items
   *   Raw synonyms data.
   * @param array $settings
   *   Import settings.
   *
   * @return array
   *   Array with info about the result.
   */
  public function createSynonyms(array $items, array $settings) {
    $context = [];

    // Import without batch.
    if (count($items) <= 50) {
      foreach ($items as $word => $synonyms) {
        $this->createSynonym($word, $synonyms, $settings, $context);
      }
    }
    // Import with batch.
    else {
      $operations = [];

      foreach ($items as $word => $synonyms) {
        $operations[] = [
          '\Drupal\search_api_synonym\Import\Importer::createSynonym',
          [$word, $synonyms, $settings]
        ];
      }

      $batch = [
        'title' => t('Import synonyms...'),
        'operations' => $operations,
        'finished' => '\Drupal\search_api_synonym\Import\Importer::createSynonymBatchFinishedCallback',
      ];
      batch_set($batch);
    }

    return isset($context['results']) ? $context['results'] : NULL;
  }

  /**
   * Create / update a synonym.
   *
   * @param string $word
   *   The source word we add the synonym for.
   * @param array $synonyms
   *   Simple array with synonyms.
   * @param array $settings
   *   Import settings.
   * @param array $context
   *   Batch context - also used for storing results in non batch operations.
   */
  public static function createSynonym($word, array $synonyms, array $settings, array &$context) {
    // Check if we have an existing synonym entity we should update.
    $sid = Importer::lookUpSynonym($word, $settings);

    // Load and update existing synonym entity.
    if ($sid) {
      $entity = Synonym::load($sid);

      // Update method = Merge.
      if ($settings['update_existing'] == 'merge') {
        $existing = $entity->getSynonyms();
        $existing = array_map('trim', explode(',', $existing));
        $synonyms = array_unique(array_merge($existing, $synonyms));
      }

      $synonyms_str = implode(',', $synonyms);
      $entity->setSynonyms($synonyms_str);
    }
    // Create new entity.
    else {
      $entity = Synonym::create([
        'langcode' => $settings['langcode'],
      ]);
      $uid = \Drupal::currentUser()->id();
      $entity->setOwnerId($uid);
      $entity->setCreatedTime(REQUEST_TIME);
      $entity->setType($settings['synonym_type']);
      $entity->setWord($word);
      $synonyms_str = implode(',', $synonyms);
      $entity->setSynonyms($synonyms_str);
    }

    $entity->setChangedTime(REQUEST_TIME);
    $entity->setActive($settings['status']);

    $entity->save();

    if ($sid = $entity->id()) {
      $context['results']['success'][] = $sid;
    }
    else {
      $context['results']['errors'][] = [
        'word' => $word,
        'synonyms' => $synonyms
      ];
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Was the batch successful or not?
   * @param array $result
   *   Array with the result of the import.
   * @param array $operations
   *   Batch operations.
   * @param string $elapsed
   *   Formatted string with the time batch operation was running.
   */
  public static function createSynonymBatchFinishedCallback($success, $result, $operations, $elapsed) {
    if ($success) {
      // Set message before returning to form.
      if (!empty($result['success'])) {
        drupal_set_message(t('@count synonyms was successfully imported.', array('@count' => count($result['success']))));
      }
      if (!empty($result['errors'])) {
        drupal_set_message(t('@count synonyms failed import.', array('@count' => count($result['errors']))));
      }
    }
  }

  /**
   * Look up synonym
   *
   * @param string $word
   *   The source word we add the synonym for.
   *
   * @return int
   *   Entity id for the found synonym.
   */
  public static function lookUpSynonym($word, $settings) {
    $query = \Drupal::database()->select('search_api_synonym', 's');
    $query->fields('s', ['sid']);
    $query->condition('s.type', $settings['synonym_type']);
    $query->condition('s.word', $word, 'LIKE');
    $query->condition('s.langcode', $settings['langcode']);
    $query->range(0, 1);
    return (int) $query->execute()->fetchField(0);
  }

}
