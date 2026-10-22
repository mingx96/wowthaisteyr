# WOW Thai Steyr — Warm Cinematic Editorial Homepage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the approved Espresso & Gold / Warm Editorial direction into an accessible, photo-led Steyr homepage while leaving ordering, reservations, opening hours and staff workflows unchanged.

**Architecture:** Keep the existing PHP classic theme, with one small, deterministic homepage photo registry and homepage-scoped CSS. Add a compact editorial section between the hero and the existing WooCommerce-managed discovery, while protecting the current data, anchors and asset-loading contracts. All photo publication is gated by source fidelity, reuse rights and a separate human visual review.

**Tech Stack:** PHP 7.4+ WordPress classic theme, WooCommerce read models, CSS, self-hosted Playfair Display / DM Sans, WebP/JPEG, Node 20 built-in test tools, PHP standalone tests, Python Playwright/Chromium.

**Spec:** `docs/superpowers/specs/2026-10-22-steyr-warm-cinematic-editorial-homepage-design.md`. Also read `AGENTS.md`, `COORDINATION.md` and `docs/TESTING.md` before execution.

**Decision state:** The user said “Thực hiện” after PR #18 was presented. This advances the design work **to implementation-plan drafting**; the plan and execution method still require review. No code, merge, WordPress write or production deployment is authorised by this plan document.

## Global Constraints

- Exact starting reference as of plan authoring: `main` = `6b76152fa083873c2f4ebb354ef8f97f86f02b3a`, theme 1.5.4, with the design-doc draft PR #18 open. Recheck HEAD/branch/owner/overlap before the later coding task.
- Do **not** implement from or merge this documentation branch. After plan approval, start a new isolated feature branch from a rechecked `main`. Do not disturb existing worktrees or uncommitted changes.
- Retain the visible German welcome, Steyr/City Point 1. OG identity, Playfair Display + DM Sans, current brand and the two **equal-weight 48px** hero CTAs linking to `/menu/` and `/reservations/`.
- Preserve `wowthai_get_homepage_dishes()`, the selected WooCommerce product mapper, dynamic names/prices/variant ranges, product-owned image eligibility, read-only `/menu/#gericht-ID` links and the no-product fallback.
- Preserve `wowthai_hours_display()`, `wowthai_get_restaurant_data()`, current-day freshness/exception and Monday Sushi messages, `#besuch`, City Point parking/lift, directions, phone, mobile drawer, cart, checkout, booking and `/betrieb/`.
- Do not touch operational database, WordPress pages/options, products, bookings, pricing, allergen/tax settings, staff scheduling, checkout/payment/emails, deployment settings, or a production form.
- Only the **verified** original photo may be used as real food or venue photography. Generated images from earlier AI comparisons are **moodboards, not real dish evidence**. Preserve original bytes/checksums; source identity, photographer/usage rights and image content require human QA before any photograph is committed publicly.
- Only publish a small number of source-faithful runtime WebPs. Keep the 173-image archive, JPEG masters and license evidence in the controlled Notion/Library/Drive album, never in the public Git repo.
- Source roles to consider: `SA-0764` hero; `SA-0735` **or** `SA-0729` wide sushi; `VA-0383` **or** `SA-0727` supporting portrait. `SA-0761` is optional and deferred from the initial Homepage; cross-branch staff or facade images must not represent Steyr.
- Use homepage-scoped design targets: espresso `#171412`, deeper surround `#211813`/`#281E19`, ivory `#F6EBDD`, secondary `#D0C1AD`, gold `#D9B875`, optional cream `#F1E7D9` with dark text `#2A211C`. Evaluate contrast against actual media/background.
- No new page builder, WordPress plugin, persistent client state, heavy animation library, autoplay video/carousel, remote font or image CDN. Real content stays visible without JS and under reduced-motion.
- Test on widths 320/390/768/1024/1280/1440/1920px; 390×667 short viewport, 200% text, absent product/media, no-JS, contrast, keyboard/focus, stale/failing hours. Final screenshots use **real approved runtime derivatives**, not AI-generated concept images.
- GitHub `main` may start a WordPress.com delivery even on documentation merges. CI green and PR merge do **not** prove runtime release; publishing requires a fresh explicit user decision.

