/**
 * Member portal specific interactions.
 *
 * Handles transaction history collapse/expand behavior.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/js
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		initTransactionToggle();
		initRenewalSection();
		initReferralCopyButton();
	});

	function initTransactionToggle() {
		const $toggle = $('.stsrc-transaction-toggle');
		if ($toggle.length === 0) {
			return;
		}

		$toggle.on('click', function() {
			const $button = $(this);
			const isOpen = $button.attr('data-open') === '1';
			const $history = $('#stsrc-member-transaction-history');
			const $hiddenItems = $history.find('.stsrc-transaction-item--hidden');

			if (isOpen) {
				$hiddenItems.attr('aria-hidden', 'true').slideUp(180);
				$button.attr('data-open', '0').text('Show More');
			} else {
				$hiddenItems.attr('aria-hidden', 'false').slideDown(180);
				$button.attr('data-open', '1').text('Show Less');
			}
		});
	}

	function initRenewalSection() {
		const $form = $('#stsrc-renewal-form');
		if ($form.length === 0) {
			return;
		}

		const renewalConfig = (window.stsrcPublic && window.stsrcPublic.renewal) ? window.stsrcPublic.renewal : null;
		const ajaxUrl = (window.stsrcPublic && window.stsrcPublic.ajaxUrl) ? window.stsrcPublic.ajaxUrl : '';
		const quoteAction = renewalConfig && renewalConfig.actions ? renewalConfig.actions.quote : 'stsrc_renewal_quote';

		const $membershipRows = $('.stsrc-renewal-card');
		const $continueBtn = $('#stsrc-renewal-continue-btn');
		const $membershipAmount = $('#stsrc-renewal-membership-amount');
		const $extrasAmount = $('#stsrc-renewal-extras-amount');
		const $feeAmount = $('#stsrc-renewal-fee-amount');
		const $totalAmount = $('#stsrc-renewal-total-amount');
		const $balanceAmount = $('#stsrc-renewal-balance-amount');

		function formatCurrency(value) {
			const parsed = Number(value || 0);
			return '$' + parsed.toFixed(2);
		}

		function getSelectedMembershipPrice() {
			const $selected = $form.find('input[name="target_membership_type_id"]:checked').closest('.stsrc-renewal-card');
			const priceText = $selected.find('.stsrc-renewal-card__price').text().replace(/[^0-9.]/g, '');
			return Number(priceText || 0);
		}

		function getBalanceAmount() {
			const value = ($balanceAmount.text() || '').replace(/[^0-9.\-]/g, '');
			return Number(value || 0);
		}

		function getPaymentMethod() {
			return $form.find('input[name="payment_method"]:checked').val() || 'card';
		}

		function calcFallbackQuote() {
			const membership = getSelectedMembershipPrice();
			const extras = 0;
			const subtotal = Math.max(0, membership + extras + getBalanceAmount());
			const paymentMethod = getPaymentMethod();
			let fee = 0;

			if (paymentMethod === 'card') {
				fee = (subtotal * 0.029) + 0.30;
			} else if (paymentMethod === 'ach' || paymentMethod === 'bank_account' || paymentMethod === 'us_bank_account') {
				fee = Math.min(subtotal * 0.008, 5.0);
			}

			fee = Number(fee.toFixed(2));
			return {
				membership_base: membership,
				extra_members_amount: extras,
				processing_fee: fee,
				total: Number((subtotal + fee).toFixed(2))
			};
		}

		function renderQuote(quote) {
			if (!quote) {
				return;
			}

			$membershipAmount.text(formatCurrency(quote.membership_base || 0));
			$extrasAmount.text(formatCurrency(quote.extra_members_amount || 0));
			$feeAmount.text(formatCurrency(quote.processing_fee || 0));
			$totalAmount.text(formatCurrency(quote.total || 0));
			$continueBtn.prop('disabled', false);
		}

		function requestQuote() {
			const membershipTypeId = $form.find('input[name="target_membership_type_id"]:checked').val();
			const paymentMethod = getPaymentMethod();
			if (!membershipTypeId) {
				$continueBtn.prop('disabled', true);
				return;
			}

			const fallbackQuote = calcFallbackQuote();
			if (!ajaxUrl || !renewalConfig || !renewalConfig.nonce) {
				renderQuote(fallbackQuote);
				return;
			}

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: quoteAction,
					nonce: renewalConfig.nonce,
					target_membership_type_id: membershipTypeId,
					payment_method: paymentMethod,
					season_key: renewalConfig.seasonKey || '',
					member_id: renewalConfig.member ? renewalConfig.member.member_id : 0
				}
			}).done(function(response) {
				const serverQuote = response && response.success && response.data ? response.data.quote : null;
				renderQuote(serverQuote || fallbackQuote);
			}).fail(function() {
				renderQuote(fallbackQuote);
			});
		}

		$membershipRows.on('click', function() {
			$membershipRows.removeClass('is-current');
			$(this).addClass('is-current');
		});

		var $instructionsWrap = $('#stsrc-renewal-payment-instructions');
		function updatePaymentInstructions() {
			var method = getPaymentMethod();
			$instructionsWrap.find('.stsrc-renewal-instruction').hide();
			var $match = $instructionsWrap.find('[data-method="' + method + '"]');
			if ($match.length) {
				$match.show();
				$instructionsWrap.slideDown(200);
			} else {
				$instructionsWrap.slideUp(200);
			}
		}
		$form.on('change', 'input[name="payment_method"]', updatePaymentInstructions);
		updatePaymentInstructions();

		var $errorBanner = $('<div class="stsrc-renewal-notice stsrc-renewal-notice--error" role="alert" style="display:none;"></div>');
		$form.closest('.stsrc-renewal-section').prepend($errorBanner);

		function showRenewalError(message) {
			$errorBanner
				.html('<p>' + $('<span/>').text(message).html() + '</p><button type="button" class="stsrc-renewal-notice__dismiss" aria-label="Dismiss">&times;</button>')
				.slideDown(200);
			$errorBanner.find('.stsrc-renewal-notice__dismiss').on('click', function() {
				$errorBanner.slideUp(200);
			});
		}

		function clearRenewalError() {
			$errorBanner.slideUp(150);
		}

		$continueBtn.on('click', function() {
			if ($continueBtn.prop('disabled')) {
				return;
			}

			clearRenewalError();

			var membershipTypeId = $form.find('input[name="target_membership_type_id"]:checked').val();
			var paymentMethod = getPaymentMethod();
			var memberId = $form.find('input[name="member_id"]').val();
			var seasonKey = $form.find('input[name="season_key"]').val();
			var nonce = $form.find('input[name="nonce"]').val();
			var submitAction = renewalConfig && renewalConfig.actions ? renewalConfig.actions.submit : 'stsrc_renewal_submit';

			if (!membershipTypeId) {
				return;
			}

			$continueBtn.prop('disabled', true).text('Processing…');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: submitAction,
					nonce: nonce,
					target_membership_type_id: membershipTypeId,
					payment_method: paymentMethod,
					season_key: seasonKey,
					member_id: memberId
				}
			}).done(function(response) {
				if (response && response.success && response.data) {
					if (response.data.redirect_url) {
						window.location.href = response.data.redirect_url;
						return;
					}
					if (response.data.message) {
						$form.html('<div class="stsrc-renewal-success"><p>' + $('<span/>').text(response.data.message).html() + '</p></div>');
						return;
					}
				}
				$continueBtn.prop('disabled', false).text('Continue to Renewal Payment');
			}).fail(function(xhr) {
				var msg = 'Something went wrong. Please try again.';
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				showRenewalError(msg);
				$continueBtn.prop('disabled', false).text('Continue to Renewal Payment');
			});
		});

		$form.on('change', 'input[name="target_membership_type_id"], input[name="payment_method"]', requestQuote);
		requestQuote();
	}

	function initReferralCopyButton() {
		const $button = $('#copy-referral-btn');
		if ($button.length === 0) {
			return;
		}

		$button.on('click', function() {
			const $btn = $(this);
			const referralUrl = String($btn.data('url') || '');
			const defaultText = String($btn.data('default-text') || 'Copy Referral Link');
			if (!referralUrl) {
				return;
			}

			const setCopiedState = function() {
				$btn.text('Copied!').addClass('is-copied');
				window.setTimeout(function() {
					$btn.text(defaultText).removeClass('is-copied');
				}, 2000);
			};

			if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
				navigator.clipboard.writeText(referralUrl).then(setCopiedState).catch(function() {
					copyWithFallback(referralUrl, setCopiedState);
				});
				return;
			}

			copyWithFallback(referralUrl, setCopiedState);
		});
	}

	function copyWithFallback(text, onSuccess) {
		const input = document.createElement('input');
		input.value = text;
		input.setAttribute('readonly', 'readonly');
		input.style.position = 'absolute';
		input.style.left = '-9999px';
		document.body.appendChild(input);
		input.select();
		input.setSelectionRange(0, input.value.length);

		const successful = document.execCommand('copy');
		document.body.removeChild(input);
		if (successful && typeof onSuccess === 'function') {
			onSuccess();
		}
	}
})(jQuery);
