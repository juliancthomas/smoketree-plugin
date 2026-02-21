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

		// Load migrator class
		require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/migration/class-stsrc-legacy-member-migrator.php';

		// Get dry run preview
		$preview = STSRC_Legacy_Member_Migrator::dry_run();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// Show success message if migration just completed
			if ( isset( $_GET['migration_complete'] ) && '1' === $_GET['migration_complete'] ) {
				$results = get_transient( 'stsrc_migration_results' );
				if ( $results ) {
					$this->display_migration_results( $results );
					delete_transient( 'stsrc_migration_results' );
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
					<li><strong>Family Members:</strong> Family members are NOT migrated automatically. You'll need to run a separate migration if needed.</li>
					<li><strong>Extra Members:</strong> Extra members are NOT migrated automatically. You'll need to run a separate migration if needed.</li>
				</ul>
			</div>
		</div>
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

		// Set time limit for large migrations
		set_time_limit( 300 ); // 5 minutes

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
	 * @param    array    $results    Migration results
	 */
	private function display_migration_results( array $results ): void {
		$is_success = $results['errors'] === 0 && $results['successful'] > 0;
		$notice_class = $is_success ? 'notice-success' : 'notice-warning';

		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<h3>Migration Complete</h3>
			
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
}
