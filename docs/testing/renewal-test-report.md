# Renewal End-to-End Test Report

## Scope
- Renewal eligibility and submission workflow for all supported membership transitions.
- Payment method paths: Stripe card/ACH and offline Zelle/check.
- Duplicate submission/idempotency behavior and Stripe webhook replay handling.
- Admin offline confirmation workflow and notification delivery.

## Environment
- WordPress plugin in local/staging mode.
- Stripe test mode enabled for checkout and webhook replay.
- Renewal feature toggle enabled in plugin settings.
- Test members prepared across `civic`, `single`, `duo`, and `household` plans.

## Scenario Matrix
| Scenario | Result | Notes |
|---|---|---|
| Eligibility blocked when renewal toggle is off | Pass | Renewal section hidden and API rejects submit attempts. |
| Duplicate renewal submit for same season | Pass | API returns deterministic duplicate guard response. |
| Single -> Household with family/extras retained | Pass | Quote and snapshot persistence matched expected totals. |
| Household -> Single with forced family/extra reconciliation | Pass | Non-retained household records soft-deleted on completion. |
| Stripe card renewal completion | Pass | Member fields updated transactionally and renewal marked completed. |
| Stripe webhook replay (`checkout.session.completed`) | Pass | Replay treated as no-op success with no duplicate mutations. |
| Zelle/check submit path to pending_payment | Pass | Renewal transitions to pending status and stays blocked for duplicates. |
| Admin confirm offline pending renewal | Pass | Pending renewal transitions to completed and member activated. |
| Renewal member confirmation email | Pass | Correct template selected (civic vs standard). |
| Renewal admin notice recipients | Pass | Treasurer/President/VP/Secretary + admin users notified. |

## Security Validation
- Verified nonce and auth checks on member renewal endpoints.
- Verified capability + admin nonce checks on offline confirmation endpoint.
- Attempted payload tampering (`member_id`, payment method, season key format) and confirmed rejection or server-side normalization.

## Observability Validation
- Confirmed structured logs for:
  - Stripe renewal completion success/failure paths.
  - Offline confirmation success/failure paths.
  - Webhook no-op replay behavior.

## Follow-Up Notes
- Continue running Stripe CLI replay checks after any webhook logic changes.
- If additional payment methods are introduced, extend payment-method whitelist and test matrix accordingly.
