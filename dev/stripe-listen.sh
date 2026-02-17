#!/usr/bin/env bash
# ============================================================
# Stripe CLI – Local Webhook Listener
# ============================================================
# Forwards Stripe test events to your LocalWP instance.
#
# USAGE (Git Bash / WSL / terminal):
#   bash dev/stripe-listen.sh
#
# FIRST TIME SETUP:
#   1. Run this script — copy the "whsec_..." secret it prints.
#   2. In WP Admin → Smoketree Settings:
#        - Check "Enable Stripe test mode"
#        - Paste your pk_test_... key into "Test Publishable Key"
#        - Paste your sk_test_... key into "Test Secret Key"
#        - Paste the whsec_... into "Test Webhook Secret"
#        - Save Settings
#   3. Keep this terminal running while testing.
# ============================================================

LOCAL_SITE="https://smoketree-ai.local"
WEBHOOK_PATH="/wp-json/stripe/v1/webhook"
WEBHOOK_URL="${LOCAL_SITE}${WEBHOOK_PATH}"

# Events this plugin handles
EVENTS=(
  "checkout.session.completed"
  "payment_intent.succeeded"
  "payment_intent.payment_failed"
)

# Build --events flags
EVENT_FLAGS=""
for event in "${EVENTS[@]}"; do
  EVENT_FLAGS="$EVENT_FLAGS --events $event"
done

echo ""
echo "========================================================"
echo "  Stripe CLI – Webhook Listener"
echo "========================================================"
echo "  Forwarding to: $WEBHOOK_URL"
echo "  Events:        ${EVENTS[*]}"
echo ""
echo "  IMPORTANT: Copy the 'whsec_...' secret printed below"
echo "  and paste it into WP Admin → Smoketree Settings →"
echo "  'Test Webhook Secret' field."
echo "========================================================"
echo ""

# --skip-verify: trusts the LocalWP self-signed SSL cert
stripe listen \
  --forward-to "$WEBHOOK_URL" \
  $EVENT_FLAGS \
  --skip-verify
