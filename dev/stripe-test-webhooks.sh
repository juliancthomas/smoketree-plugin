#!/usr/bin/env bash
# ============================================================
# Stripe Webhook Test Runner
# Smoketree Swim & Recreation Club
# ============================================================
# Tests the top 5 most-used webhook flows using stripe trigger
# with --override to inject real member/customer metadata.
#
# PREREQUISITES:
#   - stripe listen must be running in another terminal
#     (run: bash dev/stripe-listen.sh)
#   - LocalWP site must be running
#
# USAGE:
#   bash dev/stripe-test-webhooks.sh
# ============================================================

# ── Test constants ──────────────────────────────────────────
MEMBER_ID=13
WP_USER_ID=2
STRIPE_CUSTOMER_ID="cus_TzvDTEXJuO0jbN"
WP_PATH="/c/Users/jtrul/Local Sites/smoketree-ai/app/public"

# Payment amounts (in dollars; script converts to cents where needed)
REGISTRATION_AMOUNT=300       # $300.00
BALANCE_PAYMENT_AMOUNT=100    # $100.00
GUEST_PASS_AMOUNT=5           # $5.00 × 1 pass
GUEST_PASS_QTY=1
EXTRA_MEMBER_AMOUNT=50        # $50.00

# Extra member test data
EXTRA_FIRST="Test"
EXTRA_LAST="Member"
EXTRA_EMAIL="test-extra@example.com"

# ── Colors ──────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

# ── Helpers ─────────────────────────────────────────────────
header() {
  echo ""
  echo -e "${CYAN}${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
  echo -e "${CYAN}${BOLD}  $1${RESET}"
  echo -e "${CYAN}${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
}

success() { echo -e "  ${GREEN}✓ $1${RESET}"; }
warn()    { echo -e "  ${YELLOW}⚠ $1${RESET}"; }
info()    { echo -e "  $1"; }

run_trigger() {
  local label="$1"
  shift
  echo ""
  echo -e "  ${BOLD}Triggering:${RESET} $label"
  echo -e "  ${YELLOW}────────────────────────────────────────${RESET}"
  stripe trigger "$@"
  local exit_code=$?
  echo -e "  ${YELLOW}────────────────────────────────────────${RESET}"
  if [ $exit_code -eq 0 ]; then
    success "Event sent. Check stripe-listen terminal for forwarding output."
  else
    echo -e "  ${RED}✗ stripe trigger exited with code $exit_code${RESET}"
  fi
  echo ""
  read -rp "  Press Enter to continue..."
}

# ── Webhook tests ────────────────────────────────────────────

test_registration() {
  header "1 · checkout.session.completed (registration — \$${REGISTRATION_AMOUNT})"
  info "member_id=${MEMBER_ID} | payment_type=registration | customer=${STRIPE_CUSTOMER_ID}"
  warn "This will activate member ${MEMBER_ID} and create a payment log row."
  warn "Use option 8 (Cleanup) to revert afterward."
  echo ""
  read -rp "  Proceed? (y/N): " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || return

  run_trigger "checkout.session.completed [registration]" \
    checkout.session.completed \
    --override "checkout_session:customer=${STRIPE_CUSTOMER_ID}" \
    --override "checkout_session:metadata[member_id]=${MEMBER_ID}" \
    --override "checkout_session:metadata[payment_type]=registration"
}

test_balance_payment() {
  header "2 · checkout.session.completed (balance_payment — \$${BALANCE_PAYMENT_AMOUNT})"
  info "member_id=${MEMBER_ID} | payment_type=balance_payment | customer=${STRIPE_CUSTOMER_ID}"
  warn "This will reduce member ${MEMBER_ID}'s balance_owed by \$${BALANCE_PAYMENT_AMOUNT} and record a transaction."

  AMOUNT_CENTS=$((BALANCE_PAYMENT_AMOUNT * 100))

  run_trigger "checkout.session.completed [balance_payment]" \
    checkout.session.completed \
    --override "checkout_session:customer=${STRIPE_CUSTOMER_ID}" \
    --override "checkout_session:metadata[member_id]=${MEMBER_ID}" \
    --override "checkout_session:metadata[payment_type]=balance_payment" \
    --override "checkout_session:metadata[payment_amount]=${BALANCE_PAYMENT_AMOUNT}"
}

test_payment_intent_succeeded() {
  header "3 · payment_intent.succeeded"
  info "Simulates a successful payment intent."
  info "Handler updates an existing payment log row status → succeeded."
  info "Note: no matching log row exists yet for the random PI ID, so no DB update will occur — but the handler must complete without errors."

  run_trigger "payment_intent.succeeded" \
    payment_intent.succeeded
}

