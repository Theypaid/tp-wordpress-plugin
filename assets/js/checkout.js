(function ($) {
  'use strict';

  /* ── Coin selector highlight (classic checkout) ── */
  $(document.body).on('change', 'input[name="theypaid_asset_id"]', function () {
    $('.theypaid-coin-selector__item input').each(function () {
      $(this)
        .closest('.theypaid-coin-selector__item')
        .toggleClass('theypaid-coin-selector__item--selected', this.checked);
    });
  });

  /* ── Payment-pending page logic ── */
  if (typeof theypaidCheckout === 'undefined') {
    return;
  }

  var cfg = theypaidCheckout;
  var polling = null;
  var timerInterval = null;
  var finalStatus = null;

  /* Copy to clipboard */
  $(document).on('click', '.theypaid-pending__copy-btn', function () {
    var $btn = $(this);
    var text = $btn.siblings('span').text().trim();

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        flashCopied($btn);
      });
    } else {
      var $tmp = $('<textarea>').val(text).appendTo('body').select();
      document.execCommand('copy');
      $tmp.remove();
      flashCopied($btn);
    }
  });

  function flashCopied($btn) {
    var original = $btn.text();
    $btn.text(cfg.i18n.copied).addClass('theypaid-pending__copy-btn--copied');
    setTimeout(function () {
      $btn.text(original).removeClass('theypaid-pending__copy-btn--copied');
    }, 2000);
  }

  /* Countdown timer */
  var $timer = $('#theypaid-countdown-timer');
  var $countdown = $('#theypaid-countdown');
  var expiresAt = $timer.data('expires');

  if (expiresAt) {
    var deadline = new Date(expiresAt).getTime();

    timerInterval = setInterval(function () {
      var now = Date.now();
      var remaining = Math.max(0, deadline - now);
      var totalSec = Math.floor(remaining / 1000);
      var hrs = Math.floor(totalSec / 3600);
      var mins = Math.floor((totalSec % 3600) / 60);
      var secs = totalSec % 60;

      var display =
        (hrs > 0 ? pad(hrs) + ':' : '') + pad(mins) + ':' + pad(secs);
      $timer.text(display);

      if (totalSec <= 300) {
        $timer.addClass('theypaid-pending__countdown-timer--urgent');
        $countdown.addClass('theypaid-pending__countdown--urgent');
      }

      if (totalSec <= 0) {
        clearInterval(timerInterval);
        timerInterval = null;
        $timer.text('00:00');

        if (finalStatus !== 'paid') {
          handleExpired();
        }
      }
    }, 1000);
  }

  function pad(n) {
    return n < 10 ? '0' + n : '' + n;
  }

  /* Poll for payment status */
  var ajaxAction = cfg.isEdd
    ? 'theypaid_check_edd_status'
    : 'theypaid_check_status';

  function checkStatus() {
    var postData = {
      action: ajaxAction,
      nonce: cfg.nonce,
      order_id: cfg.orderId,
    };

    if (cfg.orderKey) {
      postData.order_key = cfg.orderKey;
    }

    $.post(cfg.ajaxUrl, postData).done(function (response) {
      if (!response.success) return;

      var status = response.data.status;
      updateStatusUI(status);

      if (status === 'paid') {
        handlePaid();
      } else if (status === 'expired') {
        handleExpired();
      } else if (status === 'insufficient') {
        handleInsufficient();
      } else if (status === 'confirming') {
        handleConfirming();
      }
    });
  }

  function handlePaid() {
    if (finalStatus === 'paid') return;
    finalStatus = 'paid';
    stopPolling();
    clearTimer();

    disableDetails();
    advanceSteps();
    $('#theypaid-notice').fadeOut(200);

    var $overlay = $('#theypaid-success-overlay');
    $overlay.removeClass('theypaid-pending__overlay--hidden')
            .addClass('theypaid-pending__overlay--visible');

    setTimeout(function () {
      window.location.href = cfg.orderReceivedUrl;
    }, 2500);
  }

  function handleExpired() {
    if (finalStatus) return;
    finalStatus = 'expired';
    stopPolling();
    clearTimer();

    updateStatusUI('expired');
    disableDetails();
    $('#theypaid-notice').fadeOut(200);

    var $overlay = $('#theypaid-expired-overlay');
    $overlay.removeClass('theypaid-pending__overlay--hidden')
            .addClass('theypaid-pending__overlay--visible');
  }

  function handleInsufficient() {
    if (finalStatus) return;
    finalStatus = 'insufficient';
    stopPolling();
    updateStatusUI('insufficient');
  }

  function handleConfirming() {
    if (finalStatus) return;
    updateStatusUI('confirming');
  }

  function advanceSteps() {
    var $stepPay = $('#theypaid-step-pay');
    var $stepDone = $('#theypaid-step-done');

    $stepPay.removeClass('theypaid-pending__step--active')
            .addClass('theypaid-pending__step--done');
    $stepPay.find('.theypaid-pending__step-icon').html('&#10003;');

    $stepDone.addClass('theypaid-pending__step--done');
    $stepDone.find('.theypaid-pending__step-icon').html('&#10003;');

    $('.theypaid-pending__step-line').addClass('theypaid-pending__step-line--done');
  }

  function disableDetails() {
    $('#theypaid-payment-details').addClass('theypaid-pending__details--disabled');
  }

  function clearTimer() {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
  }

  function updateStatusUI(status) {
    var $el = $('#theypaid-status-text');
    var $dot = $el.find('.theypaid-pending__status-dot');

    $el
      .removeClass(
        'theypaid-pending__status-text--waiting theypaid-pending__status-text--paid theypaid-pending__status-text--expired theypaid-pending__status-text--insufficient theypaid-pending__status-text--confirming'
      )
      .addClass('theypaid-pending__status-text--' + status);

    var label = cfg.i18n[status] || cfg.i18n.waiting;
    $el.find('.theypaid-pending__status-label').text(label);

    if (status !== 'waiting' && status !== 'confirming') {
      $dot.css('animation', 'none').hide();
    }
  }

  function startPolling() {
    checkStatus();
    polling = setInterval(checkStatus, cfg.pollInterval);
  }

  function stopPolling() {
    if (polling) {
      clearInterval(polling);
      polling = null;
    }
  }

  if ($('#theypaid-pending-page').length) {
    startPolling();
  }
})(jQuery);
