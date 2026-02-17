<?php
/**
 * Member transaction history table partial
 *
 * Displays transaction history for a member on the admin member edit page.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/admin/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Support both variable names for flexibility
if ( isset( $transaction_history_data['member_id'] ) ) {
	$member_id = $transaction_history_data['member_id'];
} elseif ( isset( $data['member_id'] ) ) {
	$member_id = $data['member_id'];
} else {
	$member_id = 0;
}

if ( empty( $member_id ) ) {
	return;
}

// Load required classes
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-transaction-db.php';

// Get filter parameters
$current_year = isset( $_GET['transaction_year'] ) ? absint( wp_unslash( $_GET['transaction_year'] ) ) : (int) gmdate( 'Y' );
$current_page = isset( $_GET['transaction_page'] ) ? absint( wp_unslash( $_GET['transaction_page'] ) ) : 1;
$current_page = max( 1, $current_page );
$per_page     = 20;

// Get transactions
$transactions = STSRC_Transaction_DB::get_transactions( $member_id, $current_year, $current_page, $per_page );
$total_count  = STSRC_Transaction_DB::count_transactions( $member_id, $current_year );
$total_pages  = ceil( $total_count / $per_page );

// Get available years (for filter dropdown)
$all_transactions = STSRC_Transaction_DB::get_transactions( $member_id, null, 1, 1000 );
$available_years  = array();
foreach ( $all_transactions as $transaction ) {
	$year = date( 'Y', strtotime( $transaction['created_at'] ) );
	if ( ! in_array( $year, $available_years, true ) ) {
		$available_years[] = $year;
	}
}
rsort( $available_years );
?>

<div class="stsrc-form-section" id="stsrc-transaction-history-section" style="display: none;">
	<h2><?php echo esc_html__( 'Transaction History', 'smoketree-plugin' ); ?></h2>

	<!-- Filters -->
	<div class="stsrc-transaction-filters" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
		<label for="stsrc-transaction-year-filter" style="font-weight: 600;">
			<?php echo esc_html__( 'Filter by Year:', 'smoketree-plugin' ); ?>
		</label>
		<select id="stsrc-transaction-year-filter" style="max-width: 150px;">
			<option value=""><?php echo esc_html__( 'All Years', 'smoketree-plugin' ); ?></option>
			<?php foreach ( $available_years as $year ) : ?>
				<option value="<?php echo esc_attr( $year ); ?>" <?php selected( $current_year, $year ); ?>>
					<?php echo esc_html( $year ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<span style="margin-left: auto; color: #646970;">
			<?php
			printf(
				esc_html__( 'Showing %d transactions', 'smoketree-plugin' ),
				count( $transactions )
			);
			?>
		</span>
	</div>

	<!-- Transaction Table -->
	<?php if ( ! empty( $transactions ) ) : ?>
		<div class="stsrc-transaction-table-wrapper">
			<table class="wp-list-table widefat fixed striped" id="stsrc-transaction-table">
				<thead>
					<tr>
						<th style="width: 130px;"><?php echo esc_html__( 'Date', 'smoketree-plugin' ); ?></th>
						<th style="width: 100px;"><?php echo esc_html__( 'Type', 'smoketree-plugin' ); ?></th>
						<th style="width: 120px;"><?php echo esc_html__( 'Payment Method', 'smoketree-plugin' ); ?></th>
						<th><?php echo esc_html__( 'Description', 'smoketree-plugin' ); ?></th>
						<th style="width: 100px; text-align: right;"><?php echo esc_html__( 'Amount', 'smoketree-plugin' ); ?></th>
						<th style="width: 100px; text-align: right;"><?php echo esc_html__( 'Balance After', 'smoketree-plugin' ); ?></th>
						<th style="width: 100px;"><?php echo esc_html__( 'Admin/Source', 'smoketree-plugin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $transactions as $transaction ) : ?>
						<?php
						$transaction_type   = $transaction['transaction_type'] ?? '';
						$payment_method     = $transaction['payment_method'] ?? '';
						$amount             = (float) ( $transaction['amount'] ?? 0.00 );
						$balance_after      = (float) ( $transaction['balance_after'] ?? 0.00 );
						$description        = $transaction['description'] ?? '';
						$admin_user_id      = $transaction['admin_user_id'] ?? null;
						$admin_notes        = $transaction['admin_notes'] ?? '';
						$created_at         = $transaction['created_at'] ?? '';
						$stripe_payment_id  = $transaction['stripe_payment_intent_id'] ?? '';
						$stripe_session_id  = $transaction['stripe_session_id'] ?? '';

						// Determine row color class based on transaction type
						$row_class = '';
						switch ( $transaction_type ) {
							case 'payment':
								$row_class = 'stsrc-transaction-payment';
								break;
							case 'adjustment':
								$row_class = 'stsrc-transaction-adjustment';
								break;
							case 'fee':
								$row_class = 'stsrc-transaction-fee';
								break;
							case 'initial':
								$row_class = 'stsrc-transaction-initial';
								break;
							case 'refund':
								$row_class = 'stsrc-transaction-refund';
								break;
						}

						// Format date
						$formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created_at ) );

						// Get admin user name if applicable
						$admin_name = '';
						if ( ! empty( $admin_user_id ) ) {
							$admin_user = get_user_by( 'id', $admin_user_id );
							if ( $admin_user ) {
								$admin_name = $admin_user->display_name;
							}
						}
						?>
						<tr class="<?php echo esc_attr( $row_class ); ?>">
							<td><?php echo esc_html( $formatted_date ); ?></td>
							<td>
								<span class="stsrc-transaction-type-badge stsrc-type-<?php echo esc_attr( $transaction_type ); ?>">
									<?php echo esc_html( ucfirst( $transaction_type ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! empty( $payment_method ) && 'initial' !== $payment_method ) : ?>
									<span class="stsrc-payment-method-badge">
										<?php
										$method_labels = array(
											'card'             => __( 'Card', 'smoketree-plugin' ),
											'us_bank_account'  => __( 'ACH', 'smoketree-plugin' ),
											'check'            => __( 'Check', 'smoketree-plugin' ),
											'zelle'            => __( 'Zelle', 'smoketree-plugin' ),
											'cash'             => __( 'Cash', 'smoketree-plugin' ),
											'admin_adjustment' => __( 'Admin', 'smoketree-plugin' ),
										);
										echo esc_html( $method_labels[ $payment_method ] ?? ucfirst( str_replace( '_', ' ', $payment_method ) ) );
										?>
									</span>
								<?php else : ?>
									<span style="color: #646970;">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $description ); ?>
								<?php if ( ! empty( $admin_notes ) ) : ?>
									<br>
									<small style="color: #646970;">
										<em><?php echo esc_html__( 'Notes:', 'smoketree-plugin' ); ?> <?php echo esc_html( $admin_notes ); ?></em>
									</small>
								<?php endif; ?>
							</td>
							<td style="text-align: right; font-weight: 600;">
								<span class="<?php echo esc_attr( $amount < 0 ? 'stsrc-amount-negative' : 'stsrc-amount-positive' ); ?>">
									<?php echo $amount > 0 ? '+' : ''; ?>$<?php echo esc_html( number_format( abs( $amount ), 2 ) ); ?>
								</span>
							</td>
							<td style="text-align: right; font-weight: 500;">
								$<?php echo esc_html( number_format( $balance_after, 2 ) ); ?>
							</td>
							<td>
								<?php if ( ! empty( $admin_name ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $admin_user_id ) ); ?>" target="_blank">
										<?php echo esc_html( $admin_name ); ?>
									</a>
								<?php elseif ( ! empty( $stripe_payment_id ) ) : ?>
									<a href="https://dashboard.stripe.com/payments/<?php echo esc_attr( $stripe_payment_id ); ?>" target="_blank" style="color: #635bff;">
										Stripe ↗
									</a>
								<?php elseif ( ! empty( $stripe_session_id ) ) : ?>
									<a href="https://dashboard.stripe.com/checkout/sessions/<?php echo esc_attr( $stripe_session_id ); ?>" target="_blank" style="color: #635bff;">
										Stripe ↗
									</a>
								<?php else : ?>
									<span style="color: #646970;">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="stsrc-transaction-pagination" style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
				<div>
					<?php
					printf(
						esc_html__( 'Page %d of %d', 'smoketree-plugin' ),
						$current_page,
						$total_pages
					);
					?>
				</div>
				<div style="display: flex; gap: 5px;">
					<?php if ( $current_page > 1 ) : ?>
						<button type="button" class="button stsrc-transaction-page-btn" data-page="1">
							<?php echo esc_html__( 'First', 'smoketree-plugin' ); ?>
						</button>
						<button type="button" class="button stsrc-transaction-page-btn" data-page="<?php echo esc_attr( $current_page - 1 ); ?>">
							<?php echo esc_html__( 'Previous', 'smoketree-plugin' ); ?>
						</button>
					<?php endif; ?>

					<?php if ( $current_page < $total_pages ) : ?>
						<button type="button" class="button stsrc-transaction-page-btn" data-page="<?php echo esc_attr( $current_page + 1 ); ?>">
							<?php echo esc_html__( 'Next', 'smoketree-plugin' ); ?>
						</button>
						<button type="button" class="button stsrc-transaction-page-btn" data-page="<?php echo esc_attr( $total_pages ); ?>">
							<?php echo esc_html__( 'Last', 'smoketree-plugin' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div style="padding: 40px; text-align: center; background: #f9f9f9; border: 1px dashed #c3c4c7; border-radius: 4px;">
			<p style="margin: 0; color: #646970; font-size: 14px;">
				<?php echo esc_html__( 'No transactions found for the selected year.', 'smoketree-plugin' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>