## Review Focus — Five Easy-to-Miss Failure Modes

1. **A new image looks like the “same dish” but is generatively different or lacks publication rights.** Task 1 introduces an explicit provenance/approved-export contract test; human checks ingredient/portion/plate consistency and source rights against the immutable original.
2. **A tall hero photo pushes both actions below a short phone screen or crops the food unrecognisably.** Task 2 adds first-fold geometry assertions at 390×667, focal-point screenshot review at 320/390/768/1440/1920, and 200% text checks in Task 4.
3. **No eligible WooCommerce products or a broken editorial image causes a blank chapter, broken scroll anchor or fake product picture.** Task 3 tests `home-empty`, `home-images`, the distinct editorial anchor, simulated image failure and original three-product price/link behavior.
4. **A special closure or stale after-midnight kitchen response is hidden by the redesign.** Task 4 exercises unchanged `data-wowthai-home-status` / weekly `details` under fresh/closed/stale/midnight/no-JS paths and retains existing kitchen browser regressions.
5. **Home-specific CSS changes the shared cart/booking/header on another page or breaks focus at high zoom.** Task 4 uses non-home public-design browser checks, keyboard/mobile drawer/cart tests, contrast and 200% text; Task 5 runs the full separate public and portal suites.

## File Map and Component Ownership

| File | Responsibility |
| --- | --- |
| `assets/img/homepage/SOURCES.json` | Public-safe provenance + derivative hash/dimension metadata; do not publish private rights evidence |
| `assets/img/homepage/warm-hero-{640,960,1440}.webp`, `warm-hero.jpg` | Approved derivative of SA-0764, never overwrite old `assets/img/hero*` until accepted |
| `assets/img/homepage/warm-sushi-{768,1440}.webp` | One approved wide sushi editorial derivative |
| `assets/img/homepage/warm-food-{640,960}.webp` | One approved supporting portrait derivative |
| `dev/tests/homepage-media.test.mjs` | Pure-Node provenance, WebP dimension/sha/path and byte-budget regression |
| `inc/homepage-media.php` | `wowthai_home_media(string $role): ?array` deterministic media whitelist (hero/sushi/food) |
| `dev/tests/homepage-media.test.php` | PHP contract for role lookup and local media URLs; no database |
| `front-page.php` | Semantic hero and editorial chapter; retain managed discovery + live visit data/anchors |
| `header.php` | Match homepage-only preload to the selected Hero media; preserve global header |
| `assets/css/homepage.css` | All homepage layout, tokens, breakpoint and reduced-motion changes |
| `dev/tests/homepage-fixture.php` | Real-template CLI synthetic variants; load registry in isolation |
| `dev/tests/homepage.browser.py` | Photo-bearing/empty-data/mobile/keyboard/hours browser checks and screenshots |
| `dev/tests/public-design.browser.py` | Shared-header/booking, typography and CTA parity regression |
| `functions.php` / `style.css` | Require media helper and bump matched theme/asset version only after passing review |
| `docs/TESTING.md` | Commands, fixture limitations, screenshot/asset provenance and release gates |

Avoid editing `assets/css/styles.css` unless the scoped homepage sheet cannot safely resolve an inherited rule; any necessary shared-style change requires a dedicated non-home regression. Do not add `assets/js/homepage.js` without a separate demonstrated interaction.

---

### Task 1: Source-faithful V3 image pack and provenance gate

**Files:**
- Create: `dev/tests/homepage-media.test.mjs`
- Create: `assets/img/homepage/SOURCES.json`
- Create approved derivatives only: `assets/img/homepage/warm-hero-640.webp`, `warm-hero-960.webp`, `warm-hero-1440.webp`, `warm-hero.jpg`, `warm-sushi-768.webp`, `warm-sushi-1440.webp`, `warm-food-640.webp`, `warm-food-960.webp`

