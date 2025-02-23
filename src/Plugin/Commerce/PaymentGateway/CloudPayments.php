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
      '#title' => $this->t('Public ID'),
      '#description' => $this->t('Your CloudPayments public ID.'),
      '#default_value' => $this->configuration['public_id'],
      '#required' => TRUE,
    ];

    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('Choose between test and live mode.'),
      '#options' => [
        'test' => $this->t('Test'),
        'live' => $this->t('Live'),
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
    \Drupal::logger('commerce_cloudpayments')->notice('Payment returned for order @order_id', ['@order_id' => $order->id()]);
    
    // Don't create a payment yet, wait for the notification webhook
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    \Drupal::logger('commerce_cloudpayments')->notice('Payment cancelled for order @order_id', ['@order_id' => $order->id()]);
    
    // Use the messenger service instead of deprecated drupal_set_message()
    $messenger = \Drupal::messenger();
    $messenger->addError($this->t('Payment was canceled.'));
    
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    \Drupal::logger('commerce_cloudpayments')->notice('Received notification request');

    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (empty($data)) {
      throw new PaymentGatewayException('Invalid notification data received');
    }

    // Log the notification data
    \Drupal::logger('commerce_cloudpayments')->notice('Notification data: @data', ['@data' => print_r($data, TRUE)]);

    return TRUE;
  }

}