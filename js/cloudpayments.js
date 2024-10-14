(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.cloudPayments = {
    attach: function (context, settings) {
      $('.cloudpayments-submit', context).once('cloudPayments').on('click', function (e) {
        e.preventDefault();
        
        var data = drupalSettings.cloudPayments.data;
        var successRedirect = drupalSettings.cloudPayments.successRedirect;
        var failRedirect = drupalSettings.cloudPayments.failRedirect;

        var widget = new cp.CloudPayments();
        widget.pay('charge', data,
          {
            onSuccess: function (options) {
              // Append the transaction ID to the success URL
              var returnUrl = successRedirect + (successRedirect.indexOf('?') > -1 ? '&' : '?') + 'TransactionId=' + options.transaction.id;
              window.location.href = returnUrl;
            },
            onFail: function (reason, options) {
              alert('Payment failed: ' + reason);
              window.location.href = failRedirect;
            }
          }
        );
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
