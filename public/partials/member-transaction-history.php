<?php
/**
 * Member transaction history partial
 *
 * Displays current-year transactions in the member portal.
 *
 * @package    Smoketree_Plugin
 * @subpackage Smoketree_Plugin/public/partials
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/database/class-stsrc-transaction-db.php';

$member_data = $data['member'] ?? array();
$member_id   = isset( $member_data['member_id'] ) ? (int) $member_data['member_id'] : 0;
$year        = (int) gmdate( 'Y' );

$transactions = array();
if ( $member_id > 0 ) {
	$transactions = STSRC_Transaction_DB::get_transactions( $member_id, $year, 1, 200 );
}

$visible_count = 5;
?>

<section class="stsrc-portal-section stsrc-transaction-history" id="stsrc-member-transaction-history">
	<div class="stsrc-transaction-history__header">
		<h2><?php echo esc_html__( 'Transaction History', 'smoketree-plugin' ); ?></h2>
		<p class="stsrc-description">
			<?php
			printf(
				/* translators: %d: year */
				esc_html__( 'Showing transactions for %d.', 'smoketree-plugin' ),
				$year
			);
			?>
		</p>
	</div>

	<?php if ( empty( $transactions ) ) : ?>
		<div class="stsrc-transaction-history__empty">
			<?php echo esc_html__( 'No transactions recorded for this year yet.', 'smoketree-plugin' ); ?>
		</div>
	<?php else : ?>
		<div class="stsrc-transaction-list" data-collapsible="<?php echo esc_attr( count( $transactions ) > $visible_count ? '1' : '0' ); ?>">
			<?php foreach ( $transactions as $index => $transaction ) : ?>
				<?php
				$amount = (float) ( $transaction['amount'] ?? 0 );
				$type   = (string) ( $transaction['transaction_type'] ?? '' );
				$method = (string) ( $transaction['payment_method'] ?? '' );

				$type_label_map = array(
					'payment'    => __( 'Payment', 'smoketree-plugin' ),
					'adjustment' => __( 'Adjustment', 'smoketree-plugin' ),
					'fee'        => __( 'Fee', 'smoketree-plugin' ),
					'refund'     => __( 'Refund', 'smoketree-plugin' ),
					'initial'    => __( 'Initial', 'smoketree-plugin' ),
				);
				$method_label_map = array(
					'card'             => __( 'Card', 'smoketree-plugin' ),
					'us_bank_account'  => __( 'Bank Account', 'smoketree-plugin' ),
					'check'            => __( 'Check', 'smoketree-plugin' ),
					'zelle'            => __( 'Zelle', 'smoketree-plugin' ),
					'cash'             => __( 'Cash', 'smoketree-plugin' ),
					'admin_adjustment' => __( 'Admin Adjustment', 'smoketree-plugin' ),
					'initial'          => __( 'Initial', 'smoketree-plugin' ),
				);

				$type_label   = $type_label_map[ $type ] ?? ucfirst( $type );
				$method_label = $method_label_map[ $method ] ?? ucfirst( str_replace( '_', ' ', $method ) );
				$description  = (string) ( $transaction['description'] ?? '' );

				$amount_class = $amount <= 0 ? 'stsrc-transaction-amount--credit' : 'stsrc-transaction-amount--debit';
				$amount_sign  = $amount > 0 ? '+' : '-';
				$formatted_amount = number_format( abs( $amount ), 2 );
				$is_hidden = $index >= $visible_count;
				?>
				<div class="stsrc-transaction-item<?php echo esc_attr( $is_hidden ? ' stsrc-transaction-item--hidden' : '' ); ?>"<?php echo $is_hidden ? ' aria-hidden="true"' : ''; ?>>
					<div class="stsrc-transaction-item__meta">
						<div class="stsrc-transaction-item__date">
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( (string) ( $transaction['created_at'] ?? '' ) ) ) ); ?>
						</div>
						<div class="stsrc-transaction-item__badges">
							<span class="stsrc-transaction-badge stsrc-transaction-badge--type"><?php echo esc_html( $type_label ); ?></span>
							<span class="stsrc-transaction-badge stsrc-transaction-badge--method"><?php echo esc_html( $method_label ); ?></span>
						</div>
					</div>

					<div class="stsrc-transaction-item__content">
						<div class="stsrc-transaction-item__description"><?php echo esc_html( $description ); ?></div>
						<div class="stsrc-transaction-item__amount <?php echo esc_attr( $amount_class ); ?>">
							<?php echo esc_html( $amount_sign . '$' . $formatted_amount ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( count( $transactions ) > $visible_count ) : ?>
			<div class="stsrc-transaction-history__actions">
				<button type="button" class="stsrc-button stsrc-button-secondary stsrc-transaction-toggle" data-open="0">
					<?php echo esc_html__( 'Show More', 'smoketree-plugin' ); ?>
				</button>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>
