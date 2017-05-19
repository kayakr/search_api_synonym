<?php

namespace Drupal\search_api_synonym\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_synonym\Import\Importer;
use Drupal\search_api_synonym\Import\ImportPluginManager;
use Drupal\search_api_synonym\Import\Import;
use Drupal\search_api_synonym\ImportException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SynonymImportForm.
 *
 * @package Drupal\search_api_synonym\Form
 *
 * @ingroup search_api_synonym
 */
class SynonymImportForm extends FormBase {

  /**
   * Import plugin manager.
   *
   * @var \Drupal\search_api_synonym\Import\ImportPluginManager
   */
  protected $pluginManager;

  /**
   * An array containing available import plugins.
   *
   * @var array
   */
  protected $availablePlugins = [];

  /**
   * Constructs a SynonymImportForm object.
   *
   * @param \Drupal\search_api_synonym\Import\ImportPluginManager $manager
   *   Import plugin manager.
   */
  public function __construct(ImportPluginManager $manager) {
    $this->pluginManager = $manager;

    foreach ($manager->getAvailableImportPlugins() as $id => $definition) {
      $this->availablePlugins[$id] = $manager->createInstance($id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.search_api_synonym.import')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_synonym_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // File.
    $form['file_upload'] = [
      '#type' => 'file',
      '#title' => $this->t('File'),
      '#description' => $this->t('Select the import file.'),
      '#required' => FALSE,
    ];

    // Update
    $form['update_existing'] = [
      '#type' => 'radios',
      '#title' => $this->t('Update existing'),
      '#description' => $this->t('What should happen with existing synonyms?'),
      '#options' => [
        'merge' => $this->t('Merge'),
        'overwrite' => $this->t('Overwrite')
      ],
      '#required' => TRUE,
    ];

    // Synonym type.
    $form['synonym_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Type '),
      '#description' => $this->t('Which synonym type should the imported data be saved as?'),
      '#options' => [
        'synonym' => 'Synonym',
        'spelling_error' => 'Spelling error'
      ],
      '#required' => TRUE,
    ];

    // Activate.
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Activate synonyms'),
      '#description' => $this->t('Mark import synonyms as active. Only active synonyms will be exported to the configured search backend.'),
      '#default_value' => TRUE,
      '#required' => TRUE,
    ];

    // Language code.
    $form['langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Language'),
      '#description' => $this->t('Which language should the imported data be saved as?'),
      '#default_value' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
    ];

    // Import plugin configuration.
    $form['plugin'] = [
      '#type' => 'radios',
      '#title' => $this->t('Import format'),
      '#description' => $this->t('Choose the import format to use.'),
      '#options' => [],
      '#default_value' => (count($this->availablePlugins) == 1) ? key($this->availablePlugins) : '',
      '#required' => TRUE,
    ];

    $form['plugin_settings'] = [
      '#tree' => TRUE,
    ];

    foreach ($this->availablePlugins as $id => $instance) {
      $definition = $instance->getPluginDefinition();
      $form['plugin']['#options'][$id] = $definition['label'];
      $form['plugin_settings'][$id] = [
        '#type' => 'details',
        '#title' => $this->t('@plugin plugin', ['@plugin' => $definition['label']]),
        '#open' => TRUE,
        '#tree' => TRUE,
        '#states' => [
          'visible' => [
            ':radio[name="plugin"]' => ['value' => $id],
          ],
        ],
      ];
      $form['plugin_settings'][$id] += $instance->buildConfigurationForm([], $form_state);
    }

    // Actions.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import file'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();

    // Get plugin instance for active plugin.
    $instance_active = $this->getPluginInstance($values['plugin']);

    // Validate the uploaded file.
    $extensions = $instance_active->allowedExtensions();
    $validators = ['file_validate_extensions' => $extensions];
    $file = file_save_upload('file_upload', $validators, FALSE, 0, FILE_EXISTS_RENAME);
    if (isset($file)) {
      if ($file) {
        $form_state->setValue('file_upload', $file);
      }
      else {
        $form_state->setErrorByName('file_upload', $this->t('The import file could not be uploaded.'));
      }
    }

    // Call the form validation handler for each of the plugins.
    foreach ($this->availablePlugins as $instance) {
      $instance->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      // All values from the form.
      $values = $form_state->getValues();

      // Instance of active import plugin.
      $plugin_id = $values['plugin'];
      $instance = $this->getPluginInstance($plugin_id);

      // Parse file.
      $data = $instance->parseFile($values['file_upload'], $values['plugin_settings'][$plugin_id]);

      // Import data.
      $importer = new Importer();
      $results = $importer->execute($data, $values);

      // Set message before returning to form.
      if (!empty($results['success'])) {
        drupal_set_message($this->t('@count synonyms was successfully imported.', array('@count' => count($results['success']))));
      }
      if (!empty($results['errors'])) {
        drupal_set_message($this->t('@count synonyms failed import.', array('@count' => count($results['errors']))));
      }
    }
    catch (ImportException $e) {
      $this->logger('Search API Synonym', $this->t('Failed to import file due to "%error".', array('%error' => $e->getMessage())));
      drupal_set_message($this->t('Failed to import file due to "%error".', array('%error' => $e->getMessage())));
    }
  }

  /**
   * Returns an import plugin instance for a given plugin id.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   *
   * @return \Drupal\search_api_synonym\Import\ImportPluginInterface
   *   An import plugin instance.
   */
  public function getPluginInstance($plugin_id) {
    return $this->pluginManager->createInstance($plugin_id, array());
  }

}
