<?php

namespace Drupal\languageicons\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class LanguageiconsAdminSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'languageicons_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('languageicons.settings');

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
    return ['languageicons.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['show'] = [
      '#type' => 'fieldset',
      '#title' => t('Add language icons'),
      '#description' => t('Link types to add language icons.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['show']['show_node'] = [
      '#type' => 'checkbox',
      '#title' => t('Node links'),
      '#default_value' => $this->config('languageicons.settings')->get('show_node'),
      '#disabled' => TRUE,
    ];
    $form['show']['show_block'] = [
      '#type' => 'checkbox',
      '#title' => t('Language switcher block'),
      '#default_value' => $this->config('languageicons.settings')->get('show_block'),
      '#disabled' => TRUE,
    ];
    $form['show']['disabled'] = [
      '#prefix' => '<div class="messages error">',
      '#markup' => t('These options are currently disabled due to <a href=":issue_url">a bug</a> that cannot currently be resolved. They may be reintroduced at a later stage.', [
        ':issue_url' => 'http://drupal.org/node/1005144'
      ]),
      '#suffix' => '</div>',
    ];
    $form['placement'] = [
      '#type' => 'radios',
      '#title' => t('Icon placement'),
      '#options' => [
        'before' => t('Before link'),
        'after' => t('After link'),
        'replace' => t('Replace link'),
      ],
      '#default_value' => $this->config('languageicons.settings')->get('placement'),
      '#description' => t('Where to display the icon, relative to the link title.'),
    ];
    $form['path'] = [
      '#type' => 'textfield',
      '#title' => t('Icons file path'),
      '#default_value' => $this->config('languageicons.settings')->get('path'),
      '#size' => 70,
      '#maxlength' => 180,
      '#description' => t('Path for language icons, relative to Drupal installation. "*" is a placeholder for language code.'),
    ];
    $form['size'] = [
      '#type' => 'textfield',
      '#title' => t('Image size'),
      '#default_value' => $this->config('languageicons.settings')->get('size'),
      '#size' => 7,
      '#maxlength' => 7,
      '#description' => t('Image size for language icons, in the form "width x height".'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
