#!/usr/bin/env bash
# ============================================================
# Stripe Webhook Test — Cleanup
# Smoketree Swim & Recreation Club
# ============================================================
# Resets test data for MEMBER_ID so webhook tests can be
# re-run from a clean state.
#
# USAGE:
#   bash dev/stripe-cleanup.sh
# ============================================================

MEMBER_ID=13
EXTRA_FIRST="Test"
EXTRA_LAST="Member"
WP_PATH="/c/Users/jtrul/Local Sites/smoketree-ai/app/public"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

echo ""
echo -e "${CYAN}${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
echo -e "${CYAN}${BOLD}  Cleanup — Reset test data for member ${MEMBER_ID}${RESET}"
echo -e "${CYAN}${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
echo ""
echo -e "  ${YELLOW}⚠ This will:${RESET}"
echo "    • Set member ${MEMBER_ID} status → pending"
echo "    • Delete all payment log rows for member ${MEMBER_ID} from Stripe events"
echo "    • Delete the test extra member: ${EXTRA_FIRST} ${EXTRA_LAST}"
echo "    • Delete all test transactions for member ${MEMBER_ID}"
echo "    • Restore balance_owed to original_membership_price"
echo "    • Clear the Stripe idempotency cache"
echo ""
read -rp "  Proceed? (y/N): " confirm
[[ "$confirm" =~ ^[Yy]$ ]] || { echo "  Cancelled."; exit 0; }

# ── WP-CLI path ──────────────────────────────────────────────
if ! command -v wp &>/dev/null; then
  echo ""
  echo -e "  ${YELLOW}⚠ WP-CLI not found. Run these SQL statements manually:${RESET}"
  echo ""
  echo "    UPDATE wp_stsrc_members SET status='pending' WHERE member_id=${MEMBER_ID};"
  echo "    DELETE FROM wp_stsrc_payment_logs WHERE member_id=${MEMBER_ID} AND stripe_event_id != '';"
  echo "    DELETE FROM wp_stsrc_extra_members WHERE member_id=${MEMBER_ID} AND first_name='${EXTRA_FIRST}' AND last_name='${EXTRA_LAST}';"
  echo "    DELETE FROM wp_stsrc_transactions WHERE member_id=${MEMBER_ID};"
  echo "    UPDATE wp_stsrc_members SET balance_owed = original_membership_price WHERE member_id=${MEMBER_ID};"
  echo ""
  exit 0
fi

echo ""
echo "  Running WP-CLI cleanup..."
echo ""

wp --path="${WP_PATH}" eval "
  global \$wpdb;

  \$updated = \$wpdb->update(
    \$wpdb->prefix . 'stsrc_members',
    ['status' => 'pending'],
    ['member_id' => ${MEMBER_ID}]
  );
  echo 'Member status reset:       ' . (\$updated !== false ? 'OK' : 'FAILED') . PHP_EOL;

  \$deleted_logs = \$wpdb->query(\$wpdb->prepare(
    \"DELETE FROM {\$wpdb->prefix}stsrc_payment_logs WHERE member_id = %d AND stripe_event_id != ''\",
    ${MEMBER_ID}
  ));
  echo 'Payment logs deleted:      ' . \$deleted_logs . PHP_EOL;

  \$deleted_extra = \$wpdb->delete(
    \$wpdb->prefix . 'stsrc_extra_members',
    ['member_id' => ${MEMBER_ID}, 'first_name' => '${EXTRA_FIRST}', 'last_name' => '${EXTRA_LAST}']
  );
  echo 'Extra members deleted:     ' . (\$deleted_extra !== false ? \$deleted_extra : 'FAILED') . PHP_EOL;

  \$deleted_txn = \$wpdb->query(\$wpdb->prepare(
    \"DELETE FROM {\$wpdb->prefix}stsrc_transactions WHERE member_id = %d\",
    ${MEMBER_ID}
  ));
  echo 'Transactions deleted:      ' . \$deleted_txn . PHP_EOL;

  \$member = \$wpdb->get_row(\$wpdb->prepare(
    \"SELECT original_membership_price FROM {\$wpdb->prefix}stsrc_members WHERE member_id = %d\",
    ${MEMBER_ID}
  ));
  if (\$member) {
    \$original_price = (float)\$member->original_membership_price;
    \$wpdb->update(
      \$wpdb->prefix . 'stsrc_members',
      ['balance_owed' => \$original_price],
      ['member_id' => ${MEMBER_ID}]
    );
    echo 'Balance_owed restored:     \$' . \$original_price . PHP_EOL;
  }

  delete_option('stsrc_stripe_processed_events');
  echo 'Idempotency cache cleared: OK' . PHP_EOL;
"

echo ""
echo -e "  ${GREEN}✓ Cleanup complete. Member ${MEMBER_ID} is ready for another test run.${RESET}"
echo ""
