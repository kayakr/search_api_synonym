<?php

namespace Drupal\search_api_synonym\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Synonym edit forms.
 *
 * @ingroup search_api_synonym
 */
class SynonymForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\search_api_synonym\Entity\Synonym */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $entity_type = $entity->getEntityTypeId();
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Synonym.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Synonym.', [
          '%label' => $entity->label(),
        ]));
    }

    $form_state->setRedirect($entity->toUrl('collection')->getRouteName());
  }

}
