<?php

/**
 * Migration admin page
 *
 * @link       https://smoketree.us
 * @since      1.0.0
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 */

/**
 * Migration admin page class.
 *
 * Provides interface for migrating legacy members.
 *
 * @since      1.0.0
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/pages
 * @author     Smoketree Swim and Recreation Club
 */
class STSRC_Migration_Page {

	/**
	 * Initialize the class and add hooks.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
		add_action( 'admin_post_stsrc_run_migration', array( $this, 'handle_migration' ) );
		add_action( 'admin_post_stsrc_run_family_migration', array( $this, 'handle_family_migration' ) );
	}

	/**
	 * Add migration page to admin menu.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'stsrc-dashboard',
			__( 'Migration', 'smoketree-plugin' ),
			__( 'Migration', 'smoketree-plugin' ),
			'manage_options',
			'smoketree-migration',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the migration page.
	 *
	 * @since    1.0.0
	 */
	public function render_page(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		// Load migrator classes
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/migration/class-stsrc-legacy-member-migrator.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/migration/class-stsrc-legacy-family-member-migrator.php';

		// Get dry run previews
		$preview        = STSRC_Legacy_Member_Migrator::dry_run();
		$family_preview = STSRC_Legacy_Family_Member_Migrator::dry_run();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// Show success message if member migration just completed
			if ( isset( $_GET['migration_complete'] ) && '1' === $_GET['migration_complete'] ) {
				$results = get_transient( 'stsrc_migration_results' );
				if ( $results ) {
					$this->display_migration_results( 'Legacy Member Migration', $results );
					delete_transient( 'stsrc_migration_results' );
				}
			}

			// Show success message if family migration just completed
			if ( isset( $_GET['family_migration_complete'] ) && '1' === $_GET['family_migration_complete'] ) {
				$family_results = get_transient( 'stsrc_family_migration_results' );
				if ( $family_results ) {
					$this->display_family_migration_results( $family_results );
					delete_transient( 'stsrc_family_migration_results' );
				}
			}
			?>

			<div class="card" style="max-width: 800px;">
				<h2>Legacy Member Migration</h2>
				
				<p>
					This tool will migrate members from the old <code>wp_smoketree_members</code> table to the new 
					<code>wp_stsrc_members</code> table. The migration will:
				</p>

				<ul style="list-style: disc; padding-left: 30px;">
					<li>Create WordPress user accounts for all members</li>
					<li>Map old membership type IDs to new types</li>
					<li>Convert payment statuses and member statuses</li>
					<li>Flag migrated users for password reset on first login</li>
					<li>Skip spam/test records (last 6 entries)</li>
					<li>Skip members that have already been migrated</li>
				</ul>

				<?php if ( isset( $preview['error'] ) ) : ?>
					<div class="notice notice-error">
						<p><strong>Error:</strong> <?php echo esc_html( $preview['error'] ); ?></p>
					</div>
				<?php else : ?>

					<h3>Migration Preview</h3>
					
					<table class="widefat" style="margin-bottom: 20px;">
						<tbody>
							<tr>
								<td><strong>Total Records in Old Table:</strong></td>
								<td><?php echo esc_html( $preview['total_records'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Spam Records (will skip):</strong></td>
								<td><?php echo esc_html( $preview['spam_records'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Already Migrated (will skip):</strong></td>
								<td><?php echo esc_html( $preview['already_exists'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Ready to Migrate:</strong></td>
								<td><strong><?php echo esc_html( $preview['to_migrate'] ); ?></strong></td>
							</tr>
						</tbody>
					</table>

					<?php if ( $preview['to_migrate'] > 0 ) : ?>
						<h4>Breakdown by Status:</h4>
						<table class="widefat" style="margin-bottom: 20px;">
							<thead>
								<tr>
									<th>Status</th>
									<th>Count</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $preview['breakdown_by_status'] as $status => $count ) : ?>
									<tr>
										<td><?php echo esc_html( ucfirst( $status ) ); ?></td>
										<td><?php echo esc_html( $count ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<h4>Breakdown by Membership Type:</h4>
						<table class="widefat" style="margin-bottom: 20px;">
							<thead>
								<tr>
									<th>Membership Type</th>
									<th>Count</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $preview['breakdown_by_type'] as $type => $count ) : ?>
									<tr>
										<td><?php echo esc_html( $type ); ?></td>
										<td><?php echo esc_html( $count ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<div class="notice notice-warning inline">
							<p>
								<strong>Important:</strong> This migration will create <?php echo esc_html( $preview['to_migrate'] ); ?> 
								WordPress user accounts and member records. This action cannot be easily undone. 
								Please ensure you have a database backup before proceeding.
							</p>
						</div>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Are you sure you want to run the migration? This will create <?php echo esc_js( $preview['to_migrate'] ); ?> new user accounts and member records.');">
							<?php wp_nonce_field( 'stsrc_run_migration', 'stsrc_migration_nonce' ); ?>
							<input type="hidden" name="action" value="stsrc_run_migration">
							<p>
								<button type="submit" class="button button-primary button-large">
									Run Migration (<?php echo esc_html( $preview['to_migrate'] ); ?> members)
								</button>
							</p>
						</form>

					<?php else : ?>
						<div class="notice notice-info inline">
							<p>No members to migrate. All members have already been migrated.</p>
						</div>
					<?php endif; ?>

				<?php endif; ?>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>Post-Migration Notes</h2>
				
				<p>After migration:</p>
				
				<ul style="list-style: disc; padding-left: 30px;">
					<li><strong>Legacy Password System:</strong> Migrated users will be required to reset their password on first login.</li>
					<li><strong>Reactivation:</strong> If a cancelled member tries to register again, they will receive a reactivation email.</li>
					<li><strong>Guest Passes:</strong> Guest pass balances are NOT migrated automatically. You'll need to run a separate migration if needed.</li>
					<li><strong>Family Members:</strong> Use the Family Members Migration section below to migrate family members.</li>
					<li><strong>Extra Members:</strong> Extra members are NOT migrated automatically. You'll need to run a separate migration if needed.</li>
				</ul>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>Family Members Migration</h2>

				<p>
					This tool migrates family members from the old <code>wp_smoketree_family_members</code> table
					to the new <code>wp_stsrc_family_members</code> table. The migration will:
				</p>

				<ul style="list-style: disc; padding-left: 30px;">
					<li>Map old parent member IDs to the new system (via primary migration metadata)</li>
					<li>Split <code>full_name</code> into <code>first_name</code> / <code>last_name</code></li>
					<li>Convert <code>isDeleted</code> to <code>status</code> (active / deleted)</li>
					<li>Deduplicate identical name entries per member</li>
					<li>Skip spam, junk, and orphan records</li>
					<li>Safe to run multiple times (idempotent)</li>
				</ul>

				<?php if ( isset( $family_preview['error'] ) ) : ?>
					<div class="notice notice-error inline">
						<p><strong>Error:</strong> <?php echo esc_html( $family_preview['error'] ); ?></p>
					</div>
				<?php else : ?>

					<h3>Migration Preview</h3>

					<table class="widefat" style="margin-bottom: 20px;">
						<tbody>
							<tr>
								<td><strong>Total Records in Old Table:</strong></td>
								<td><?php echo esc_html( $family_preview['total_old_rows'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Ready to Migrate:</strong></td>
								<td><strong><?php echo esc_html( $family_preview['migrated'] ); ?></strong></td>
							</tr>
							<tr>
								<td><strong>Skipped (junk):</strong></td>
								<td><?php echo esc_html( $family_preview['skipped_junk'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Skipped (spam parent):</strong></td>
								<td><?php echo esc_html( $family_preview['skipped_spam'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Skipped (orphan — no parent):</strong></td>
								<td><?php echo esc_html( $family_preview['skipped_orphan'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Skipped (unmapped parent):</strong></td>
								<td><?php echo esc_html( $family_preview['skipped_unmapped'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Skipped (duplicate):</strong></td>
								<td><?php echo esc_html( $family_preview['skipped_dup'] ); ?></td>
							</tr>
							<tr>
								<td><strong>Already in new table:</strong></td>
								<td><?php echo esc_html( $family_preview['skipped_exists'] ); ?></td>
							</tr>
						</tbody>
					</table>

					<?php if ( ! empty( $family_preview['orphan_details'] ) ) : ?>
						<details style="margin-bottom: 15px;">
							<summary><strong>Orphan records (<?php echo count( $family_preview['orphan_details'] ); ?>)</strong> — no parent member, skipped</summary>
							<pre style="background: #f5f5f5; padding: 10px; margin-top: 5px; overflow-x: auto;"><?php
								echo esc_html( implode( "\n", $family_preview['orphan_details'] ) );
							?></pre>
						</details>
					<?php endif; ?>

					<?php if ( ! empty( $family_preview['unmapped_details'] ) ) : ?>
						<details style="margin-bottom: 15px;">
							<summary><strong>Unmapped parents (<?php echo count( $family_preview['unmapped_details'] ); ?>)</strong> — old member not in new system, skipped</summary>
							<pre style="background: #f5f5f5; padding: 10px; margin-top: 5px; overflow-x: auto;"><?php
								echo esc_html( implode( "\n", $family_preview['unmapped_details'] ) );
							?></pre>
						</details>
					<?php endif; ?>

					<?php if ( ! empty( $family_preview['single_name_rows'] ) ) : ?>
						<details style="margin-bottom: 15px;">
							<summary><strong>Single-word names (<?php echo count( $family_preview['single_name_rows'] ); ?>)</strong> — migrated with empty last name</summary>
							<pre style="background: #f5f5f5; padding: 10px; margin-top: 5px; overflow-x: auto;"><?php
								echo esc_html( implode( "\n", $family_preview['single_name_rows'] ) );
							?></pre>
						</details>
					<?php endif; ?>

					<?php if ( $family_preview['migrated'] > 0 ) : ?>
						<div class="notice notice-warning inline">
							<p>
								<strong>Important:</strong> This will insert <?php echo esc_html( $family_preview['migrated'] ); ?>
								family member records. Please ensure you have a database backup before proceeding.
							</p>
						</div>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Are you sure? This will migrate <?php echo esc_js( $family_preview['migrated'] ); ?> family members.');">
							<?php wp_nonce_field( 'stsrc_run_family_migration', 'stsrc_family_migration_nonce' ); ?>
							<input type="hidden" name="action" value="stsrc_run_family_migration">
							<p>
								<button type="submit" class="button button-primary button-large">
									Run Family Migration (<?php echo esc_html( $family_preview['migrated'] ); ?> records)
								</button>
							</p>
						</form>

					<?php else : ?>
						<div class="notice notice-info inline">
							<p>No family members to migrate. All eligible records have already been migrated.</p>
						</div>
					<?php endif; ?>

				<?php endif; ?>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>Affiliate Code Backfill</h2>
				<p>
					Use this tool to generate affiliate codes for existing members who do not already have one.
					This can be safely run multiple times and only fills missing codes.
				</p>
				<p>
					<button type="button" class="button button-secondary" id="stsrc-run-affiliate-backfill-btn">
						Backfill Affiliate Codes
					</button>
				</p>
				<div id="stsrc-affiliate-backfill-result" style="display:none;"></div>
			</div>
		</div>
		<script>
			(function($) {
				'use strict';
				$(function() {
					var $button = $('#stsrc-run-affiliate-backfill-btn');
					var $result = $('#stsrc-affiliate-backfill-result');
					if ($button.length === 0) {
						return;
					}

					$button.on('click', function() {
						if (!window.confirm('Run affiliate code backfill now?')) {
							return;
						}

						$button.prop('disabled', true).text('Running backfill...');
						$result.hide().removeClass('notice notice-success notice-error').empty();

						$.post(ajaxurl, {
							action: 'stsrc_run_affiliate_backfill',
							nonce: '<?php echo esc_js( wp_create_nonce( 'stsrc_admin_nonce' ) ); ?>'
						}).done(function(response) {
							if (!response || !response.success) {
								var msg = (response && response.data && response.data.message) ? response.data.message : 'Backfill failed.';
								$result.addClass('notice notice-error').html('<p><strong>Error:</strong> ' + msg + '</p>').show();
								return;
							}

							var data = response.data || {};
							var errors = Array.isArray(data.errors) ? data.errors : [];
							var html = '<p><strong>Backfill complete.</strong><br>' +
								'Processed: ' + Number(data.processed || 0) + '<br>' +
								'Skipped: ' + Number(data.skipped || 0) + '<br>' +
								'Errors: ' + errors.length + '</p>';
							if (errors.length > 0) {
								html += '<ul style="list-style: disc; padding-left: 30px;">';
								errors.forEach(function(error) {
									html += '<li>' + String(error) + '</li>';
								});
								html += '</ul>';
							}

							$result.addClass(errors.length ? 'notice notice-error' : 'notice notice-success').html(html).show();
						}).fail(function() {
							$result.addClass('notice notice-error').html('<p><strong>Error:</strong> Request failed.</p>').show();
						}).always(function() {
							$button.prop('disabled', false).text('Backfill Affiliate Codes');
						});
					});
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Handle migration form submission.
	 *
	 * @since    1.0.0
	 */
	public function handle_migration(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['stsrc_migration_nonce'] ) || ! wp_verify_nonce( $_POST['stsrc_migration_nonce'], 'stsrc_run_migration' ) ) {
			wp_die( __( 'Security check failed.', 'smoketree-plugin' ) );
		}

		// Load migrator class
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/migration/class-stsrc-legacy-member-migrator.php';
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-database.php';

		// Set time limit for large migrations
		set_time_limit( 300 ); // 5 minutes

		// Run related-table status column migration for existing installs.
		STSRC_Database::add_status_columns_to_related_tables();

		// Run migration
		$results = STSRC_Legacy_Member_Migrator::run_migration();

		// Store results in transient to display on redirect
		set_transient( 'stsrc_migration_results', $results, 60 );

		// Redirect back to migration page with success flag
		wp_redirect( add_query_arg( 'migration_complete', '1', admin_url( 'admin.php?page=smoketree-migration' ) ) );
		exit;
	}

	/**
	 * Display migration results.
	 *
	 * @since    1.0.0
	 * @param    string   $title      Section title
	 * @param    array    $results    Migration results
	 */
	private function display_migration_results( string $title, array $results ): void {
		$is_success = $results['errors'] === 0 && $results['successful'] > 0;
		$notice_class = $is_success ? 'notice-success' : 'notice-warning';

		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<h3><?php echo esc_html( $title ); ?> Complete</h3>
			
			<p>
				<strong>Total Processed:</strong> <?php echo esc_html( $results['total_processed'] ); ?><br>
				<strong>Successfully Migrated:</strong> <?php echo esc_html( $results['successful'] ); ?><br>
				<strong>Skipped (already exist):</strong> <?php echo esc_html( $results['skipped'] ); ?><br>
				<strong>Errors:</strong> <?php echo esc_html( $results['errors'] ); ?>
			</p>

			<?php if ( ! empty( $results['error_messages'] ) ) : ?>
				<h4>Error Messages:</h4>
				<ul style="list-style: disc; padding-left: 30px;">
					<?php foreach ( $results['error_messages'] as $error_message ) : ?>
						<li><?php echo esc_html( $error_message ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle family member migration form submission.
	 *
	 * @since    1.3.0
	 */
	public function handle_family_migration(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'smoketree-plugin' ) );
		}

		if ( ! isset( $_POST['stsrc_family_migration_nonce'] ) || ! wp_verify_nonce( $_POST['stsrc_family_migration_nonce'], 'stsrc_run_family_migration' ) ) {
			wp_die( __( 'Security check failed.', 'smoketree-plugin' ) );
		}

		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/migration/class-stsrc-legacy-family-member-migrator.php';

		set_time_limit( 300 );

		$results = STSRC_Legacy_Family_Member_Migrator::run_migration();

		set_transient( 'stsrc_family_migration_results', $results, 60 );

		wp_redirect( add_query_arg( 'family_migration_complete', '1', admin_url( 'admin.php?page=smoketree-migration' ) ) );
		exit;
	}

	/**
	 * Display family member migration results.
	 *
	 * @since    1.3.0
	 * @param    array    $results    Migration results
	 */
	private function display_family_migration_results( array $results ): void {
		$has_errors    = ( $results['errors'] ?? 0 ) > 0;
		$notice_class  = $has_errors ? 'notice-warning' : 'notice-success';

		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<h3>Family Members Migration Complete</h3>

			<p>
				<strong>Migrated:</strong> <?php echo esc_html( $results['migrated'] ); ?><br>
				<strong>Skipped (junk):</strong> <?php echo esc_html( $results['skipped_junk'] ); ?><br>
				<strong>Skipped (spam parent):</strong> <?php echo esc_html( $results['skipped_spam'] ); ?><br>
				<strong>Skipped (orphan):</strong> <?php echo esc_html( $results['skipped_orphan'] ); ?><br>
				<strong>Skipped (unmapped parent):</strong> <?php echo esc_html( $results['skipped_unmapped'] ); ?><br>
				<strong>Skipped (duplicate):</strong> <?php echo esc_html( $results['skipped_dup'] ); ?><br>
				<strong>Skipped (already exists):</strong> <?php echo esc_html( $results['skipped_exists'] ); ?><br>
				<strong>Errors:</strong> <?php echo esc_html( $results['errors'] ); ?>
			</p>

			<?php if ( ! empty( $results['error_messages'] ) ) : ?>
				<h4>Error Messages:</h4>
				<ul style="list-style: disc; padding-left: 30px;">
					<?php foreach ( $results['error_messages'] as $msg ) : ?>
						<li><?php echo esc_html( $msg ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
