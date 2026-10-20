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

## Full Operations roundtrip fixture

`wp eval-file wp-content/themes/wowthai/dev/tests/portal-roundtrip.integration.php`
requires the **full inspected Operations plugin**, its schema and hooks in the
same disposable setup above. It additionally checks `WP_HTTP_BLOCK_EXTERNAL`.
Suppress outbound email before activation/testing. Run separately in legacy and
HPOS modes. It makes synthetic REST writes, reads real stored values back,
checks concurrency conflicts and validates prep choices; it cleans its own
orders/reservations and restores the prep option. Contact/audit fixtures may
remain in this disposable database. Never run this on the restaurant database.

The browser suite also checks compact pagination, shared help, mobile failure
labels, cash display and cancelled-history reachability at 390×667, including
polling disclosure/focus preservation. These are synthetic fixtures, not live
staff acceptance. See [portal-data-flow.md](portal-data-flow.md) for boundaries.

The single-dropdown follow-up keeps native disclosure semantics and tests Enter,
Tab exit, Escape/focus return, outside dismissal, current archive labeling,
viewport-bounded scrolling, visible mobile logout/network state, mobile column
selection, reservation dialog access, and the relocated CRM filter. The suite
includes 320px phones as well as 390/768/1440px and the short-phone history case.
These tests intercept APIs; they do not authenticate to production.

## Schedule editor and fixed navigation (1.5)

The same `portal.browser.py` suite renders the actual staff template and its
content-fingerprinted `portal-hours.js`. It intercepts every API request locally;
only synthetic hours preview/PUT requests are permitted. Order, booking, prep
and contact mutations remain forbidden by a separate request counter.

At 320/390/768/1440px, plus the 390×667 short viewport, it checks the real logo,
always-visible labeled queue buttons/counts, fixed Menü label, right-aligned
viewport-bounded popup, keyboard Enter/Tab/Escape and outside dismissal,
orders-only board/archive toolbar, retained counts on failed polling and
contextual atomic count announcements. On the 320×667 screen the persistent
navigation leaves at least 66% of the viewport for the reservation list; other
sizes retain the 70% minimum. Screenshot artifacts include the hours view.

Schedule checks cover lazy load, nonce headers, closed days and multiple weekly
windows, slots/conflict/unknown-time preview, explicit conflict acknowledgement,
preview invalidation and a held earlier preview after a draft edit, stale409
reload without draft loss, server500 draft retention, a held PUT double-submit
guard, successful revision/event/header refresh, existing exception prefill,
367-day validation, remove-exception payloads, read-only capability, published
date preview, failed initial load and navigation-only legacy URL prefill. The
CLI fixture also renders an anonymous login variant: it must not initialize
staff API reads or writes. These are browser/API fakes, not authenticated
production staff acceptance or database persistence evidence.

The schedule/API standalone suites also run in G5:

```sh
php dev/tests/hours-schedule.test.php
php dev/tests/hours-api.test.php
python dev/tests/hours-public.browser.py
```

The schedule suite covers normalization, effective weekly/exception ranges,
DST and resolver behavior. The API suite covers draft validation, permissions,
revision/token binding, impact checks, service labels and Operations version
changes with in-memory WordPress boundaries. These do not prove persistence.

The public browser suite runs in CI with actual home/footer/reservation/checkout
templates and assets. Synthetic no-store availability responses cover pending,
closed/error/recovery states, older-response races, retained guest inputs,
empty unavailable checkout FormData, preserved WooCommerce disabled state,
hours rows/closure notices, Restaurant JSON-LD and revision refresh events.
It never submits an order or reservation.

`node dev/tests/public-design.test.mjs` and
`python dev/tests/public-design.browser.py` protect the customer-facing visual
contract. They verify the local Chonburi/DM Sans font pair, shared decorative
SVG action icon, equal-weight glass Hero actions, 44px touch targets, no
horizontal overflow, and form-first reservation layout at 320/390/768px while
preserving the two-column desktop layout. The browser test renders synthetic
templates only and does not submit a reservation.

`wp eval-file wp-content/themes/wowthai/dev/tests/hours-api.integration.php`
is an opt-in **disposable-database** test with the same exact fixture URL,
`WOWTHAI_PORTAL_TEST`, WP-CLI and outbound HTTP/mail protections described above.
Its 59 REST/database assertions cover actual nonce/capability denial, no-store
and public privacy, no option seeding, competing writers/lock contention,
no-op/storage failure and lock release, more than 100 orders/bookings,
service-date conflicts/unknown times, changed commitments, tampered/expired
preview tokens, explicit acknowledgement, resource separation and immediate
public read-after-save. Run separately in legacy and HPOS storage in a fresh
PHP process with the full inspected Operations plugin. It cleans its synthetic
records and restores schedule options. Never run it against production or
import/synchronize the restaurant database. Fixture evidence does not verify
production advisory-lock support, platform CDN invalidation or authenticated
staff runtime behavior.
