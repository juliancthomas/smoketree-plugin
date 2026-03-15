<?php
/**
 * One-time migration: wp_smoketree_family_members → wp_stsrc_family_members
 *
 * Maps old primary_member_id to new member_id via the stsrc_old_member_id
 * user-meta stored by the primary member migration.
 *
 * Usage (WP-CLI — recommended):
 *   wp eval-file migrate-family-members.php              # dry run (default)
 *   wp eval-file migrate-family-members.php -- --run     # execute migration
 *
 * Usage (browser — admin only):
 *   Visit /wp-content/plugins/smoketree-plugin/migrate-family-members.php
 *   Add &run=1 to the URL to execute (after reviewing dry-run output).
 *
 * Delete this file after migration is complete.
 */

/* ---------- Bootstrap WordPress if not already loaded ---------- */
if ( ! defined( 'ABSPATH' ) ) {
	$wp_load = dirname( __FILE__, 4 ) . '/wp-load.php';
	if ( ! file_exists( $wp_load ) ) {
		echo "Could not locate wp-load.php. Run via WP-CLI instead:\n";
		echo "  wp eval-file migrate-family-members.php\n";
		exit( 1 );
	}
	require_once $wp_load;
}

/* ---------- Determine output mode ---------- */
$is_cli = ( php_sapi_name() === 'cli' || defined( 'WP_CLI' ) );

if ( ! $is_cli ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized.' );
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
}

/* ---------- Determine dry-run vs live ---------- */
if ( $is_cli ) {
	$args    = $GLOBALS['argv'] ?? array();
	$do_run  = in_array( '--run', $args, true );
} else {
	$do_run = isset( $_GET['run'] ) && $_GET['run'] === '1';
}

$dry_run = ! $do_run;

/* ---------- Helpers ---------- */

function mfm_log( string $msg ): void {
	echo $msg . "\n";
}

/**
 * Split a full name into first_name / last_name on the first space.
 * Single-word names get an empty last_name.
 */
function mfm_split_name( string $full_name ): array {
	$full_name   = trim( $full_name );
	$first_space = strpos( $full_name, ' ' );

	if ( false === $first_space ) {
		return array(
			'first_name' => $full_name,
			'last_name'  => '',
		);
	}

	return array(
		'first_name' => trim( substr( $full_name, 0, $first_space ) ),
		'last_name'  => trim( substr( $full_name, $first_space + 1 ) ),
	);
}

/* ---------- Run migration ---------- */

global $wpdb;

mfm_log( '=== Family Members Migration ===' );
mfm_log( $dry_run ? 'MODE: DRY RUN (pass --run to execute)' : 'MODE: LIVE — changes will be written' );
mfm_log( '' );

$old_table = $wpdb->prefix . 'smoketree_family_members';
$new_table = $wpdb->prefix . 'stsrc_family_members';

// Verify both tables exist.
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) !== $old_table ) {
	mfm_log( "ERROR: Old table {$old_table} does not exist." );
	exit( 1 );
}
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) ) !== $new_table ) {
	mfm_log( "ERROR: New table {$new_table} does not exist." );
	exit( 1 );
}

// Build member-ID map: old member id → new member_id.
$mapping_rows = $wpdb->get_results(
	"SELECT um.meta_value AS old_id, m.member_id AS new_id
	 FROM {$wpdb->usermeta} um
	 JOIN {$wpdb->prefix}stsrc_members m ON m.user_id = um.user_id
	 WHERE um.meta_key = 'stsrc_old_member_id'",
	ARRAY_A
);

$member_id_map = array();
foreach ( $mapping_rows as $row ) {
	$member_id_map[ (int) $row['old_id'] ] = (int) $row['new_id'];
}

mfm_log( sprintf( 'Member-ID map loaded: %d entries', count( $member_id_map ) ) );

// IDs / values to skip.
$spam_member_ids = array( 139, 140, 141, 142, 143, 144 );
$junk_record_ids = array( 75 ); // full_name='full_name', email='email'

// Fetch old rows, active first so the first-seen record wins dedup.
$old_rows = $wpdb->get_results(
	"SELECT * FROM {$old_table} ORDER BY isDeleted ASC, id ASC",
	ARRAY_A
);

mfm_log( sprintf( 'Old table rows: %d', count( $old_rows ) ) );
mfm_log( '' );

// Counters.
$stats = array(
	'migrated'     => 0,
	'skipped_junk' => 0,
	'skipped_spam' => 0,
	'skipped_orphan' => 0,
	'skipped_unmapped' => 0,
	'skipped_dup'  => 0,
	'skipped_exists' => 0,
	'errors'       => 0,
);
$error_messages   = array();
$orphan_details   = array();
$unmapped_details = array();
$single_name_rows = array();

// Track seen combos to dedup within the migration batch.
$seen = array();

$now = current_time( 'mysql' );

