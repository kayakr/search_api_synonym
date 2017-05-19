<?php

namespace Drupal\search_api_synonym\Import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api_synonym\Entity\Synonym;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Database\Connection;

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
   * Import settings
   *
   * @var array
   */
  protected $settings;

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
  public function __construct(EntityTypeManagerInterface $entity_manager,
                              EntityRepositoryInterface $entity_repository, ModuleHandlerInterface $module_handler,
                              Connection $connection) {
    $this->entityManager = $entity_manager;
    $this->entityRepository = $entity_repository;
    $this->moduleHandler = $module_handler;
    $this->connection = $connection;
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
    $this->settings = $settings;

    // Prepare items.
    $items = $this->prepare($items);

    // Import each single item.
    $results = ['success' => [], 'errors' => []];
    if (count($items)) {
      foreach ($items as $word => $synonyms) {
        if ($sid = $this->createSynonym($word, $synonyms)) {
          $results['success'][] = $sid;
        }
        else {
          $results['errors'][] = [
            'word' => $word,
            'synonyms' => $synonyms
          ];
        }
      }
    }

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
   * Create / update a synonym.
   *
   * @param string $word
   *   The source word we add the synonym for.
   * @param array $synonyms
   *   Simple array with synonyms.
   *
   * @return int
   *   Entity id for the imported synonym.
   */
  private function createSynonym($word, array $synonyms) {
    // Check if we have an existing synonym entity we should update.
    $sid = $this->lookUpSynonym($word);

    // Load and update existing synonym entity.
    if ($sid) {
      $entity = Synonym::load($sid);

      // Update method = Merge.
      if ($this->getSetting('update_existing') == 'merge') {
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
        'langcode' => $this->getSetting('langcode'),
      ]);
      $uid = \Drupal::currentUser()->id();
      $entity->setOwnerId($uid);
      $entity->setCreatedTime(REQUEST_TIME);
      $entity->setType($this->getSetting('synonym_type'));
      $entity->setWord($word);
      $synonyms_str = implode(',', $synonyms);
      $entity->setSynonyms($synonyms_str);
    }

    $entity->setChangedTime(REQUEST_TIME);
    $entity->setActive($this->getSetting('status'));

    $entity->save();

    return $entity->id();
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
  private function lookUpSynonym($word) {
    $query = \Drupal::database()->select('search_api_synonym', 's');
    $query->fields('s', ['sid']);
    $query->condition('s.type', $this->getSetting('synonym_type'));
    $query->condition('s.word', $word, 'LIKE');
    $query->condition('s.langcode', $this->getSetting('langcode'));
    $query->range(0, 1);
    return (int) $query->execute()->fetchField(0);
  }

  /**
   * Get a single settings variable.
   *
   * @param string $key
   *   The settings key.
   *
   * @return string
   *   The setting value.
   */
  private function getSetting($key) {
    if (isset($this->settings[$key])) {
      return $this->settings[$key];
    }

    return FALSE;
  }

}
