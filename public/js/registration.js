(function($) {
	'use strict';

	$(function() {
		if (typeof stsrc_registration === 'undefined') {
			return;
		}

		var $promoInput = $('#stsrc_promo_code');
		var $affiliateInput = $('#stsrc_affiliate_code');
		var $promoFeedback = $('#promo-feedback');
		var $affiliateFeedback = $('#affiliate-feedback');
		var $promoGroup = $('#promo-code-group');
		var $affiliateGroup = $('#affiliate-code-group');
		var $referralBanner = $('#stsrc-referral-banner');
		var cookieName = stsrc_registration.ref_cookie_name || 'stsrc_ref_code';
		var appliedState = null;
		var baselineTotal = null;

		function parseMoney(text) {
			var normalized = String(text || '').replace(/[^0-9.\-]/g, '');
			return parseFloat(normalized || '0');
		}

		function setCookie(name, value, maxAge) {
			document.cookie = name + '=' + encodeURIComponent(value) + '; max-age=' + maxAge + '; path=/; SameSite=Lax';
		}

		function getCookie(name) {
			var parts = document.cookie ? document.cookie.split(';') : [];
			for (var i = 0; i < parts.length; i++) {
				var item = parts[i].trim();
				if (item.indexOf(name + '=') === 0) {
					return decodeURIComponent(item.substring(name.length + 1));
				}
			}
			return '';
		}

		function clearCookie(name) {
			document.cookie = name + '=; max-age=0; path=/; SameSite=Lax';
		}

		function updateSummaryDiscount(discountAmount, label) {
			var $row = $('#stsrc-discount-row');
			if (!$row.length) {
				$row = $('<div id="stsrc-discount-row" class="stsrc-pay-balance-summary__row stsrc-pay-balance-summary__row--discount"><span class="stsrc-pay-balance-summary__label"></span><span class="stsrc-pay-balance-summary__value"></span></div>');
				$row.insertBefore('.stsrc-pay-balance-summary__row--total');
			}
			$row.find('.stsrc-pay-balance-summary__label').text(label);
			$row.find('.stsrc-pay-balance-summary__value').text('$' + Number(discountAmount || 0).toFixed(2));

			if (baselineTotal === null) {
				baselineTotal = parseMoney($('#stsrc-total').text());
			}
			var newTotal = Math.max(0, baselineTotal - Number(discountAmount || 0));
			$('#stsrc-total').text('$' + newTotal.toFixed(2));
		}

		function clearSummaryDiscount() {
			$('#stsrc-discount-row').remove();
			baselineTotal = null;
		}

		function setFeedback($el, message, type, includeRemove) {
			$el.removeClass('is-success is-error').addClass(type === 'success' ? 'is-success' : 'is-error');
			var html = message;
			if (includeRemove) {
				html += ' <a href="#" class="stsrc-discount-remove" id="stsrc-remove-discount">Remove</a>';
			}
			$el.html(html);
		}

		function setDisabledStates(activeType) {
			if (activeType === 'promo') {
				$affiliateGroup.addClass('is-disabled');
				$affiliateInput.prop('disabled', true);
			} else if (activeType === 'affiliate') {
				$promoGroup.addClass('is-disabled');
				$promoInput.prop('disabled', true);
			} else {
				$promoGroup.removeClass('is-disabled');
				$affiliateGroup.removeClass('is-disabled');
				$promoInput.prop('disabled', false);
				$affiliateInput.prop('disabled', false);
			}
		}

		function applyDiscount(type, payload) {
			appliedState = {
				type: type,
				code: payload.code || '',
				amount: Number(payload.computed_amount || payload.discount_amount || 0),
				label: payload.label || (type === 'affiliate' ? (stsrc_registration.affiliate_discount_label || 'Referral Discount') : 'Promo Discount'),
				referrer_name: payload.referrer_name || ''
			};

			$('#applied_discount_type').val(type);
			$('#applied_discount_code').val(appliedState.code);
			$('#applied_discount_amount').val(appliedState.amount.toFixed(2));
			$('#applied_discount_computed').val(appliedState.amount.toFixed(2));

			if (type === 'promo') {
				setFeedback($promoFeedback, '✓ ' + appliedState.label, 'success', true);
				$promoGroup.addClass('is-applied');
			} else {
				setFeedback($affiliateFeedback, '✓ ' + appliedState.label, 'success', true);
				$affiliateGroup.addClass('is-applied');
				if (appliedState.referrer_name) {
					$referralBanner.text('Referral discount from ' + appliedState.referrer_name + ' will be applied at checkout!').show();
				}
			}

			setDisabledStates(type);
			updateSummaryDiscount(appliedState.amount, appliedState.label);
		}

		function removeDiscount(clearCookieToo) {
			appliedState = null;
			$promoFeedback.removeClass('is-success is-error').empty();
			$affiliateFeedback.removeClass('is-success is-error').empty();
			$promoGroup.removeClass('is-applied');
			$affiliateGroup.removeClass('is-applied');
			setDisabledStates('');
			clearSummaryDiscount();
			$referralBanner.hide().empty();
			$('#applied_discount_type, #applied_discount_code, #applied_discount_amount, #applied_discount_computed').val('');
			if (clearCookieToo) {
				clearCookie(cookieName);
			}
			$('#membership_type_id').trigger('change');
		}

		function postValidation(action, data, onSuccess, onError) {
			$.post(stsrc_registration.ajax_url, $.extend({
				action: action,
				nonce: stsrc_registration.nonce
			}, data)).done(function(response) {
				if (!response || !response.success) {
					var msg = response && response.data && response.data.message ? response.data.message : 'Unable to validate code.';
					onError(msg);
					return;
				}
				onSuccess(response.data || {});
			}).fail(function() {
				onError('Unable to validate code.');
			});
		}

		function validatePromo(silent) {
			var code = $.trim($promoInput.val());
			var membershipTypeId = Number($('#membership_type_id').val() || 0);
			if (!code || membershipTypeId <= 0) {
				if (!silent) {
					setFeedback($promoFeedback, 'Please enter a promo code and choose a membership type first.', 'error', false);
				}
				return;
			}
			postValidation('stsrc_validate_promo_code', {
				code: code,
				membership_type_id: membershipTypeId
			}, function(data) {
				data.code = code;
				applyDiscount('promo', data);
			}, function(message) {
				if (!silent) {
					setFeedback($promoFeedback, message, 'error', false);
				}
			});
		}

		function validateAffiliate(silent) {
			var code = $.trim($affiliateInput.val());
			if (!code) {
				if (!silent) {
					setFeedback($affiliateFeedback, 'Please enter a referral code.', 'error', false);
				}
				return;
			}
			postValidation('stsrc_validate_affiliate_code', { code: code }, function(data) {
				data.code = code;
				applyDiscount('affiliate', data);
			}, function(message) {
				if (!silent) {
					setFeedback($affiliateFeedback, message, 'error', false);
				}
			});
		}

		$('#apply-promo-btn').on('click', function() {
			validatePromo(false);
		});

		$('#apply-affiliate-btn').on('click', function() {
			validateAffiliate(false);
		});

		$(document).on('click', '#stsrc-remove-discount', function(e) {
			e.preventDefault();
			removeDiscount(true);
		});

		$('#membership_type_id').on('change', function() {
			if (appliedState && appliedState.type === 'promo') {
				validatePromo(true);
			}
		});

		var params = new URLSearchParams(window.location.search);
		var refCode = params.get('ref');
		if (refCode) {
			$affiliateInput.val(refCode);
			setCookie(cookieName, refCode, 172800);
			validateAffiliate(true);
		} else {
			var cookieRef = getCookie(cookieName);
			if (cookieRef) {
				$affiliateInput.val(cookieRef);
				validateAffiliate(true);
			}
		}
	});
})(jQuery);