foreach ( $old_rows as $row ) {
	$old_id            = (int) $row['id'];
	$primary_member_id = (int) $row['primary_member_id'];

	// Skip junk records.
	if ( in_array( $old_id, $junk_record_ids, true ) ) {
		$stats['skipped_junk']++;
		continue;
	}

	// Skip spam members' family.
	if ( in_array( $primary_member_id, $spam_member_ids, true ) ) {
		$stats['skipped_spam']++;
		continue;
	}

	// Skip orphans (no parent member).
	if ( 0 === $primary_member_id ) {
		$stats['skipped_orphan']++;
		$orphan_details[] = sprintf(
			'  id=%d  name="%s"  email="%s"  legacy=%d',
			$old_id,
			$row['full_name'],
			$row['email'],
			$row['isLegacy']
		);
		continue;
	}

	// Map to new member_id.
	$new_member_id = $member_id_map[ $primary_member_id ] ?? null;
	if ( null === $new_member_id ) {
		$stats['skipped_unmapped']++;
		$unmapped_details[] = sprintf(
			'  id=%d  primary_member_id=%d  name="%s"',
			$old_id,
			$primary_member_id,
			$row['full_name']
		);
		continue;
	}

	// Split name.
	$name       = mfm_split_name( $row['full_name'] );
	$first_name = sanitize_text_field( $name['first_name'] );
	$last_name  = sanitize_text_field( $name['last_name'] );

	if ( '' === $first_name ) {
		$stats['skipped_junk']++;
		continue;
	}

	// Log single-word names (still migrated, just flagged for review).
	if ( '' === $last_name ) {
		$single_name_rows[] = sprintf(
			'  id=%d  name="%s"  → first="%s" last=""',
			$old_id,
			$row['full_name'],
			$first_name
		);
	}

	// Dedup within this batch.
	$dedup_key = "{$new_member_id}|{$first_name}|{$last_name}";
	if ( isset( $seen[ $dedup_key ] ) ) {
		$stats['skipped_dup']++;
		continue;
	}
	$seen[ $dedup_key ] = true;

	// Check if already exists in new table.
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$new_table}
			 WHERE member_id = %d AND first_name = %s AND last_name = %s",
			$new_member_id,
			$first_name,
			$last_name
		)
	);
	if ( $exists > 0 ) {
		$stats['skipped_exists']++;
		continue;
	}

	// Prepare values.
	$status     = ( (int) $row['isDeleted'] === 1 ) ? 'deleted' : 'active';
	$created_at = ( '0000-00-00 00:00:00' === $row['created_at'] || empty( $row['created_at'] ) )
		? $now
		: $row['created_at'];
	$email      = ( empty( $row['email'] ) || '' === trim( $row['email'] ) ) ? null : sanitize_email( $row['email'] );

	if ( $dry_run ) {
		mfm_log( sprintf(
			'[DRY] Would insert: member_id=%d  first="%s"  last="%s"  email=%s  status=%s  created=%s',
			$new_member_id,
			$first_name,
			$last_name,
			$email ?? 'NULL',
			$status,
			$created_at
		) );
		$stats['migrated']++;
		continue;
	}

	// Live insert — use raw query so we can properly handle NULL email.
	if ( null === $email ) {
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$new_table}
				 (member_id, first_name, last_name, email, status, created_at, updated_at)
				 VALUES (%d, %s, %s, NULL, %s, %s, %s)",
				$new_member_id,
				$first_name,
				$last_name,
				$status,
				$created_at,
				$now
			)
		);
	} else {
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$new_table}
				 (member_id, first_name, last_name, email, status, created_at, updated_at)
				 VALUES (%d, %s, %s, %s, %s, %s, %s)",
				$new_member_id,
				$first_name,
				$last_name,
				$email,
				$status,
				$created_at,
				$now
			)
		);
	}

	if ( false === $result ) {
		$stats['errors']++;
		$error_messages[] = sprintf(
			'  id=%d  member_id=%d  name="%s %s"  DB error: %s',
			$old_id,
			$new_member_id,
			$first_name,
			$last_name,
			$wpdb->last_error
		);
	} else {
		$stats['migrated']++;
	}
}

/* ---------- Report ---------- */

mfm_log( '' );
mfm_log( '=== Results ===' );
mfm_log( sprintf( 'Migrated:                %d', $stats['migrated'] ) );
mfm_log( sprintf( 'Skipped (junk):          %d', $stats['skipped_junk'] ) );
mfm_log( sprintf( 'Skipped (spam parent):   %d', $stats['skipped_spam'] ) );
mfm_log( sprintf( 'Skipped (orphan, no parent): %d', $stats['skipped_orphan'] ) );
mfm_log( sprintf( 'Skipped (unmapped parent):   %d', $stats['skipped_unmapped'] ) );
mfm_log( sprintf( 'Skipped (dup in batch):  %d', $stats['skipped_dup'] ) );
mfm_log( sprintf( 'Skipped (already exists): %d', $stats['skipped_exists'] ) );
mfm_log( sprintf( 'Errors:                  %d', $stats['errors'] ) );

if ( ! empty( $orphan_details ) ) {
	mfm_log( '' );
	mfm_log( '--- Orphan records (primary_member_id = 0) ---' );
	mfm_log( 'These have no parent member and cannot be linked. Review manually:' );
	foreach ( $orphan_details as $line ) {
		mfm_log( $line );
	}
}

if ( ! empty( $unmapped_details ) ) {
	mfm_log( '' );
	mfm_log( '--- Unmapped parent IDs (old member not migrated) ---' );
	mfm_log( 'The parent member was not found in the new system:' );
	foreach ( $unmapped_details as $line ) {
		mfm_log( $line );
	}
}

if ( ! empty( $single_name_rows ) ) {
	mfm_log( '' );
	mfm_log( '--- Single-word names (migrated with empty last_name) ---' );
	foreach ( $single_name_rows as $line ) {
		mfm_log( $line );
	}
}

if ( ! empty( $error_messages ) ) {
	mfm_log( '' );
	mfm_log( '--- Errors ---' );
	foreach ( $error_messages as $line ) {
		mfm_log( $line );
	}
}

mfm_log( '' );
if ( $dry_run ) {
	mfm_log( 'This was a DRY RUN. No data was written.' );
	mfm_log( 'To execute: wp eval-file migrate-family-members.php -- --run' );
} else {
	mfm_log( 'Migration complete. Delete this file when finished.' );
}
