<?php

namespace Drupal\commerce_cloudpayments\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form for CloudPayments settings.
 */
class CloudPaymentsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_cloudpayments_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_cloudpayments.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_cloudpayments.settings');

    $form['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret Key'),
      '#default_value' => $config->get('api_secret'),
      '#required' => TRUE,
      '#description' => $this->t('Enter your CloudPayments API Secret Key.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('commerce_cloudpayments.settings')
      ->set('api_secret', $form_state->getValue('api_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}