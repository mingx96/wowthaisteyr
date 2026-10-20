# Betrieb data boundaries

The public menu and staff portal use the same WordPress/WooCommerce database.
There is no second portal database or periodic database copy. Browser polling
refreshes a view; it does not synchronize databases.

| Boundary | Owner / storage | Contract |
| --- | --- | --- |
| Menu / checkout | WooCommerce products, order CRUD; theme menu normalizer | Product descriptions normalized for display; checkout owns authoritative prices. |
| Staff reads | Theme `wowthai-portal/v1/{summary,orders,reservations,daily}` | Staff permission check, no-store responses, pagination with filter-wide totals. WooCommerce CRUD supports legacy and HPOS storage. |
| Order writes | Installed Operations `wowthai-operations/v1/orders/{id}/action` | Existing actions and concurrency tokens; the theme does not replace this plugin. |
| Reservation writes | Installed Operations reservations API; private `tischanfrage` posts and `_res_*` metadata | Same ID/version returned on reread; transitions are defined by the installed plugin. An arrived reservation has no reverse action in the inspected version. |
| Kitchen pacing | Operations prep endpoint and canonical theme option | Invalid choices rejected; kitchen availability comes from the shared Vienna schedule. |
| Service schedule | Theme `wowthai-hours/v1`; `wowthai_weekly_hours` and `wowthai_special_days` options | `inc/hours.php` resolves weekly hours plus replacing date exceptions; no configuration is seeded merely by deploying code. |
| Daily totals | WooCommerce order values and refunds, grouped by currency | Minor-unit totals reconcile with all detail pages. Order value is not cash received. |

The read adapter reuses Operations serializers/version tokens. It adds a
`payment_display` label for cash/COD methods (`Barzahlung vor Ort`). Legacy
`payment` / `payment_status` fields are preserved for compatibility, but a
WooCommerce processing/completed state does **not** establish that cash was
collected. The portal card and kitchen print use the display label. Cash
closing and receipt reconciliation remain outside this theme patch.

Polling keeps focused controls, disclosure state and scrolled columns where
possible. A failed channel leaves a visible stale-data warning and updates
both desktop and mobile connection labels. A successful summary is not proof
that every other channel succeeded.

## Schedule read/write boundary

`GET /schedule` serves staff configuration and edit permission; public
`GET /availability?date=Y-m-d` serves only derived hours, generic slots, current
status and grouped schema hours. Public dates are bounded to today through
90 days in Europe/Vienna. Responses, including errors, are no-store.

A manager (`manage_options` or `manage_woocommerce`) previews one resource
before saving it. A signed ten-minute token binds the normalized draft,
schedule revision and observed service commitments. Active orders use their
explicit pickup date/time, never creation date; ambiguous labels stay visible
for manual checking. Pending/confirmed bookings use service metadata. A
nonempty impact list additionally requires explicit acknowledgement.

The save checks REST nonce and permissions, acquires a nonblocking MySQL
advisory lock, refreshes option caches, rechecks revision and commitments,
and persists only the requested option with readback. Stale previews return
409. No order or reservation is changed or cancelled; no customer message is
sent. Legacy special-day admin shortcuts only navigate to the shared editor.
The schedule lock serializes schedule writers; it does not freeze transaction
creation in the existing Operations plugin.

Public pages rehydrate actual-date slots, grouped hours, current closure
notices and Restaurant JSON-LD. Late booking/checkout responses cannot restore
older slots. WordPress post-cache invalidation is used, but does not itself
prove a WordPress.com CDN purge; final server validation remains authoritative.

If a UI rollback is needed after customized hours have been saved, retain the
schedule resolver, options reader, API and public consumers. Revert only the
staff presentation under review. Do not restore a production database or
revert to code which ignores the saved schedule.

## Evidence and limits — 2026-10-20

- Native PHP 8.2.34 / Node verification and actual-template Playwright fixtures
  cover compact controls, history, filters, totals, mobile disconnection,
  short-phone cancelled history, focus/scroll and read-only navigation.
- Disposable WordPress 7.1.3 / WooCommerce 11.2.1 / inspected Operations 1.0.2
  tests exercise real REST handlers and database persistence in legacy and
  HPOS modes: reservation create/confirm/arrival, UTF-8/contact/notes, stale
  tokens, order accept/ready/complete/archive, modifiers, cash display, prep
  validation and anonymous denial. Read integration also reconciles >200
  synthetic orders and a partial refund across all pages.
- Test installation has a fixture-only URL, explicit test opt-in, blocked
  external HTTP and suppressed outbound mail. No production data is imported.
- A plugin version match is not a live source fingerprint. Public route
  denial and asset fingerprints verify release/protection, not authenticated
  live writes. Production staff/API and database-level diagnosis require an
  authenticated runtime session; report those checks separately.

See [TESTING.md](TESTING.md) for the opt-in test commands. Task lifecycle and
Business OS audit priorities remain in the existing private project tracker.
