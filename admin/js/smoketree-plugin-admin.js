/**
 * Admin-specific JavaScript for Smoketree Plugin
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/js
 */

(function($) {
	'use strict';

	/**
	 * Main admin object
	 */
	const STSRCAdmin = {
		ajaxUrl: stsrcAdmin.ajaxUrl || ajaxurl,
		nonce: stsrcAdmin.nonce || '',
		strings: stsrcAdmin.strings || {},

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initTooltips();
			this.initConfirmations();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Generic form submissions
			$(document).on('submit', '.stsrc-ajax-form', this.handleFormSubmit);

			// Delete buttons
			$(document).on('click', '.stsrc-delete', this.handleDelete);

			// Bulk actions
			$(document).on('change', '.stsrc-bulk-action-select', this.handleBulkAction);
			$(document).on('click', '.stsrc-apply-bulk-action', this.applyBulkAction);

			// Search
			$(document).on('keyup', '.stsrc-search-input', this.debounce(this.handleSearch, 500));

			// Filters
			$(document).on('change', '.stsrc-filter', this.handleFilterChange);

			// Tabs
			$(document).on('click', '.stsrc-tab', this.handleTabClick);

			// Modal
			$(document).on('click', '.stsrc-modal-trigger', this.openModal);
			$(document).on('click', '.stsrc-modal-close, .stsrc-modal-overlay', this.closeModal);

			// Settings form
			if ($('#stsrc-settings-form').length) {
				this.initSettingsForm();
			}

			// Email composer
			if ($('#stsrc-email-composer-form').length) {
				this.initEmailComposer();
			}

			// Access code form
			if ($('#stsrc-access-code-form').length) {
				this.initAccessCodeForm();
			}

			// Member form
			if ($('#stsrc-member-edit-form').length) {
				this.initMemberForm();
			}

			// Membership type form
			if ($('#stsrc-membership-type-form').length) {
				this.initMembershipTypeForm();
			}
		},

		/**
		 * Handle generic AJAX form submission
		 */
		handleFormSubmit: function(e) {
			e.preventDefault();

		const $form = $(this);
		const strings = STSRCAdmin.strings || {};
		const confirmTemplate = $form.data('confirm') || '';

		// Bulk members validation and confirmation.
		if ($form.hasClass('stsrc-members-bulk-form')) {
			const selectedCount = $form.find('input[name="member_ids[]"]:checked').length;
			if (selectedCount === 0) {
				STSRCAdmin.showNotice(strings.noMembersSelected || 'Please select at least one member.', 'warning');
				return;
			}

			const $statusSelect = $form.find('select[name="new_status"]');
			const statusValue = $statusSelect.val();

			if (!statusValue) {
				STSRCAdmin.showNotice(strings.statusRequired || 'Please choose a status before applying changes.', 'warning');
				return;
			}

			const statusLabel = $statusSelect.find('option:selected').text();
			let confirmMessage = confirmTemplate
				? confirmTemplate.replace('%status%', statusLabel).replace('%count%', selectedCount)
				: (strings.confirmBulkStatus
					? strings.confirmBulkStatus.replace('%status%', statusLabel).replace('%count%', selectedCount)
					: (strings.confirmBulk || 'Are you sure you want to continue?'));

			if (confirmMessage && !window.confirm(confirmMessage)) {
				return;
			}
		}

		// Season reset confirmation.
		if ($form.hasClass('stsrc-season-reset-form')) {
			const confirmMessage = confirmTemplate || strings.confirmSeasonReset || strings.confirmBulk || 'Are you sure you want to continue?';
			if (!window.confirm(confirmMessage)) {
				return;
			}
		}

		const $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
		const action = $form.data('action') || $form.find('input[name="action"]').val();
		const formData = $form.serialize();

			// Disable submit button
			$submitBtn.prop('disabled', true).addClass('disabled');

			// Show loading state
			$form.addClass('stsrc-loading');

			$.ajax({
				url: STSRCAdmin.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						const data = response.data || {};
						STSRCAdmin.showNotice(data.message || 'Operation completed successfully.', 'success');

						if (data.redirect_url) {
							setTimeout(function() {
								window.location.href = data.redirect_url;
							}, 1000);
							return;
						}

						if ($form.data('reload') === true) {
							setTimeout(function() {
								location.reload();
							}, 1000);
							return;
						}
					} else {
						STSRCAdmin.showNotice(response.data.message || 'An error occurred.', 'error');
					}
				},
				error: function() {
					STSRCAdmin.showNotice('An error occurred. Please try again.', 'error');
				},
				complete: function() {
					$form.removeClass('stsrc-loading');
					$submitBtn.prop('disabled', false).removeClass('disabled');
				}
			});
		},

		/**
		 * Handle delete actions
		 */
		handleDelete: function(e) {
			e.preventDefault();

			const $button = $(this);
			const itemId = $button.data('id');
			const itemName = $button.data('name') || 'item';
			const action = $button.data('action');

			if (!confirm('Are you sure you want to delete ' + itemName + '? This action cannot be undone.')) {
				return;
			}

			$button.prop('disabled', true).addClass('disabled');

			$.ajax({
				url: STSRCAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: action,
					nonce: STSRCAdmin.nonce,
					id: itemId
				},
				success: function(response) {
					if (response.success) {
						STSRCAdmin.showNotice(response.data.message || 'Item deleted successfully.', 'success');
						$button.closest('tr').fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						STSRCAdmin.showNotice(response.data.message || 'Failed to delete item.', 'error');
						$button.prop('disabled', false).removeClass('disabled');
					}
				},
				error: function() {
					STSRCAdmin.showNotice('An error occurred. Please try again.', 'error');
					$button.prop('disabled', false).removeClass('disabled');
				}
			});
		},

		/**
		 * Handle bulk actions
		 */
		handleBulkAction: function() {
			const $select = $(this);
			const $applyBtn = $('.stsrc-apply-bulk-action');
			
			if ($select.val()) {
				$applyBtn.prop('disabled', false);
			} else {
				$applyBtn.prop('disabled', true);
			}
		},

		/**
		 * Apply bulk action
		 */
		applyBulkAction: function(e) {
			e.preventDefault();

			const $button = $(this);
			const action = $('.stsrc-bulk-action-select').val();
			const selectedItems = [];

			$('input[name="item[]"]:checked').each(function() {
				selectedItems.push($(this).val());
			});

			if (selectedItems.length === 0) {
				STSRCAdmin.showNotice('Please select at least one item.', 'warning');
				return;
			}

			if (!confirm('Are you sure you want to apply this action to ' + selectedItems.length + ' item(s)?')) {
				return;
			}

			$button.prop('disabled', true).addClass('disabled');

			$.ajax({
				url: STSRCAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'stsrc_bulk_update_members',
					nonce: STSRCAdmin.nonce,
					bulk_action: action,
					items: selectedItems
				},
				success: function(response) {
					if (response.success) {
						STSRCAdmin.showNotice(response.data.message || 'Bulk action completed successfully.', 'success');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						STSRCAdmin.showNotice(response.data.message || 'Bulk action failed.', 'error');
						$button.prop('disabled', false).removeClass('disabled');
					}
				},
				error: function() {
					STSRCAdmin.showNotice('An error occurred. Please try again.', 'error');
					$button.prop('disabled', false).removeClass('disabled');
				}
			});
		},

		/**
		 * Handle search
		 */
		handleSearch: function() {
			const $input = $(this);
			const searchTerm = $input.val();
			const $table = $input.closest('.stsrc-table-wrapper').find('table tbody');

			if (searchTerm.length < 2) {
				$table.find('tr').show();
				return;
			}

			$table.find('tr').each(function() {
				const $row = $(this);
				const text = $row.text().toLowerCase();
				if (text.indexOf(searchTerm.toLowerCase()) !== -1) {
					$row.show();
				} else {
					$row.hide();
				}
			});
		},

		/**
		 * Handle filter change
		 */
		handleFilterChange: function() {
			const $form = $(this).closest('form');
			if ($form.length) {
				$form.submit();
			}
		},

		/**
		 * Handle tab click
		 */
		handleTabClick: function(e) {
			e.preventDefault();

			const $tab = $(this);
			const target = $tab.data('target');

			$('.stsrc-tab').removeClass('active');
			$tab.addClass('active');

			$('.stsrc-tab-content').removeClass('active');
			$(target).addClass('active');
		},

		/**
		 * Initialize settings form
		 */
		initSettingsForm: function() {
			$('#captcha_provider').on('change', function() {
				const provider = $(this).val();
				const providerName = provider === 'recaptcha' ? 'reCAPTCHA' : 'hCaptcha';
				$('label[for="captcha_site_key"]').text('Site Key (' + providerName + ')');
				$('label[for="captcha_secret_key"]').text('Secret Key (' + providerName + ')');
			});

			$('#stsrc-settings-form').on('submit', function(e) {
				e.preventDefault();
				
				const $form = $(this);
				const formData = $form.serialize();
				const $submitBtn = $('#submit');
				$submitBtn.prop('disabled', true).val('Saving...');

				$.ajax({
					url: STSRCAdmin.ajaxUrl,
					type: 'POST',
					data: formData,
					success: function(response) {
						if (response.success) {
							STSRCAdmin.showNotice('Settings saved successfully!', 'success');
							setTimeout(function() {
								location.reload();
							}, 1000);
						} else {
							STSRCAdmin.showNotice('Error: ' + (response.data.message || 'Unknown error'), 'error');
							$submitBtn.prop('disabled', false).val('Save Settings');
						}
					},
					error: function() {
						STSRCAdmin.showNotice('Error saving settings', 'error');
						$submitBtn.prop('disabled', false).val('Save Settings');
					}
				});
			});
		},

		/**
		 * Initialize email composer
		 */
		initEmailComposer: function() {
			// Template change handler
			$('#template').on('change', function() {
				if ($(this).val()) {
					$('#message-required').hide();
					$('#message').removeAttr('required');
				} else {
					$('#message-required').show();
					$('#message').attr('required', 'required');
				}
			});

			// Preview recipients
			$('#preview-recipients-btn').on('click', function(e) {
				e.preventDefault();

				const filters = {
					membership_type_id: $('#membership_type_id').val(),
					status: $('#status').val(),
					payment_type: $('#payment_type').val(),
					date_from: $('#date_from').val(),
					date_to: $('#date_to').val()
				};

				$.ajax({
					url: STSRCAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'stsrc_preview_recipients',
						nonce: STSRCAdmin.nonce,
						filters: filters
					},
					success: function(response) {
						if (response.success) {
							$('#recipient-count').text(response.data.count + ' ' + (response.data.count === 1 ? 'recipient' : 'recipients') + ' will receive this email');
						} else {
							$('#recipient-count').text('Error: ' + response.data.message);
						}
					},
					error: function() {
						$('#recipient-count').text('Error loading recipient count');
					}
				});
			});

			// Send test email
			$('#send-test-email-btn').on('click', function(e) {
				e.preventDefault();

				const $button = $(this);
				const formData = new FormData($('#stsrc-email-composer-form')[0]);
				formData.append('action', 'stsrc_send_test_email');
				formData.append('nonce', STSRCAdmin.nonce);

				$button.prop('disabled', true).text('Sending...');

				$.ajax({
					url: STSRCAdmin.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if (response.success) {
							STSRCAdmin.showNotice('Test email sent successfully!', 'success');
						} else {
							STSRCAdmin.showNotice('Error: ' + (response.data.message || 'Unknown error'), 'error');
						}
						$button.prop('disabled', false).text('Send Test Email');
					},
					error: function() {
						STSRCAdmin.showNotice('Error sending test email', 'error');
						$button.prop('disabled', false).text('Send Test Email');
					}
				});
			});

			// Send batch email
			$('#stsrc-email-composer-form').on('submit', function(e) {
				e.preventDefault();

				const $form = $(this);
				const formData = new FormData($form[0]);
				formData.append('action', 'stsrc_send_batch_email');
				formData.append('nonce', STSRCAdmin.nonce);

				$('#email-progress').show();
				$('#submit').prop('disabled', true);

				$.ajax({
					url: STSRCAdmin.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					xhr: function() {
						const xhr = new window.XMLHttpRequest();
						xhr.upload.addEventListener('progress', function(e) {
							if (e.lengthComputable) {
								const percentComplete = (e.loaded / e.total) * 100;
								$('#progress-bar').css('width', percentComplete + '%');
							}
						}, false);
						return xhr;
					},
					success: function(response) {
						$('#progress-bar').css('width', '100%');
						if (response.success) {
							$('#progress-text').text(response.data.message);
							STSRCAdmin.showNotice(response.data.message, 'success');
						} else {
							$('#progress-text').text('Error: ' + response.data.message);
							STSRCAdmin.showNotice('Error: ' + response.data.message, 'error');
						}
						$('#submit').prop('disabled', false);
					},
					error: function() {
						$('#progress-text').text('Error sending batch email');
						STSRCAdmin.showNotice('Error sending batch email', 'error');
						$('#submit').prop('disabled', false);
					}
				});
			});
		},

		/**
		 * Initialize access code form
		 */
		initAccessCodeForm: function() {
			$('.stsrc-delete-access-code').on('click', function(e) {
				e.preventDefault();

				const $button = $(this);
				const codeId = $button.data('id');
				const code = $button.data('code');

				if (!confirm('Are you sure you want to delete access code "' + code + '"? This action cannot be undone.')) {
					return;
				}

				$button.prop('disabled', true).addClass('disabled');

				$.ajax({
					url: STSRCAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'stsrc_delete_access_code',
						nonce: STSRCAdmin.nonce,
						id: codeId
					},
					success: function(response) {
						if (response.success) {
							STSRCAdmin.showNotice(response.data.message || 'Access code deleted successfully.', 'success');
							$button.closest('tr').fadeOut(300, function() {
								$(this).remove();
							});
						} else {
							STSRCAdmin.showNotice(response.data.message || 'Failed to delete access code.', 'error');
							$button.prop('disabled', false).removeClass('disabled');
						}
					},
					error: function() {
						STSRCAdmin.showNotice('An error occurred. Please try again.', 'error');
						$button.prop('disabled', false).removeClass('disabled');
					}
				});
			});

			$('#stsrc-access-code-form').on('submit', function(e) {
				e.preventDefault();

				const $form = $(this);
				const formData = $form.serialize();
				const $submitBtn = $('#submit');
				$submitBtn.prop('disabled', true).val('Saving...');

				$.ajax({
					url: STSRCAdmin.ajaxUrl,
					type: 'POST',
					data: formData,
					success: function(response) {
						if (response.success) {
							STSRCAdmin.showNotice(response.data.message || 'Access code saved successfully.', 'success');
							setTimeout(function() {
								location.reload();
							}, 1000);
						} else {
							STSRCAdmin.showNotice(response.data.message || 'Failed to save access code.', 'error');
							$submitBtn.prop('disabled', false).val('Save Access Code');
						}
					},
					error: function() {
						STSRCAdmin.showNotice('An error occurred. Please try again.', 'error');
						$submitBtn.prop('disabled', false).val('Save Access Code');
					}
				});
			});
		},

		/**
		 * Initialize member form
		 */
		initMemberForm: function() {
			// Password validation
			$('#new_password, #confirm_password').on('input', function() {
				const newPassword = $('#new_password').val();
				const confirmPassword = $('#confirm_password').val();
				
				if (newPassword && confirmPassword) {
					if (newPassword !== confirmPassword) {
						$('#confirm_password')[0].setCustomValidity('Passwords do not match');
					} else {
						$('#confirm_password')[0].setCustomValidity('');
					}
				}
			});

			// Require confirm password if new password is filled
			$('#new_password').on('input', function() {
				if ($(this).val()) {
					$('#confirm_password').attr('required', true);
				} else {
					$('#confirm_password').removeAttr('required');
				}
			});
		},

		/**
		 * Initialize membership type form
		 */
		initMembershipTypeForm: function() {
			// Add any membership type-specific form handlers here
		},

		/**
		 * Open modal
		 */
		openModal: function(e) {
			e.preventDefault();

			const $trigger = $(this);
			const target = $trigger.data('target');
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
		 * Show notice
		 */
		showNotice: function(message, type) {
			type = type || 'info';
			const $notice = $('<div class="stsrc-notice ' + type + '">' + message + '</div>');
			$('.wrap').prepend($notice);

			setTimeout(function() {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Initialize tooltips
		 */
		initTooltips: function() {
			// Tooltip implementation if needed
		},

		/**
		 * Initialize confirmations
		 */
		initConfirmations: function() {
			// Confirmation dialogs implementation
		},

		/**
		 * Debounce function
		 */
		debounce: function(func, wait) {
			let timeout;
			return function() {
				const context = this;
				const args = arguments;
				clearTimeout(timeout);
				timeout = setTimeout(function() {
					func.apply(context, args);
				}, wait);
			};
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		STSRCAdmin.init();
	});

})(jQuery);
