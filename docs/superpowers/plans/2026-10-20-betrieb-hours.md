# Betrieb Hours Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development task by task. Read the task brief, not unrelated history.

**Goal:** Deliver the approved navigation and one consistent, editable opening schedule.
**Architecture:** Shared PHP schedule resolver, theme-scoped hours REST service, vanilla JS editor/public availability hydration. Existing Operations plugin keeps transaction writes.
**Tech Stack:** WordPress/PHP8.2, Woo CRUD/HPOS, vanilla JS/CSS, Python Playwright.
**Spec:** docs/superpowers/specs/2026-10-20-betrieb-hours.md

## Global Constraints
- Same private Task ID; no private URLs/customer data in public Git. Base7126f35, feature branch only.
- Options wowthai_weekly_hours and wowthai_special_days; no auto-seeding on release, no DB sync/plugin replacement.
- Europe/Vienna; no overnight/overlapping windows; manager-only writes;44px mobile controls.
- DST ruling: omit a date-specific window if either local boundary does not round-trip to its saved wall time during the spring gap. Do not silently shift service hours; preserve other valid windows on that date. Fall repeated times retain PHP occurrence policy.
- API namespace wowthai-hours/v1. GET /schedule -> {weekly,exceptions,revision,can_edit,timezone}. GET /availability?date=Y-m-d -> {date,timezone,ranges,reservation_slots,pickup_slots,display_rows,opening_status,revision}; public, no PII, no-store, valid dates today..+90days.
- POST /schedule/preview accepts {kind:'weekly'|'exceptions',revision,weekly?:map,exception?:{start_date,end_date,mode:'closed'|'hours'|'remove',ranges?:[[HH:MM,HH:MM]],reason?:string},preview_date?:Y-m-d}; returns {revision,confirmation_token,conflicts:[{kind,id,date,time,status,unknown}],preview:availability}. Read-only access for portal staff; edit controls disabled for non-managers.
- PUT /schedule/weekly or /schedule/exceptions accepts the matching draft payload, revision and confirmation_token; requires manager, REST nonce and current signed preview including affected-record signature; nonempty conflicts also require acknowledge_conflicts:true. Return current schedule after save;409 stale schedule/preview,400 invalid,401/403 unauthorized,503 lock/unavailable dependencies.
- Save preview token binds normalized draft, revision, current conflict signature and expiry (10minutes), signed using WP auth salt. Serialize save under a MySQL named advisory lock (site-scoped name <=64chars, nonblocking, fail closed); refresh option caches after locking and release in finally. No stale transient lease takeover.
- GET config/public slots never create options. Each save changes only its resource option, checks DB success, records manager/time in separate metadata if used; no second canonical schedule.

## Review Focus
- Zero weekly windows must remain closed; defaults must not reactivate it.
- Cached/open public pages cannot offer a stale exceptional-day slot as valid.
- Concurrent changes/new affected reservations invalidate a preview before a save.
- Ambiguous legacy pickup labels cannot silently disappear from impact checking.
- Non-manager staff and legacy admin links cannot bypass scheduling permissions/warnings.

## Task 1: Canonical schedule and consistent consumers
- [x] Implement/test weekly override/defaults, exception precedence, validation, preview resolver, grouping, next-opening, lunch intersection in inc/hours.php.
- [x] Update public displays/Schema/restaurant data consumers to iterate grouped rows, preserving existing legacy return keys without using them for weekday grouping.
- [x] Add standalone failing unit tests then implement; run existing hours/menu/model suites. No REST/store/UI editor work in this task.
- [x] Commit only owned files; report interfaces/tests/remaining concerns. Review spec+quality before Task2.
**Interfaces:** wowthai_hours_default_schedule():array; wowthai_hours_validate_ranges($ranges) -> normalized array|WP_Error; wowthai_hours_validate_weekly($weekly) -> map|WP_Error; wowthai_hours_ranges_for_config(DateTimeImmutable $date,array $weekly,array $exceptions):array; wowthai_hours_grouped_rows(?array $weekly=null):array of {days,hours,weekdays}; existing wowthai_hours_schedule/ranges_for_date/display/is_open/reservation_slots/schema API kept compatible. wowthai_hours_display()['rows'] canonical grouped display. Other helpers stay in hours.php and can be reused by the REST service.

## Task 2: REST persistence, impact checks and public freshness
- [x] Add inc/hours-api.php, load from functions.php; exact REST contract above, nonce/capability/validation/lock/revision/preview conflict logic.
- [x] Use paginated WC CRUD plus private booking meta; active orders today/future by service date, pending/confirmed bookings date>=today. Parse canonical Abholzeit and legacy date/time; ambiguous active records explicitly unknown, never infer service date from creation.
- [x] Existing admin special-day writes redirect to shared calendar editor with prefill only; remove direct save links bypass. Backend server already validates slots.
- [x] Add assets/js/hours-public.js, enqueued with content fingerprint; date-specific public booking availability (replace fixed weekday map), rehydrate tagged public schedule/status/checkout slots on load/focus, reject stale response races, no-store/cache hooks.
- [x] Add unit/API and opt-in full WordPress integration fixture tests (real DB writes only fixture.invalid with HTTP/mail off), including permissions, two writers, changed conflicts, legacy options, WP options failure and read-after-save. Include meaningful new tests in verify/CI where standalone.
- [x] Commit owned files and report. Review before Task3.

## Task 3: Navigation and staff schedule UI
- [x] Actual logo left, fixed primary buttons/counters and active state; menu right, popup aligned right; orders-only Küchenboard/Archiv toolbar. Keep stale/network/alerts visible at320px.
- [x] New Öffnungszeiten view and assets/js/portal-hours.js consumes exact API. Three tabs, per-day range editor/closed controls, date-range exceptions add/remove, preview and conflict table; read-only staff state.
- [x] Preview then Save, explicit conflict confirmation, preserve draft/error on failures; invalidate preview on every input edit; handle409 by asking for renewed preview without overwriting draft. Refresh header/status/booking slots after save through event wowthai:hours-saved and existing polling handlers.
- [x] Adapt actual-template fixtures/browser suite for navigation and schedule editor, permissions, conflicts, stale tokens, errors and mobile. Add content-fingerprinted new asset and fixture rendering compatibility.
- [x] Bump version1.5.0 in functions.php/style.css/page-betrieb.php; verify all gates/browser, commit owned files and report. Review then full branch review.

## Task 4: Integration and release
- [x] Controller runs real fixture DB roundtrip tests legacy/HPOS plus relevant existing integrations and native/browser checks; resolves findings through implementer.
- [ ] Full independent branch review; update docs/TESTING and COORDINATION with exact evidence and approved rollback boundary.
- [ ] Publish PR, verify exact-head CI, merge under user's explicit release authorization and observe actual rendered asset URLs/API protections/public schedule. Don't change live schedule just to test it.
- [ ] Update same Notion task with runtime and authenticated-production limits. Retain schema/DB resolver on UI rollback; no production DB restore.
