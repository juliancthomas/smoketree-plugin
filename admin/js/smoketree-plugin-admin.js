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
			$(document).on('click', '.stsrc-delete-membership-type', this.handleDeleteMembershipType);
			$(document).on('click', '.stsrc-delete', this.handleDelete);
			$(document).on('click', '.stsrc-delete-access-code', this.handleDeleteAccessCode);

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

			// Quick Edit (members list)
			if ($('#stsrc-quick-edit-template').length) {
				this.initQuickEdit();
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
			var self = this;

		// Reset recipient list when filters change so the next submit auto-reloads.
		$('#membership_type_id, #status, #payment_type, #date_from, #date_to').on('change', function() {
			self.recipientListLoaded = false;
			$('#stsrc-recipient-list-wrap').hide();
			$('#stsrc-recipient-list').empty();
			$('#recipient-count').text('Click "Preview Recipients" to see count');
		});

		// Template change handler — toggle editor vs preview
		$('#template').on('change', function() {
				var templateVal = $(this).val();

				if (templateVal) {
					$('#message-required').hide();
					$('#stsrc-message-row').hide();
					$('#stsrc-template-preview-row').show();
					self.loadTemplatePreview(templateVal);
				} else {
					$('#message-required').show();
					$('#stsrc-message-row').show();
					$('#stsrc-template-preview-row').hide();
					$('#stsrc-template-preview-content').empty();
				}
			});

		// Preview recipients
		$('#preview-recipients-btn').on('click', function(e) {
			e.preventDefault();
			self.loadRecipientPreview();
		});

			// Send test email
			$('#send-test-email-btn').on('click', function(e) {
				e.preventDefault();

				if (!self.validateEmailComposer()) {
					return;
				}

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

			if (!self.validateEmailComposer()) {
				return;
			}

			// If the recipient list hasn't been loaded yet, auto-load it first.
			if (!self.recipientListLoaded) {
				self.loadRecipientPreview(function() {
					$('#stsrc-email-composer-form').trigger('submit');
				});
				return;
			}

			const selectedCount = parseInt($('#stsrc-selected-count').text(), 10) || 0;
			if (selectedCount === 0) {
				STSRCAdmin.showNotice('No recipients selected. Please select at least one recipient.', 'warning');
				return;
			}

			if (!confirm('Are you sure you want to send this email to ' + selectedCount + ' ' + (selectedCount === 1 ? 'recipient' : 'recipients') + '?')) {
				return;
			}

			const $form = $(this);
			const formData = new FormData($form[0]);
			formData.append('action', 'stsrc_send_batch_email');
			formData.append('nonce', STSRCAdmin.nonce);

			// Append excluded member IDs
			self.getExcludedMemberIds().forEach(function(id) {
				formData.append('excluded_member_ids[]', id);
			});

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

	// Tracks whether the recipient list has been loaded at least once.
	recipientListLoaded: false,

	/**
	 * Load recipient preview via AJAX and render the checkbox list.
	 *
	 * @param {Function} [callback] Optional callback invoked after list renders.
	 */
	loadRecipientPreview: function(callback) {
		var self = this;
		var $btn = $('#preview-recipients-btn');

		$btn.prop('disabled', true).text('Loading…');
		$('#recipient-count').text('Loading…');

		var filters = {
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
					var count = response.data.count;
					$('#recipient-count').text(count + ' ' + (count === 1 ? 'recipient' : 'recipients') + ' will receive this email');
					self.renderRecipientList(response.data.recipients || []);
					self.recipientListLoaded = true;
					if (typeof callback === 'function') {
						callback();
					}
				} else {
					$('#recipient-count').text('Error: ' + (response.data.message || 'Unknown error'));
				}
			},
			error: function() {
				$('#recipient-count').text('Error loading recipient list');
			},
			complete: function() {
				$btn.prop('disabled', false).text('Preview Recipients');
			}
		});
	},

	/**
	 * Render the recipient checkbox list.
	 *
	 * @param {Array} recipients Array of {member_id, first_name, last_name, email}.
	 */
	renderRecipientList: function(recipients) {
		var self = this;
		var $wrap = $('#stsrc-recipient-list-wrap');
		var $list = $('#stsrc-recipient-list');
		var $selectAll = $('#stsrc-select-all-recipients');

		$list.empty();

		if (recipients.length === 0) {
			$wrap.hide();
			return;
		}

		recipients.forEach(function(r) {
			var label = $('<label>').css({
				display: 'flex',
				alignItems: 'center',
				gap: '8px',
				padding: '4px 0',
				cursor: 'pointer',
				borderBottom: '1px solid #f0f0f1'
			});

			var cb = $('<input>', {
				type: 'checkbox',
				checked: true,
				'data-member-id': r.member_id
			}).addClass('stsrc-recipient-checkbox');

			var text = $('<span>').text(r.first_name + ' ' + r.last_name + ' \u2014 ' + r.email);

			label.append(cb).append(text);
			$list.append(label);
		});

		// Update count whenever a checkbox changes
		$list.off('change', '.stsrc-recipient-checkbox').on('change', '.stsrc-recipient-checkbox', function() {
			self.updateSelectedCount();
			// Sync the Select All checkbox state
			var total = $list.find('.stsrc-recipient-checkbox').length;
			var checked = $list.find('.stsrc-recipient-checkbox:checked').length;
			$selectAll.prop('checked', total === checked);
			$selectAll.prop('indeterminate', checked > 0 && checked < total);
		});

		// Select All toggle
		$selectAll.off('change').on('change', function() {
			$list.find('.stsrc-recipient-checkbox').prop('checked', $(this).is(':checked'));
			self.updateSelectedCount();
		});

		$selectAll.prop('checked', true).prop('indeterminate', false);
		$wrap.show();
		self.updateSelectedCount();
	},

	/**
	 * Update the "X selected" count displayed in the list header.
	 */
	updateSelectedCount: function() {
		var checked = $('#stsrc-recipient-list .stsrc-recipient-checkbox:checked').length;
		$('#stsrc-selected-count').text(checked);
	},

	/**
	 * Return an array of member IDs that are unchecked (excluded).
	 *
	 * @return {Array}
	 */
	getExcludedMemberIds: function() {
		var excluded = [];
		$('#stsrc-recipient-list .stsrc-recipient-checkbox:not(:checked)').each(function() {
			excluded.push($(this).data('member-id'));
		});
		return excluded;
	},

	/**
	 * Validate email composer before send.
	 */
	validateEmailComposer: function() {
			var subject = $.trim($('#subject').val());
			if (!subject) {
				STSRCAdmin.showNotice('Subject is required.', 'error');
				$('#subject').focus();
				return false;
			}

		var template = $('#template').val();
		// Sync Quill content to hidden input before reading (belt-and-suspenders
		// in case a browser skips the text-change event on programmatic edits).
		if (typeof window.stsrcQuill !== 'undefined') {
			$('#message').val(window.stsrcQuill.root.innerHTML);
		}
		var message = $.trim($('#message').val());
		// Quill's empty-editor sentinel value
		if (message === '<p><br></p>') {
			message = '';
		}

		if (!template && !message) {
				STSRCAdmin.showNotice('Either a message or a template is required.', 'error');
				return false;
			}

			return true;
		},

		/**
		 * Load a rendered template preview via AJAX.
		 */
		loadTemplatePreview: function(template) {
			var $container = $('#stsrc-template-preview-content');
			$container.html('<p style="color:#666;"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span> Loading preview&hellip;</p>');

			$.ajax({
				url: STSRCAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'stsrc_preview_email_template',
					nonce: STSRCAdmin.nonce,
					template: template
				},
				success: function(response) {
					if (response.success) {
						$container.html(
							'<iframe id="stsrc-template-preview-iframe" ' +
							'style="width:100%;border:1px solid #c3c4c7;border-radius:4px;background:#fff;" ' +
							'sandbox="allow-same-origin"></iframe>'
						);
						var iframe = document.getElementById('stsrc-template-preview-iframe');
						var doc = iframe.contentDocument || iframe.contentWindow.document;
						doc.open();
						doc.write(response.data.html);
						doc.close();
						// Auto-resize iframe to content height
						var resizeIframe = function() {
							try {
								iframe.style.height = doc.documentElement.scrollHeight + 'px';
							} catch(e) {}
						};
						iframe.onload = resizeIframe;
						setTimeout(resizeIframe, 200);
					} else {
						$container.html('<p class="description" style="color:#d63638;">' + (response.data.message || 'Error loading preview.') + '</p>');
					}
				},
				error: function() {
					$container.html('<p class="description" style="color:#d63638;">Error loading template preview.</p>');
				}
			});
		},

		/**
		 * Handle delete access code
		 */
		handleDeleteAccessCode: function(e) {
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
					code_id: codeId
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
		},

		/**
		 * Handle delete membership type
		 */
		handleDeleteMembershipType: function(e) {
			e.preventDefault();

			const $button = $(this);
			const typeId = $button.data('id');
			const typeName = $button.data('name') || 'this membership type';

			if (!confirm('Are you sure you want to delete "' + typeName + '"? This action cannot be undone.')) {
				return;
			}

			$button.prop('disabled', true).addClass('disabled');

			$.ajax({
				url: STSRCAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'stsrc_delete_membership_type',
					nonce: STSRCAdmin.nonce,
					membership_type_id: typeId
				},
				success: function(response) {
					if (response.success) {
						STSRCAdmin.showNotice(response.data.message || 'Membership type deleted successfully.', 'success');
						$button.closest('tr').fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						STSRCAdmin.showNotice(response.data.message || 'Failed to delete membership type.', 'error');
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
		 * Initialize access code form
		 */
		initAccessCodeForm: function() {
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

			// Soft-delete modal interactions.
			const $deleteButton = $('#stsrc-delete-member-btn');
			const $modal = $('#stsrc-delete-member-modal');
			const $summaryList = $('#stsrc-delete-summary-list');
			const $confirmDeleteButton = $('#stsrc-confirm-delete-btn');
			const $cancelDeleteButton = $('#stsrc-cancel-delete-btn');

			if (!$deleteButton.length || !$modal.length) {
				return;
			}

			const closeDeleteModal = function() {
				$modal.hide().attr('aria-hidden', 'true');
			};

			const openDeleteModal = function() {
				const memberName = $deleteButton.data('member-name') || 'this member';
				const familyCount = parseInt($deleteButton.data('family-count'), 10) || 0;
				const extraCount = parseInt($deleteButton.data('extra-count'), 10) || 0;
				const hasWpUser = String($deleteButton.data('has-wp-user')) === '1';
				const wpUserId = parseInt($deleteButton.data('wp-user-id'), 10) || 0;

				const summaryItems = [
					`Member account: ${memberName}`,
					`${familyCount} active family member(s)`,
					`${extraCount} active extra member(s)`
				];

				if (hasWpUser) {
					summaryItems.push(`WordPress user account (ID: ${wpUserId})`);
				} else {
					summaryItems.push('No linked WordPress user account');
				}

				$summaryList.empty();
				summaryItems.forEach((item) => {
					$summaryList.append($('<li>').text(item));
				});

				$modal.show().attr('aria-hidden', 'false');
			};

			$deleteButton.on('click', function(e) {
				e.preventDefault();
				openDeleteModal();
			});

			$cancelDeleteButton.on('click', function(e) {
				e.preventDefault();
				closeDeleteModal();
			});

			$modal.on('click', '.stsrc-delete-modal-backdrop', function() {
				closeDeleteModal();
			});

			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $modal.is(':visible')) {
					closeDeleteModal();
				}
			});

			$confirmDeleteButton.on('click', function(e) {
				e.preventDefault();

				const memberId = parseInt($deleteButton.data('member-id'), 10) || 0;
				const nonce = $deleteButton.data('nonce') || STSRCAdmin.nonce;
				const action = $deleteButton.data('action') || 'stsrc_soft_delete_member';
				const redirectUrl = $deleteButton.data('redirect-url') || 'admin.php?page=stsrc-members&deleted=1';

				if (!memberId || !nonce) {
					STSRCAdmin.showNotice('Missing member delete metadata. Please refresh the page.', 'error');
					return;
				}

				$confirmDeleteButton.prop('disabled', true).addClass('disabled').text('Deleting...');

				$.ajax({
					url: STSRCAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: action,
						nonce: nonce,
						member_id: memberId
					},
					success: function(response) {
						if (response.success) {
							window.location.href = redirectUrl;
							return;
						}

						STSRCAdmin.showNotice((response.data && response.data.message) || 'Failed to delete member.', 'error');
						$confirmDeleteButton.prop('disabled', false).removeClass('disabled').text('Yes, Delete Member');
					},
					error: function() {
						STSRCAdmin.showNotice('An error occurred while deleting the member.', 'error');
						$confirmDeleteButton.prop('disabled', false).removeClass('disabled').text('Yes, Delete Member');
					}
				});
			});
		},

		/**
		 * Initialize inline quick-edit for the members list table.
		 */
		initQuickEdit: function() {
			const self = this;
			const $template = $('#stsrc-quick-edit-template');

			function closeQuickEdit() {
				$('.stsrc-quick-edit-row:not(#stsrc-quick-edit-template)').each(function() {
					const memberId = $(this).find('.stsrc-qe-member-id').val();
					$('#member-row-' + memberId).show();
					$(this).remove();
				});
			}

			$(document).on('click', '.stsrc-quick-edit-btn', function(e) {
				e.preventDefault();
				closeQuickEdit();

				const memberId = $(this).data('member-id');
				const $row = $('#member-row-' + memberId);
				const data = $row.data();

				const $editRow = $template.clone().removeAttr('id');
				$editRow.find('.stsrc-qe-member-id').val(memberId);
				$editRow.find('.stsrc-qe-status').val(data.status);
				$editRow.find('.stsrc-qe-membership-type').val(data.membershipTypeId);
				$editRow.find('.stsrc-qe-payment-type').val(data.paymentType);
				$editRow.find('.stsrc-qe-auto-renewal').prop('checked', data.autoRenewal === 1 || data.autoRenewal === '1');
				$editRow.find('.stsrc-qe-guest-passes').val('');

				$editRow.data('original', {
					status: String(data.status),
					membershipTypeId: String(data.membershipTypeId),
					paymentType: String(data.paymentType),
					autoRenewal: data.autoRenewal === 1 || data.autoRenewal === '1'
				});

				$row.hide();
				$row.after($editRow);
				$editRow.show();
				$editRow.find('.stsrc-qe-status').trigger('focus');
			});

			$(document).on('click', '.stsrc-qe-cancel', function(e) {
				e.preventDefault();
				closeQuickEdit();
			});

			$(document).on('keydown', '.stsrc-quick-edit-row', function(e) {
				if (e.key === 'Escape') {
					closeQuickEdit();
				}
			});

			$(document).on('click', '.stsrc-qe-save', function(e) {
				e.preventDefault();
				const $editRow = $(this).closest('.stsrc-quick-edit-row');
				const memberId = $editRow.find('.stsrc-qe-member-id').val();
				const original = $editRow.data('original');

				const currentStatus = $editRow.find('.stsrc-qe-status').val();
				const currentType = $editRow.find('.stsrc-qe-membership-type').val();
				const currentPayment = $editRow.find('.stsrc-qe-payment-type').val();
				const currentAR = $editRow.find('.stsrc-qe-auto-renewal').is(':checked');
				const gpRaw = $editRow.find('.stsrc-qe-guest-passes').val().trim();

				if (gpRaw !== '' && (!/^\d+$/.test(gpRaw) || parseInt(gpRaw, 10) < 0)) {
					alert('Guest passes must be a positive whole number.');
					$editRow.find('.stsrc-qe-guest-passes').trigger('focus');
					return;
				}
				const gpAmount = gpRaw !== '' ? parseInt(gpRaw, 10) : 0;

				const payload = {
					action: 'stsrc_quick_edit_member',
					nonce: self.nonce,
					member_id: memberId
				};
				let hasChanges = false;

				if (currentStatus !== original.status) {
					payload.status = currentStatus;
					hasChanges = true;
				}
				if (currentType !== original.membershipTypeId) {
					payload.membership_type_id = currentType;
					hasChanges = true;
				}
				if (currentPayment !== original.paymentType) {
					payload.payment_type = currentPayment;
					hasChanges = true;
				}
				if (currentAR !== original.autoRenewal) {
					payload.auto_renewal_enabled = currentAR ? 1 : 0;
					hasChanges = true;
				}
				if (gpAmount > 0) {
					payload.guest_pass_adjustment = gpAmount;
					hasChanges = true;
				}

				if (!hasChanges) {
					closeQuickEdit();
					return;
				}

				const $saveBtn = $editRow.find('.stsrc-qe-save');
				const $spinner = $editRow.find('.stsrc-qe-spinner');
				$saveBtn.prop('disabled', true);
				$spinner.addClass('is-active');

				$.post(self.ajaxUrl, payload, function(response) {
					$spinner.removeClass('is-active');
					$saveBtn.prop('disabled', false);

					if (response.success) {
						const m = response.data.member;
						const gpBal = response.data.guest_pass_balance;
						const $row = $('#member-row-' + memberId);

						$row.data('status', m.status);
						$row.attr('data-status', m.status);
						$row.data('membershipTypeId', m.membership_type_id);
						$row.attr('data-membership-type-id', m.membership_type_id);
						$row.data('paymentType', m.payment_type);
						$row.attr('data-payment-type', m.payment_type);
						$row.data('autoRenewal', m.auto_renewal_enabled ? '1' : '0');
						$row.attr('data-auto-renewal', m.auto_renewal_enabled ? '1' : '0');
						$row.data('guestPassBalance', gpBal);
						$row.attr('data-guest-pass-balance', gpBal);

						const typeSelect = $editRow.find('.stsrc-qe-membership-type');
						const typeName = typeSelect.find('option:selected').text().trim();
						$row.find('.column-membership-type').text(typeName);

						const statusLabel = m.status.charAt(0).toUpperCase() + m.status.slice(1);
						$row.find('.column-status').html(
							'<span class="stsrc-status stsrc-status-' + m.status + '">' + statusLabel + '</span>'
						);

						const ptLabel = m.payment_type.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
						$row.find('.column-payment-type').text(ptLabel);

						if (parseInt(m.auto_renewal_enabled)) {
							$row.find('.column-auto-renewal').html(
								'<span class="dashicons dashicons-update" style="color: #00a32a;" title="Auto-renewal enabled"></span>'
							);
						} else {
							$row.find('.column-auto-renewal').html(
								'<span class="dashicons dashicons-minus" style="color: #b0b0b0;" title="Auto-renewal disabled"></span>'
							);
						}

						$row.find('.column-guest-passes').text(gpBal);

						closeQuickEdit();
						self.showNotice(response.data.message, 'success');
					} else {
						alert(response.data.message || 'An error occurred.');
					}
				}).fail(function() {
					$spinner.removeClass('is-active');
					$saveBtn.prop('disabled', false);
					alert('An error occurred. Please try again.');
				});
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
