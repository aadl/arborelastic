<?php

/**
 * @file
 * Contains \Drupal\arborelastic\Form\ArborelasticAdminForm.
 */

namespace Drupal\arborelastic\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class ArborelasticAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'arborelastic_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('arborelastic.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['arborelastic.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = [];
    $form['arborelastic_host'] = [
      '#type' => 'textfield',
      '#title' => t('Elasticsearch Host'),
      '#default_value' => \Drupal::config('arborelastic.settings')->get('arborelastic_host'),
      '#size' => 32,
      '#maxlength' => 64,
      '#description' => t('URL of the Elasticsearch server for Arborelastic. (e.g. es.website.org)'),
    ];
    $form['arborelastic_port'] = [
      '#type' => 'textfield',
      '#title' => t('Elasticsearch Port'),
      '#default_value' => \Drupal::config('arborelastic.settings')->get('arborelastic_port'),
      '#size' => 32,
      '#maxlength' => 64,
      '#description' => t('Port of the Elasticsearch server for Arborelastic. (e.g. 9200)'),
    ];
    $form['arborelastic_user'] = [
      '#type' => 'textfield',
      '#title' => t('Elasticsearch User'),
      '#default_value' => \Drupal::config('arborelastic.settings')->get('arborelastic_user'),
      '#size' => 32,
      '#maxlength' => 64,
      '#description' => t('User name to access the Elasticsearch server for Arborelastic. (e.g. elastic)'),
    ];
    $form['arborelastic_pass'] = [
      '#type' => 'textfield',
      '#title' => t('Elasticsearch Password'),
      '#default_value' => \Drupal::config('arborelastic.settings')->get('arborelastic_pass'),
      '#size' => 32,
      '#maxlength' => 64,
      '#description' => t('Password to access the Elasticsearch server for Arborelastic.'),
    ];
    $form['arborelastic_indexes'] = [
      '#type' => 'textarea',
      '#title' => t('Elasticsearch Indexes'),
      '#default_value' => \Drupal::config('arborelastic.settings')->get('arborelastic_indexes'),
      '#description' => t('Indexes to present on the search page. Each index on own line. Format is path_id:index:type'),
    ];
    return parent::buildForm($form, $form_state);
  }

}
