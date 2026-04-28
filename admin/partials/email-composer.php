<?php
/**
 * Email composer template
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$membership_types = $data['membership_types'] ?? array();
$templates = $data['templates'] ?? array();
?>

<div class="wrap">
	<h1><?php echo esc_html__( 'Batch Email Composer', 'smoketree-plugin' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="stsrc-email-composer-form" enctype="multipart/form-data" novalidate>
		<input type="hidden" name="action" value="stsrc_send_batch_email">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>">

		<div class="stsrc-form-sections">
			<!-- Email Content -->
			<div class="stsrc-form-section">
				<h2><?php echo esc_html__( 'Email Content', 'smoketree-plugin' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="subject"><?php echo esc_html__( 'Subject', 'smoketree-plugin' ); ?> <span class="required">*</span></label></th>
						<td>
							<input type="text" name="subject" id="subject" value="" class="large-text" placeholder="<?php echo esc_attr__( 'Email subject line', 'smoketree-plugin' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="template"><?php echo esc_html__( 'Email Template', 'smoketree-plugin' ); ?></label></th>
						<td>
							<select name="template" id="template">
								<option value=""><?php echo esc_html__( 'Custom Message (use WYSIWYG below)', 'smoketree-plugin' ); ?></option>
								<?php foreach ( $templates as $template ) : ?>
									<option value="<?php echo esc_attr( $template ); ?>"><?php echo esc_html( str_replace( array( '.php', '-' ), array( '', ' ' ), $template ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__( 'Select a template to use, or leave blank to compose a custom message.', 'smoketree-plugin' ); ?></p>
						</td>
					</tr>
					<tr id="stsrc-message-row">
					<th><label for="quill-message-editor"><?php echo esc_html__( 'Message', 'smoketree-plugin' ); ?> <span class="required" id="message-required">*</span></label></th>
					<td>
						<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
						<style>
							#quill-message-editor { background: #fff; font-size: 14px; }
							#quill-message-editor .ql-editor { min-height: 300px; }
							.ql-toolbar.ql-snow { border-color: #8c8f94; border-radius: 4px 4px 0 0; }
							.ql-container.ql-snow { border-color: #8c8f94; border-radius: 0 0 4px 4px; }
						</style>
						<div id="quill-message-editor"></div>
						<input type="hidden" name="message" id="message">
						<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
						<script>
						(function () {
							var quill = new Quill( '#quill-message-editor', {
								theme: 'snow',
								placeholder: '<?php echo esc_js( __( 'Compose your message here. Available placeholders: {first_name}, {last_name}, {email}, {member_id}', 'smoketree-plugin' ) ); ?>',
								modules: {
									toolbar: [
										[ { header: [ 1, 2, 3, false ] } ],
										[ 'bold', 'italic', 'underline' ],
										[ { list: 'ordered' }, { list: 'bullet' } ],
										[ { align: [] } ],
									]
								}
							} );

							var $hidden = document.getElementById( 'message' );

							quill.on( 'text-change', function () {
								$hidden.value = quill.root.innerHTML;
							} );

							window.stsrcQuill = quill;
						})();
						</script>
						<p class="description">
							<?php echo esc_html__( 'Available placeholders: {first_name}, {last_name}, {email}, {member_id}. Required if no template is selected.', 'smoketree-plugin' ); ?>
						</p>
					</td>
				</tr>
					<tr id="stsrc-template-preview-row" style="display: none;">
						<th><?php echo esc_html__( 'Template Preview', 'smoketree-plugin' ); ?></th>
						<td>
							<div id="stsrc-template-preview-content"></div>
							<p class="description">
								<?php echo esc_html__( 'This is a preview with sample data. Placeholders like {first_name} will be replaced with each recipient\'s actual information.', 'smoketree-plugin' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="attachments"><?php echo esc_html__( 'Attachments', 'smoketree-plugin' ); ?></label></th>
						<td>
							<input type="file" name="attachments[]" id="attachments" multiple>
							<p class="description"><?php echo esc_html__( 'You can attach multiple files. Maximum file size: ', 'smoketree-plugin' ) . esc_html( size_format( wp_max_upload_size() ) ); ?></p>
						</td>
					</tr>
				</table>
			</div>

		<!-- Quick Send -->
		<div class="stsrc-form-section">
			<h2><?php echo esc_html__( 'Quick Send', 'smoketree-plugin' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Enter email addresses to send to directly. These recipients will receive the email in addition to any filtered members below.', 'smoketree-plugin' ); ?></p>
			<table class="form-table">
				<tr>
					<th><label for="manual_emails"><?php echo esc_html__( 'Email Addresses', 'smoketree-plugin' ); ?></label></th>
					<td>
						<textarea name="manual_emails" id="manual_emails" rows="3" class="large-text" placeholder="<?php echo esc_attr__( 'e.g. john@example.com, jane@example.com', 'smoketree-plugin' ); ?>"></textarea>
						<p class="description"><?php echo esc_html__( 'Comma-separated email addresses. Note: placeholders like {first_name} will not be replaced for these recipients.', 'smoketree-plugin' ); ?></p>
						<div id="stsrc-manual-emails-errors" style="display: none; color: #d63638; margin-top: 6px;"></div>
					</td>
				</tr>
			</table>
		</div>

		<!-- Member Filters -->
		<div class="stsrc-form-section">
			<h2><?php echo esc_html__( 'Recipient Filters', 'smoketree-plugin' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Select filters to target specific members. Leave all filters empty to send to all members.', 'smoketree-plugin' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="membership_type_id"><?php echo esc_html__( 'Membership Type', 'smoketree-plugin' ); ?></label></th>
						<td>
							<select name="membership_type_id" id="membership_type_id">
								<option value=""><?php echo esc_html__( 'All Types', 'smoketree-plugin' ); ?></option>
								<?php foreach ( $membership_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type['membership_type_id'] ); ?>">
										<?php echo esc_html( $type['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="status"><?php echo esc_html__( 'Status', 'smoketree-plugin' ); ?></label></th>
						<td>
							<select name="status" id="status">
								<option value=""><?php echo esc_html__( 'All Statuses', 'smoketree-plugin' ); ?></option>
								<option value="active"><?php echo esc_html__( 'Active', 'smoketree-plugin' ); ?></option>
								<option value="inactive"><?php echo esc_html__( 'Inactive', 'smoketree-plugin' ); ?></option>
								<option value="pending"><?php echo esc_html__( 'Pending', 'smoketree-plugin' ); ?></option>
								<option value="cancelled"><?php echo esc_html__( 'Cancelled', 'smoketree-plugin' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="payment_type"><?php echo esc_html__( 'Payment Type', 'smoketree-plugin' ); ?></label></th>
						<td>
							<select name="payment_type" id="payment_type">
								<option value=""><?php echo esc_html__( 'All Types', 'smoketree-plugin' ); ?></option>
								<option value="card"><?php echo esc_html__( 'Card', 'smoketree-plugin' ); ?></option>
								<option value="bank_account"><?php echo esc_html__( 'Bank Account', 'smoketree-plugin' ); ?></option>
								<option value="zelle"><?php echo esc_html__( 'Zelle', 'smoketree-plugin' ); ?></option>
								<option value="check"><?php echo esc_html__( 'Check', 'smoketree-plugin' ); ?></option>
								<option value="pay_later"><?php echo esc_html__( 'Pay Later', 'smoketree-plugin' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="date_from"><?php echo esc_html__( 'Registered From', 'smoketree-plugin' ); ?></label></th>
						<td>
							<input type="date" name="date_from" id="date_from">
						</td>
					</tr>
					<tr>
						<th><label for="date_to"><?php echo esc_html__( 'Registered To', 'smoketree-plugin' ); ?></label></th>
						<td>
							<input type="date" name="date_to" id="date_to">
						</td>
					</tr>
				</table>

			<table class="form-table" style="margin-top: 0;">
				<tr>
					<th><?php echo esc_html__( 'Include Sub-Members', 'smoketree-plugin' ); ?></th>
					<td>
						<label style="display: inline-flex; align-items: center; gap: 6px; margin-right: 20px; cursor: pointer;">
							<input type="checkbox" id="include_family_members" name="include_family_members" value="1" checked>
							<?php echo esc_html__( 'Family Members', 'smoketree-plugin' ); ?>
						</label>
						<label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
							<input type="checkbox" id="include_extra_members" name="include_extra_members" value="1" checked>
							<?php echo esc_html__( 'Extra Members (paid only)', 'smoketree-plugin' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<!-- Preview Recipient Count -->
			<div class="stsrc-recipient-preview" style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
				<strong><?php echo esc_html__( 'Recipient Preview:', 'smoketree-plugin' ); ?></strong>
				<span id="recipient-count" style="margin-left: 10px;"><?php echo esc_html__( 'Click "Preview Recipients" to see count', 'smoketree-plugin' ); ?></span>
				<button type="button" id="preview-recipients-btn" class="button" style="margin-left: 15px;">
					<?php echo esc_html__( 'Preview Recipients', 'smoketree-plugin' ); ?>
				</button>
			</div>

			<!-- Recipient List -->
			<div id="stsrc-recipient-list-wrap" style="display: none; margin-top: 15px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff;">
				<div style="padding: 10px 15px; border-bottom: 1px solid #c3c4c7; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
					<strong><?php echo esc_html__( 'Recipients', 'smoketree-plugin' ); ?> (<span id="stsrc-selected-count">0</span> <?php echo esc_html__( 'selected', 'smoketree-plugin' ); ?>)</strong>
					<label style="font-weight: normal; margin: 0; display: flex; align-items: center; gap: 5px; cursor: pointer;">
						<input type="checkbox" id="stsrc-select-all-recipients" checked>
						<?php echo esc_html__( 'Select All', 'smoketree-plugin' ); ?>
					</label>
				</div>
				<div id="stsrc-recipient-list" style="max-height: 300px; overflow-y: auto; padding: 10px 15px;"></div>
			</div>
			</div>
		</div>

		<!-- Progress Bar (hidden initially) -->
		<div id="email-progress" style="display: none; margin: 20px 0;">
			<div style="background: #f0f0f1; border-radius: 4px; padding: 10px; margin-bottom: 10px;">
				<div id="progress-bar" style="background: #2271b1; height: 20px; border-radius: 4px; width: 0%; transition: width 0.3s;"></div>
			</div>
			<p id="progress-text" style="text-align: center; margin: 0;"></p>
		</div>

		<!-- Action Buttons -->
		<p class="submit">
			<button type="button" id="send-test-email-btn" class="button">
				<?php echo esc_html__( 'Send Test Email', 'smoketree-plugin' ); ?>
			</button>
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Send Email', 'smoketree-plugin' ); ?>">
		</p>
	</form>
</div>

