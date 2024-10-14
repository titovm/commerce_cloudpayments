<?php

namespace Drupal\commerce_cloudpayments\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class CloudPaymentsForm extends PaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_cloudpayments\Plugin\Commerce\PaymentGateway\CloudPayments $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    $data = $payment_gateway_plugin->buildPaymentData($payment);

    $order = $payment->getOrder();
    $form['#attached']['library'][] = 'commerce_cloudpayments/cloudpayments';
    $form['#attached']['drupalSettings']['cloudPayments'] = [
      'data' => $data,
      'successRedirect' => Url::fromRoute('commerce_cloudpayments.return', ['commerce_order' => $order->id()])->toString(),
      'failRedirect' => $order->toUrl('canonical')->toString(),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Proceed to payment'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['cloudpayments-submit'],
      ],
    ];

    return $form;
  }

}