test_guest_pass() {
  header "4 · checkout.session.completed (guest_pass — ${GUEST_PASS_QTY} × \$${GUEST_PASS_AMOUNT})"
  info "member_id=${MEMBER_ID} | payment_type=guest_pass | quantity=${GUEST_PASS_QTY}"
  warn "This will add ${GUEST_PASS_QTY} guest pass(es) to member ${MEMBER_ID} and send a confirmation email."

  run_trigger "checkout.session.completed [guest_pass]" \
    checkout.session.completed \
    --override "checkout_session:customer=${STRIPE_CUSTOMER_ID}" \
    --override "checkout_session:metadata[member_id]=${MEMBER_ID}" \
    --override "checkout_session:metadata[payment_type]=guest_pass" \
    --override "checkout_session:metadata[quantity]=${GUEST_PASS_QTY}"
}

test_payment_intent_failed() {
  header "5 · payment_intent.payment_failed"
  info "Simulates a declined card for a balance payment on member ${MEMBER_ID}."
  info "Handler will send failure emails to member and admins."

  run_trigger "payment_intent.payment_failed" \
    payment_intent.payment_failed \
    --override "payment_intent:metadata[member_id]=${MEMBER_ID}" \
    --override "payment_intent:metadata[payment_type]=balance_payment" \
    --override "payment_intent:metadata[payment_amount]=${BALANCE_PAYMENT_AMOUNT}"
}

test_extra_member() {
  header "Bonus · checkout.session.completed (extra_member — \$${EXTRA_MEMBER_AMOUNT})"
  info "member_id=${MEMBER_ID} | extra member: ${EXTRA_FIRST} ${EXTRA_LAST} <${EXTRA_EMAIL}>"
  warn "This will create an extra_member record linked to member ${MEMBER_ID}."

  run_trigger "checkout.session.completed [extra_member]" \
    checkout.session.completed \
    --override "checkout_session:customer=${STRIPE_CUSTOMER_ID}" \
    --override "checkout_session:metadata[member_id]=${MEMBER_ID}" \
    --override "checkout_session:metadata[payment_type]=extra_member" \
    --override "checkout_session:metadata[first_name]=${EXTRA_FIRST}" \
    --override "checkout_session:metadata[last_name]=${EXTRA_LAST}" \
    --override "checkout_session:metadata[email]=${EXTRA_EMAIL}"
}

run_all() {
  header "Running all 5 tests in sequence"
  warn "This will modify DB data for member ${MEMBER_ID}. Run Cleanup (option 8) afterward."
  echo ""
  read -rp "  Proceed? (y/N): " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || return

  test_registration
  test_balance_payment
  test_payment_intent_succeeded
  test_guest_pass
  test_payment_intent_failed
}

