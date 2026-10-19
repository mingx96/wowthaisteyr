# Theme verification

## Automated checks

Run from the repository root:

```sh
node dev/verify.mjs
```

The script checks gates G1–G11 and runs JavaScript syntax checks, PHP lint, and the hours, menu-description and portal read-model PHP suites when PHP is installed. The GitHub Actions workflow installs Node 20 and PHP 8.2 to ensure those checks execute.

For a local theme package, `node dev/build.mjs` runs verification first and writes a ZIP beside the repository. It does not upload or deploy anything.

## Scope of current unit coverage

The committed PHP test suite exercises opening hours, lunch/Sushi availability, reservation slots, and daylight-saving boundaries. It does not exercise a live WordPress installation, payment gateway, order database, email transport, or the browser checkout flow.

`php dev/tests/menu-description.test.php` covers rich editor markup converted to
plain menu descriptions, encoded markup, block spacing, entities, script/style
removal, and preservation of default rich-text and fallback behavior. Its
standalone bootstrap supplies a minimal WordPress tag-stripping shim; it is not
a full WordPress/WooCommerce integration test. Cards, modal text, search, and
menu schema consume the same description produced by the menu data builder.

## Reporting

Report the command and outcome. If PHP cannot be found, treat the PHP checks as not run rather than as passing. Distinguish automated checks from manual browser checks.

## Betrieb regression coverage

`php dev/tests/portal-read-model.test.php` uses in-memory WordPress/WooCommerce
boundary fakes to verify staff permissions, Vienna DST date boundaries,
uncapped totals, refunds, per-filter booking totals, pagination and global alerts.
It is part of G5/PR CI. It does not prove database or live plugin compatibility.

`python dev/tests/portal.browser.py` requires Python Playwright with Chromium and
native PHP in PATH. It renders the actual template/CSS/JS through a local-only
fixture server and intercepts APIs with synthetic data. It checks desktop,
phone and tablet archive visibility/pagination, read-only history, booking
scope/date/counts, polling focus/scroll, daily details/refund totals, global
new-order sound, and failed-load/recovery feedback. Set `PORTAL_TEST_ARTIFACTS` to choose the screenshot folder (default: a temporary
`wowthai-portal-artifacts` directory). It never calls live
WordPress or submits real orders/bookings. Fixture PHP exits outside CLI.

`wp eval-file wp-content/themes/wowthai/dev/tests/portal.integration.php` is a
**destructive disposable-database test**, not a production verification command.
It refuses to run without WP-CLI, `WOWTHAI_PORTAL_TEST === true` and exact
`home_url() === 'http://fixture.invalid'`. Use a separate, isolated WordPress
7.1.3/WooCommerce 11.2.1 installation with outbound mail/HTTP disabled and only
synthetic fixtures. Load the inspected Operations DTO implementation there.
Run once with legacy order storage and once with HPOS after a supported sync /
switch, in a fresh PHP process. It creates more than 200 synthetic orders and
a partial refund, exercises real REST permission checks, confirms no-store,
compares total with all detail pages, validates WP booking metadata, then
removes its synthetic orders/bookings. Do not import a production database.
This test is opt-in and is not run by the normal PR CI.

The inspected Operations source snapshot used in local integration is not proof
that the live plugin has those exact bytes. Before calling a live release
verified, inspect authenticated `wowthai-portal/v1/{summary,orders,reservations,daily}`
responses and staff UI, preserve current order/version behavior, and record the
runtime source/asset fingerprint separately from CI or merge status.
