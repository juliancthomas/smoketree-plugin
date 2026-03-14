/**
 * Frontend JavaScript for Smoketree Plugin
 *
 * Handles AJAX form submissions, loading states, error handling, and UI interactions.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/js
 */

(function($) {
	'use strict';

	/**
	 * Main frontend object
	 */
	const STSRCPublic = {
		ajaxUrl: stsrcPublic.ajaxUrl || ajaxurl,
		nonce: stsrcPublic.nonce || '',
		portalNonce: stsrcPublic.portalNonce || '',
		strings: stsrcPublic.strings || {},

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initRegistrationForm();
			this.initMemberPortal();
			this.initModals();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Generic form submissions
			$(document).on('submit', '.stsrc-ajax-form', this.handleFormSubmit);

			// Modal handlers
			$(document).on('click', '.stsrc-modal-trigger', this.openModal);
			$(document).on('click', '.stsrc-modal-close, .stsrc-modal-overlay', this.closeModal);

			// Password confirmation validation
			$(document).on('input', 'input[name="password_confirm"], input[name="confirm_password"]', this.validatePasswordMatch);
		},

		/**
		 * Initialize registration form
		 *
		 * Registration form event handlers (membership change, add/remove members,
		 * form submission, order summary) are defined in the inline script within
		 * the registration-form.php partial, which has access to server-side
		 * PHP values (tax rate, fees, CAPTCHA config, etc.).
		 */
		initRegistrationForm: function() {
		},

		/**
		 * Submit registration form
		 */
		submitRegistrationForm: function($form, $submitBtn, $messages) {
			$submitBtn.prop('disabled', true).text('Submitting...');
			$messages.html('');

			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: (response) => {
					if (response.success) {
						if (response.data.checkout_url) {
							// Redirect to Stripe checkout
							window.location.href = response.data.checkout_url;
						} else {
							// Manual payment - show success message
							this.showNotice(response.data.message, 'success', $messages);
							$form[0].reset();
						}
					} else {
						this.showNotice(response.data.message, 'error', $messages);
						$submitBtn.prop('disabled', false).text('Submit Registration');
					}
				},
				error: () => {
					this.showNotice('An error occurred. Please try again.', 'error', $messages);
					$submitBtn.prop('disabled', false).text('Submit Registration');
				}
			});
		},

		/**
		 * Initialize member portal
		 */
		initMemberPortal: function() {
			if ($('.stsrc-member-portal').length === 0) {
				return;
			}

			this.initProfileEdit();
			this.initPasswordChange();
			this.initFamilyMembers();
			this.initExtraMembers();
			this.initRestoreMembers();
			this.initGuestPasses();
			this.initStripePortal();
			this.initAutoRenewal();
		},

		/**
		 * Initialize profile edit
		 */
		initProfileEdit: function() {
			$('#stsrc-edit-profile-btn').on('click', () => {
				$('#stsrc-edit-profile-modal').addClass('active');
			});

			$('#stsrc-edit-profile-form').on('submit', (e) => {
				e.preventDefault();
				this.submitAjaxForm($('#stsrc-edit-profile-form'), 'stsrc_update_profile', (response) => {
					if (response.success) {
						this.showNotice(response.data.message, 'success', $('#stsrc-portal-messages'));
						setTimeout(() => location.reload(), 1000);
					}
				});
			});
		},

		/**
		 * Initialize password change
		 */
		initPasswordChange: function() {
			$('#stsrc-change-password-btn').on('click', () => {
				$('#stsrc-change-password-modal').addClass('active');
			});

			$('#stsrc-change-password-form').on('submit', (e) => {
				e.preventDefault();

				const $form = $('#stsrc-change-password-form');
				const $newPassword = $('#new_password');
				const $confirmPassword = $('#confirm_password');

				if ($newPassword.val() !== $confirmPassword.val()) {
					this.showNotice('New passwords do not match.', 'error', $('#stsrc-portal-messages'));
					return;
				}

				this.submitAjaxForm($form, 'stsrc_change_password', (response) => {
					if (response.success) {
						this.showNotice(response.data.message, 'success', $('#stsrc-portal-messages'));
						$form[0].reset();
						setTimeout(() => $('#stsrc-change-password-modal').removeClass('active'), 1500);
					}
				});
			});
		},

		/**
		 * Initialize family members
		 */
		initFamilyMembers: function() {
			// Add family member
			$('#stsrc-add-family-member-btn').on('click', () => {
				$('#stsrc-family-member-modal-title').text('Add Family Member');
				$('#stsrc-family-member-action').val('stsrc_add_family_member');
				$('#stsrc-family-member-id').val('');
				$('#stsrc-family-member-form')[0].reset();
				$('#stsrc-family-member-modal').addClass('active');
			});

			// Edit family member
			$(document).on('click', '.stsrc-edit-family-member', function() {
				const $item = $(this).closest('.stsrc-family-member-item');
				const id = $(this).data('id');
				const firstName = $item.find('strong').text().split(' ')[0];
				const lastName = $item.find('strong').text().split(' ').slice(1).join(' ');
				const email = $item.find('.stsrc-member-email').text() || '';

				$('#stsrc-family-member-modal-title').text('Edit Family Member');
				$('#stsrc-family-member-action').val('stsrc_update_family_member');
				$('#stsrc-family-member-id').val(id);
				$('#family_first_name').val(firstName);
				$('#family_last_name').val(lastName);
				$('#family_email').val(email);
				$('#stsrc-family-member-modal').addClass('active');
			});

			// Delete family member
			$(document).on('click', '.stsrc-delete-family-member', function() {
				if (!confirm('Are you sure you want to delete this family member?')) {
					return;
				}

				const id = $(this).data('id');
				STSRCPublic.submitAjaxForm(
					$('<form>').append($('<input>').attr({type: 'hidden', name: 'action', value: 'stsrc_delete_family_member'}))
						.append($('<input>').attr({type: 'hidden', name: 'nonce', value: STSRCPublic.nonce}))
						.append($('<input>').attr({type: 'hidden', name: 'family_member_id', value: id})),
					'stsrc_delete_family_member',
					(response) => {
						if (response.success) {
							STSRCPublic.showNotice(response.data.message, 'success', $('#stsrc-portal-messages'));
							setTimeout(() => location.reload(), 1000);
						}
					}
				);
			});

			// Submit family member form
			$('#stsrc-family-member-form').on('submit', (e) => {
				e.preventDefault();
				const action = $('#stsrc-family-member-action').val();
				this.submitAjaxForm($('#stsrc-family-member-form'), action, (response) => {
					if (response.success) {
						this.showNotice(response.data.message, 'success', $('#stsrc-portal-messages'));
						setTimeout(() => location.reload(), 1000);
					}
				});
			});
		},

		/**
		 * Initialize extra members
		 */
		initExtraMembers: function() {
			const PRICE_PER_MEMBER = 50;
			const FEE_RATES = {
				card:            { percent: 0.029, flat: 0.30, cap: null },
				us_bank_account: { percent: 0.008, flat: 0,    cap: 5.00 }
			};
			const $addModal = $('#stsrc-add-extra-member-modal');
			const $slotsContainer = $('#stsrc-extra-member-slots');
			const $addAnotherBtn = $('#stsrc-add-another-member-btn');
			const slotsAvailable = parseInt($('#stsrc-extra-member-slots-available').val()) || 0;
			let slotCount = 1;

			function calculateFee(amount, method) {
				const rate = FEE_RATES[method];
				if (!rate || amount <= 0) return 0;
				let fee = amount * rate.percent + rate.flat;
				if (rate.cap !== null && fee > rate.cap) fee = rate.cap;
				return Math.round(fee * 100) / 100;
			}

			function formatCurrency(val) {
				return '$' + Number(val).toFixed(2);
			}

			function getSelectedMethod() {
				return $addModal.find('input[name="payment_method"]:checked').val() || 'card';
			}

			function updateSummary() {
				const count = $slotsContainer.children('.stsrc-extra-member-slot').length;
				const subtotal = count * PRICE_PER_MEMBER;
				const method = getSelectedMethod();
				const fee = calculateFee(subtotal, method);
				const total = subtotal + fee;

				const label = count === 1
					? 'Extra Member (1)'
					: 'Extra Members (' + count + ')';

				$('#stsrc-em-summary-label').text(label);
				$('#stsrc-em-summary-subtotal').text(formatCurrency(subtotal));
				$('#stsrc-em-summary-fee').text(formatCurrency(fee));
				$('#stsrc-em-summary-total').text(formatCurrency(total));
			}

			function addSlot() {
				if (slotCount >= slotsAvailable) return;
				const index = slotCount;
				const $slot = $(
					'<div class="stsrc-extra-member-slot" data-index="' + index + '">' +
						'<div class="stsrc-extra-member-slot__header">' +
							'<strong>Extra Member ' + (index + 1) + '</strong>' +
							'<button type="button" class="stsrc-extra-member-slot__remove">&times;</button>' +
						'</div>' +
						'<div class="stsrc-form-row">' +
							'<div class="stsrc-form-group">' +
								'<label>First Name</label>' +
								'<input type="text" name="members[' + index + '][first_name]" required>' +
							'</div>' +
							'<div class="stsrc-form-group">' +
								'<label>Last Name</label>' +
								'<input type="text" name="members[' + index + '][last_name]" required>' +
							'</div>' +
						'</div>' +
						'<div class="stsrc-form-group">' +
							'<label>Email (optional)</label>' +
							'<input type="email" name="members[' + index + '][email]">' +
						'</div>' +
					'</div>'
				);
				$slotsContainer.append($slot);
				slotCount++;

				if (slotCount >= slotsAvailable) {
					$addAnotherBtn.addClass('stsrc-hidden');
				}
				updateSummary();
			}

			// Open Add modal
			$('#stsrc-add-extra-member-btn').on('click', () => {
				$addModal.addClass('active');
				updateSummary();
			});

			// Add another member slot
			$addAnotherBtn.on('click', addSlot);

			// Remove a member slot
			$slotsContainer.on('click', '.stsrc-extra-member-slot__remove', function() {
				$(this).closest('.stsrc-extra-member-slot').remove();
				slotCount = $slotsContainer.children('.stsrc-extra-member-slot').length;
				if (slotCount < slotsAvailable) {
					$addAnotherBtn.removeClass('stsrc-hidden');
				}
				updateSummary();
			});

			// Payment method change
			$addModal.find('input[name="payment_method"]').on('change', function() {
				$addModal.find('.stsrc-pay-balance-method').removeClass('stsrc-pay-balance-method--selected');
				$(this).closest('.stsrc-pay-balance-method').addClass('stsrc-pay-balance-method--selected');
				updateSummary();
			});

			// Submit add form
			$('#stsrc-add-extra-members-form').on('submit', (e) => {
				e.preventDefault();
				const $form = $('#stsrc-add-extra-members-form');
				const $submitBtn = $('#stsrc-extra-member-submit');
				const $error = $('#stsrc-extra-member-error');
				const originalText = $submitBtn.text();

				$submitBtn.prop('disabled', true).text('Redirecting...');
				$error.addClass('stsrc-hidden').text('');

				$.ajax({
					url: this.ajaxUrl,
					type: 'POST',
					data: $form.serialize(),
					success: (response) => {
						if (response.success && response.data.checkout_url) {
							window.location.href = response.data.checkout_url;
						} else if (response.success) {
							this.showNotice(response.data.message, 'success', $('#stsrc-portal-messages'));
							setTimeout(() => location.reload(), 1000);
						} else {
							$error.removeClass('stsrc-hidden').text(response.data.message || 'An error occurred.');
							$submitBtn.prop('disabled', false).text(originalText);
						}
					},
					error: () => {
						$error.removeClass('stsrc-hidden').text('A network error occurred. Please try again.');
						$submitBtn.prop('disabled', false).text(originalText);
					}
				});
			});

			// Edit extra member (opens separate modal)
			$(document).on('click', '.stsrc-edit-extra-member', function() {
				const $item = $(this).closest('.stsrc-extra-member-item');
				const id = $(this).data('id');
				const nameParts = $item.find('strong').text().split(' ');
				const firstName = nameParts[0];
				const lastName = nameParts.slice(1).join(' ');
				const email = $item.find('.stsrc-member-email').text() || '';

				$('#stsrc-extra-member-id').val(id);
				$('#extra_first_name').val(firstName);
				$('#extra_last_name').val(lastName);
				$('#extra_email').val(email);
				$('#stsrc-extra-member-modal').addClass('active');
			});

			// Delete extra member
			$(document).on('click', '.stsrc-delete-extra-member', function() {
				if (!confirm('Are you sure you want to delete this extra member?')) {
					return;
				}

				const id = $(this).data('id');
				STSRCPublic.submitAjaxForm(
					$('<form>').append($('<input>').attr({type: 'hidden', name: 'action', value: 'stsrc_delete_extra_member'}))
						.append($('<input>').attr({type: 'hidden', name: 'nonce', value: STSRCPublic.nonce}))
						.append($('<input>').attr({type: 'hidden', name: 'extra_member_id', value: id})),
					'stsrc_delete_extra_member',
					(response) => {
						if (response.success) {
							STSRCPublic.showNotice(response.data.message, 'success', $('#stsrc-portal-messages'));
							setTimeout(() => location.reload(), 1000);
						}
					}
				);
			});

			// Submit edit form (unchanged behavior)
			$('#stsrc-extra-member-form').on('submit', (e) => {
				e.preventDefault();
				this.submitAjaxForm($('#stsrc-extra-member-form'), 'stsrc_update_extra_member', (response) => {
					if (response.success) {
						this.showNotice(response.data.message, 'success', $('#stsrc-portal-messages'));
						setTimeout(() => location.reload(), 1000);
					}
				});
			});
		},

		/**
		 * Initialize restore actions for deleted portal members.
		 */
		initRestoreMembers: function() {
			$(document).on('click', '.stsrc-restore-family-member', function() {
				const familyMemberId = parseInt($(this).data('id'), 10) || 0;
				if (!familyMemberId) {
					return;
				}

				if (!window.confirm('Restore this family member?')) {
					return;
				}

				$.ajax({
					url: STSRCPublic.ajaxUrl,
					type: 'POST',
					data: {
						action: 'stsrc_restore_family_member',
						nonce: STSRCPublic.portalNonce || STSRCPublic.nonce,
						family_member_id: familyMemberId
					},
					success: function(response) {
						if (response.success) {
							STSRCPublic.showNotice(response.data.message || 'Family member restored.', 'success', $('#stsrc-portal-messages'));
							setTimeout(() => location.reload(), 800);
						} else {
							STSRCPublic.showNotice((response.data && response.data.message) || 'Failed to restore family member.', 'error', $('#stsrc-portal-messages'));
						}
					},
					error: function() {
						STSRCPublic.showNotice('An error occurred. Please try again.', 'error', $('#stsrc-portal-messages'));
					}
				});
			});

			$(document).on('click', '.stsrc-restore-extra-member', function() {
				const extraMemberId = parseInt($(this).data('id'), 10) || 0;
				if (!extraMemberId) {
					return;
				}

				if (!window.confirm('Restore this extra member?')) {
					return;
				}

				$.ajax({
					url: STSRCPublic.ajaxUrl,
					type: 'POST',
					data: {
						action: 'stsrc_restore_extra_member',
						nonce: STSRCPublic.portalNonce || STSRCPublic.nonce,
						extra_member_id: extraMemberId
					},
					success: function(response) {
						if (response.success) {
							STSRCPublic.showNotice(response.data.message || 'Extra member restored.', 'success', $('#stsrc-portal-messages'));
							setTimeout(() => location.reload(), 800);
						} else {
							STSRCPublic.showNotice((response.data && response.data.message) || 'Failed to restore extra member.', 'error', $('#stsrc-portal-messages'));
						}
					},
					error: function() {
						STSRCPublic.showNotice('An error occurred. Please try again.', 'error', $('#stsrc-portal-messages'));
					}
				});
			});
		},

		/**
		 * Initialize guest passes
		 */
		initGuestPasses: function() {
			// Purchase guest passes button
			$('#stsrc-purchase-guest-passes-btn').on('click', () => {
				$('#stsrc-purchase-guest-passes-modal').addClass('active');
			});

			// Calculate total when quantity changes
			$('#guest_pass_quantity').on('change', function() {
				const quantity = parseInt($(this).val()) || 1;
				const total = (quantity * 5).toFixed(2);
				$('#stsrc-guest-pass-total').text(total);
			});

			// Submit purchase form
			$('#stsrc-purchase-guest-passes-form').on('submit', (e) => {
				e.preventDefault();
				this.submitAjaxForm($('#stsrc-purchase-guest-passes-form'), 'stsrc_purchase_guest_passes', (response) => {
					if (response.success) {
						if (response.data.checkout_url) {
							// Redirect to Stripe checkout
							window.location.href = response.data.checkout_url;
						} else {
							this.showNotice(response.data.message, 'success', $('#stsrc-portal-messages'));
							setTimeout(() => location.reload(), 1000);
						}
					}
				});
			});
		},

		/**
		 * Initialize Stripe portal
		 */
		initStripePortal: function() {
			$('#stsrc-stripe-portal-btn').on('click', () => {
				const $button = $('#stsrc-stripe-portal-btn');
				$button.prop('disabled', true).text('Loading...');

				$.ajax({
					url: this.ajaxUrl,
					type: 'POST',
					data: {
						action: 'stsrc_get_customer_portal_url',
						nonce: this.portalNonce || this.nonce
					},
					success: (response) => {
						if (response.success && response.data.portal_url) {
							window.location.href = response.data.portal_url;
						} else {
							this.showNotice(response.data.message || 'Failed to load payment portal.', 'error', $('#stsrc-portal-messages'));
							$button.prop('disabled', false).text('Manage Payment Methods');
						}
					},
					error: () => {
						this.showNotice('An error occurred. Please try again.', 'error', $('#stsrc-portal-messages'));
						$button.prop('disabled', false).text('Manage Payment Methods');
					}
				});
			});
		},

		/**
		 * Initialize auto-renewal toggle
		 */
		initAutoRenewal: function() {
			const $form = $('#stsrc-auto-renewal-form');
			const $toggle = $('#stsrc-auto-renewal-toggle');

			if ($form.length === 0 || $toggle.length === 0) {
				return;
			}

			const $status = $('#stsrc-auto-renewal-status');
			const enabledText = $status.data('enabledText') || this.strings.autoRenewalEnabled || 'Enabled';
			const disabledText = $status.data('disabledText') || this.strings.autoRenewalDisabled || 'Disabled';
			const updatingText = this.strings.autoRenewalUpdating || this.strings.saving || 'Saving...';
			const errorMessage = this.strings.autoRenewalError || this.strings.error || 'Unable to update auto-renewal.';

			$toggle.on('change', () => {
				if ($toggle.prop('disabled')) {
					return;
				}

				const previousEnabled = $form.find('input[name="enabled"]').val() === '1';
				const newEnabled = $toggle.is(':checked');

				if (previousEnabled === newEnabled) {
					return;
				}

				$form.find('input[name="enabled"]').val(newEnabled ? '1' : '0');
				$status.text(updatingText);
				$toggle.prop('disabled', true);

				$.ajax({
					url: this.ajaxUrl,
					type: 'POST',
					data: $form.serialize(),
					success: (response) => {
						if (response.success) {
							const enabled = !!response.data.enabled;
							$form.find('input[name="enabled"]').val(enabled ? '1' : '0');
							$toggle.prop('checked', enabled);
							$status.text(enabled ? enabledText : disabledText);
							this.showNotice(response.data.message || (enabled ? enabledText : disabledText), 'success', $('#stsrc-portal-messages'));
						} else {
							$form.find('input[name="enabled"]').val(previousEnabled ? '1' : '0');
							$toggle.prop('checked', previousEnabled);
							$status.text(previousEnabled ? enabledText : disabledText);
							this.showNotice(response.data.message || errorMessage, 'error', $('#stsrc-portal-messages'));
						}
					},
					error: () => {
						$form.find('input[name="enabled"]').val(previousEnabled ? '1' : '0');
						$toggle.prop('checked', previousEnabled);
						$status.text(previousEnabled ? enabledText : disabledText);
						this.showNotice(errorMessage, 'error', $('#stsrc-portal-messages'));
					},
					complete: () => {
						$toggle.prop('disabled', false);
					}
				});
			});
		},

		/**
		 * Initialize modals
		 */
		initModals: function() {
			// Close modal on overlay click
			$(document).on('click', '.stsrc-modal-overlay', function(e) {
				if ($(e.target).hasClass('stsrc-modal-overlay')) {
					$('.stsrc-modal-overlay').removeClass('active');
				}
			});

			// Close modal on close button click
			$(document).on('click', '.stsrc-modal-close', function() {
				$('.stsrc-modal-overlay').removeClass('active');
			});

			// Close modal on ESC key
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					$('.stsrc-modal-overlay').removeClass('active');
				}
			});
		},

		/**
		 * Open modal
		 */
		openModal: function(e) {
			e.preventDefault();
			const target = $(this).data('target');
			$(target).addClass('active');
		},

		/**
		 * Close modal
		 */
		closeModal: function(e) {
			if ($(e.target).hasClass('stsrc-modal-overlay') || $(e.target).hasClass('stsrc-modal-close')) {
				$('.stsrc-modal-overlay').removeClass('active');
			}
		},

		/**
		 * Submit AJAX form
		 */
		submitAjaxForm: function($form, action, successCallback) {
			const $submitBtn = $form.find('button[type="submit"]');
			const originalText = $submitBtn.text();
			const $messages = $('#stsrc-portal-messages, #stsrc-form-messages');

			// Update action if needed
			if (action) {
				$form.find('input[name="action"]').val(action);
			}

			$submitBtn.prop('disabled', true).text('Saving...');
			$form.addClass('stsrc-loading');

			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: (response) => {
					if (response.success) {
						if (successCallback) {
							successCallback(response);
						} else {
							this.showNotice(response.data.message || 'Operation completed successfully.', 'success', $messages);
						}
					} else {
						this.showNotice(response.data.message || 'An error occurred.', 'error', $messages);
						$submitBtn.prop('disabled', false).text(originalText);
					}
				},
				error: () => {
					this.showNotice('An error occurred. Please try again.', 'error', $messages);
					$submitBtn.prop('disabled', false).text(originalText);
				},
				complete: () => {
					$form.removeClass('stsrc-loading');
				}
			});
		},

		/**
		 * Handle generic form submission
		 */
		handleFormSubmit: function(e) {
			e.preventDefault();
			const $form = $(this);
			const action = $form.data('action') || $form.find('input[name="action"]').val();
			STSRCPublic.submitAjaxForm($form, action);
		},

		/**
		 * Validate password match
		 */
		validatePasswordMatch: function() {
			const $confirm = $(this);
			const $password = $confirm.closest('form').find('input[name="password"], input[name="new_password"]');
			
			if ($password.length && $confirm.val() && $password.val() !== $confirm.val()) {
				$confirm[0].setCustomValidity('Passwords do not match.');
			} else {
				$confirm[0].setCustomValidity('');
			}
		},

		/**
		 * Show notice
		 */
		showNotice: function(message, type, $container) {
			type = type || 'info';
			$container = $container || $('#stsrc-portal-messages, #stsrc-form-messages');
			
			const $notice = $('<div class="stsrc-notice ' + type + '"><p>' + message + '</p></div>');
			$container.html($notice);

			// Auto-hide after 5 seconds
			setTimeout(() => {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		STSRCPublic.init();
	});

})(jQuery);