**Interfaces:**
- Consumes: immutable SA-0764 source and one sushi/one supporting candidate in the existing Steyr photo archive; private Notion review and an explicit brand/photographer usage-rights check.
- Produces: the eight named image paths and `SOURCES.json` with entries `role`, `source_id`, `source_sha256` (64 hex), `credit`, `editorial_only` (=true), `site_use_approved` (=true **only following evidence**), `variants` with `path`, `width`, `height`, `sha256`, `bytes`. The full rights proof and any identifying contact remain outside GitHub.

- [ ] **Step 1: Write failing tests** in `dev/tests/homepage-media.test.mjs` using `node:test` and `assert/strict`. Assert exactly three roles `hero` / `sushi` / `food`; only whitelisted source IDs; `editorial_only===true`; `site_use_approved===true`; SHA-256 of each named runtime file equals manifest; all paths remain below `assets/img/homepage/`; images are locally available WebP/JPEG, not remote/generated; dimensions and budget are met. Parse WebP RIFF VP8/VP8L/VP8X metadata without adding packages; use file header for JPEG fallback. Intended ratios: hero/food 4:5, sushi 16:9, with tolerances only for integer rounding. Limit highest-resolution WebP to 350 KB for Hero, 320 KB for Sushi and 260 KB for support unless the reviewer explicitly records a quality exception.
- [ ] **Step 2: Run** `node --test dev/tests/homepage-media.test.mjs`. **Expected:** FAIL because provenance manifest/derivatives do not yet exist; do not “fix” it with fictional approvals.
- [ ] **Step 3: Check the actual original and rights, then export** warm-bright V3 colour corrected, source-faithful images (no semantic inpainting or ingredient changes). Compare each against source at 1:1 detail plus crop at mobile/desktop. Preserve the source files privately with hashes. The named WebPs are 640×800, 960×1200, 1440×1800 (hero); 768×432 and 1440×810 (sushi); 640×800 and 960×1200 (support). `warm-hero.jpg` fallback is 1440×1800. Fill `SOURCES.json` only with verifiable facts. **STOP and request image/rights review** if any source cannot be cleared; do not publicly commit questionable images or mark them approved just to pass.
- [ ] **Step 4: Run** `node --test dev/tests/homepage-media.test.mjs`. **Expected:** all media assertions PASS, and a reviewer compares the contact sheet plus screenshot with the unchanged sources; otherwise STOP.
- [ ] **Step 5: Commit** the manifest, verifier and only the three approved derivative sets with message `feat(home): add verified warm-editorial homepage media`; archive untouched.

### Task 2: Single media registry and responsive food-first hero

**Files:**
- Create: `inc/homepage-media.php`
- Create: `dev/tests/homepage-media.test.php`
- Modify: `functions.php` (require registry)
- Modify: `dev/tests/homepage-fixture.php` (require registry before rendering)
- Modify: `front-page.php:15–42` (hero)
- Modify: `header.php:49–51` (homepage preload)
- Modify: `assets/css/homepage.css` (hero styles only in this task)
- Modify: `dev/tests/homepage.browser.py` (hero assertions)

**Interfaces:**
- Consumes: approved Task 1 file names/dimensions; current `get_template_directory_uri()`, existing `.hero-actions`/`.hero-scroll` and global header/cart.
- Produces: `wowthai_home_media(string $role): ?array` with whitelist roles `hero`/`sushi`/`food` and keys `src`, `srcset`, `sizes`, `width`, `height`, `alt`, `source_id`. Unknown role returns `null`; no DB or network read. Hero `img` and header preload use identical role configuration.

