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
		 */
		initRegistrationForm: function() {
			if ($('#stsrc-registration-form').length === 0) {
				return;
			}

			const self = this;
			let familyMemberCount = 0;
			let extraMemberCount = 0;
			let familyLimit = 0;

			// Membership type change handler
			$('#membership_type_id').on('change', function() {
				const $option = $(this).find('option:selected');
				const allowsFamily = $option.data('allows-family') === '1';
				const allowsExtra = $option.data('allows-extra') === '1';
				familyLimit = parseInt($option.data('family-limit')) || 0;

				// Show/hide family members section
				if (allowsFamily) {
					$('#stsrc-family-members-section').show();
				} else {
					$('#stsrc-family-members-section').hide();
					$('#stsrc-family-members-container').empty();
					familyMemberCount = 0;
				}

				// Show/hide extra members section
				if (allowsExtra) {
					$('#stsrc-extra-members-section').show();
				} else {
					$('#stsrc-extra-members-section').hide();
					$('#stsrc-extra-members-container').empty();
					extraMemberCount = 0;
				}

				// Show membership description
				$('.stsrc-membership-description').hide();
				$('.stsrc-membership-description[data-membership-id="' + $(this).val() + '"]').show();
			});

			// Add family member
			$('#stsrc-add-family-member').on('click', function() {
				if (familyMemberCount >= familyLimit) {
					alert('Maximum of ' + familyLimit + ' family members allowed for this membership type.');
					return;
				}

				familyMemberCount++;
				const html = `
					<div class="stsrc-family-member-item" data-index="${familyMemberCount}">
						<h3>Family Member ${familyMemberCount}</h3>
						<div class="stsrc-form-row">
							<div class="stsrc-form-group">
								<label>First Name</label>
								<input type="text" name="family_members[${familyMemberCount}][first_name]" required>
							</div>
							<div class="stsrc-form-group">
								<label>Last Name</label>
								<input type="text" name="family_members[${familyMemberCount}][last_name]" required>
							</div>
						</div>
						<div class="stsrc-form-group">
							<label>Email (optional)</label>
							<input type="email" name="family_members[${familyMemberCount}][email]">
						</div>
						<button type="button" class="stsrc-button stsrc-button-danger stsrc-remove-family-member">Remove</button>
					</div>
				`;
				$('#stsrc-family-members-container').append(html);
			});

			// Remove family member
			$(document).on('click', '.stsrc-remove-family-member', function() {
				$(this).closest('.stsrc-family-member-item').remove();
				familyMemberCount--;
			});

			// Add extra member
			$('#stsrc-add-extra-member').on('click', function() {
				if (extraMemberCount >= 3) {
					alert('Maximum of 3 extra members allowed.');
					return;
				}

				extraMemberCount++;
				const html = `
					<div class="stsrc-extra-member-item" data-index="${extraMemberCount}">
						<h3>Extra Member ${extraMemberCount} ($50)</h3>
						<div class="stsrc-form-row">
							<div class="stsrc-form-group">
								<label>First Name</label>
								<input type="text" name="extra_members[${extraMemberCount}][first_name]" required>
							</div>
							<div class="stsrc-form-group">
								<label>Last Name</label>
								<input type="text" name="extra_members[${extraMemberCount}][last_name]" required>
							</div>
						</div>
						<div class="stsrc-form-group">
							<label>Email (optional)</label>
							<input type="email" name="extra_members[${extraMemberCount}][email]">
						</div>
						<button type="button" class="stsrc-button stsrc-button-danger stsrc-remove-extra-member">Remove</button>
					</div>
				`;
				$('#stsrc-extra-members-container').append(html);
			});

			// Remove extra member
			$(document).on('click', '.stsrc-remove-extra-member', function() {
				$(this).closest('.stsrc-extra-member-item').remove();
				extraMemberCount--;
			});

			// Form submission
			$('#stsrc-registration-form').on('submit', function(e) {
				e.preventDefault();

				const $form = $(this);
				const $submitBtn = $('#stsrc-submit-registration');
				const $messages = $('#stsrc-form-messages');

				// Validate password match
				if ($('#password').val() !== $('#password_confirm').val()) {
					self.showNotice('Passwords do not match.', 'error', $messages);
					return;
				}

				// Get CAPTCHA token if enabled
				const captchaToken = $('#captcha_token').val();
				if ($('#captcha_token').length && !captchaToken) {
					// Try to get reCAPTCHA token
					if (typeof grecaptcha !== 'undefined') {
						grecaptcha.ready(function() {
							grecaptcha.execute($('#captcha_token').data('site-key') || '', {action: 'register'}).then(function(token) {
								$('#captcha_token').val(token);
								self.submitRegistrationForm($form, $submitBtn, $messages);
							});
						});
						return;
					}
				}

				self.submitRegistrationForm($form, $submitBtn, $messages);
			});
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
			// Add extra member
			$('#stsrc-add-extra-member-btn').on('click', () => {
				$('#stsrc-extra-member-modal-title').text('Add Extra Member');
				$('#stsrc-extra-member-action').val('stsrc_add_extra_member');
				$('#stsrc-extra-member-id').val('');
				$('#stsrc-extra-member-form')[0].reset();
				$('#stsrc-extra-member-modal').addClass('active');
			});

			// Edit extra member
			$(document).on('click', '.stsrc-edit-extra-member', function() {
				const $item = $(this).closest('.stsrc-extra-member-item');
				const id = $(this).data('id');
				const nameParts = $item.find('strong').text().split(' ');
				const firstName = nameParts[0];
				const lastName = nameParts.slice(1).join(' ');
				const email = $item.find('.stsrc-member-email').text() || '';

				$('#stsrc-extra-member-modal-title').text('Edit Extra Member');
				$('#stsrc-extra-member-action').val('stsrc_update_extra_member');
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

			// Submit extra member form
			$('#stsrc-extra-member-form').on('submit', (e) => {
				e.preventDefault();
				const action = $('#stsrc-extra-member-action').val();
				this.submitAjaxForm($('#stsrc-extra-member-form'), action, (response) => {
					if (response.success) {
						if (response.data.checkout_url) {
							// Redirect to Stripe checkout for payment
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
						nonce: this.nonce
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
