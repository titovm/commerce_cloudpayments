(function ($, Drupal, drupalSettings) {
  'use strict';

  // Add debug flag
  const DEBUG = true;

  function log(...args) {
    if (DEBUG) {
      console.log('[CloudPayments]', ...args);
    }
  }

  function error(...args) {
    if (DEBUG) {
      console.error('[CloudPayments]', ...args);
    }
  }

  Drupal.behaviors.cloudPayments = {
    attach: function (context, settings) {
      log('Behavior attached');

      // Only run once on the main form
      if (!settings.cloudPayments || !once('cloudPayments', '.cloudpayments-container', context).length) {
        log('No container or already processed');
        return;
      }

      log('Settings:', settings.cloudPayments);

      // Function to initialize payment
      function initializePayment() {
        log('Attempting to initialize widget');
        
        // Verify SDK is loaded
        if (typeof window.cp === 'undefined' || typeof window.cp.CloudPayments === 'undefined') {
          log('SDK not loaded, retrying in 500ms');
          setTimeout(initializePayment, 500);
          return;
        }

        try {
          log('Creating CloudPayments instance');
          var widget = new window.cp.CloudPayments();
          
          log('Calling charge with data:', settings.cloudPayments.data);
          widget.charge(
            settings.cloudPayments.data,
            function (options) {
              // Success callback
              log('Payment successful, redirecting to:', settings.cloudPayments.successRedirect);
              window.location.href = settings.cloudPayments.successRedirect;
            },
            function (reason, options) {
              // Fail callback
              error('Payment failed:', reason);
              window.location.href = settings.cloudPayments.failRedirect;
            }
          );
        } catch (e) {
          error('Widget initialization error:', e);
          // Show error in the container
          $('.cloudpayments-container .payment-loading').html(
            Drupal.t('Error initializing payment system. Please refresh the page or try again later.')
          );
        }
      }

      // Check if SDK script is loaded
      if (document.querySelector('script[src*="cloudpayments.js"]')) {
        log('SDK script found');
        // Add ready event listener
        if (window.cp && window.cp.CloudPayments) {
          log('SDK already loaded, initializing');
          initializePayment();
        } else {
          log('Waiting for SDK ready event');
          window.addEventListener('cloudpaymentsready', function() {
            log('SDK ready event received');
            initializePayment();
          });
        }
      } else {
        error('CloudPayments SDK script not found');
        $('.cloudpayments-container .payment-loading').html(
          Drupal.t('Payment system failed to load. Please refresh the page or contact support.')
        );
      }

      // Fallback timeout
      setTimeout(function() {
        if (!window.cp || !window.cp.CloudPayments) {
          error('SDK failed to load after timeout');
          $('.cloudpayments-container .payment-loading').html(
            Drupal.t('Payment system failed to load. Please refresh the page or contact support.')
          );
        }
      }, 10000);
    }
  };
})(jQuery, Drupal, drupalSettings);