- [ ] **Step 1: Add failing PHP contract tests**: roles map to expected local URLs and source IDs, source-aware accurate alt, deterministic `srcset`/`sizes`, dimensions, unknown role `null` and no external media. Extend homepage Playwright to assert one eager, high-priority responsive hero and exactly one matching preorder preload, visible H1, original copy, two equal 48px actions and their original URLs. At 390×667 assert both actions are reachable in the first viewport; at 320×667 allow scroll but no clipping/overlap and no more than one viewport of initial delay. Confirm actual photo does not disappear when reduced-motion is enabled.
- [ ] **Step 2: Run** `php dev/tests/homepage-media.test.php` and `python dev/tests/homepage.browser.py`. **Expected:** FAIL on missing helper/updated Hero contract; record the failing assertions, not an unrelated dependency problem.
- [ ] **Step 3: Implement** the smallest helper and semantic hero markup; replace the old anonymous full-screen cover treatment with responsive **copy-left / image-right** media on desktop and a compact, intentional crop on mobile. Keep H1 and both actions inside `.hero-copy`; preserve `#home-title`, location and scroll anchor. Ensure the media photo has an accurate alt and is not hidden with `aria-hidden="true"` if it conveys content. Keep prior `assets/img/hero*` assets untouched for rollback.
- [ ] **Step 4: Run** `php dev/tests/homepage-media.test.php`, `python dev/tests/homepage.browser.py` and `python dev/tests/public-design.browser.py`. **Expected:** PASS including booking unchanged; visually inspect first fold at 320, 390, 768, 1024, 1440 and 1920 using the real derivative, not a generated image.
- [ ] **Step 5: Commit** helper/hero/preload/tests with `feat(home): compose accessible warm food-first hero`.

### Task 3: Editorial kitchen chapter without fabricated WooCommerce photography

**Files:**
- Modify: `front-page.php:43–77` (insert chapter immediately after Hero; leave existing discovery conditional)
- Modify: `assets/css/homepage.css` (editorial section, responsive photo framing)
- Modify: `dev/tests/homepage.browser.py`
- Modify: `dev/tests/homepage-fixture.php` only if an additional failure fixture is needed

**Interfaces:**
- Consumes: `wowthai_home_media('sushi')`, `wowthai_home_media('food')` (Task 2); managed `$wow_dishes` and its existing `#aus-unserer-kueche` section.
- Produces: a distinct static `<section id="wowthai-kueche" class="home-editorial">` with a wide focal image and one supporting portrait, informative honest alt text, concise German editorial copy and a quiet `/menu/` link. This section does not bind a price, product ID, ingredient claim or current special to these editorial photos.

- [ ] **Step 1: Extend failing PHP/Playwright checks**: `#wowthai-kueche` appears once before the `#aus-unserer-kueche` discovery when eligible media exists; role photos keep `source_id` attributes, dimensions and `loading="lazy"`; product links/prices stay precisely the synthetic fixture values (`/menu/#gericht-56`, etc.) and `.home-dish img` remains zero when products lack mapped photos. In `home-empty`, `#aus-unserer-kueche` remains absent and `.hero-scroll[href="#besuch"]` works; images remain editorial and cannot become `.home-dish img`. Intercept/abort an editorial image request and assert text/link/visit remain usable. Ensure the section is absent, not broken, if its approved photo registry is unavailable.
- [ ] **Step 2: Run** `python dev/tests/homepage.browser.py` and `php dev/tests/homepage.test.php`. **Expected:** FAIL the new editorial assertions while the existing managed-menu checks continue to pass.
- [ ] **Step 3: Insert** the two-photo `home-editorial` markup and style asymmetrical aspect ratios/spacing; keep anchors and the existing conditional catalog loop untouched. Use short non-product-specific German copy such as “Ein Vorgeschmack.” and “Thai-Küche, Wok und Sushi bei WoW Thai Steyr.” No carousel/video, cross-branch interior imagery or photo gallery wall.
- [ ] **Step 4: Run** `python dev/tests/homepage.browser.py` and `php dev/tests/homepage.test.php`. **Expected:** PASS for no-product/image-failure and preserved catalog semantics.
- [ ] **Step 5: Commit** editorial section and tests with `feat(home): add source-faithful kitchen editorial chapter`.

### Task 4: Unify homepage rhythm, managed discovery and Steyr visit states

