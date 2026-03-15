(function($) {
	'use strict';

	var $modal = $('#stsrc-promo-code-modal');
	if (!$modal.length || typeof stsrcPromoAdmin === 'undefined') {
		return;
	}

	function closeModal() {
		$modal.hide().attr('aria-hidden', 'true');
	}

	function openModal() {
		$modal.show().attr('aria-hidden', 'false');
	}

	function resetForm() {
		var $form = $('#stsrc-promo-code-form');
		$form[0].reset();
		$('#stsrc_promo_code_id').val('');
		$('#stsrc-promo-modal-title').text('Add Promo Code');
		$('#stsrc_is_active').prop('checked', true);
	}

	function submitPromoForm(e) {
		e.preventDefault();

		var $form = $('#stsrc-promo-code-form');
		var payload = $form.serializeArray();
		var codeId = $('#stsrc_promo_code_id').val();

		payload.push({ name: 'action', value: codeId ? 'stsrc_update_promo_code' : 'stsrc_create_promo_code' });
		payload.push({ name: 'nonce', value: stsrcPromoAdmin.nonce });

		$.post(stsrcPromoAdmin.ajaxUrl, payload).done(function(response) {
			if (!response.success) {
				window.alert(response.data && response.data.message ? response.data.message : 'Unable to save promo code.');
				return;
			}
			window.location.reload();
		}).fail(function() {
			window.alert('Unable to save promo code.');
		});
	}

	function fillFormFromPayload(payload) {
		resetForm();
		$('#stsrc-promo-modal-title').text('Edit Promo Code');
		$('#stsrc_promo_code_id').val(payload.code_id || '');
		$('#stsrc_code_name').val(payload.code_name || '');
		$('input[name="discount_type"][value="' + (payload.discount_type || 'flat') + '"]').prop('checked', true);
		$('#stsrc_discount_value').val(payload.discount_value || '');
		if (payload.expires_at) {
			$('#stsrc_expires_at').val(String(payload.expires_at).slice(0, 10));
		}
		$('#stsrc_is_one_time_use').prop('checked', Number(payload.is_one_time_use) === 1);
		$('#stsrc_usage_limit').val(payload.usage_limit || '');
		$('#stsrc_is_active').prop('checked', Number(payload.is_active) === 1);

		$('#stsrc_allowed_type_ids option').prop('selected', false);
		if (Array.isArray(payload.allowed_type_ids)) {
			payload.allowed_type_ids.forEach(function(typeId) {
				$('#stsrc_allowed_type_ids option[value="' + Number(typeId) + '"]').prop('selected', true);
			});
		}
	}

	$(document).on('click', '#stsrc-open-promo-modal', function() {
		resetForm();
		openModal();
	});

	$(document).on('click', '.stsrc-edit-promo-code', function() {
		var payload = $(this).data('code');
		if (typeof payload === 'string') {
			try {
				payload = JSON.parse(payload);
			} catch (e) {
				payload = {};
			}
		}
		fillFormFromPayload(payload || {});
		openModal();
	});

	$(document).on('click', '.stsrc-promo-modal__close, .stsrc-promo-modal-cancel, .stsrc-promo-modal__overlay', function() {
		closeModal();
	});

	$(document).on('submit', '#stsrc-promo-code-form', submitPromoForm);

	$(document).on('click', '.stsrc-delete-promo-code', function() {
		var codeId = $(this).data('id');
		if (!window.confirm(stsrcPromoAdmin.strings.confirmDelete || 'Delete this promo code?')) {
			return;
		}

		$.post(stsrcPromoAdmin.ajaxUrl, {
			action: 'stsrc_delete_promo_code',
			nonce: stsrcPromoAdmin.nonce,
			code_id: codeId
		}).done(function(response) {
			if (!response.success) {
				window.alert(response.data && response.data.message ? response.data.message : 'Unable to delete promo code.');
				return;
			}
			window.location.reload();
		});
	});

	$(document).on('click', '.stsrc-toggle-promo-code', function() {
		var codeId = $(this).data('id');
		var next = Number($(this).data('next'));
		var message = next === 1 ? stsrcPromoAdmin.strings.confirmActivate : stsrcPromoAdmin.strings.confirmDeactivate;

		if (!window.confirm(message || 'Update promo code status?')) {
			return;
		}

		$.post(stsrcPromoAdmin.ajaxUrl, {
			action: 'stsrc_update_promo_code',
			nonce: stsrcPromoAdmin.nonce,
			code_id: codeId,
			is_active: next
		}).done(function(response) {
			if (!response.success) {
				window.alert(response.data && response.data.message ? response.data.message : 'Unable to update status.');
				return;
			}
			window.location.reload();
		});
	});

	$(document).on('click', '.stsrc-toggle-payout-status', function() {
		var referralId = $(this).data('id');
		var nextStatus = $(this).data('next');

		$.post(stsrcPromoAdmin.ajaxUrl, {
			action: 'stsrc_toggle_payout_status',
			nonce: stsrcPromoAdmin.nonce,
			referral_id: referralId,
			status: nextStatus
		}).done(function(response) {
			if (!response.success) {
				window.alert(response.data && response.data.message ? response.data.message : 'Unable to update payout status.');
				return;
			}
			window.location.reload();
		});
	});
})(jQuery);