cleanup() {
  header "Cleanup — Reset test data for member ${MEMBER_ID}"
  warn "This will:"
  info "  • Set member ${MEMBER_ID} status → pending"
  info "  • Delete payment log rows for member ${MEMBER_ID} with stripe_event_id matching test events"
  info "  • Delete the test extra member: ${EXTRA_FIRST} ${EXTRA_LAST}"
  info "  • Delete test transactions for member ${MEMBER_ID} (amount = ${BALANCE_PAYMENT_AMOUNT})"
  echo ""
  read -rp "  Proceed? (y/N): " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || return

  # Check WP-CLI availability
  if ! command -v wp &>/dev/null; then
    warn "WP-CLI not found. Run cleanup manually in WP Admin or phpMyAdmin."
    info "SQL to run:"
    echo ""
    echo "    -- Reset member status"
    echo "    UPDATE wp_stsrc_members SET status='pending' WHERE member_id=${MEMBER_ID};"
    echo ""
    echo "    -- Remove test payment logs"
    echo "    DELETE FROM wp_stsrc_payment_log WHERE member_id=${MEMBER_ID} AND stripe_event_id != '';"
    echo ""
    echo "    -- Remove test extra member"
    echo "    DELETE FROM wp_stsrc_extra_members WHERE member_id=${MEMBER_ID} AND first_name='${EXTRA_FIRST}' AND last_name='${EXTRA_LAST}';"
    echo ""
    echo "    -- Remove all test transactions"
    echo "    DELETE FROM wp_stsrc_transactions WHERE member_id=${MEMBER_ID};"
    echo ""
    echo "    -- Restore balance_owed to original membership price"
    echo "    UPDATE wp_stsrc_members SET balance_owed = original_membership_price WHERE member_id=${MEMBER_ID};"
    echo ""
    return
  fi

  echo ""
  info "Running WP-CLI cleanup..."

  wp --path="${WP_PATH}" eval "
    global \$wpdb;

    // Reset member status
    \$updated = \$wpdb->update(
      \$wpdb->prefix . 'stsrc_members',
      ['status' => 'pending'],
      ['member_id' => ${MEMBER_ID}]
    );
    echo 'Member status reset: ' . (\$updated !== false ? 'OK' : 'FAILED') . PHP_EOL;

    // Delete test payment logs (non-empty stripe_event_id = came from Stripe)
    \$deleted_logs = \$wpdb->query(\$wpdb->prepare(
      \"DELETE FROM {\$wpdb->prefix}stsrc_payment_log WHERE member_id = %d AND stripe_event_id != ''\",
      ${MEMBER_ID}
    ));
    echo 'Payment logs deleted: ' . \$deleted_logs . PHP_EOL;

    // Delete test extra member
    \$deleted_extra = \$wpdb->delete(
      \$wpdb->prefix . 'stsrc_extra_members',
      ['member_id' => ${MEMBER_ID}, 'first_name' => '${EXTRA_FIRST}', 'last_name' => '${EXTRA_LAST}']
    );
    echo 'Extra members deleted: ' . (\$deleted_extra !== false ? \$deleted_extra : 'FAILED') . PHP_EOL;

    // Delete all test transactions (registration + balance payments)
    \$deleted_txn = \$wpdb->query(\$wpdb->prepare(
      \"DELETE FROM {\$wpdb->prefix}stsrc_transactions WHERE member_id = %d\",
      ${MEMBER_ID}
    ));
    echo 'Transactions deleted: ' . \$deleted_txn . PHP_EOL;

    // Restore balance_owed to original_membership_price
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
      echo 'Balance_owed restored to original price: \$' . \$original_price . PHP_EOL;
    }

    // Clear idempotency cache so events can be re-triggered
    delete_option('stsrc_stripe_processed_events');
    echo 'Processed events cache cleared.' . PHP_EOL;
  "

  echo ""
  success "Cleanup complete. Member ${MEMBER_ID} is ready for another test run."
}

# ── Main menu ────────────────────────────────────────────────

show_menu() {
  clear
  echo ""
  echo -e "${CYAN}${BOLD}╔══════════════════════════════════════════════════════╗${RESET}"
  echo -e "${CYAN}${BOLD}║       Stripe Webhook Test Runner — Smoketree         ║${RESET}"
  echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════════════════╝${RESET}"
  echo ""
  echo -e "  ${BOLD}Member:${RESET}   ${MEMBER_ID}  |  ${BOLD}Customer:${RESET} ${STRIPE_CUSTOMER_ID}"
  echo ""
  echo -e "  ${YELLOW}Requires:${RESET} stripe listen running in another terminal"
  echo ""
  echo -e "  ${BOLD}─── Top 5 Webhooks ───────────────────────────────────${RESET}"
  echo "   1)  checkout.session.completed  →  registration       (\$${REGISTRATION_AMOUNT})"
  echo "   2)  checkout.session.completed  →  balance_payment    (\$${BALANCE_PAYMENT_AMOUNT})"
  echo "   3)  payment_intent.succeeded"
  echo "   4)  checkout.session.completed  →  guest_pass         (${GUEST_PASS_QTY} × \$${GUEST_PASS_AMOUNT})"
  echo "   5)  payment_intent.payment_failed"
  echo ""
  echo -e "  ${BOLD}─── Extras ───────────────────────────────────────────${RESET}"
  echo "   6)  checkout.session.completed  →  extra_member       (\$${EXTRA_MEMBER_AMOUNT})"
  echo "   7)  Run all 5 in sequence"
  echo "   8)  Cleanup / Reset test data"
  echo "   q)  Quit"
  echo ""
  read -rp "  Choice: " choice

  case "$choice" in
    1) test_registration ;;
    2) test_balance_payment ;;
    3) test_payment_intent_succeeded ;;
    4) test_guest_pass ;;
    5) test_payment_intent_failed ;;
    6) test_extra_member ;;
    7) run_all ;;
    8) cleanup ;;
    q|Q) echo ""; exit 0 ;;
    *) warn "Invalid choice." ;;
  esac

  show_menu
}

show_menu