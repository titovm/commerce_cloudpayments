(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.cloudPayments = {
    attach: function (context, settings) {
      // Only run once on the main form
      if (!settings.cloudPayments || !once('cloudPayments', '.cloudpayments-container', context).length) {
        return;
      }

      // Function to initialize payment
      function initializePayment() {
        // Verify SDK is loaded
        if (typeof window.cp === 'undefined' || typeof window.cp.CloudPayments === 'undefined') {
          setTimeout(initializePayment, 500);
          return;
        }

        try {
          var widget = new window.cp.CloudPayments();
          widget.charge(
            settings.cloudPayments.data,
            function (options) {
              window.location.href = settings.cloudPayments.successRedirect;
            },
            function (reason, options) {
              window.location.href = settings.cloudPayments.failRedirect;
            }
          );
        } catch (e) {
          $('.cloudpayments-container .payment-loading').html(
            Drupal.t('Ошибка инициализации платежной системы. Пожалуйста, обновите страницу или попробуйте позже.')
          );
        }
      }

      // Check if SDK script is loaded
      if (document.querySelector('script[src*="cloudpayments.js"]')) {
        if (window.cp && window.cp.CloudPayments) {
          initializePayment();
        } else {
          window.addEventListener('cloudpaymentsready', initializePayment);
        }
      } else {
        $('.cloudpayments-container .payment-loading').html(
          Drupal.t('Не удалось загрузить платежную систему. Пожалуйста, обновите страницу или обратитесь в службу поддержки.')
        );
      }

      // Fallback timeout
      setTimeout(function() {
        if (!window.cp || !window.cp.CloudPayments) {
          $('.cloudpayments-container .payment-loading').html(
            Drupal.t('Не удалось загрузить платежную систему. Пожалуйста, обновите страницу или обратитесь в службу поддержки.')
          );
        }
      }, 10000);
    }
  };
})(jQuery, Drupal, drupalSettings);