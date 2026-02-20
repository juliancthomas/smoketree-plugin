/**
 * Balance payment modal interactions.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/js
 */

(function($) {
	'use strict';

	var FEE_RATES = {
		card:            { percent: 0.029, flat: 0.30, cap: null },
		us_bank_account: { percent: 0.008, flat: 0,    cap: 5.00 }
	};

	function formatCurrency(value) {
		return '$' + Number(value).toFixed(2);
	}

	function calculateFee(amount, method) {
		var rate = FEE_RATES[method];
		if (!rate) {
			return 0;
		}
		var fee = amount * rate.percent + rate.flat;
		if (rate.cap !== null && fee > rate.cap) {
			fee = rate.cap;
		}
		return Math.round(fee * 100) / 100;
	}

	$(document).ready(function() {
		var $modal = $('#stsrc-pay-balance-modal');
		if ($modal.length === 0) {
			return;
		}

		var $amountInput    = $('#stsrc-balance-payment-amount');
		var $preview        = $('#stsrc-balance-after-preview');
		var $error          = $('#stsrc-balance-payment-error');
		var $submit         = $('#stsrc-continue-to-payment');
		var $summaryPayment = $('#stsrc-summary-payment');
		var $summaryFee     = $('#stsrc-summary-fee');
		var $summaryTotal   = $('#stsrc-summary-total');
		var $methodInputs   = $modal.find('input[name="payment_method"]');
		var $methodLabels   = $modal.find('.stsrc-pay-balance-method');

		var defaultSubmitText = $submit.text();
		var localized         = window.stsrcBalancePayment || {};
		var minimumValue      = Number(localized.minimumPayment || $('#stsrc-minimum-balance-payment-value').val() || 10);
		var currentBalance    = Number(localized.currentBalance || $('#stsrc-current-balance-value').val() || 0);
		var ajaxUrl           = localized.ajaxUrl || (window.stsrcPublic && window.stsrcPublic.ajaxUrl) || window.ajaxurl || '';

		function getSelectedMethod() {
			return $modal.find('input[name="payment_method"]:checked').val() || 'card';
		}

		function openModal() {
			$modal.removeClass('stsrc-hidden').attr('aria-hidden', 'false');
			validateAndPreview();
			$amountInput.trigger('focus');
		}

		function closeModal() {
			$modal.addClass('stsrc-hidden').attr('aria-hidden', 'true');
			$error.addClass('stsrc-hidden').text('');
			$submit.prop('disabled', false).text(defaultSubmitText);
		}

		function validateAndPreview() {
			var amount  = Number($amountInput.val());
			var method  = getSelectedMethod();
			var message = '';

			if (!amount || amount <= 0) {
				message = 'Enter a payment amount greater than $0.00.';
			} else if (amount < minimumValue) {
				message = 'Minimum payment is ' + formatCurrency(minimumValue) + '.';
			} else if (amount > currentBalance) {
				message = 'Payment cannot exceed current balance.';
			} else {
				var remaining = currentBalance - amount;
				if (remaining > 0 && remaining < minimumValue) {
					message = 'Payment would leave a remaining balance of ' + formatCurrency(remaining) +
						', which is below the minimum payment of ' + formatCurrency(minimumValue) +
						'. Please pay at least ' + formatCurrency(currentBalance - minimumValue) +
						' or the full balance of ' + formatCurrency(currentBalance) + '.';
				}
			}

			var safeAmount = (!amount || isNaN(amount) || amount <= 0) ? 0 : amount;
			var fee   = calculateFee(safeAmount, method);
			var total = safeAmount + fee;
			var remainingBalance = Math.max(currentBalance - safeAmount, 0);

			$summaryPayment.text(formatCurrency(safeAmount));
			$summaryFee.text(formatCurrency(fee));
			$summaryTotal.text(formatCurrency(total));
			$preview.text(formatCurrency(remainingBalance));

			if (message) {
				$error.removeClass('stsrc-hidden').text(message);
				$submit.prop('disabled', true);
				return false;
			}

			$error.addClass('stsrc-hidden').text('');
			$submit.prop('disabled', false);
			return true;
		}

		$methodInputs.on('change', function() {
			$methodLabels.removeClass('stsrc-pay-balance-method--selected');
			$(this).closest('.stsrc-pay-balance-method').addClass('stsrc-pay-balance-method--selected');
			validateAndPreview();
		});

		$(document).on('click', '.stsrc-pay-balance-btn', function(e) {
			e.preventDefault();
			openModal();
		});

		$modal.on('click', '[data-close="1"]', function(e) {
			e.preventDefault();
			closeModal();
		});

		$amountInput.on('input change', validateAndPreview);

		$('#stsrc-pay-balance-form').on('submit', function(e) {
			e.preventDefault();
			if (!validateAndPreview()) {
				return;
			}
			$submit.prop('disabled', true).text('Redirecting...');
			$error.addClass('stsrc-hidden').text('');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'stsrc_create_balance_payment',
					member_id: localized.memberId || $('#stsrc-pay-balance-form input[name="member_id"]').val(),
					amount: $amountInput.val(),
					payment_method: getSelectedMethod(),
					nonce: localized.nonce || $('#stsrc-pay-balance-form input[name="nonce"]').val()
				},
				success: function(response) {
					if (response && response.success && response.data && response.data.session_url) {
						window.location.href = response.data.session_url;
						return;
					}

					var message = response && response.data && response.data.message
						? response.data.message
						: 'Unable to start payment. Please try again.';
					$error.removeClass('stsrc-hidden').text(message);
					$submit.prop('disabled', false).text(defaultSubmitText);
				},
				error: function() {
					$error.removeClass('stsrc-hidden').text('A network error occurred. Please try again.');
					$submit.prop('disabled', false).text(defaultSubmitText);
				}
			});
		});
	});
})(jQuery);
