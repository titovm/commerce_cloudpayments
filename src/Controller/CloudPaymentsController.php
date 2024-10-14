<?php

namespace Drupal\commerce_cloudpayments\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\Entity\Payment;

class CloudPaymentsController extends ControllerBase {

  public function onReturn(OrderInterface $commerce_order, Request $request) {
    /** @var \Drupal\commerce_payment\PaymentGatewayManager $payment_gateway_manager */
    $payment_gateway_manager = \Drupal::service('plugin.manager.commerce_payment_gateway');
    /** @var \Drupal\commerce_cloudpayments\Plugin\Commerce\PaymentGateway\CloudPayments $payment_gateway */
    $payment_gateway = $payment_gateway_manager->createInstance($commerce_order->get('payment_gateway')->target_id);

    $transaction_id = $request->query->get('TransactionId');
    
    if ($transaction_id) {
      // Create a payment.
      $payment = Payment::create([
        'state' => 'completed',
        'amount' => $commerce_order->getTotalPrice(),
        'payment_gateway' => $payment_gateway->getPluginId(),
        'order_id' => $commerce_order->id(),
        'remote_id' => $transaction_id,
        'remote_state' => 'completed',
      ]);
      $payment->save();

      // Update the order state.
      $commerce_order->set('state', 'completed');
      $commerce_order->save();

      $this->messenger()->addMessage($this->t('Your payment was successful.'));
      
      // Redirect to the order completion page.
      return $this->redirect('commerce_checkout.completion', ['commerce_order' => $commerce_order->id()]);
    } else {
      $this->messenger()->addError($this->t('Payment failed. Please try again or contact support.'));
      
      // Redirect back to the order page.
      return $this->redirect('entity.commerce_order.canonical', ['commerce_order' => $commerce_order->id()]);
    }
  }
}
