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
	});
})(jQuery);
