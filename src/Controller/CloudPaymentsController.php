<?php

namespace Drupal\commerce_cloudpayments\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_order\Entity\Order;

/**
 * Handles server-to-server notifications from CloudPayments.
 */
class CloudPaymentsController extends ControllerBase {

  /**
   * Receives and processes a CloudPayments notification.
   */
  public function notify(Request $request) {
    \Drupal::logger('commerce_cloudpayments')->notice('Received notification request');

    // Retrieve the API secret from configuration
    $config = $this->config('commerce_cloudpayments.settings');
    $api_secret = $config->get('api_secret');

    if (empty($api_secret)) {
      \Drupal::logger('commerce_cloudpayments')->error('API secret not configured');
      return new JsonResponse(['code' => 13, 'message' => 'API secret not configured']);
    }

    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (empty($data)) {
      \Drupal::logger('commerce_cloudpayments')->error('Invalid notification data received');
      return new JsonResponse(['code' => 13, 'message' => 'Invalid data']);
    }

    // Log the raw notification data
    \Drupal::logger('commerce_cloudpayments')->notice('Notification data: @data', ['@data' => print_r($data, TRUE)]);

    // Validate HMAC signature
    $hmac = base64_encode(hash_hmac('sha256', $content, $api_secret, TRUE));
    if ($request->headers->get('Content-HMAC') !== $hmac) {
      \Drupal::logger('commerce_cloudpayments')->error('Invalid HMAC signature');
      return new JsonResponse(['code' => 13, 'message' => 'Invalid signature']);
    }

    // Extract order ID from the notification data
    $orderId = isset($data['Data']['orderId']) ? $data['Data']['orderId'] : NULL;
    if (!$orderId) {
      \Drupal::logger('commerce_cloudpayments')->error('Order ID not found in notification data');
      return new JsonResponse(['code' => 13, 'message' => 'Order ID not found']);
    }

    // Load the order
    $order = Order::load($orderId);
    if (!$order) {
      \Drupal::logger('commerce_cloudpayments')->error('Order @order_id not found', ['@order_id' => $orderId]);
      return new JsonResponse(['code' => 13, 'message' => 'Order not found']);
    }

    // Get the payment
    $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties([
      'order_id' => $orderId,
      'payment_gateway' => 'cloudpayments',
    ]);
    $payment = reset($payments);

    if (!$payment instanceof PaymentInterface) {
      \Drupal::logger('commerce_cloudpayments')->error('Payment not found for order @order_id', ['@order_id' => $orderId]);
      return new JsonResponse(['code' => 13, 'message' => 'Payment not found']);
    }

    // Process the payment status
    $status = isset($data['Status']) ? strtolower($data['Status']) : '';
    switch ($status) {
      case 'completed':
        $payment->setState('completed');
        $payment->save();
        \Drupal::logger('commerce_cloudpayments')->notice('Payment completed for order @order_id', ['@order_id' => $orderId]);
        break;

      case 'cancelled':
      case 'failed':
        $payment->setState('failed');
        $payment->save();
        \Drupal::logger('commerce_cloudpayments')->notice('Payment failed for order @order_id', ['@order_id' => $orderId]);
        break;

      default:
        \Drupal::logger('commerce_cloudpayments')->notice('Unknown payment status @status for order @order_id', [
          '@status' => $status,
          '@order_id' => $orderId,
        ]);
    }

    return new JsonResponse(['code' => 0]);
  }
}