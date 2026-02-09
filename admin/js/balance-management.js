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

		// Placeholder for adjust balance modal (will be implemented in next step)
		$('#stsrc-adjust-balance-btn').on('click', function(e) {
			e.preventDefault();
			alert('Adjust balance modal will be implemented in the next step.');
		});

		// Placeholder for record payment modal (will be implemented in next step)
		$('#stsrc-record-payment-btn').on('click', function(e) {
			e.preventDefault();
			alert('Record payment modal will be implemented in the next step.');
		});
	});

})(jQuery);