**Files:**
- Modify: `assets/css/homepage.css` (scoped home tokens and coherent responsive treatment)
- Modify: `dev/tests/homepage.browser.py` (visual/interaction checks)
- Modify: `dev/tests/public-design.browser.py` (non-home styles and CTA parity)
- Modify: `front-page.php` only for neutral semantic presentation wrappers; never replace dynamic content or hooks

**Interfaces:**
- Consumes: actual `.home-dishes`, `.home-dish` data cards, `.home-visit`, `.home-weekly` and `data-wowthai-home-status` DOM.
- Produces: one homepage-scoped colour/type/spacing system, editorial hierarchy and refined cards/visit contrast, without changing helper outputs or global menu/checkout styling.

- [ ] **Step 1: Add failing regression checks**: desktop/mid/mobile editorial layouts and no horizontal overflow at 320/390/768/1024/1280/1440/1920; `#home-title` and two 48px equal-weight buttons; healthy `h1/h2` order and Playfair/DM Sans; readable content at 200% text zoom; useful image placement without a forced multi-screen portrait; keyboard focus visible on CTA/menu/visit/weekly details; no hidden critical content with JavaScript off. Existing fresh, closed, failed-refresh, timeout and midnight recovery states must still update visible `[data-wowthai-home-status]`/`[data-wowthai-today-hours]`, with weekly details in no-JS. Compare the `/booking` public-design fixture before and after so the homepage styles cannot alter reservation form priority.
- [ ] **Step 2: Run** `python dev/tests/homepage.browser.py` and `python dev/tests/public-design.browser.py`. **Expected:** new geometry/token assertions FAIL on old styles. Verify new assertions fail for the intended cause, not a network/dependency error.
- [ ] **Step 3: Rework only** `assets/css/homepage.css` into a coherent homepage layer; scope variables to `body.home` and descendants, do not append many contradictory `@media` patches to shared `styles.css`. Keep two equal glass/quiet Hero action treatments as already agreed, now with WCAG-compliant contrast on real backgrounds; tone down uniform bordered-card chrome and integrate one optional warm-cream breathing region without hiding visit details. Animation is optional and finite; reduced-motion removes it.
- [ ] **Step 4: Run** `python dev/tests/homepage.browser.py` and `python dev/tests/public-design.browser.py`. **Expected:** PASS including high zoom, source crop review at each width, touch targets ≥44px, first-fold CTA and reduced-motion. Check actual foreground/background ratios: text ≥4.5:1, large ≥3:1 in the observed image-backed positions; revise if they fail.
- [ ] **Step 5: Commit** homepage-only styles and test updates with `style(home): unify warm editorial responsive system`.

### Task 5: Whole-theme regression, previews and release-ready PR

**Files:**
- Modify: `docs/TESTING.md` (new media contract, images, viewports and evidence boundaries)
- Modify: `functions.php` / `style.css` only to align the final theme version/cache-busting if not already updated in Task 2
- Modify: existing test files only for legitimate discovered regressions, with failing-first reproduction
- Update: `COORDINATION.md` checkpoint only after confirming current branch ownership and repository-public disclosure limits; keep task-specific/private data in Notion

**Interfaces:**
- Consumes: Tasks 1–4, actual approved image variants, the existing fixtures, established GitHub CI and the original theme behaviour.
- Produces: documented screenshot/contact-sheet review at desktop/mobile, test evidence at an exact code HEAD, a single implementation PR **separate from PR #18** and a clear merge/release decision gate.

