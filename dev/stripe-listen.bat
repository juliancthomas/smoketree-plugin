@echo off
REM ============================================================
REM Stripe CLI - Local Webhook Listener (Windows)
REM ============================================================
REM Forwards Stripe test events to your LocalWP instance.
REM
REM USAGE: Double-click this file, or run from command prompt:
REM   dev\stripe-listen.bat
REM
REM FIRST TIME SETUP:
REM   1. Run this script - copy the "whsec_..." secret it prints.
REM   2. In WP Admin > Smoketree Settings:
REM        - Check "Enable Stripe test mode"
REM        - Paste your pk_test_... key into "Test Publishable Key"
REM        - Paste your sk_test_... key into "Test Secret Key"
REM        - Paste the whsec_... into "Test Webhook Secret"
REM        - Save Settings
REM   3. Keep this window open while testing.
REM ============================================================

SET LOCAL_WEBHOOK=https://smoketree-ai.local/wp-json/stripe/v1/webhook

echo.
echo ========================================================
echo   Stripe CLI - Webhook Listener
echo ========================================================
echo   Forwarding to: %LOCAL_WEBHOOK%
echo   Events: checkout.session.completed,
echo           payment_intent.succeeded,
echo           payment_intent.payment_failed
echo.
echo   IMPORTANT: Copy the whsec_... secret printed below
echo   and paste it into:
echo   WP Admin -^> Smoketree Settings -^> Test Webhook Secret
echo ========================================================
echo.

stripe listen ^
  --forward-to %LOCAL_WEBHOOK% ^
  --events checkout.session.completed,payment_intent.succeeded,payment_intent.payment_failed ^
  --skip-verify

pause
