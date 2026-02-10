/**
 * Balance Management Admin JavaScript
 *
 * Handles admin UI interactions for member balance and transaction management.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/js
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		
		// Toggle transaction history visibility
		$('#stsrc-view-transactions-btn').on('click', function(e) {
			e.preventDefault();
			const $section = $('#stsrc-transaction-history-section');
			const $button = $(this);
			
			if ($section.is(':visible')) {
				$section.slideUp(300);
				$button.text($button.data('show-text') || 'View Transaction History');
			} else {
				$section.slideDown(300);
				$button.data('show-text', $button.text());
				$button.text('Hide Transaction History');
				
				// Scroll to transaction section
				$('html, body').animate({
					scrollTop: $section.offset().top - 50
				}, 300);
			}
		});

		// Transaction year filter
		$('#stsrc-transaction-year-filter').on('change', function() {
			const year = $(this).val();
			const memberId = getMemberId();
			
			if (!memberId) {
				return;
			}

			// Reload transaction table via AJAX
			loadTransactions(memberId, year, 1);
		});

		// Transaction pagination
		$(document).on('click', '.stsrc-transaction-page-btn', function(e) {
			e.preventDefault();
			const page = $(this).data('page');
			const year = $('#stsrc-transaction-year-filter').val();
			const memberId = getMemberId();
			
			if (!memberId) {
				return;
			}

			loadTransactions(memberId, year, page);
		});

		/**
		 * Get member ID from the page
		 */
		function getMemberId() {
			// Try to get from hidden input
			const $input = $('input[name="member_id"]');
			if ($input.length) {
				return parseInt($input.val(), 10);
			}
			
			// Try to get from URL
			const urlParams = new URLSearchParams(window.location.search);
			return parseInt(urlParams.get('member_id'), 10) || 0;
		}

		/**
		 * Load transactions via AJAX
		 */
		function loadTransactions(memberId, year, page) {
			const $tableWrapper = $('.stsrc-transaction-table-wrapper');
			const $pagination = $('.stsrc-transaction-pagination');
			
			// Show loading state
			$tableWrapper.css('opacity', '0.5');
			
			$.ajax({
				url: ajaxurl,
				type: 'GET',
				data: {
					action: 'stsrc_load_transactions',
					member_id: memberId,
					year: year || '',
					page: page || 1
				},
				success: function(response) {
					if (response.success && response.data.html) {
						// Replace table content
						$('#stsrc-transaction-table tbody').html(response.data.table_rows);
						
						// Update pagination
						if (response.data.pagination_html) {
							$pagination.html(response.data.pagination_html).show();
						} else {
							$pagination.hide();
						}
						
						// Update count
						$('.stsrc-transaction-filters span').text(
							response.data.count_text || 'Showing transactions'
						);
					} else {
						alert('Failed to load transactions. Please refresh the page.');
					}
				},
				error: function() {
					alert('An error occurred while loading transactions.');
				},
				complete: function() {
					$tableWrapper.css('opacity', '1');
				}
			});
		}

		/**
		 * Adjust Balance Modal - Open
		 */
		$('#stsrc-adjust-balance-btn').on('click', function(e) {
			e.preventDefault();
			openAdjustBalanceModal();
		});

		/**
		 * Open Adjust Balance Modal
		 */
		function openAdjustBalanceModal() {
			const memberId = getMemberId();
			if (!memberId) {
				alert('Unable to determine member ID.');
				return;
			}

			// Get current balance from display
			const currentBalanceText = $('#stsrc-balance-section').find('div[style*="font-size: 32px"]').text().trim();
			const currentBalance = parseFloat(currentBalanceText.replace(/[^0-9.-]/g, '')) || 0;

			// Set member ID and current balance
			$('#stsrc-adjust-member-id').val(memberId);
			$('#stsrc-adjust-current-balance').text(formatCurrency(Math.abs(currentBalance)));

			// Reset form
			resetAdjustBalanceModal();

			// Store current balance for calculations
			$('#stsrc-adjust-balance-modal').data('currentBalance', currentBalance);

			// Show modal
			$('#stsrc-adjust-balance-modal').fadeIn(200);
			$('body').addClass('stsrc-modal-open');
		}

		/**
		 * Close Adjust Balance Modal
		 */
		$(document).on('click', '.stsrc-modal-close', function() {
			const $modal = $(this).closest('.stsrc-modal');
			if ($modal.attr('id') === 'stsrc-adjust-balance-modal') {
				closeAdjustBalanceModal();
			}
			if ($modal.attr('id') === 'stsrc-record-payment-modal') {
				closeRecordPaymentModal();
			}
		});

		$(document).on('click', '.stsrc-modal-overlay', function() {
			const $modal = $(this).closest('.stsrc-modal');
			if ($modal.attr('id') === 'stsrc-adjust-balance-modal') {
				closeAdjustBalanceModal();
			}
			if ($modal.attr('id') === 'stsrc-record-payment-modal') {
				closeRecordPaymentModal();
			}
		});

		function closeAdjustBalanceModal() {
			$('#stsrc-adjust-balance-modal').fadeOut(200);
			$('body').removeClass('stsrc-modal-open');
			setTimeout(resetAdjustBalanceModal, 300);
		}

		/**
		 * Close and Reload Page
		 */
		$(document).on('click', '.stsrc-modal-close-reload', function() {
			location.reload();
		});

		/**
		 * Reset Modal to Initial State
		 */
		function resetAdjustBalanceModal() {
			// Reset form
			$('#stsrc-adjust-balance-form')[0].reset();
			
			// Hide all steps except form
			$('#stsrc-adjust-form-step').show();
			$('#stsrc-adjust-confirm-step').hide();
			$('#stsrc-adjust-success-step').hide();
			$('#stsrc-adjust-error-step').hide();
			
			// Hide all button groups except form buttons
			$('#stsrc-adjust-form-buttons').show();
			$('#stsrc-adjust-confirm-buttons').hide();
			$('#stsrc-adjust-close-button').hide();
			$('#stsrc-adjust-loading').hide();
			
			// Reset preview
			$('#stsrc-balance-preview').hide();
			
			// Disable continue button
			$('#stsrc-continue-to-confirm').prop('disabled', true);
			
			// Clear errors
			$('.stsrc-error-message').hide().text('');
			$('#stsrc-adjustment-type-hint').text('');
		}

		/**
		 * Record Manual Payment Modal - Open
		 */
		$('#stsrc-record-payment-btn').on('click', function(e) {
			e.preventDefault();
			openRecordPaymentModal();
		});

		function openRecordPaymentModal() {
			const memberId = getMemberId();
			if (!memberId) {
				alert('Unable to determine member ID.');
				return;
			}

			$('#stsrc-record-payment-member-id').val(memberId);
			resetRecordPaymentModal();

			$('#stsrc-record-payment-modal').fadeIn(200);
			$('body').addClass('stsrc-modal-open');
		}

		function closeRecordPaymentModal() {
			$('#stsrc-record-payment-modal').fadeOut(200);
			$('body').removeClass('stsrc-modal-open');
			setTimeout(resetRecordPaymentModal, 300);
		}

		function resetRecordPaymentModal() {
			const $form = $('#stsrc-record-payment-form');
			if ($form.length) {
				$form[0].reset();
			}
			updateCheckNumberVisibility();
			$('#stsrc-payment-amount-error').hide().text('');
			$('#stsrc-submit-record-payment').prop('disabled', true);
		}

		function updateCheckNumberVisibility() {
			const method = $('#stsrc-payment-method').val();
			const $checkGroup = $('.stsrc-check-number-group');
			if (method === 'check') {
				$checkGroup.removeClass('stsrc-hidden');
			} else {
				$checkGroup.addClass('stsrc-hidden');
				$('#stsrc-check-number').val('');
			}
		}

		function validateRecordPaymentForm() {
			const method = $('#stsrc-payment-method').val();
			const amount = parseFloat($('#stsrc-payment-amount').val());
			const description = $('#stsrc-payment-description').val().trim();
			const dateReceived = $('#stsrc-payment-date').val();

			let isValid = true;
			$('#stsrc-payment-amount-error').hide();

			if (!method || !description || !dateReceived) {
				isValid = false;
			}

			if (isNaN(amount) || amount <= 0) {
				isValid = false;
			} else if (amount < 0.01) {
				$('#stsrc-payment-amount-error').text('Amount must be at least $0.01').show();
				isValid = false;
			}

			$('#stsrc-submit-record-payment').prop('disabled', !isValid);
			return isValid;
		}

		$('#stsrc-payment-method').on('change', function() {
			updateCheckNumberVisibility();
			validateRecordPaymentForm();
		});

		$('#stsrc-record-payment-form input, #stsrc-record-payment-form select, #stsrc-record-payment-form textarea').on('input change', function() {
			validateRecordPaymentForm();
		});

		$('#stsrc-submit-record-payment').on('click', function() {
			if (!validateRecordPaymentForm()) {
				return;
			}
			alert('Record payment submission will be connected in a future step.');
		});

		/**
		 * Adjustment Type Change - Update Hints
		 */
		$('#stsrc-adjustment-type').on('change', function() {
			const type = $(this).val();
			const hints = {
				'discount': 'This will reduce the member\'s balance (negative adjustment).',
				'fee': 'This will increase the member\'s balance (positive adjustment).',
				'correction': 'Use this for correcting errors. Can increase or decrease balance.',
				'other': 'Generic adjustment. Please provide clear description.'
			};
			
			$('#stsrc-adjustment-type-hint').text(hints[type] || '');
			updateBalancePreview();
		});

		/**
		 * Amount Input - Real-time Preview
		 */
		$('#stsrc-adjustment-amount').on('input', function() {
			updateBalancePreview();
		});

		/**
		 * Form Input - Validate and Enable Continue Button
		 */
		$('#stsrc-adjust-balance-form input, #stsrc-adjust-balance-form select, #stsrc-adjust-balance-form textarea').on('input change', function() {
			validateAdjustBalanceForm();
		});

		/**
		 * Validate Adjust Balance Form
		 */
		function validateAdjustBalanceForm() {
			const type = $('#stsrc-adjustment-type').val();
			const amount = parseFloat($('#stsrc-adjustment-amount').val());
			const description = $('#stsrc-adjustment-description').val().trim();

			let isValid = true;
			$('#stsrc-amount-error').hide();

			// Check required fields
			if (!type || !description) {
				isValid = false;
			}

			// Validate amount
			if (isNaN(amount) || amount <= 0) {
				isValid = false;
			} else if (amount < 0.01) {
				$('#stsrc-amount-error').text('Amount must be at least $0.01').show();
				isValid = false;
			}

			// Enable/disable continue button
			$('#stsrc-continue-to-confirm').prop('disabled', !isValid);

			return isValid;
		}

		/**
		 * Update Balance Preview
		 */
		function updateBalancePreview() {
			const type = $('#stsrc-adjustment-type').val();
			const amount = parseFloat($('#stsrc-adjustment-amount').val());
			const currentBalance = $('#stsrc-adjust-balance-modal').data('currentBalance') || 0;

			if (!type || isNaN(amount) || amount <= 0) {
				$('#stsrc-balance-preview').hide();
				return;
			}

			// Calculate adjustment based on type
			let adjustmentAmount = 0;
			let adjustmentSign = '';
			let adjustmentLabel = 'Adjustment:';

			switch (type) {
				case 'discount':
					adjustmentAmount = -amount;
					adjustmentSign = '-';
					adjustmentLabel = 'Discount:';
					break;
				case 'fee':
					adjustmentAmount = amount;
					adjustmentSign = '+';
					adjustmentLabel = 'Fee:';
					break;
				case 'correction':
					// For correction, we'll treat positive amounts as reducing balance by default
					// Admin can change in description if needed
					adjustmentAmount = -amount;
					adjustmentSign = '-';
					adjustmentLabel = 'Correction:';
					break;
				case 'other':
					adjustmentAmount = -amount;
					adjustmentSign = '-';
					adjustmentLabel = 'Adjustment:';
					break;
			}

			const newBalance = currentBalance + adjustmentAmount;

			// Update preview display
			$('#stsrc-preview-current-amount').text(formatCurrency(Math.abs(currentBalance)));
			$('#stsrc-preview-adjustment-label').text(adjustmentLabel);
			$('#stsrc-preview-adjustment-sign').text(adjustmentSign);
			$('#stsrc-preview-adjustment-amount').text(formatCurrency(Math.abs(adjustmentAmount)));
			$('#stsrc-preview-new-amount').text(formatCurrency(Math.abs(newBalance)));

			// Color coding
			const $newBalanceSpan = $('.stsrc-preview-new-balance');
			if (newBalance > 0) {
				$newBalanceSpan.css('color', '#f44336'); // Red for outstanding
			} else if (newBalance < 0) {
				$newBalanceSpan.css('color', '#ff9800'); // Orange for overpaid
			} else {
				$newBalanceSpan.css('color', '#4caf50'); // Green for paid in full
			}

			// Show preview
			$('#stsrc-balance-preview').slideDown(200);
		}

		/**
		 * Continue to Confirmation Step
		 */
		$('#stsrc-continue-to-confirm').on('click', function() {
			if (!validateAdjustBalanceForm()) {
				return;
			}

			const type = $('#stsrc-adjustment-type').val();
			const amount = parseFloat($('#stsrc-adjustment-amount').val());
			const description = $('#stsrc-adjustment-description').val().trim();
			const currentBalance = $('#stsrc-adjust-balance-modal').data('currentBalance') || 0;

			// Calculate new balance
			let adjustmentAmount = 0;
			let typeLabel = '';
			
			switch (type) {
				case 'discount':
					adjustmentAmount = -amount;
					typeLabel = 'Discount';
					break;
				case 'fee':
					adjustmentAmount = amount;
					typeLabel = 'Fee';
					break;
				case 'correction':
					adjustmentAmount = -amount;
					typeLabel = 'Correction';
					break;
				case 'other':
					adjustmentAmount = -amount;
					typeLabel = 'Other Adjustment';
					break;
			}

			const newBalance = currentBalance + adjustmentAmount;

			// Populate confirmation details
			$('#stsrc-confirm-type').text(typeLabel);
			$('#stsrc-confirm-amount').text('$' + formatCurrency(amount));
			$('#stsrc-confirm-description').text(description);
			$('#stsrc-confirm-new-balance').text('$' + formatCurrency(Math.abs(newBalance)));

			// Switch to confirmation step
			$('#stsrc-adjust-form-step').hide();
			$('#stsrc-adjust-confirm-step').show();
			$('#stsrc-adjust-form-buttons').hide();
			$('#stsrc-adjust-confirm-buttons').show();
		});

		/**
		 * Back to Form
		 */
		$('#stsrc-back-to-form').on('click', function() {
			$('#stsrc-adjust-confirm-step').hide();
			$('#stsrc-adjust-form-step').show();
			$('#stsrc-adjust-confirm-buttons').hide();
			$('#stsrc-adjust-form-buttons').show();
		});

		/**
		 * Submit Adjustment (will be connected to AJAX in step 4.4)
		 */
		$('#stsrc-submit-adjustment').on('click', function() {
			// Show loading
			$('#stsrc-adjust-confirm-buttons').hide();
			$('#stsrc-adjust-loading').show();

			const formData = {
				action: 'stsrc_adjust_balance',
				member_id: $('#stsrc-adjust-member-id').val(),
				adjustment_type: $('#stsrc-adjustment-type').val(),
				amount: $('#stsrc-adjustment-amount').val(),
				description: $('#stsrc-adjustment-description').val(),
				admin_notes: $('#stsrc-adjustment-admin-notes').val(),
				stsrc_adjust_balance_nonce: $('#stsrc-adjust-balance-form').find('input[name="stsrc_adjust_balance_nonce"]').val()
			};

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						$('#stsrc-adjust-confirm-step').hide();
						$('#stsrc-adjust-success-step').show();
						$('#stsrc-success-message').text(response.data.message || 'Balance adjustment has been recorded successfully.');
						$('#stsrc-adjust-loading').hide();
						$('#stsrc-adjust-close-button').show();

						// Refresh balance summary and transaction history
						refreshBalanceDisplay(response.data.new_balance);
						const memberId = getMemberId();
						if (memberId) {
							loadTransactions(memberId, $('#stsrc-transaction-year-filter').val() || '', 1);
						}
					} else {
						showAdjustBalanceError(response.data && response.data.message ? response.data.message : 'Failed to adjust balance.');
					}
				},
				error: function() {
					showAdjustBalanceError('An error occurred while adjusting the balance.');
				}
			});
		});

		/**
		 * Show Adjust Balance Error State
		 */
		function showAdjustBalanceError(message) {
			$('#stsrc-adjust-confirm-step').hide();
			$('#stsrc-adjust-error-step').show();
			$('#stsrc-error-message').text(message);
			$('#stsrc-adjust-loading').hide();
			$('#stsrc-adjust-close-button').show();
		}

		/**
		 * Refresh Balance Display UI
		 */
		function refreshBalanceDisplay(newBalance) {
			if (typeof newBalance === 'undefined' || newBalance === null) {
				return;
			}

			const balanceValue = parseFloat(newBalance);
			const $balanceSection = $('#stsrc-balance-section');
			const $balanceAmount = $balanceSection.find('div[style*="font-size: 32px"]');

			if ($balanceAmount.length) {
				const formatted = formatCurrency(Math.abs(balanceValue));
				if (balanceValue < 0) {
					$balanceAmount.html('-$' + formatted);
				} else {
					$balanceAmount.html('$' + formatted);
				}
			}
		}

		/**
		 * Format Currency Helper
		 */
		function formatCurrency(amount) {
			return parseFloat(amount).toFixed(2);
		}

		// Record payment modal submission will be wired to AJAX in a later step.
	});

})(jQuery);

