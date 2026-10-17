# WooCommerce checkout QA

Use this checklist for code changes that affect menu products, cart, checkout, pickup, orders, reservations, confirmation, or customer/admin email.

## Safe setup

- Follow `COORDINATION.md`; use only a disposable local installation or an explicitly approved staging site.
- Use test products and a non-live payment method. Do not create real orders or send real customer emails.
- Do not copy a production database or customer/order records into a test environment. Do not sync a database as part of code QA.
- Never put credentials in the repository, logs, screenshots, or test fixtures.

## Scenarios to select by change

- Product options/modifiers are retained through add-to-cart, cart refresh, and checkout.
- Cart/session state and totals remain consistent after reload and validation errors.
- Pickup date/time and opening-hour constraints accept valid slots and reject invalid slots.
- Checkout validation displays actionable errors without losing the cart.
- A test order reaches the expected confirmation and admin status; item variants and modifiers remain visible.
- Email templates and links render as expected with a test recipient and captured mail transport.
- Mobile and desktop layouts remain usable for affected customer flows.

## Evidence

Record the environment type (local or staging), scenarios exercised, result, and any scenarios not run. The existing `dev/tests/hours.test.php` is unit coverage for schedule logic, not proof of end-to-end checkout behavior.
