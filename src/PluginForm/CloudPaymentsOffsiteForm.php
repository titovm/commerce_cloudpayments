<?php

namespace Drupal\commerce_cloudpayments\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Provides the Off-site payment form for CloudPayments.
 */
class CloudPaymentsOffsiteForm extends PaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    
    $form['#attached']['library'][] = 'commerce_cloudpayments/cloudpayments';
    
    // Get payment data from the plugin
    $data = [
      'publicId' => $payment->getPaymentGateway()->getPlugin()->getConfiguration()['public_id'],
      'description' => t('Заказ №@number', ['@number' => $payment->getOrderId()]),
      'amount' => $payment->getAmount()->getNumber(),
      'currency' => $payment->getAmount()->getCurrencyCode(),
      'invoiceId' => $payment->getOrderId(),
      'accountId' => $payment->getOrder()->getCustomerId(),
      'skin' => 'classic',
      'data' => [
        'orderId' => $payment->getOrderId(),
        'paymentId' => $payment->id(),
      ],
    ];

    // Add the data to drupalSettings
    $form['#attached']['drupalSettings']['cloudPayments'] = [
      'data' => $data,
      'successRedirect' => $payment->getPaymentGateway()->getPlugin()->getReturnUrl($payment->getOrder())->toString(),
      'failRedirect' => $payment->getPaymentGateway()->getPlugin()->getCancelUrl($payment->getOrder())->toString(),
    ];

    // Add a container for the payment widget
    $form['payment_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['cloudpayments-container'],
      ],
      'message' => [
        '#type' => 'markup',
        '#markup' => '<div class="payment-loading">' . t('Инициализация платежной системы...') . '</div>',
      ],
    ];

    // Add some basic styling
    $form['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .cloudpayments-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
            padding: 20px;
          }
          .payment-loading {
            text-align: center;
            font-size: 18px;
            color: #666;
          }
        ',
      ],
      'cloudpayments_styles',
    ];

    return $form;
  }

}