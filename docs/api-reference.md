# API Reference

## Balance and Payment Services

This reference documents the primary public methods used by the balance tracking feature.

## `STSRC_Balance_Service`

### `adjust_balance(int $member_id, string $adjustment_type, float $amount, string $description, string $admin_notes = '', int $admin_user_id = 0): array|false`

Creates an adjustment transaction and updates `balance_owed`.

- Positive amount increases balance (fee/charge).
- Negative amount reduces balance (discount/credit).
- Returns transaction array on success, `false` on failure.

### `record_manual_payment(int $member_id, string $payment_method, float $amount, string $description, string $admin_notes = '', int $admin_user_id = 0, string $date_received = ''): array|false`

Records an offline payment (`check`, `zelle`, `cash`) and reduces balance.

- Writes payment transaction.
- Updates `final_payment_method`.
- Sends manual payment confirmation email.

### `record_stripe_payment(int $member_id, float $amount, string $payment_method, array $stripe_ids, string $description): array|false`

Records a Stripe-backed balance payment.

- Stores Stripe metadata IDs on transaction row.
- Sends success/admin notifications.
- Sends overpayment alert when balance becomes negative.

### `update_member_status_if_paid(int $member_id): bool`

Auto-activates member when status is `pending` and `balance_owed <= 0`.

### `handle_admin_status_override(int $member_id, int $admin_user_id = 0, string $context = ''): bool`

Logs manual admin activation when outstanding balance remains.

### `get_balance_display_data(int $member_id): ?array`

Returns presentation-ready data for admin/member UI components.

## `STSRC_Payment_Service`

### `create_balance_payment_checkout_session(int $member_id, float $amount): string|false`

Creates Stripe Checkout session URL for balance payment.

- Enforces minimum payment setting.
- Validates member and outstanding balance.
- Includes metadata used by webhook routing.

### `get_minimum_balance_payment(): float`

Returns configured minimum balance payment amount with fallback to `10.00`.

## `STSRC_Transaction_DB`

### `create_transaction(int $member_id, array $transaction_data): int|false`

Creates a transaction ledger row and returns transaction ID.

### `get_transactions(int $member_id, ?int $year = null, int $page = 1, int $per_page = 20): array`

Returns member transactions with optional year filter and pagination.

### `get_transaction(int $transaction_id): ?array`

Returns one transaction row by ID.

### `get_balance_summary(int $member_id): ?array`

Returns summary values used by UI and service layer.

## `STSRC_Balance_Ajax` Endpoints

- `wp_ajax_stsrc_adjust_balance` -> `handle_admin_adjust_balance()`
- `wp_ajax_stsrc_record_payment` -> `handle_admin_record_payment()`
- `wp_ajax_stsrc_create_balance_payment` -> `handle_create_balance_payment()`

All endpoints require nonce validation and sanitize incoming values before use.

## Hook Reference

### Actions Fired

- `stsrc_member_auto_activated_after_balance_paid`
- `stsrc_member_status_override_with_balance`
- `stsrc_balance_payment_succeeded`
- `stsrc_balance_payment_failed`
