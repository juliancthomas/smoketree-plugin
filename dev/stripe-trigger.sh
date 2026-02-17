#!/usr/bin/env bash
# ============================================================
# Stripe CLI – Trigger Test Events
# ============================================================
# Sends fake Stripe events to your local webhook listener.
# Requires stripe-listen.sh to be running in another terminal.
#
# USAGE:
#   bash dev/stripe-trigger.sh [event]
#
# EXAMPLES:
#   bash dev/stripe-trigger.sh checkout
#   bash dev/stripe-trigger.sh payment_success
#   bash dev/stripe-trigger.sh payment_failed
#   bash dev/stripe-trigger.sh          (shows menu)
# ============================================================

EVENT=${1:-""}

show_menu() {
  echo ""
  echo "========================================================"
  echo "  Stripe CLI – Trigger Test Events"
  echo "========================================================"
  echo "  Pick an event to trigger:"
  echo ""
  echo "  1) checkout.session.completed   (main payment flow)"
  echo "  2) payment_intent.succeeded     (payment success)"
  echo "  3) payment_intent.payment_failed (payment failed)"
  echo ""
  echo "  Or pass an event name as an argument:"
  echo "    bash dev/stripe-trigger.sh checkout"
  echo "========================================================"
  echo ""
  read -rp "Enter choice (1-3): " choice

  case $choice in
    1) trigger_event "checkout.session.completed" ;;
    2) trigger_event "payment_intent.succeeded" ;;
    3) trigger_event "payment_intent.payment_failed" ;;
    *) echo "Invalid choice."; exit 1 ;;
  esac
}

trigger_event() {
  local event="$1"
  echo ""
  echo "Triggering: $event"
  echo "--------------------------------------------------------"
  stripe trigger "$event"
  echo ""
  echo "Check your stripe-listen terminal for the forwarded event."
}

case "$EVENT" in
  "checkout"|"checkout.session.completed")
    trigger_event "checkout.session.completed"
    ;;
  "payment_success"|"payment_intent.succeeded")
    trigger_event "payment_intent.succeeded"
    ;;
  "payment_failed"|"payment_intent.payment_failed")
    trigger_event "payment_intent.payment_failed"
    ;;
  "")
    show_menu
    ;;
  *)
    # Pass through any valid Stripe event name
    trigger_event "$EVENT"
    ;;
esac
