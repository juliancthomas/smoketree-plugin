/**
 * Balance payment modal interactions.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/js
 */

(function($) {
	'use strict';

	function formatCurrency(value) {
		return '$' + Number(value).toFixed(2);
	}

	$(document).ready(function() {
		const $modal = $('#stsrc-pay-balance-modal');
		if ($modal.length === 0) {
			return;
		}

		const $amountInput = $('#stsrc-balance-payment-amount');
		const $preview = $('#stsrc-balance-after-preview');
		const $error = $('#stsrc-balance-payment-error');
		const $submit = $('#stsrc-continue-to-payment');
		const minimumValue = Number((window.stsrcBalancePayment && window.stsrcBalancePayment.minimumPayment) || $('#stsrc-minimum-balance-payment-value').val() || 10);
		const currentBalance = Number($('#stsrc-current-balance-value').val() || 0);

		function openModal() {
			$modal.removeClass('stsrc-hidden').attr('aria-hidden', 'false');
			validateAndPreview();
			$amountInput.trigger('focus');
		}

		function closeModal() {
			$modal.addClass('stsrc-hidden').attr('aria-hidden', 'true');
			$error.addClass('stsrc-hidden').text('');
		}

		function validateAndPreview() {
			const amount = Number($amountInput.val());
			let message = '';

			if (!amount || amount <= 0) {
				message = 'Enter a payment amount greater than $0.00.';
			} else if (amount < minimumValue) {
				message = 'Minimum payment is ' + formatCurrency(minimumValue) + '.';
			} else if (amount > currentBalance) {
				message = 'Payment cannot exceed current balance.';
			}

			const remaining = Math.max(currentBalance - (isNaN(amount) ? 0 : amount), 0);
			$preview.text(formatCurrency(remaining));

			if (message) {
				$error.removeClass('stsrc-hidden').text(message);
				$submit.prop('disabled', true);
				return false;
			}

			$error.addClass('stsrc-hidden').text('');
			$submit.prop('disabled', false);
			return true;
		}

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

			// AJAX checkout redirect integration is implemented in Step 6.5.
			$error.removeClass('stsrc-hidden').text('Ready to continue. Payment session integration is in the next step.');
		});
	});
})(jQuery);
