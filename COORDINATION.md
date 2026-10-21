# COORDINATION & HANDOVER PROTOCOL (WOW THAI STEYR)

## Homepage photography and motion — review handoff — 2026-10-21T20:27:00Z

- Task ID `notion:3fe9ccae-c10f-81f4-b321-fb6e7839d67d`; same execution owner and isolated public-theme/media/docs/test scope. The existing Notion task was updated and read back at 20:26:26Z with [PR #17](https://github.com/mingx96/wowthaisteyr/pull/17), implementation evidence and status `Cần xem xét`. No competing execution owner was recorded. No shared-site write is claimed; earlier machine WIP remains unknown and untouched.
- Base/main `6b76152fa083873c2f4ebb354ef8f97f86f02b3a` was fetched again at 20:25Z. Branch `feat/homepage-atmosphere-20261021`, observed implementation HEAD `fc590c06bc70ffbc2168853105217e59ba488d8d`, tree `1b60621a06d323b4fb29bf86e78546b30d1eb62b`. The published tree exactly matched the reviewed local index; the local branch was advanced only after that comparison. The only follow-up WIP at this checkpoint is this documentation entry; the PR owns its subsequent head and current checks.
- Delivered source: theme 1.5.5, warm cream editorial gallery, three authentic food moments, a native 10.75-second silent H.264 film (706,193 bytes) with poster/controls and no autoplay or initial MP4 request, responsive WebP images, manual swipe/arrow browsing and brief once-only reveals. Reduced-motion changes and no-JavaScript use are supported. Playfair Display/DM Sans, 48px Hero actions, managed menu associations, canonical hours and operational behavior remain intact. `DESIGN.md`, the dated research report and the actual-template preview record the visual direction.
- Media evidence: the actual public Steyr Facebook film was played and downloaded from its video element; original SHA-256, attribution, original/derivative dimensions and dates, export settings and limitations are in `assets/img/homepage/SOURCES.json`. All derivative bytes/hashes were independently checked; the MP4 has no audio track and its metadata precedes the media payload. Instagram is verified and linked, but no Instagram asset is claimed. Generic historical food imagery is not assigned to named menu products; pre-relocation interiors and the unverified publisher candidate remain excluded.
- Local implementation evidence (PHP 8.3.6, Node, Playwright 1.62.0 / Chromium 151): `node dev/verify.mjs` G1–G11 PASS; `php dev/tests/homepage.test.php` 76/76 PASS; `python dev/tests/{homepage,public-design,hours-public,portal}.browser.py` all PASS; `git diff --check` PASS. Public viewports cover 320/390/768px and desktop; the portal also covers 667px landscape. Browser fixtures use synthetic APIs. Gallery endpoints, unequal widths/resize, focus transfer, native keys with/without JavaScript, runtime reduced motion, real video playhead progression, offscreen/slide-change pause and no request before playback were verified. Hidden/pagehide paths used synthetic events, not an operating-system tab switch.
- Independent review found no remaining blockers after the native video focus ring was inset to avoid clipping. Actual-template desktop/mobile visual review found no layout overflow or console errors. The checked-in desktop preview uses real media and no fabricated prices, hours or customer data. These local results are not production WordPress or Core Web Vitals evidence.
- Initial remote CI on `fc590c0`: [design/workflow skills](https://github.com/mingx96/wowthaisteyr/actions/runs/39670178808) PASS; [agent skills](https://github.com/mingx96/wowthaisteyr/actions/runs/39670178837) PASS; [theme verification](https://github.com/mingx96/wowthaisteyr/actions/runs/39670178830) is running at this checkpoint, with repository gates already passed. [Current PR checks](https://github.com/mingx96/wowthaisteyr/pull/17/checks) are authoritative for the follow-up head; the final result is also recorded in the same Notion task and PR body.
- Release boundary: PR #17 is open for review. No merge, deployment, database synchronization or production transaction occurred. The main publication workflow was reread at 20:25Z: every push to main creates the `wpcom` artifact without a path filter; actual external delivery still requires separate observation. Next: finish current-head CI, then Minh reviews the PR and decides whether to merge/release. A future release must be followed by read-only live asset/runtime verification.

## Historical — Homepage photography and motion — 2026-10-21T20:05:40Z

- Task ID `notion:3fe9ccae-c10f-81f4-b321-fb6e7839d67d`; execution owner is the current ChatGPT/Codex continuation. The existing task was updated and read back at 20:02Z with the user's request to study Kei, NKORA, the Elias selection and two design repositories, and discretion to reuse the restaurant's media. Scope: isolated public-theme code, genuine media, design documentation, tests and a reviewable PR. The task is `Đang thực hiện`.
- [PR #16](https://github.com/mingx96/wowthaisteyr/pull/16) was merged by the user at 19:47:06Z. Fresh base/main and current starting HEAD are `6b76152fa083873c2f4ebb354ef8f97f86f02b3a`. Branch `feat/homepage-atmosphere-20261021` has a separate worktree. No open PRs overlapped at the scope check. Prior machine WIP remains unavailable, unknown and untouched. A merged PR does not establish the live theme.
- File ownership: root handles front-page PHP, homepage CSS, enqueue/version, media provenance and this checkpoint; a helper handles the new homepage script and existing homepage browser fixture/tests; another handles `DESIGN.md` and the dated design research document. The media researcher performed read-only source discovery. No agent owns a production write.
- In progress: a warm cream editorial gallery after the Hero, owner-attributed food media, native swipe/arrow browsing and finite reveal motion. Keep Playfair Display/DM Sans, equal 48px Hero actions, managed menu prices and canonical hours. Follow-up inspection in a logged-out public browser resolved the initial poster-only HTML limitation: the actual 10.755-second Steyr clip plays and could be downloaded through the supported video element. The local silent H.264 export is 706,193 bytes, with native controls, a poster and no autoplay; the original Facebook post remains linked. Social scripts/iframes are not loaded. Historical food media stays separate from named menu products; pre-relocation interiors and an unattributed interior candidate are excluded.
- Baseline evidence: native PHP 8.3.6/Node `node dev/verify.mjs` G1–G11 PASS on the clean base above. Current media derivatives preserve source content with orientation/resize/WebP encoding; their sources, original hashes, dates and limits are recorded in `assets/img/homepage/SOURCES.json`. Implementation and browser verification are pending at this checkpoint; modified/untracked paths belong to the scope above.
- Next: complete and inspect the actual-template gallery on desktop/mobile, run the existing native/browser gates and independent review, then publish a PR with the final head and evidence. No new merge, deployment, database synchronization or production transaction has occurred.

## Historical — Homepage discovery and visit planning — 2026-10-21T19:33:34Z

- Task ID `notion:3fe9ccae-c10f-81f4-b321-fb6e7839d67d`; execution owner Codex in the assigned continuation. The existing task was updated and read back at 19:22:19Z with PR/evidence and status `Cần xem xét`. Allowed scope is isolated public-theme code, tests, documentation and a reviewable PR. Merge, deployment and production data writes are not authorized by this checkpoint.
- Base/main `e777628fe0e7f14b281327649de97e7242468bd9` was fetched again at 19:30Z; branch `feat/homepage-discovery-20261021`; [PR #16](https://github.com/mingx96/wowthaisteyr/pull/16) is open for review and conflict-free. Implementation/observed HEAD `49a914b69c252dc6ae8c62b1ed3660f035b8006a`, tree `d6dde989b1c20bb0d102e5a228ed905ab61cd83e`, matches the locally reviewed tree exactly. Pending follow-up paths at this checkpoint are only `COORDINATION.md` and `dev/tests/portal.browser.py`; the PR owns the subsequent head and checks. Prior machine worktrees are unavailable here, so their WIP remains unknown and untouched.
- Changes: menu-managed dish discovery with optional associated images; shared menu mapper and bounded eligible-product selection; practical visit/contact/parking section; canonical navigation and current-day kitchen hours; compact homepage footer; responsive WebP versions of the existing Hero. Read-only menu anchors reveal and focus a dish without invoking ordering controls. Preserve Playfair Display/DM Sans, equal 48px Hero actions, reservation layout and operations behavior.
- Final local evidence for the implementation above: native PHP 8.3.6/Node `node dev/verify.mjs` G1–G11 PASS; `php dev/tests/homepage.test.php` 76/76 PASS; `python dev/tests/{homepage,public-design,hours-public,portal}.browser.py` all PASS using Playwright 1.62.0/Chromium 151 and synthetic APIs. Public viewports include 320/390/768/1440px; the portal also covers a short landscape viewport. Homepage regressions prove click-free dish navigation, foreground closing/midnight refresh, ten-second timeout, same-day stale feedback, new-day fallback, recovery and no-JavaScript weekly-plan access. Independent re-review found no remaining blockers after restoring WordPress protected-content semantics and adding bounded homepage polling. Unchanged live-region text no longer gets rewritten by each poll.
- Initial CI: both skills workflows PASS on `49a914b`; [theme verification run](https://github.com/mingx96/wowthaisteyr/actions/runs/39660894652) failed at the existing portal test's focus assertion before reaching the public/Homepage browser suites. The production portal sources and its test were unchanged from main in that implementation commit. A deterministic trace confirmed that native dialog closing restores focus before the queued application close handler, so the next independent focus test began too early. The follow-up waits for the real close event with a bounded timeout. A second reproduced test race read the Python summary counter before the save-triggered request reached its route; the follow-up drains a poll, pauses its clock, awaits the exact GET-summary response after save, retains the original assertions, then resumes the clock. No production portal code changes or assertion weakening were needed.
- Fresh follow-up evidence: native `node dev/verify.mjs` G1–G11 PASS; full `python dev/tests/portal.browser.py` PASS at 1440/390/768/667/320px, exit 0; `git diff --check` PASS. [PR checks](https://github.com/mingx96/wowthaisteyr/pull/16/checks) are authoritative for the published follow-up head. The theme workflow exports `homepage-browser-screenshots` alongside the existing portal evidence; those screenshots use local synthetic data.
- Visual/asset evidence: actual-template 390px and 1440px before/after review with self-hosted Playfair Display/DM Sans found no clipping, horizontal overflow or console error. Hero variants are 40,452 and 88,666 bytes versus the existing 292,131-byte JPEG; actual source selection was verified at DPR1 and DPR2. Local timing is experimental on a shared host, not a production speed claim.
- Limits: the inspected media catalog contains logos/placeholder, with no verified set of dish photographs. Missing photos remain absent, never inferred from the Hero. Browser fixtures are not live WordPress/database or production Core Web Vitals evidence. No production transaction, database synchronization or release occurred. The main publish workflow uploads an artifact; runtime delivery requires separate observation.
- Next: verify the current PR head's CI and update the same task with its final result, then obtain the user's merge/release decision. Re-read the main publish trigger and separately observe live runtime after any authorized release. No merge or live release is included in this handoff.

## Historical checkpoints

The entries below preserve earlier observations and permissions at their stated
times. Their pending/next steps are historical unless reverified in the active
checkpoint above; they do not authorize a new merge, deployment or data write.

## Public font rollback — 2026-10-20T20:18:00Z

- Same Notion task `3fe9ccae-c10f-81f4-b321-fb6e7839d67d`; user found Chonburi visually unbalanced and explicitly requested the previous typography. Branch `fix/restore-public-fonts-20261020` from merged main `331c872`; theme version 1.5.3.
- Scope is font-only: restore the prior self-hosted Playfair Display files/roles across public, checkout and portal typography; remove Chonburi assets. Keep the 1.5.2 SVG action icons, equal glass Hero CTAs, reservation source/display order, API, slots and all data behavior unchanged.
- TDD: the revised static contract failed against Chonburi, then passed after the rollback. Final local G1–G11, portal, public-hours and public-design browser suites pass at 320/390/768/1440; theme build succeeds. Visual review of 390px and 1440px synthetic Homepage/Reservierung renders confirms the prior proportions and no layout regression.
- Next: commit, PR, exact-head CI and established GitHub→WordPress.com release, followed by GET-only runtime font/version verification. No production form submission or data mutation.

## Public mobile typography/CTA/reservation follow-up — 2026-10-20T16:17:00Z

- Task ID `notion:3fe9ccae-c10f-81f4-b321-fb6e7839d67d`; owner Codex in this assigned chat. User approved the bounded public-theme implementation and Homepage proposal. Branch `feat/mobile-home-reservation-20261020` from current main `3bf4b3b`; worktree `/workspace/wowthai-mobile-refresh`; planned theme version 1.5.2. No overlapping open PR at start. The separate modified hours plan remains untouched in `/workspace/wowthai-compact`.
- Scope: self-host Chonburi for public marketing headings while DM Sans stays functional UI; shared decorative circular SVG arrow; equal-weight transparent Hero CTAs; form-first reservation source order with mobile/tablet form→menu link→information and unchanged desktop visual columns. No API, slots, bookings, orders, payment, email, schedule or database behavior changes.
- TDD evidence: new static/browser contracts first failed on Unicode arrows/missing icon/font/form order. The browser contract then exposed a four-line Chonburi desktop Hero and passed after a desktop-only size correction. Final local verification passes G1–G11 plus portal, public-hours and public-design browser suites at 320×667, 390×844, 768×1024 and 1440×900. Theme 1.5.2 builds successfully; diff and public-design self-review are clean. Exact-head CI/runtime observation remain pending.
- Existing Notion audit task is now `Đang thực hiện` and contains the 390px before/after images plus a mobile Homepage wireframe and prioritized P1–P3 proposal. This Git branch carries implementation/tests only; Notion remains roadmap/lifecycle authority.
- Next: commit and open a PR, verify exact-head CI, then observe the established GitHub→WordPress.com deployment and public GET-only runtime. No production mutation has occurred.

## Sondertage save UX and compact menu follow-up — 2026-10-20T12:37:29Z

- Same Task ID `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`; owner Codex in this assigned chat. User reported that a closed Sondertag could not be saved and the dropdown remained visually weak, then approved the bounded repair design. Branch `fix/betrieb-hours-menu-20261020` from merged main `e2e5bee`; theme version1.5.1. Existing post-release plan WIP remains preserved.
- Root cause: the live screenshot had `Von`24.12.2026 and retained `Bis`24.10.2026. The client correctly rejected the inverted range but displayed a disabled save control before preview, so the UI looked unresponsive. No production API/database fault was established and no live schedule/order/booking/customer/payment/mail write or DB sync was performed.
- Changes: changing the start date advances an earlier end date and draft preview date; invalid ranges receive inline field errors and focus without reaching the API. Save stays hidden until a signed preview exists. Dropdown is a 360px single-column hierarchy with consistent SVG area icons, grouped context/settings/account controls, no duplicate clock/network copy and reset-to-top behavior on every open; primary queues and connection indicator remain outside.
- Verification at local WIP: G1–G11 PASS with native PHP8.2 fixture; actual-template Playwright PASS at1440×1000,768×1024,390×844,320×667 and landscape667×390. New assertions reproduce the reported date inversion, prove no invalid preview request, verify the exact closed-exception payload/preview date, two-step save visibility, active/current navigation, keyboard dismissal, compact bounds and short-screen scroll reset. Independent review found a hidden-tab validation focus and a35px footer logout target; both now have Chromium regressions and pass after revealing Sondertage before focus and restoring44px. Exact-head CI/PR/release and live asset observation remain pending.
- Next: commit the review fixes without the separate plan WIP, then publish through the established GitHub→WordPress.com path. Authenticated live save should use an actual intended closure rather than a synthetic production mutation.

## Opening-hours1.5.0 runtime checkpoint — 2026-10-20T11:49:25Z

- Same Task ID `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`, Codex/user-authorized scope. [PR11](https://github.com/mingx96/wowthaisteyr/pull/11), source6b6f12189d063fc1945b74ed31aa6f9c69763575, mergee2e5bee80525b67317c95898a2110245c95d3d1f. Published source treeb0da40e8cb16fde0a212127abc04843dcce69cab exactly matches independently reviewed localcafced667 tree. Local feature branch kept; this checkpoint/plan completion is local post-release documentation WIP, not another main push/deployment.
- All3 exact-head PR checks PASS; main verify/agent-skills/ai-skills and Publish-Website PASS. Whole-branch review approved after independent native and both browser suites; final native/unit/browser/fixture evidence remains in the release-preparation entry below and PR. No production schedule/order/booking/payment/customer/mail write or database sync performed.
- Runtime11:45–11:46UTC: actual emitted1.5.0 URLs on fresh and canonical gzip homepage/menu/reservations/Betrieb match source bytes: portal.css65ff6602914e, portal.js7e30cfeec0aa, portal-hours.jsc5853b3a09a8, wowthai.js8f2dde27a2a226c8, hours-public.js7095b58968230a71. Five staff GET routes including schedule return401 anonymously; staff app hidden. Public availability200/no-store, Vienna date/ranges/slots and grouped schema, no private configuration/commitments. Today's original11:00–14:30 and17:00–21:00 windows remain effective.
- Public browser executed actual live HTML/assets/API via GET-only proxy/CA broker: homepage and reservations each hydrate2 hours regions, no JS errors, booking slots17:00–20:30. No response mocks, login, form submit or mutation. This verifies observed canonical/encoding variants, not every global CDN edge. Private execution artifacts: artifacts/hours/{live-release,canonical-live-release,live-public-browser}.json and screenshots; implementation/review reports, ledger and verification logs archived in artifacts/hours/evidence before plan-scratch cleanup.
- Existing Notion task and Business OS roadmap updated/read back; task Cần xem xét for authenticated staff acceptance. Production staff UI/save, MySQL advisory-lock support and direct SQL diagnosis NOT RUN because this cloud has no authenticated staff/SQL session. Don't modify a live schedule just to test it. Use the normal staff session for read-only navigation acceptance and apply real schedule changes only when needed.
- Rollback remains staff presentation only while retaining schedule readers/API/public consumers; no production DB restore/sync. Disposable fixture containers are stopped after completed checks; volumes remain for later isolated tests. Next: staff acceptance, then audit per the existing roadmap rather than another database copy.

## Opening-hours feature release preparation — 2026-10-20T11:38:29Z

- Same Task ID `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`; owner Codex in this assigned chat, complete implementation/release explicitly authorized. Base main7126f35, branch `feat/betrieb-hours-20261020`; latest source70755315ee3b724a5c44382f3988aad6e16028c3, version1.5.0. Local task commits c987133/8f8f5d7 (core),5ce85c5/058b652 (API/public),e45ad21/7075531 (staff UI). Root-only plan/data-boundary/checkpoint docs are being committed; no unrelated WIP moved. Remote main unchanged and no competing open PRs at latest check.
- Implemented: actual logo and always-visible order/booking buttons/counts, Menü right with popup below the full header, contextual orders toolbar; weekly/date-exception editor and published/draft preview, manager-only save with nonce/revision/signed impact/acknowledgement/advisory lock. Canonical resolver and public actual-date availability keep display/schema/booking/checkout consistent. Deployment never seeds schedule options or replaces historical closures. No production transaction/customer/payment/mail changes or database sync.
- Root final verification at source7075531: native PHP8.2.34/Node24 G1–G11 PASS (69 schedule,98 hours,47 API,13 menu,25 read-model assertions). Actual-template staff browser1440/390/768/320 plus390×667 PASS; actual public home/booking/checkout browser320/390-short/768/1440 PASS, including retained inputs and both stale pickup-response races. Artifacts saved under the task's private execution workspace, not public Git.
- Real disposable WordPress7.1.3/Woo11.2.1/full inspected Operations1.0.2: root59 hours REST/database assertions per legacy/HPOS at5ce85c5 PASS; unchanged backend since then. Existing read14+transaction roundtrip26 per mode PASS. Additional11 cross-surface checks PASS: API close/remove, phone slots/create rejection/recovery, final checkout validation. Fixture URL/test/HTTP/mail guards active; options restored and own synthetic orders/bookings/users removed. Plugin-owned synthetic contact/audit fixtures can remain only in that disposable installation.
- Independent task reviews approved after three fixes: nonexistent spring-DST windows fail closed, old checkout AJAX cannot restore stale slots, and mobile dropdown no longer covers primary navigation. Missing local-time boundaries omit that date-specific window; other valid windows remain. Whole-branch review and exact-head remote CI remain pending; no release/runtime claim yet.
- Next: independent full-branch review, publish PR, verify exact-head CI, merge through existing GitHub→WordPress.com Deployments, and compare actual rendered assets plus public/private API boundaries. No live schedule write just for smoke testing. Authenticated production staff UI/save, advisory-lock capability and direct SQL diagnosis are unavailable from this cloud and must remain explicitly separate.
- Rollback boundary: preserve canonical schedule readers/options/API/public consumers once custom hours exist; revert staff presentation separately. Never restore/synchronize production DB for this UI rollback. Existing worktree retained for review/recovery.

## Approved schedule/editor implementation — 2026-10-20T10:53:35Z

- Task ID `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`; execution owner Codex in this assigned chat. User explicitly requested implementing and releasing the complete navigation/schedule plan. Scope recorded/read in existing task, status in progress; no overlapping open PRs at start. Preserve older local doc WIP below.
- Base main `7126f35f22f8e4fa761bdd66c309a2ca4da8fd33`; branch `feat/betrieb-hours-20261020`, existing isolated worktree. Approved spec and implementation plan in docs/superpowers/. Subagent task briefs/reports are private workspace scratch; this file remains public technical checkpoint. Core commits c987133 + 8f8f5d7 completed; current task source HEAD remains local until reviewed publication. REST/public freshness work in progress; root plan/checkpoint docs are unstaged WIP.
- Scope: real logo, fixed order/booking buttons/new-only badges/Menu right; manager-editable weekly/day exceptions in the existing options model, preview/conflict confirmation/revision/nonce/lock, public-date slots and consistent hours displays. Operations plugin retains transaction writes. No production schedule edit just for testing, no customer/order/payment/mail writes or DB sync.
- Baseline native PHP8.2.34 G1–G11 PASS; disposable WP7.1.3/Woo11.2.1 guards verified (fixture.invalid, test opt-in, outbound HTTP blocked, mail suppressed), legacy storage. Docker fixture containers only; no production shell/browser credentials inferred.
- Core review checkpoint 2026-10-20T11:05:03Z: independent review approved after spring-DST missing-time fix; native G1–G11 PASS, 69 schedule/98 hours/13 menu/25 model assertions PASS. Root reran69 schedule assertions PASS. A nonexistent local-time boundary makes that date-specific window unavailable rather than silently shifting it; valid other windows remain.
- Next: API/store tests/review, UI/browser/review, full fixture integration and exact-head CI before the authorized GitHub→WordPress.com release. Runtime remains1.4.5 until separately observed; authenticated production staff UI/write verification unavailable in this cloud.

## Single-dropdown runtime checkpoint — 2026-10-20T10:19:00Z

- Same Task ID `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`, Codex/user-authorized scope. Source `dcb95a4a957870bd877cfe4891305e83f8ed277c`, [PR10](https://github.com/mingx96/wowthaisteyr/pull/10), merged main `7126f35f22f8e4fa761bdd66c309a2ca4da8fd33`. Three exact-head checks and Publish-Website success. Source clean; this post-release checkpoint is local documentation-only WIP, not a second deployment.
- Runtime observed10:18 UTC: actual rendered1.4.5 asset URLs match source JS SHA256 `afdc8c373cf0dfcea5b8f1e7e46348b402be0a87df8c0ed4181d3d4ad4849803`, CSS `fdd74be90ab46e2f7d93677ff4b3e68199fdbe1076cca30b3476ff88597571a6`; menu/icon200; protected summary/orders/reservations/daily401anonymous without staff items; login hides app. Authenticated production staff interactions NOT RUN; no production data mutations/API/backend changes.
- Native G1–G11 and Playwright1440/390/768/320 plus390×667 PASS, independent re-review approved. PHP fixture container stopped. Existing Notion task receives final evidence/status Cần xem xét for staff acceptance. Next: user/staff checks the compact dropdown on their normal authenticated device; continue established Business OS audit, not another database sync.

## Single-dropdown follow-up — 2026-10-20T10:15:00Z

- Task ID `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`, same owner Codex/chat. User shows1.4.4 running and asks to collapse menus/options into one dropdown. Scope and continuation via established GitHub→WordPress.com deployment recorded in the existing task, status in progress; no overlapping open PR. Prior local checkpoints preserved and included here.
- Base main `5dab100382b821d60a4f6c88ae2472d9d16541d4`; isolated branch `fix/portal-dropdown-20261020`, v1.4.5. Current scoped PHP/JS/CSS/browser/docs WIP; eventual branch/PR HEAD is authoritative. No backend, API, order/payment/email or database changes.
- Changes: single native disclosure for modules, active-view filters, mobile columns, kitchen pacing, sound, Help, account/logout and CRM filter. Outside it remain current view, kitchen/network state, global queue badges, and applicable date/scope/KPIs/compact + reservation action. Booking cards have less spacing; duplicate mobile settings removed. Selected all-day scope remains visible outside; a disabled irrelevant date is hidden.
- Verification: native PHP8.2.34/Node24 G1–G11 PASS. Actual-template Playwright PASS1440/390/768/320 plus390×667; covers keyboard Enter/Tab/Escape, outside dismiss/focus, mobile logout/dot, popup bounds, column changes, reservation dialog, CRM filter and existing archive/filter/polling/daily assertions. No real requests/writes. Independent review found obsolete mobile CSS hiding logout/network; new regression reproduced it, fix passed and re-review approved. git diff --check PASS. Database tests from1.4.4 remain historical, not rerun for this UI-only patch.
- Release pending exact-head CI/PR/merge/runtime observation; existing user authorization covers this scoped continuation. No production staff session available. Next: publish, verify exact-head CI, merge and compare actual rendered1.4.5 assets on live; update existing Notion task. Rollback is scoped theme revert, no DB restore/sync.

## Follow-up runtime / handoff — 2026-10-20T09:29:00Z

- Same Task ID `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`, owner Codex, scoped user-authorized repair/release. Source `4915e655787152143b21617a827c8c9f58ed0a65`, [PR9](https://github.com/mingx96/wowthaisteyr/pull/9), merged main `5dab100382b821d60a4f6c88ae2472d9d16541d4`. Exact-head and main verify/skills/publish all success. No source WIP; this post-release checkpoint is a local documentation-only update, not another deployment. Earlier worktree WIP preserved.
- Observed live1.4.4: actual rendered JS SHA256 `de4650f0984a4fb0fe5ab87dd5868de866c07d6f30c754675b65e6ffe406b07b`, CSS `70dd2cf37bbd0bff56cdbe41e058ea2df85104968134b8ae7ad03f5d566bf88d`, both match source. Canonical homepage icon is brand at09:27:40; canonical portal browser/gzip request renders1.4.4 at09:28:08. Icon/menu200; four protected anonymous read routes401 and no staff items; login hides app. A separate anonymous identity-encoding login cache still held old HTML, so do not claim all CDN variants converged. Authenticated production staff API/UI/SQL NOT RUN.
- Test evidence: native G1–G11/build and actual-template browser1440/390/768 plus390×667 PASS. Disposable full-plugin REST/DB26 + read14 assertions each legacy/HPOS =80 PASS; independent re-review approved. ZIP1.4.4 SHA256 `0dfb87ab0820d19546d38aef3e39b1f4614567159c430cbc8e75149ab7cb829a`. Local fixture containers stopped. No production order/payment/customer/database writes or sync.
- Existing Notion task receives final runtime/test/limits and status Cần xem xét for staff acceptance; roadmap receives bounded audit findings. No deployment credential blocker; no redundant permission request. Next: staff read-only acceptance in an authenticated session, then comprehensive audit per existing roadmap; cash closing stays later scope.

## Compact portal / favicon follow-up — 2026-10-20T09:18:00Z

- Task ID: `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`. Owner Codex in the same repair chat; user confirms prior release works and requests favicon, compact staff layout and data-flow verification. Scope/release via existing GitHub → WordPress.com Deployments recorded in that task, status in progress. No competing owner/open overlapping PR found; prior worktree WIP preserved.
- Base main `ee0857cf47ee86380b5302549e967bcb0663cc6d`; isolated branch `fix/compact-portal-favicon-20261020`, v1.4.4. Scoped source/tests/docs currently uncommitted; subsequent branch/PR HEAD is authoritative.
- Changes: brand favicon fallback after platform defaults when no native site icon; smaller desktop toolbar/buttons, conditional compact pagination, shared on-demand help, readable booking counts, separate cancelled history. Mobile retains 44px targets. Connection failures now reach mobile settings too. Unsupported arrived→confirmed button removed to match inspected Operations API. Additive `payment_display` avoids calling COD money collected; no financial flags or plugin mutations.
- Verification: `node dev/verify.mjs` G1–G11 and `node dev/build.mjs` PASS using native PHP8.2.34/Node24. Actual-template Playwright1440/390/768 PASS; added 390×667 cancelled-history regression reproduced overflow before fix then passed. Independent review found that clipping issue; outer history scrolling fixed, final re-review approved with no remaining blockers. Full inspected Operations1.0.2 in isolated WordPress7.1.3/Woo11.2.1:26 REST/database roundtrip assertions PASS each legacy/HPOS; read reconciliation14 each PASS; final HPOS and legacy reruns PASS. Synthetic data only, HTTP/mail blocked; no production writes or database sync.
- Live evidence at start: native site_icon0; public homepage platform icon vs portal brand icon; supported WordPress settings/plugin reads work. No authenticated production staff API/browser or direct SQL session is available. Tests prove the inspected fixture contract, not live plugin byte equality or cash collection. User screenshots are prior1.4.3 staff acceptance, not1.4.4 acceptance.
- Next: final review and exact-head CI, merge scoped repair under existing authorization, observe actual rendered asset URLs/favicon on live and anonymous API protection; save runtime evidence in same Notion task. Rollback is scoped theme revert to base; never restore/sync a database.
- Notion follow-up scope saved/read back at start; test evidence sync prepared; PR/runtime evidence follows in the same task. Details: docs/portal-data-flow.md and docs/TESTING.md.

## Live release/cache observation — 2026-10-19T22:35:00Z

- Same Task ID: `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`. Source PR7/f63c9dc merged to main797139e; exact-head and main CI/publish-artifact jobs passed. The scoped release was authorized by the user.
- At22:32 UTC public probes still saw old runtime and404. At22:33 a cache-busting probe saw new JS SHA256 `e0e82f0aec6ab767534c6e06d726238ed0a629cef314c35d9c918ed975548647`, style1.4.2, new login config and401 on the new protected API. The external publication path therefore delivered the code after a delay; no new credentials or deployment setup is requested.
- **Material cache defect:** at22:34 the actual `portal.js?v=1.4.2` / `portal.css?v=1.4.2` URLs still returned old assets, while a fresh query served new code. The bare summary URL also retained an earlier404 while uncached protected routes returned401. Do not call the UI smoke complete from the fresh-query response alone.
- Follow-up branch `fix/betrieb-release-cache-20261019`, based on797139e, bumps patch to1.4.3 and adds content fingerprints to rendered portal asset URLs/read requests and a regression assertion. Same owner/scope; no changes to business transitions, plugin, data, credentials or infrastructure. The cache correction, fixture assertions, version bump and checkpoint are local WIP pending the follow-up commit.
- Full backend and synthetic UI coverage for the original repair remains as below. Native G1–G11, packaging and browser1440/390/768 passed after the1.4.3 cache correction; independent review approved; authenticated live staff API/UI still requires a logged-in session and remains NOT RUN in this cloud.
- Next: review/publish the small cache follow-up, verify actual rendered JS/CSS URLs against source bytes and anonymous API denial, then record acceptance/remaining authenticated smoke in the existing task. No DB restore or sync.

## Betrieb repair checkpoint — 2026-10-19T22:30:18+00:00

- Task ID: `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`. Execution owner: Codex in the assigned repair chat; owner/scope registered in the existing private project task. The user approved implementing/releasing these scoped repairs. Historical multi-branch/other-agent local work remains outside scope.
- Scope: theme portal/read-only API only, v1.4.2. Canonical kitchen hours, archive visibility/pagination, reservation scope/date/totals, global alerts, daily order-value drilldown and accessible polling feedback. Current Operations plugin remains responsible for writes, version tokens, email and payment behavior. No product/customer/production database or CRM changes.
- Base: main `5980f6bb5b72a3f9fbb85e9560cb72e48ad74882`; branch `fix/betrieb-consistency-20261019`. HEAD before commit: base; scoped PHP/JS/CSS/tests/docs are in the isolated worktree. The proposed branch/PR HEAD is authoritative after commit; no unrelated WIP was moved.
- Verification: native PHP8.2.34/Node24, `node dev/verify.mjs` G1–G11 PASS (98 hours, 13 menu-description, 25 read-model assertions). `python dev/tests/portal.browser.py` PASS at1440/390/768px using actual template/assets and synthetic APIs. Independent review approved after global-chime/focus fixes. Local WordPress7.1.3/WooCommerce11.2.1 integration:14 assertions PASS each in legacy and HPOS, including REST permissions/no-store, >200orders, real refund and full detail reconciliation. Local fixture database only; outbound mail/HTTP blocked. Snapshot DTOs used for integration are not a live plugin source fingerprint.
- Tested runtime source fingerprints: assets/js/portal.js: `e0e82f0aec6ab767534c6e06d726238ed0a629cef314c35d9c918ed975548647`; inc/portal-read-model.php: `692b59c7bd9c6e7fb3490ed4b27dc0ab01315a3be73b8df61660919708493c61`.
- Release: pending. Public live JS previously matched main (`da88798512f08019022dfd20e36567bc9397b4c27e4023072fe9e0a9d9a80889`); current live plugin reports Operations1.0.2, its source bytes are unverified. GitHub's main publish workflow uploads an artifact; external deployment and authenticated staff API/UI smoke require separate observed evidence.
- Rollback: preserve base code/ZIP and revert only scoped theme changes if release smoke fails; do not restore/sync a database. Managed backup check reports active/last attempt finished, not a restore rehearsal.
- Notion: owner/scope and Business OS audit plan saved and read back in the existing project. Lifecycle remains in progress until release evidence is recorded; no cash-closing/POS feature in this patch.
- Next: publish reviewable branch/PR, verify exact-head CI, release through the configured path, compare live assets and staff read API behavior, then record observed result/limitations in the same task.


## Documentation setup checkpoint — 2026-10-19T20:16:00Z

- Task reference: `notion:3fe9ccae-c10f-81de-8d73-d35baf473442`. The task's private record owns lifecycle, acceptance and execution owner; no private Notion URL is published here.
- Scope: documentation only on `docs/ai-handoff-contract-20261019`, based on main `2784511`. No PHP/JS/CSS/runtime/workflow changes, no merge, deployment or database write.
- Delivery boundary: these instructions are proposed on this branch/PR, not activated on default main until reviewed and merged. Check the branch/PR for its current HEAD and CI, not a stale hash in prose.
- Next: review docs, CI and main's publish trigger, then obtain the required merge decision. Opening the PR does not complete main activation.

## Verified technical checkpoint — 2026-10-19T20:10:27Z

- **Source:** GitHub read-only audit. Default branch `main`, HEAD [2784511](https://github.com/mingx96/wowthaisteyr/commit/278451185705cebc7b0e86f3617ee6fb544b493b); source `style.css` says 1.4.1. Neither value proves the running live revision.
- **Menu HTML fix:** [PR #5](https://github.com/mingx96/wowthaisteyr/pull/5) merged 2026-10-19T19:02:51Z from `fix/menu-description-html-20261019` / `ca6178f`. No open PRs were returned at audit time.
- **Evidence:** [Verify WordPress theme](https://github.com/mingx96/wowthaisteyr/actions/runs/39251193554) passed on `ca6178f`; [agent skills](https://github.com/mingx96/wowthaisteyr/actions/runs/39251193684) and [design/workflow skills](https://github.com/mingx96/wowthaisteyr/actions/runs/39251193598) also passed. The PR reports 13 menu-description cases and 98 hours assertions. These are not live WordPress/browser checkout checks.
- **Live:** unknown in this audit. No deployment, product save, database write or live test was performed. The checked-in `.github/workflows/wpcom.yml` runs “Publish Website” artifact creation on every push to main without a path filter. The external deployment connection is not verified; historical hub notes describe auto-deploy. Recheck the actual deployment configuration and runtime before release work.
- **Owner / shared-site claim:** not reverified. The older Antigravity claim below is preserved as a historical claim, not silently released or assigned to the current reader. Confirm current execution owner, allowed scope and site-write permission in the existing private task before any shared-site write.
- **Next action:** read the existing task's latest acceptance/release decision and compare the approved source with the active runtime using read-only checks. Do not repeat the old “review/merge PR #5” step; do not deploy based only on this file.

## Checkpoint contract

Follow [AGENTS.md](AGENTS.md). Notion owns task lifecycle/priority/decisions; this file owns the repo's technical checkpoint and shared-site coordination evidence. Keep private task URLs/session links out of this public repo.

For the active task record: `Task ID`; `updated_at/observed_at (UTC)`; `execution owner + allowed file/site scope`; `base/branch/HEAD/PR`; `dirty/untracked WIP`; `changed`; `test command + environment + SHA + result + evidence URL`; `release observed/reported/unknown`; `blocker/resume condition`; `next action`; `Notion sync time or pending`.

Checkpoint before stopping or handing off. Re-read current owner before accepting a handoff. Missing freshness is a reason to verify, not permission to overwrite. Review the actual script and [docs/TESTING.md](docs/TESTING.md), not historic gate counts.

## Historical records — preserve context, do not treat as current authorization

Entries below record what was known at their written time. “Active/current”, branch/PR state, version numbers, machine paths and test counts in these entries are historical unless reverified above. Their safety constraints continue to apply; they do not grant permission to merge, deploy or mutate a database.


## 2026-10-19 — Codex: menu description HTML hotfix, review only

- Scope: branch `fix/menu-description-html-20261019`, based on `dbd75c0`.
  Public `/menu/` inspection found six lunch cards displaying literal `<em>`
  markup after product edits. The menu builder passed rich product content to
  plain-text card/modal/search/schema consumers.
- Change: opt-in plain-text normalization at the shared menu data boundary;
  decode entities, strip editor HTML and preserve word spacing. Existing rich
  WooCommerce descriptions, product data, prices and choices are unchanged.
- Verification: regression reproduced nine failures before the fix, then
  13/13 passed; existing hours suite 98/98 passed. G1–G11 passed using the local
  PHP-WASM CLI. Native PHP 8.2 verification is required in PR CI; these are not
  live WordPress/browser checkout tests.
- Next: review the PR and its CI, compare the changed PHP files against the
  active theme before a scoped release, then verify menu cards and selection
  dialogs on mobile. No live upload, database write, or deployment occurred.
  This branch does not claim the shared-site write lock listed below.

- **Active Agent in Charge**: **Antigravity (Mobile-First Redesign & Operations Portal v1.4.0)**
- **Current scope**: Mobile-First redesign for `/betrieb/` (compact header, segmented Kanban switcher), customer menu above-the-fold, global header drawer, typography unification.
- **Current Theme Version**: `v1.4.0`
- **Current Plugin Version**: `v1.0.0`
- **Git Repository**: `https://github.com/mingx96/wowthaisteyr.git`
- **Primary Branch**: `main`

---

## 🔒 QUY TẮC PHỐI HỢP (RULES OF ENGAGEMENT)
1. **Chỉ Agent giữ lượt được ghi vào site chung**: Trước khi ghi vào Studio hoặc push lên Live (`wpcomstaging.com`), kiểm tra và cập nhật `COORDINATION.md`.
2. **Triển khai Theme — KHÔNG đồng bộ Database**: Mọi thay đổi nằm hoàn toàn trong mã nguồn theme `wp-content/themes/wowthai`. Tuyệt đối không import/overwrite database (`--options "themes"` duy nhất) để bảo toàn dữ liệu đơn hàng và cấu hình thực tế.
3. **Kiểm định Tự động Bắt buộc**: Luôn chạy `node dev/verify.mjs` (10/10 cổng G1–G10 PASS + 98 unit assertions trong `dev/tests/hours.test.php`) trước khi đóng gói `node dev/build.mjs` và đẩy lên Live.

---

## 🚀 TÓM TẮT THAY ĐỔI TỪ v1.1.4 ĐẾN v1.3.1 (CHANGELOG)

### 1. Phiên bản v1.3.1 (Chuẩn hóa Giờ mở cửa 11:00–14:30 Mo–Sa, Mittagsmenü M4 Di–Fr, Kết nối Email Toàn diện)
- **Chuẩn hóa Giờ Mở Cửa Mới (`inc/hours.php`)**:
  - Thứ 2 đến Thứ 7 (`Mo–Sa`): Bếp mở từ **`11:00 – 14:30`** và **`17:00 – 21:00`** (điều chỉnh ca trưa từ `11:30` thành `11:00`).
  - Chủ Nhật (`So`): Mở liên tục từ **`11:30 – 19:00`**.
  - Thứ 2 giữ nguyên là ngày nghỉ Sushi (`Montags Sushi-Ruhetag`, bếp nóng mở bình thường).
- **Quy tắc Nghiêm ngặt cho Mittagsmenü M4 (`★ M4 – Spicy Crunchy Tuna Rolls`, ID 84)**:
  - Khác với Mittagsmenü thường (phục vụ `Mo–Sa`), riêng **M4 Sushi chỉ phục vụ từ Thứ 3 đến Thứ 6 (`Di – Fr` `[2, 3, 4, 5]`) trong khung giờ `11:00 – 14:30`**.
  - Bổ sung `wowthai_product_is_mittagsmenu_m4()`, `wowthai_cart_has_mittagsmenu_m4()`, `wowthai_mittagsmenu_m4_available_on_dow()`, `wowthai_is_slot_valid_for_mittagsmenu_m4()`.
  - Trên `/menu/`: Gắn badge nổi bật `🍣 Nur Di – Fr · 11:00 – 14:30 Uhr` trên thẻ món M4; vô hiệu hóa nút đặt món M4 vào Thứ 2, Thứ 7, Chủ Nhật (`🍣 Nur Di – Fr verfügbar`).
  - Tại Giỏ hàng / Kasse: Chỉ hiển thị các khung giờ Thứ 3–Thứ 6 cho đơn chứa M4; tự động chặn đặt ASAP ngoài giờ M4 với thông báo lịch sự tiếng Đức.
- **Giải quyết Triệt để Lỗi Ký tự Thực thể HTML & Bổ sung Mô tả Món Ăn (`inc/menu-normalizer.php`)**:
  - Giải mã thực thể HTML (`html_entity_decode`) tránh lỗi đúp mã hóa `&#8211;` của WordPress `wptexturize()`.
  - Đồng bộ khoảng trắng tên món: `★M` → `★ M` (nhất quán `★ M1`, `★ M2`, `★ M3R`, `★ M3H`, `★ M4`, `★ M2V`, `★ M3V`).
  - Sửa lỗi chính tả `(inkl.Reis)` → `(inkl. Reis)`, `Osinko Maki` → `Oshinko Maki`.
  - Bổ sung mô tả ẩm thực chuyên nghiệp cho 5 món bị bỏ trống trên Live (`V9`, `V11`, `V12`, `Lachs Bowl`, `Vegi Bowl`).
- **Nâng cấp Hệ thống Kết nối Email Khách hàng & Bếp (`inc/email-templates.php`, `inc/admin-orders.php`, `inc/admin-reservations.php`)**:
  - Thêm header `Reply-To: WOW Thai Steyr <info@wow-thai-restaurant.at>`.
  - Bổ sung `wowthai_email_log` (ring buffer 20 email gần nhất) và hook `wp_mail_failed` để bắt lỗi SMTP từ server.
  - Tự động gửi email HTML Branded khi nhân viên bấm **✓ Annehmen** (`✓ Bestellung #... angenommen – In Zubereitung`).
  - Tự động gửi email HTML Branded khi nhân viên bấm **🥡 Abholbereit** (`🥡 Ihre Bestellung #... ist abholbereit!`).
  - Đính kèm link tải file lịch `.ics` và link tự hủy đặt bàn 1-click trong email xác nhận bàn của khách.
  - Tích hợp thanh trạng thái kết nối E-mail trực quan ngay tại bảng điều khiển Admin Orders.
- **Bộ Kiểm định Tự động**: Tăng từ 62 lên **98 unit assertions** trong `dev/tests/hours.test.php`; vượt qua 10/10 cổng kiểm định tự động `dev/verify.mjs`.

### 2. Phiên bản v1.2.0 – v1.2.3
- **Live Search & Filter**, **In Phiếu Bếp 80mm (`[🖨️ Bon]`)**, **Chuông báo Web Audio Ding-Dong**, **Email HTML Branded Luxury**, **Trang pháp lý `/impressum/` & `/datenschutz/`**, **Schema.org JSON-LD & PWA Manifest**.
- **Thang độ cay (`🌶️`)**, **Bảng tra dị ứng Codex EU (A–R)**, **Slide-Cart Upsell 1-Click**, **Điều tiết nhịp độ Bếp ("Küchen-Taktung")**, **Bảo mật tính giá Server-side**, **Modifier Groups**, **`inc/pickup.php`**.

### 2. Phiên bản v1.3.0 (Phiên bản Hiện tại — Master Architecture, Legal, Kitchen & Modern Web Upgrade)
- **Phase 0 — Hệ thống Kiểm định Tự động (`dev/verify.mjs`, `dev/build.mjs`, `dev/tests/hours.test.php`)**:
  - 10 cổng kiểm định tự động **G1–G10** (kiểm tra Single Source of Truth giờ mở cửa, không Google Fonts ngoài, không lỗi UTF-8 mojibake, LF line endings, `node --check` + `php -l` toàn bộ 22 tệp PHP, đồng bộ version, chống truy cập superglobal thô, chống hàm UTC `date()`/`time()`/`strtotime()`, số điện thoại chuẩn `+43 7252 74973`, hiển thị đầy đủ biến thể/modifier trên phiếu bếp 80mm).
  - **62 unit assertions** trong `dev/tests/hours.test.php` kiểm thử đầy đủ biên giờ mở cửa, Thứ 2 nghỉ Sushi, khung giờ `Mittagsmenü` (Thứ 2–Thứ 7 `11:30–14:30`), hạn đặt bàn trước 30 phút đóng bếp, và chuyển đổi giờ mùa hè/mùa đông (`Europe/Vienna` DST).
- **Phase 1 — Hotfix Vận hành Bếp, Hóa đơn, Email & Pháp lý**:
  - **Phiếu bếp 80mm & Bảng Admin Orders (`inc/admin-orders.php`)**: Bổ sung `wowthai_get_order_item_customizations($item)` hiển thị đầy đủ `Variante` và `Modifiers` (loại mì, topping...) trên cả bảng đơn hàng và phiếu in nhiệt 80mm; đọc đúng khóa `'Abholzeit'` (kèm fallback tương thích ngược).
  - **Hóa đơn Vàng (`woocommerce/checkout/thankyou.php`)**: Sửa số điện thoại sai `+43 7252 45866` thành động `+43 7252 74973` và hiển thị chi tiết biến thể/modifier từng món.
  - **Email Hệ thống (`inc/email-templates.php`)**: Loại bỏ hoàn toàn fallback `dev-email@wpengine.local`, dùng `wowthai_get_notification_email()`.
  - **Chống 404 Trang Pháp lý (`functions.php`)**: Tự động khởi tạo trang `impressum` & `datenschutz` (`wowthai_ensure_legal_pages`) kết hợp virtual template routing đảm bảo `/impressum/` và `/datenschutz/` luôn trả về HTTP 200 mà không cần đồng bộ DB.
- **Phase 2 — Single Source of Truth Giờ Mở Cửa & Timezone (`inc/hours.php`)**:
  - Tập trung duy nhất toàn bộ cấu hình giờ mở cửa (`Mo–Sa 11:30–14:30 & 17:00–21:00`, `So 11:30–19:00`, Thứ 2 nghỉ Sushi, `Mittagsmenü` `Mo–Sa 11:30–14:30`) vào `inc/hours.php`.
  - Đồng bộ `inc/pickup.php`, `inc/restaurant-info.php`, `footer.php`, `front-page.php`, `inc/admin-reservations.php`; khóa đặt món `Mittagsmenü` ngoài khung giờ trưa Thứ 2–Thứ 7.
- **Phase 3 — Bảo mật Đặt bàn, DSGVO, `.ics` & Tự Hủy 1-Click (`inc/reservations-front.php`, `page-reservations.php`)**:
  - Chuyển sang luồng `admin-post.php` (`wowthai_reserve`) + Post-Redirect-Get với HMAC token, honeypot chống bot, giới hạn tần suất IP (ẩn danh SHA-256), kiểm tra tick đồng ý DSGVO, kiểm tra khung giờ hợp lệ từ `wowthai_reservation_slots()`, chống đặt trùng, tải lịch `.ics` (`wowthai_res_ics`), link khách tự hủy đặt bàn 1-click (`wowthai_res_cancel`), và cron tự động dọn dữ liệu quá 90 ngày theo DSGVO.
- **Phase 4 — Chuẩn hóa Thực đơn, Gỡ Google Fonts, Logo Retina & SEO/OG**:
  - **`inc/menu-normalizer.php`**: Tự động sửa lỗi chính tả danh mục `Vorpseise Sushi` → `Vorspeise Sushi`, khôi phục mô tả món `V10. Tod Man Gung`, và chuẩn hóa chính tả tiếng Đức cho toàn bộ 94 món mà không cần ghi đè DB.
  - **DSGVO Fonts (`assets/css/styles.css`)**: Gỡ bỏ `@import` Google Fonts bên ngoài, dùng 100% font tự host WOFF2 (`Playfair Display` & `DM Sans`).
  - **Tối ưu LCP & Logo (`assets/img/logo-sm.png`, `header.php`)**: Tạo logo 2x Retina `logo-sm.png` (21.6 KB, giảm 95% dung lượng so với 426 KB), chỉ preload ảnh Hero LCP trên trang chủ, bổ sung `<meta name="description">`, OpenGraph `og:*`, skip-link `#main`, và banner Sondertag.
- **Phase 5 — Modern Web `<dialog>`, Accessibility WCAG 2.1 AA, Tối ưu INP & Quản lý Ngày nghỉ Lễ (`page-menu.php`, `assets/js/wowthai.js`, `assets/css/styles.css`, `inc/admin-orders.php`)**:
  - Nâng cấp toàn bộ Modal món ăn, Giỏ hàng trượt và Bảng Allergen sang phần tử gốc `<dialog closedby="any">` mở bằng `.showModal()` kèm coordinate light-dismiss fallback cho Safari và khóa cuộn nền bằng CSS (`overscroll-behavior: contain`).
  - Chuẩn hóa `role="tab"`, `aria-selected`, `aria-pressed`, vùng thông báo `#dish-result-status` (`role="status" aria-live="polite"`), nhãn giỏ hàng động `Bestellung (N Artikel)` chuẩn WCAG 2.5.3.
  - Thay vòng lặp `setInterval` 1s bằng sự kiện WooCommerce (`updated_checkout`, `checkout_error`) và tự động tạm dừng polling 30s khi tab ẩn (`document.hidden`).
  - Bổ sung thanh điều khiển **Sondertage / Feiertage / Betriebsurlaub** ngay trong trang Đơn hàng Admin.

---

## 📂 DANH MỤC TỆP TIN THUỘC THEME (22 PHP Files — 10/10 Gates Passed)
- `style.css` (v1.3.0)
- `functions.php`
- `header.php`, `footer.php`, `front-page.php`, `index.php`, `page.php`, `woocommerce.php`
- `page-menu.php`, `page-reservations.php`, `page-impressum.php`, `page-datenschutz.php`
- `inc/hours.php` (Single Source of Truth cho giờ mở cửa, timezone `Europe/Vienna`, Sushi & Mittagsmenü)
- `inc/menu-normalizer.php` (Chuẩn hóa tên danh mục & chính tả tiếng Đức cho 94 món ăn)
- `inc/reservations-front.php` (PRG, Rate-limit, Honeypot, DSGVO, `.ics`, 1-click Cancel, 90-day Cron)
- `inc/pickup.php`, `inc/restaurant-info.php`, `inc/restaurant-experience.php`
- `inc/admin-orders.php`, `inc/admin-reservations.php`, `inc/email-templates.php`
- `woocommerce/checkout/form-checkout.php`, `woocommerce/checkout/thankyou.php`
- `assets/js/wowthai.js`, `assets/css/styles.css`, `assets/css/checkout.css`, `assets/css/fonts.css`
- `assets/img/logo-sm.png`, `assets/img/logo.png`, `assets/img/hero.jpg`, `assets/pwa/manifest.json`

