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
    // Retrieve the API secret from configuration
    $config = $this->config('commerce_cloudpayments.settings');
    $api_secret = $config->get('api_secret');

    if (empty($api_secret)) {
      \Drupal::logger('commerce_cloudpayments')->error('API секрет не настроен');
      return new JsonResponse(['code' => 13, 'message' => 'API секрет не настроен']);
    }

    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (empty($data)) {
      \Drupal::logger('commerce_cloudpayments')->error('Получены недопустимые данные');
      return new JsonResponse(['code' => 13, 'message' => 'Недопустимые данные']);
    }

    // Validate HMAC signature
    $hmac = base64_encode(hash_hmac('sha256', $content, $api_secret, TRUE));
    if ($request->headers->get('Content-HMAC') !== $hmac) {
      \Drupal::logger('commerce_cloudpayments')->error('Неверная подпись HMAC');
      return new JsonResponse(['code' => 13, 'message' => 'Неверная подпись']);
    }

    // Extract order ID from the notification data
    $orderId = isset($data['Data']['orderId']) ? $data['Data']['orderId'] : NULL;
    if (!$orderId) {
      \Drupal::logger('commerce_cloudpayments')->error('ID заказа не найден в данных уведомления');
      return new JsonResponse(['code' => 13, 'message' => 'ID заказа не найден']);
    }

    // Load the order
    $order = Order::load($orderId);
    if (!$order) {
      \Drupal::logger('commerce_cloudpayments')->error('Заказ @order_id не найден', ['@order_id' => $orderId]);
      return new JsonResponse(['code' => 13, 'message' => 'Заказ не найден']);
    }

    // Get the payment
    $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties([
      'order_id' => $orderId,
      'payment_gateway' => 'cloudpayments',
    ]);
    $payment = reset($payments);

    if (!$payment instanceof PaymentInterface) {
      \Drupal::logger('commerce_cloudpayments')->error('Платеж не найден для заказа @order_id', ['@order_id' => $orderId]);
      return new JsonResponse(['code' => 13, 'message' => 'Платеж не найден']);
    }

    // Process the payment status
    $status = isset($data['Status']) ? strtolower($data['Status']) : '';
    switch ($status) {
      case 'completed':
        $payment->setState('completed');
        $payment->save();
        break;

      case 'cancelled':
      case 'failed':
        $payment->setState('failed');
        $payment->save();
        break;

      default:
        \Drupal::logger('commerce_cloudpayments')->error('Неизвестный статус платежа @status для заказа @order_id', [
          '@status' => $status,
          '@order_id' => $orderId,
        ]);
    }

    return new JsonResponse(['code' => 0]);
  }
}