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
		const defaultSubmitText = $submit.text();
		const localized = window.stsrcBalancePayment || {};
		const minimumValue = Number(localized.minimumPayment || $('#stsrc-minimum-balance-payment-value').val() || 10);
		const currentBalance = Number(localized.currentBalance || $('#stsrc-current-balance-value').val() || 0);
		const ajaxUrl = localized.ajaxUrl || (window.stsrcPublic && window.stsrcPublic.ajaxUrl) || window.ajaxurl || '';

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
			$submit.prop('disabled', true).text('Redirecting...');
			$error.addClass('stsrc-hidden').text('');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'stsrc_create_balance_payment',
					member_id: localized.memberId || $('#stsrc-pay-balance-form input[name="member_id"]').val(),
					amount: $amountInput.val(),
					nonce: localized.nonce || $('#stsrc-pay-balance-form input[name="nonce"]').val()
				},
				success: function(response) {
					if (response && response.success && response.data && response.data.session_url) {
						window.location.href = response.data.session_url;
						return;
					}

					const message = response && response.data && response.data.message
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
