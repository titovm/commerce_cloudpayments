<?php

namespace Drupal\commerce_cloudpayments\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\commerce_payment\Exception\PaymentGatewayException;

/**
 * Provides the CloudPayments payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "cloudpayments",
 *   label = @Translation("CloudPayments"),
 *   display_label = @Translation("CloudPayments"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_cloudpayments\PluginForm\CloudPaymentsOffsiteForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "visa", "mastercard"
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class CloudPayments extends OffsitePaymentGatewayBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'public_id' => '',
      'mode' => 'test',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['public_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Публичный ID'),
      '#description' => $this->t('Ваш публичный ID CloudPayments.'),
      '#default_value' => $this->configuration['public_id'],
      '#required' => TRUE,
    ];

    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Режим'),
      '#description' => $this->t('Выберите между тестовым и боевым режимом.'),
      '#options' => [
        'test' => $this->t('Тестовый'),
        'live' => $this->t('Боевой'),
      ],
      '#default_value' => $this->configuration['mode'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['public_id'] = $values['public_id'];
    $this->configuration['mode'] = $values['mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function getReturnUrl(OrderInterface $order) {
    return Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ], ['absolute' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(OrderInterface $order) {
    return Url::fromRoute('commerce_payment.checkout.cancel', [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ], ['absolute' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // Don't create a payment yet, wait for the notification webhook
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    $messenger = \Drupal::messenger();
    $messenger->addError($this->t('Платеж был отменен.'));
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (empty($data)) {
      throw new PaymentGatewayException('Получены недопустимые данные уведомления');
    }

    return TRUE;
  }

}