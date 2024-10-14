<?php

namespace Drupal\commerce_cloudpayments\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the CloudPayments payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "cloudpayments",
 *   label = @Translation("CloudPayments"),
 *   display_label = @Translation("CloudPayments"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_cloudpayments\PluginForm\CloudPaymentsForm",
 *   },
 *   payment_method_types = {"credit_card"},
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
      'api_secret' => '',
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
      '#default_value' => $this->configuration['public_id'],
      '#required' => TRUE,
    ];
    
    $form['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#default_value' => $this->configuration['api_secret'],
      '#required' => TRUE,
    ];

    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
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
    $this->configuration['api_secret'] = $values['api_secret'];
    $this->configuration['mode'] = $values['mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function redirectPayment(PaymentInterface $payment, array $form, FormStateInterface $form_state) {
    $order = $payment->getOrder();
    $amount = $payment->getAmount()->getNumber();
    $currency = $payment->getAmount()->getCurrencyCode();

    // Prepare data for the payment widget.
    $data = [
      'publicId' => $this->configuration['public_id'],
      'description' => $this->t('Order #@number', ['@number' => $order->id()]),
      'amount' => $amount,
      'currency' => $currency,
      'invoiceId' => $order->id(),
      'accountId' => $order->getCustomerId(),
      'skin' => 'classic',
      'data' => [],
    ];

    // Build the redirection page with the payment widget.
    $build = [
      '#theme' => 'cloudpayments_redirect',
      '#data' => $data,
      '#attached' => [
        'library' => [
          'commerce_cloudpayments/cloudpayments',
        ],
        'drupalSettings' => [
          'cloudPayments' => [
            'data' => $data,
            'successRedirect' => $this->getReturnUrl($order)->toString(),
            'failRedirect' => $this->getCancelUrl($order)->toString(),
          ],
        ],
      ],
    ];

    // Set the response to render the payment page.
    $form_state->setResponse($build);
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    // Validate the HMAC signature.
    $hmac = base64_encode(hash_hmac('sha256', $request->getContent(), $this->configuration['api_secret'], TRUE));

    if ($request->headers->get('Content-HMAC') !== $hmac) {
      throw new PaymentGatewayException('Invalid signature');
    }

    // Process the notification.
    $order_id = $data['InvoiceId'];
    $order = Order::load($order_id);

    if ($order) {
      // Update order and payment status based on $data['Status']
      // For example, set the order to completed if payment is successful.
      if ($data['Status'] === 'Completed') {
        $payment = Payment::create([
          'state' => 'completed',
          'amount' => $order->getTotalPrice(),
          'payment_gateway' => $this->entityId,
          'order_id' => $order->id(),
          'remote_id' => $data['TransactionId'],
          'remote_state' => $data['Status'],
        ]);
        $payment->save();
        $order->set('state', 'completed');
        $order->save();
      }
    }

    return new Response('OK', 200);
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // The order is already saved, but we need to finalize the payment.
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state' => 'completed',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $this->entityId,
      'order_id' => $order->id(),
      'remote_id' => $request->query->get('TransactionId'),
      'remote_state' => 'completed',
    ]);
    $payment->save();

    // Update the order state.
    $order->set('state', 'completed');
    $order->save();

    drupal_set_message($this->t('Your payment was successful.'));
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    // Handle payment cancellation.
    // Update the payment and order status as necessary.
  }

  /**
   * Builds the payment data for CloudPayments.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return array
   *   The payment data.
   */
  public function buildPaymentData(PaymentInterface $payment) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $payment->getOrder();

    $data = [
      'publicId' => $this->configuration['public_id'],
      'amount' => $payment->getAmount()->getNumber(),
      'currency' => $payment->getAmount()->getCurrencyCode(),
      'invoiceId' => $order->id(),
      'description' => $this->t('Order #@number', ['@number' => $order->getOrderNumber()]),
    ];

    // Get the customer's email from the order.
    $customer = $order->getCustomer();
    if ($customer && $customer->getEmail()) {
      $data['email'] = $customer->getEmail();
    }

    return $data;
  }
}
