(function ($) {
  'use strict';

  $(function () {
    var $btn = $('#theypaid-test-connection');
    var $result = $('#theypaid-test-result');

    if (!$btn.length) {
      return;
    }

    $btn.on('click', function () {
      var apiKey = $('#woocommerce_theypaid_api_key').val();

      if (!apiKey) {
        $result
          .css('color', '#d63638')
          .text(theypaidAdmin.i18n.noKey);
        return;
      }

      $btn.prop('disabled', true);
      $result
        .css('color', '#2271b1')
        .text(theypaidAdmin.i18n.testing);

      $.post(theypaidAdmin.ajaxUrl, {
        action: 'theypaid_test_connection',
        nonce: theypaidAdmin.nonce,
        api_key: apiKey,
      })
        .done(function (response) {
          if (response.success) {
            $result
              .css('color', '#00a32a')
              .text(
                theypaidAdmin.i18n.success.replace(
                  '%d',
                  response.data.count
                )
              );
          } else {
            $result
              .css('color', '#d63638')
              .text(
                theypaidAdmin.i18n.fail.replace(
                  '%s',
                  response.data || 'Unknown error'
                )
              );
          }
        })
        .fail(function () {
          $result
            .css('color', '#d63638')
            .text(
              theypaidAdmin.i18n.fail.replace('%s', 'Network error')
            );
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });
  });
})(jQuery);