- [ ] **Step 1: Write any remaining failing tests** for discovered regressions: header/cart/mobile drawer keyboard focus and Escape; image failure; 200% zoom; a delayed 404 or no image; stale kitchen hours; missing font fallback; a menu-linked dish. Assert no external calls or POST/PUT when running the standalone fixtures. Reuse existing suites rather than creating a second test framework.
- [ ] **Step 2: Run** `node --test dev/tests/homepage-media.test.mjs`, `php dev/tests/homepage-media.test.php`, `php dev/tests/homepage.test.php`, `node dev/verify.mjs`, `python dev/tests/homepage.browser.py`, `python dev/tests/public-design.browser.py`, `python dev/tests/hours-public.browser.py` and `python dev/tests/portal.browser.py`. **Expected:** PASS on native Node/PHP/Playwright; report **NOT RUN** for any unavailable runtime, never substitute a prior session's result. Document limits: fixtures ≠ real order, booking, payment or production performance.
- [ ] **Step 3: Capture** first-fold and full-page screenshots at 320/390/768/1024/1280/1440/1920, the short 390×667 viewport, 200% zoom and no-JS; inspect actual media focus, legibility, Chrome console and no overflow. Produce human-review before/after against untouched `main` and source-photo contact sheet. Confirm front-page/preload image file hashes and no unapproved external media.
- [ ] **Step 4: Update** `docs/TESTING.md` and only then synchronise the `functions.php` and `style.css` version to the next revision **based on the rechecked main**, with a regression asserting enqueue version matches. Run `node dev/verify.mjs` and `git diff --check` once more. **Expected:** PASS; any missing rights or visual concerns block PR readiness.
- [ ] **Step 5: Commit** docs/final asset-version changes with `test(home): document visual QA and regression evidence`. Request an independent design/code review against the spec, review full diff, then open a dedicated implementation PR; wait for exact-head CI. Share PR screenshots with Minh and request explicit visual acceptance; **do not merge** as part of this step.

### Task 6: Separate release and GET-only runtime verification — only if authorised later

**Files:** No automatic code modifications; record release evidence in the existing GitHub PR/Notion task when separately authorised.

**Interfaces:**
- Consumes: implementation PR with approved licensed visuals, full CI and Minh's explicit merge/release decision.
- Produces: recorded release commit, observed WordPress.com runtime asset version, HTTP GET-only HTML/assets verification and a rollback reference.

- [ ] **Step 1: Before merge**, reread `AGENTS.md`, `COORDINATION.md`, current main/PR overlap and `.github/workflows/wpcom.yml`; identify which WordPress.com integration actually deploys source.
- [ ] **Step 2: Request explicit release approval** after visual review; never infer this from plan/design approval or CI.
- [ ] **Step 3: If approved**, merge through the normal guarded process and observe the real deployed HTML, homepage asset hashes/cache URLs, both CTA links, canonical opening hours and source-faithful photos using **GET only**, at phone and desktop sizes.
- [ ] **Step 4: Record** deployed or not-deployed outcome and errors. If rollback becomes necessary, revert theme code/assets only, not databases, and do not run synthetic live purchases/booking submissions.

## Plan Self-Review / Handoff Gate

- **Coverage:** Spec ``1–4` intent/tokens → Tasks 1, 2, 4; `5` hero/editorial/discovery/visit → Tasks 2–4; `6` provenance → Task 1; `7` a11y/responsive → Tasks 2–5; `8` source boundaries → File Map and Tasks 2–5; ``9–10` acceptance/exclusions → Tasks 5–6.
- **Interfaces:** `wowthai_home_media(string $role): ?array` defined in Task 2, consumed in Tasks 3–4; no additional JavaScript dependency; the manifest is QA metadata only and does not create a second runtime database.
- **Review Focus coverage:** asset integrity/rights (Task 1); short/mobile focal safety (Task 2); empty catalog and missing media (Task 3); stale hours and non-home CSS (Tasks 4–5). No known missing essential case.
- **Human gates:** First approve source fidelity/usage rights and this plan; then select execution method; review implementation screenshots and PR; merge/deploy only after another explicit decision.
- **Current status:** Documentation/plan only. No implementation tasks are checked off, no asset V3 is represented as published and no CI/test results are claimed for a code change.

**Execution choice after review:** Prefer **subagent-driven** for independent media/provenance review, then homepage integration, responsive QA and exact-head PR review. **Native** implementation in one environment with an independent final branch review is also acceptable if the user prefers speed. Either path must honour the same explicit gates and avoid production writes.
