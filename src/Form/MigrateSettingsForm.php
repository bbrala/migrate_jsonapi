<?php

namespace Drupal\migrate_jsonapi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure json api migrate settings.
 */
class MigrateSettingsForm extends ConfigFormBase {

  const MIGRATE_JSONAPI_SETTINGS = 'migrate_jsonapi.settings';
  const JSONAPI_REMOTE_HOST = 'jsonapi_remote_host';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_jsonapi_migrate_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::MIGRATE_JSONAPI_SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form[self::JSONAPI_REMOTE_HOST] = [
      '#type' => 'textfield',
      '#title' => $this->t('Migrate Site Host'),
      '#default_value' => $this->config(self::MIGRATE_JSONAPI_SETTINGS)->get(self::JSONAPI_REMOTE_HOST),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::MIGRATE_JSONAPI_SETTINGS)
      ->set(self::JSONAPI_REMOTE_HOST, $form_state->getValue(self::JSONAPI_REMOTE_HOST))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
