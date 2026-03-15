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
		var $form = $('#stsrc-renewal-form');
		var $wizard = $('#stsrc-renewal-wizard');
		if ($form.length === 0 || $wizard.length === 0) {
			return;
		}

		var STEP_PLAN = 0;
		var STEP_MEMBERS = 1;
		var STEP_PAYMENT = 2;
		var STEP_REVIEW = 3;

		var renewalConfig = (window.stsrcPublic && window.stsrcPublic.renewal) ? window.stsrcPublic.renewal : null;
		var ajaxUrl = (window.stsrcPublic && window.stsrcPublic.ajaxUrl) ? window.stsrcPublic.ajaxUrl : '';
		var quoteAction = renewalConfig && renewalConfig.actions ? renewalConfig.actions.quote : 'stsrc_renewal_quote';

		var $membershipRows = $('.stsrc-renewal-card');
		var $continueBtn = $('#stsrc-renewal-continue-btn');
		var $membershipAmount = $('#stsrc-renewal-membership-amount');
		var $extrasAmount = $('#stsrc-renewal-extras-amount');
		var $extrasRow = $('#stsrc-renewal-extras-row');
		var $feeAmount = $('#stsrc-renewal-fee-amount');
		var $totalAmount = $('#stsrc-renewal-total-amount');
		var $balanceAmount = $('#stsrc-renewal-balance-amount');

		var $familyGroup = $('#stsrc-renewal-family-group');
		var $extrasGroup = $('#stsrc-renewal-extras-group');
		var $newExtraCountEl = $('#stsrc-new-extra-count');
		var $extraMinus = $('#stsrc-extra-minus');
		var $extraPlus = $('#stsrc-extra-plus');
		var $familyHint = $('#stsrc-family-hint');
		var extraPrice = parseFloat($wizard.data('extra-price') || 50);
		var maxExtras = parseInt($wizard.data('max-extras') || 3, 10);
		var balanceOwed = parseFloat($wizard.data('balance') || 0);
		var newExtraCount = 0;

		var skipMembersStep = false;

		// --- SmartWizard initialization ---
		$wizard.smartWizard({
			selected: STEP_PLAN,
			theme: 'arrows',
			autoAdjustHeight: true,
			transition: {
				animation: 'slideHorizontal',
				speed: '400',
				easing: 'ease'
			},
			toolbar: {
				position: 'bottom',
				showNextButton: true,
				showPreviousButton: true
			},
			anchor: {
				enableNavigation: true,
				enableNavigationAlways: false,
				enableDoneStep: true,
				markPreviousStepsAsDone: true,
				removeDoneStepOnNavigateBack: true,
				enableDoneStepNavigation: true
			},
			keyboard: {
				keyNavigation: false
			},
			lang: {
				next: 'Next Step',
				previous: 'Back'
			}
		});

		// --- Step skip logic ---
		function needsMembersStep() {
			var typeName = getSelectedTypeName();
			return typeName === 'household' || typeName === 'duo';
		}

		$wizard.on('leaveStep', function(e, anchorObject, currentStepIdx, nextStepIdx, stepDirection) {
			if (stepDirection === 'forward' && !validateStep(currentStepIdx)) {
				return false;
			}

			if (nextStepIdx === STEP_MEMBERS && !needsMembersStep()) {
				var skipTo = (stepDirection === 'forward') ? STEP_PAYMENT : STEP_PLAN;
				setTimeout(function() { $wizard.smartWizard('goToStep', skipTo); }, 10);
				return false;
			}

			return true;
		});

		$wizard.on('showStep', function(e, anchorObject, stepIdx, stepDirection) {
			if (stepIdx === STEP_MEMBERS) {
				updateMemberSections();
			}

			if (stepIdx === STEP_PAYMENT) {
				updatePaymentInstructions();
				updateAutoRenewalVisibility();
			}

			if (stepIdx === STEP_REVIEW) {
				populateReviewStep();
				requestQuote();
			}

			updateToolbarVisibility(stepIdx);
		});

		function updateToolbarVisibility(stepIdx) {
			var $toolbar = $wizard.find('.toolbar-bottom');
			if (stepIdx === STEP_REVIEW) {
				$toolbar.find('.sw-btn-next').hide();
			} else {
				$toolbar.find('.sw-btn-next').show();
			}
		}

		// --- Step validation ---
		function validateStep(stepIdx) {
			if (stepIdx === STEP_PLAN) {
				return !!$form.find('input[name="target_membership_type_id"]:checked').val();
			}
			if (stepIdx === STEP_MEMBERS) {
				return validateMembersStep();
			}
			if (stepIdx === STEP_PAYMENT) {
				return !!$form.find('input[name="payment_method"]:checked').val();
			}
			return true;
		}

		function validateMembersStep() {
			var typeName = getSelectedTypeName();
			var required = typeName === 'household' ? 2 : (typeName === 'duo' ? 1 : 0);
			if (required === 0) {
				return true;
			}
			var retained = getRetainedFamilyIds().length;
			if (retained < required) {
				updateFamilyHint();
				return false;
			}
			return true;
		}

		// --- Helpers ---
		function getSelectedTypeName() {
			return ($form.find('input[name="target_membership_type_id"]:checked').data('type-name') || '').toLowerCase();
		}

		function getSelectedTypeLabel() {
			return $form.find('input[name="target_membership_type_id"]:checked').data('type-label') || '';
		}

		function getSelectedTypePrice() {
			return parseFloat($form.find('input[name="target_membership_type_id"]:checked').data('type-price') || 0);
		}

		function getRetainedFamilyIds() {
			var ids = [];
			$form.find('input[name="retain_family_member_ids[]"]:checked').each(function() {
				ids.push(parseInt($(this).val(), 10));
			});
			return ids;
		}

		function getRetainedExtraIds() {
			var ids = [];
			$form.find('input[name="retain_extra_member_ids[]"]:checked').each(function() {
				ids.push(parseInt($(this).val(), 10));
			});
			return ids;
		}

		function getMemberPayload() {
			var typeName = getSelectedTypeName();
			var payload = {};

			if (typeName === 'household' || typeName === 'duo') {
				payload.retain_family_member_ids = getRetainedFamilyIds();
				payload.new_family_member_count = 0;
			}

			if (typeName === 'household') {
				payload.retain_extra_member_ids = getRetainedExtraIds();
				payload.new_extra_member_count = newExtraCount;
			}

			return payload;
		}

		function getExtrasMemberAmount() {
			var typeName = getSelectedTypeName();
			if (typeName !== 'household') {
				return 0;
			}
			var retainedCount = getRetainedExtraIds().length;
			return (retainedCount + newExtraCount) * extraPrice;
		}

		function updateMemberSections() {
			var typeName = getSelectedTypeName();
			var hasFamily = $familyGroup.length > 0;
			var showFamily = (typeName === 'household' || typeName === 'duo') && hasFamily;
			var showExtras = typeName === 'household';

			if (showFamily) {
				$familyGroup.show();
			} else {
				$familyGroup.hide();
			}

			if (showExtras) {
				$extrasGroup.show();
			} else {
				$extrasGroup.hide();
				newExtraCount = 0;
				$newExtraCountEl.text('0');
			}

			updateFamilyHint();
			updateStepperLimits();
		}

		function updateFamilyHint() {
			var typeName = getSelectedTypeName();
			var required = typeName === 'household' ? 2 : (typeName === 'duo' ? 1 : 0);
			if (required === 0 || !$familyHint.length) {
				$familyHint.hide();
				return;
			}
			var retained = getRetainedFamilyIds().length;
			if (retained < required) {
				$familyHint.text('At least ' + required + ' family member' + (required > 1 ? 's' : '') + ' required for this plan.').show();
			} else {
				$familyHint.hide();
			}
		}

		function updateStepperLimits() {
			var retainedCount = getRetainedExtraIds().length;
			var totalExtras = retainedCount + newExtraCount;
			$extraMinus.prop('disabled', newExtraCount <= 0);
			$extraPlus.prop('disabled', totalExtras >= maxExtras);
		}

		function formatCurrency(value) {
			var parsed = Number(value || 0);
			return '$' + parsed.toFixed(2);
		}

		function getPaymentMethod() {
			return $form.find('input[name="payment_method"]:checked').val() || 'card';
		}

		var paymentMethodLabels = {
			card: 'Credit/Debit Card',
			ach: 'Bank Account (ACH)',
			zelle: 'Zelle',
			check: 'Check',
			cash: 'Cash',
			payment_plan: 'Payment Plan'
		};

		function getPaymentMethodLabel() {
			return paymentMethodLabels[getPaymentMethod()] || getPaymentMethod();
		}

		// --- Quote calculation ---
		function calcFallbackQuote() {
			var membership = getSelectedTypePrice();
			var extras = getExtrasMemberAmount();
			var subtotal = Math.max(0, membership + extras + balanceOwed);
			var paymentMethod = getPaymentMethod();
			var fee = 0;

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

			var extrasAmt = quote.extra_members_amount || 0;
			$extrasAmount.text(formatCurrency(extrasAmt));
			if (extrasAmt > 0) {
				$extrasRow.show();
			} else {
				$extrasRow.hide();
			}

			$feeAmount.text(formatCurrency(quote.processing_fee || 0));
			$totalAmount.text(formatCurrency(quote.total || 0));
		}

		function requestQuote() {
			var membershipTypeId = $form.find('input[name="target_membership_type_id"]:checked').val();
			var paymentMethod = getPaymentMethod();
			if (!membershipTypeId) {
				return;
			}

			var fallbackQuote = calcFallbackQuote();
			if (!ajaxUrl || !renewalConfig || !renewalConfig.nonce) {
				renderQuote(fallbackQuote);
				return;
			}

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: $.extend({
					action: quoteAction,
					nonce: renewalConfig.nonce,
					target_membership_type_id: membershipTypeId,
					payment_method: paymentMethod,
					season_key: renewalConfig.seasonKey || '',
					member_id: renewalConfig.member ? renewalConfig.member.member_id : 0
				}, getMemberPayload())
			}).done(function(response) {
				var serverQuote = response && response.success && response.data ? response.data.quote : null;
				renderQuote(serverQuote || fallbackQuote);
			}).fail(function() {
				renderQuote(fallbackQuote);
			});
		}

		// --- Review step population ---
		function populateReviewStep() {
			var typeName = getSelectedTypeName();
			$('#stsrc-review-plan').text(getSelectedTypeLabel() + ' — ' + formatCurrency(getSelectedTypePrice()));

			$('#stsrc-review-payment').text(getPaymentMethodLabel());

			var $membersRow = $('#stsrc-review-members-row');
			if (typeName === 'household' || typeName === 'duo') {
				var parts = [];
				var familyCount = getRetainedFamilyIds().length;
				if (familyCount > 0) {
					parts.push(familyCount + ' family member' + (familyCount !== 1 ? 's' : ''));
				}
				if (typeName === 'household') {
					var extraCount = getRetainedExtraIds().length + newExtraCount;
					if (extraCount > 0) {
						parts.push(extraCount + ' extra member' + (extraCount !== 1 ? 's' : ''));
					}
				}
				$('#stsrc-review-members').text(parts.length ? parts.join(', ') : 'None');
				$membersRow.show();
			} else {
				$membersRow.hide();
			}

			var stripePaymentMethods = ['card', 'ach'];
			var $autoRenewalRow = $('#stsrc-review-auto-renewal-row');
			var method = getPaymentMethod();
			if (stripePaymentMethods.indexOf(method) !== -1) {
				var isOptedIn = $('#stsrc-renewal-auto-renewal-optin').is(':checked');
				$('#stsrc-review-auto-renewal').text(isOptedIn ? 'Yes' : 'No');
				$autoRenewalRow.show();
			} else {
				$autoRenewalRow.hide();
			}
		}

		// --- Payment step interactions ---
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
				$instructionsWrap.show();
			} else {
				$instructionsWrap.hide();
			}
		}
		$form.on('change', 'input[name="payment_method"]', updatePaymentInstructions);

		var $autoRenewalSection = $('#stsrc-renewal-auto-renewal');
		var $autoRenewalCheckbox = $('#stsrc-renewal-auto-renewal-optin');
		var stripePaymentMethods = ['card', 'ach'];
		function updateAutoRenewalVisibility() {
			var method = getPaymentMethod();
			if (stripePaymentMethods.indexOf(method) !== -1) {
				$autoRenewalSection.slideDown(200);
			} else {
				$autoRenewalSection.slideUp(200);
				$autoRenewalCheckbox.prop('checked', false);
			}
		}
		$form.on('change', 'input[name="payment_method"]', updateAutoRenewalVisibility);

		// --- Extra member stepper ---
		$extraMinus.on('click', function() {
			if (newExtraCount > 0) {
				newExtraCount--;
				$newExtraCountEl.text(newExtraCount);
				updateStepperLimits();
			}
		});

		$extraPlus.on('click', function() {
			var retainedCount = getRetainedExtraIds().length;
			if (retainedCount + newExtraCount < maxExtras) {
				newExtraCount++;
				$newExtraCountEl.text(newExtraCount);
				updateStepperLimits();
			}
		});

		$form.on('change', 'input[name="retain_family_member_ids[]"]', updateFamilyHint);
		$form.on('change', 'input[name="retain_extra_member_ids[]"]', updateStepperLimits);

		// --- Error handling ---
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

		// --- Submit ---
		$continueBtn.on('click', function() {
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
				data: $.extend({
					action: submitAction,
					nonce: nonce,
					target_membership_type_id: membershipTypeId,
					payment_method: paymentMethod,
					season_key: seasonKey,
					member_id: memberId,
					auto_renewal_optin: $autoRenewalCheckbox.is(':checked') ? '1' : '0'
				}, getMemberPayload())
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

		$form.on('change', 'input[name="target_membership_type_id"]', function() {
			$membershipRows.removeClass('is-current');
			$(this).closest('.stsrc-renewal-card').addClass('is-current');
		});
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
