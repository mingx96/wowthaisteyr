# WOW Thai Steyr — Warm Cinematic Editorial Homepage

**Design specification — review candidate, 22 October 2026**  
**Owner/scope:** ChatGPT, design documentation and review only.  
**Task:** notion:3fe9ccaec10f81f4b321fb6e7839d67d (existing WoW Thai Steyr website roadmap).  
**Repository:** mingx96/wowthaisteyr; **baseline:** main at 6b76152fa083873c2f4ebb354ef8f97f86f02b3a, theme v1.5.4.  
**Reference:** [Valentin Cinematic Editorial design specification](https://github.com/mingx96/wowthaistvalentin/blob/design/valentin-cinematic-editorial-homepage-20261022/docs/superpowers/specs/2026-10-22-valentin-cinematic-editorial-homepage-design.md), reviewed at blob 258f9a1a0f944de9466b05f9cd9d0a32b7d1117c.  
**Status:** The Espresso & Gold visual direction was approved in conversation. **This Steyr specification awaits Minh's review**; approval of the visual direction does not authorize a theme change, merge, deployment or production WordPress write.

## 1. Outcome and intent

The Steyr homepage should feel like entering an inviting, contemporary Thai restaurant: **rich food photography, genuine warmth, graceful editorial rhythm, and effortless access to ordering and table reservations**. The current page already solves important operational problems. This design project upgrades the photographic and typographic experience without rebuilding those systems.

The visual brief is **Food-first Cinematic Editorial / Espresso & Gold — Warm Editorial**. Compared with the Valentin reference, Steyr's first visual is a beautifully lit **real dish**, not a facade: reliable current Steyr entrance and dining-room photography has not yet been approved. Bright amber highlights and readable food detail replace the older, overly dark colour grade. A dark backdrop supplies depth, not gloom.

Success means:
- One coherent story from homepage header through welcome/hero, food editorial, live menu discovery, visit planning and footer.
- A distinctive desktop first fold and an intentionally composed mobile first fold, not a miniature desktop layout.
- Warm, appetising real food imagery; the dish is discernible at 320px and does not become an invented product.
- Both primary commercial paths are unmistakable: **Speisekarte & bestellen** and **Tisch reservieren**, equal-weight 48px hero actions.
- Current WooCommerce, reservations, staff portal, opening-hours and location data behave exactly as before.

This is not a proposal to duplicate Valentin's theme, Gutenberg architecture, Cormorant Garamond, facade-first hero, service readiness gates or its WordPress staging setup.

## 2. Verified source baseline and immutable contracts

The Steyr theme currently uses classic PHP templates, not Valentin's 40 Gutenberg blocks:
- `front-page.php`: hero; optional WooCommerce-managed discovery from `wowthai_get_homepage_dishes()`; visit/hours; compact footer.
- `header.php`: global header, current closure banner, cart trigger, mobile navigation, self-hosted assets, SEO/OG metadata and priority hero image.
- `footer.php`: homepage footer plus legal links; cart and modal roots.
- `assets/css/styles.css`: global theme foundation; `assets/css/homepage.css`: current homepage-only discovery and visit rules.
- `functions.php`: homepage stylesheet conditional enqueue, public hours script, WooCommerce/cart integration. Main does **not** have `assets/js/homepage.js`.
- `docs/TESTING.md`: verification and browser fixture contracts.
- `AGENTS.md` and `COORDINATION.md`: ownership, checks, PR and deployment safety requirements.

**Must preserve:**
1. Self-hosted **Playfair Display** headings and **DM Sans** body/actions. Do not reintroduce Chonburi, remote fonts or Valentin's Cormorant.
2. Existing German hero message (including `Schön, dass Sie da sind.`), recognisable WoW/Steyr branding, accurate City Point / first-floor location.
3. Hero links `/menu/` and `/reservations/`, both 48px high, equal visual priority, mobile stacking without downgrade. Keep the ordering cart/navigation and mobile drawer operational.
4. Managed title, description, categories and prices for discovery cards from WooCommerce. A generic editorial picture **must not be presented as that product's photograph** unless identity and portion have been checked. If the data source supplies no product photo, retain a robust image-free card.
5. `wowthai_hours_display()`, `wowthai_get_restaurant_data()`, `wowthai_get_homepage_dishes()`, current-day availability and special-closure presentation, Sushi Monday caveat and read-only `/menu/#gericht-ID` links.
6. Existing visit details (City Point, 1. OG, parking/lift, routes, phone and map). A Saalbach/Valentin interior or facade must never imply the current Steyr location.
7. Conditional empty-discovery fallback: scrolling from Hero still leads to `#besuch` when no eligible menu products exist.
8. Checkout, reservations, payment, CRM, operations/staff portal, opening-hours editor, WooCommerce product data, site domains, legal text and production database stay outside scope.

A code merge is not evidence of a live WordPress.com release. The existing main workflow called **Publish Website** uploads a GitHub artifact; runtime delivery remains a separate observation.

## 3. Design alternatives and decision rationale

| Route | Experience | Advantages | Risks | Decision |
| --- | --- | --- | --- | --- |
| **A — Food-first cinematic editorial** | Wide, asymmetrical image/text compositions; food-led hero; controlled cream/espresso chapters | Works with the portrait SA-0764 source; retains commercial CTAs and layout safety; closest to approved image tone | Requires disciplined cropping and section rhythm | **Recommended specification** |
| B — Full-bleed photo with overlaid headline | Single edge-to-edge dish image, type over photograph | Dramatic first impression | Portrait source may crop the dish severely; mobile copy/CTA contrast conflicts | Not default; reconsider only with a separately approved wide original |
| C — Cream-first editorial | Light canvas, dark food imagery as framed features | Readable, airy | Moves away from approved Espresso & Gold and risks inconsistent dark menu/navigation | Alternative reference only |

A borrows **principles** from the Valentin spec—one visual system, restrained emphasis, real image crop rules, responsive composition and explicit acceptance gates—not its facade/copy/layout or data structures.

## 4. Visual foundation — design targets, not deployed tokens

These values describe the desired **homepage-scoped** identity; they are not measured production values or permission to change global commerce styles.

| Use | Proposed token / rule |
| --- | --- |
| Base espresso | `#171412` (compatible with current global dark theme) |
| Deeper photographic surround | `#211813` / `#281E19` for subtle tonal layering |
| Warm ivory text | `#F6EBDD`; secondary copy `#D0C1AD` subject to measured contrast |
| Champagne gold | `#D9B875` for quiet headings, labels, highlights, not an everywhere-metallic gradient |
| Warm cream breathing chapter | `#F1E7D9` with dark text `#2A211C`; used sparingly |
| Fine rule | `#5A4837` at low visual prominence; avoid boxed layouts |
| H1/H2 | Self-hosted Playfair Display, H1 ~58–84px desktop / ~38–50px mobile; H2 ~40–60px desktop / ~30–42px mobile |
| Body/actions | Self-hosted DM Sans; reading copy 16–18px, accessible line-height; avoid decorative small-body type |
| Horizontal grid | Editorial maximum ~1280–1360px when media needs it; fluid gutters roughly 20–80px |
| Vertical rhythm | Spacious 80–128px desktop, 48–80px mobile, adapted section by section rather than uniform gaps |
| Controls | Two equal 48px Hero actions; all interactive targets at least 44px; visible focus and hover |
| Image grade | Soft amber directional light; bright edible midtones, natural shrimp/salmon/vegetable colours, detailed shadows; avoid black crush or excessive orange wash |

Maintain the existing global variables where required by Menu/Reservierung. Introduce scoped `home-*` design tokens only during the later implementation phase, and check actual WCAG contrast for every foreground/background combination.

The texture should come from **genuine image detail and quiet editorial asymmetry**, not gradients on every container, glass-card overlays, or decorative sliders. Motion may use optional small opacity/translate changes; primary content must never depend on animation to appear. `prefers-reduced-motion` disables it.

## 5. Homepage composition and copy hierarchy

### 5.1 Header + food-first hero

The existing global header remains intact functionally: logo, Speisekarte, Tisch reservieren, phone, cart count, mobile drawer and any closure banner. The homepage may visually share a contiguous espresso surface with the hero (rather than a disconnected strip), but no global header rewrite or opaque overlay over the cart is justified solely by appearance. Non-home pages must retain current header behaviour.

**Desktop:** one seamless editorial composition with copy to the left and a substantial, softly feathered portrait food image on the right. **SA-0764** is the leading real-photo candidate. Do not place the text across shrimp or crop the food to an unrecognisable fragment. Crop and focal point must be checked against the approved real-source version at each target width. A darker local area under the copy is preferred to a solid glass box over the dish.

Preserve the current German welcome headline, one honest sentence about Thai/Wok/Sushi at City Point and two equal actions. Location/1. OG remains discoverable without competing with the headline. No aggressive animated zoom, carousel or autoplay video.

**Mobile:** make the title and both actions promptly accessible, with an intentionally positioned portrait crop so the food still feels like the hero. Avoid a multi-screen portrait image before the visitor reaches a meaningful action. The 390×667 first fold is a separate design acceptance case. DOM and keyboard order stay logical even when image/copy placements change visually.

### 5.2 Küche — a chapter, not a generic photo gallery

An editorial food chapter follows the Hero, using **one dominant** wide real-photo candidate (`SA-0735` or `SA-0729`) and **one supporting** portrait (`VA-0383` or `SA-0727`). Small German eyebrow, one serif heading, concise copy and a quiet link into the menu. Each image has a deliberate size/ratio and natural transition; do not implement a swipe carousel, autoplay or a tiled social feed.

These are **editorial food photographs**, not WooCommerce product claims. Do not add unverified ingredients, current specials, dish names, prices or allergen claims as image captions.

### 5.3 Managed menu discovery — clearly distinct from the editorial images

Preserve the existing `#aus-unserer-kueche` semantics and managed three-product selection; dynamic product availability, prices, prices with variants, labels and `/menu/#gericht-ID` navigation remain unchanged. Restyle the current cards to look calmer and more typographic, with less repeated card chrome. Only show a product photograph when its product mapping is verified.

The block may be absent if no eligible products are returned; spacing, anchors and screen-reader navigation must still work. If the same `#aus-unserer-kueche` anchor must lead to the menu discovery rather than the photo chapter, keep that compatibility explicitly; do not silently move the anchor to an unrelated section.

### 5.4 One subtle human/drink moment — optional, source-bound

A compact image/text pause may feature `SA-0761` (drink) or another verified food moment to keep the story human and warm. A photograph of staff from Saalbach (`SA-0778`) or a group at St. Valentin (`VA-0333`) may appear only with a **truthful branch-specific context**; it is not Steyr's current team or venue. If there is no compelling Steyr-specific story, omit this chapter rather than manufacture one.

The design may use one restrained cream transition to vary the visual cadence, never alternate dark/light colour blocks mechanically.

### 5.5 Besuch + kitchen hours + footer

Preserve the current `#besuch` section as the practical conversion destination: verified Steyr location, floor, parking, directions, phone, email, opening and kitchen hours, live today state and weekly details. This is information architecture, not a decorative photo block. Do not use cross-branch interior photos as a Steyr arrival/booking visual.

The Homepage footer remains compact and keeps the genuine legal links and contact affordances. Do not reintroduce previously removed repetitive global footer content.

## 6. Asset eligibility and image provenance

The established private album contains **173 indexed original images** from Steyr, Saalbach and St. Valentin; a first batch of **10 candidates** has v1/v2 working versions, catalogued separately from originals. Photo processing and site release are not the same approval.

**Priority candidates for actual site design:**

| Role | Candidate | Rule |
| --- | --- | --- |
| Hero | SA-0764 | Source-faithful high-quality portrait crop; compare warm v3 concept against unchanged original |
| Sushi editorial | SA-0735 or SA-0729 | One wide focal image, not two competing product shots |
| Supporting food | VA-0383, SA-0727 or VA-0385 | Choose by composition; confirm no claim to a specific Steyr menu item |
| Beverage accent | SA-0761 | Optional; only if story genuinely benefits |
| People/location | SA-0778, VA-0333 | Always label original branch; do not represent Steyr |
| Steyr visit | New confirmed current City Point Steyr photos | Required before showing a current Steyr facade/interior |

**Approval rules:**
1. Keep the downloaded original byte-identical and traceable by source/hash; each edit gets its own version, crop, approval state and export dimensions.
2. A warm cinematic generative image may inspire the colour grade but can **invent ingredients, quantities, objects, signage or faces**. Such generated images are moodboard candidates only unless positively checked against the real scene/product; do not substitute them silently.
3. For true editorial photography, prioritise controlled colour correction, selective illumination and crop; reject unnatural food colours, crushed shadows and strong brown/yellow casts. Use the brighter approved-direction reference, not the darker v2 grades, as the target.
4. Confirm rights/credit to republish cross-branch photography, including professional photographer rights, before shipping images to this public repository and website.
5. Store masters, exported WebP, QA contacts and manifests in the existing managed album/Library and Drive; check the actual files on readback. The public Git repository should contain only the small **approved** runtime derivatives, not private archives or 100MB originals.
6. Record genuine photo credit and truthful alt/caption. The photographer/location source is not automatically the photographed restaurant's current menu.

Only a useful subset (initially about 4–6 images) belongs on the Homepage; the other candidates remain in the library for later pages/social media.

## 7. Responsive system and accessible degradation

Review true image/copy combinations at **320, 390, 768, 1024, 1280, 1440 and 1920px**, plus **390×667**, text zoom 200%, no-JS, reduced-motion and absent/delayed images.

- **>=1280:** generous asymmetrical media, H1/CTA within first fold, food focal point fully visible.
- **768–1279:** retune image/text shares and copy line lengths; no abrupt hero crop or nav collision near 960–1280px.
- **<=767:** one-column narrative but not a single endless portrait; two 48px CTA targets remain reachable and equal; menu drawer and cart retain focus/close behaviour.
- Product data absent, kitchen hours stale/failure, special closures, long German copy, browser font fallback or broken images: no blank critical content, clipped actions or overlap.
- WCAG AA contrast targets: ordinary text at least 4.5:1 and large text at least 3:1 against the **actual** photographic or surface background. Measure, not guess from the palette.
- Visible focus, keyboard navigation, semantic sections/heading order, accessible image alt, skip link and readable weekly-hour fallback even without JavaScript.

## 8. Technical approach after design approval

Use the **existing classic theme**. No Gutenberg migration, page builder, plugin, custom post type, database schema, client animation library or external media embed.

Prefer:
- `assets/css/homepage.css`: consolidate new homepage-specific tokens/typography/media rules in a coherent layer; remove outdated homepage rules only after regressions prove no behaviour loss. Avoid piling a cascade of late override patches into `assets/css/styles.css`.
- `front-page.php`: add only semantic editorial wrappers/sections required by the approved design; preserve PHP helpers, link destinations, canonical anchors, exact menu semantics and hours DOM hooks.
- `header.php`/`footer.php`: presentation-only targeted changes if required. Keep the site-wide navigation, cart, live state, closure and legal semantics.
- `functions.php`/`style.css`: change asset/theme version only in a later approved implementation PR, not in this design PR.
- `assets/img/`: include only approved, optimised, source-faithful derivatives plus manifest/credit; photo files are not added in the design-spec PR.
- Reuse `assets/js/hours-public.js` and the app JS. Do not invent a homepage.js dependency unless a reviewed interaction demonstrably needs it.

Protect the existing on-page hours hooks (including `data-wowthai-home-status`, `data-wowthai-hours-rows` and Monday Sushi note), menu / ordering distinctions, special-day notices, responsive mobile nav and existing staff portal. The PHP theme has no Valentin-style site readiness gate: do not copy that logic.

## 9. Acceptance, evidence and release boundaries

**Gate A — spec review:** Minh reviews and signs off this written design including the photographic hierarchy, desktop/mobile hero and source-fidelity/rights rules. Any desired Figma/mockup is a subsequent visual design deliverable, not evidence that this PR is implemented.

**Gate B — implementation plan:** Write a separate Superpowers plan under `docs/superpowers/plans/` with exact file scope, failing-first tests and tasks. Minh reviews it and selects an execution method. This spec alone authorises no code.

**Gate C — isolated implementation and QA:** Execute on a new branch from rechecked current main, with separate PR, **not** by silently changing this design PR. Baseline read-only screenshots of the actual PHP theme and real approved photo exports; render first fold and full page at target viewports; test title/CTA placement, visible food focal point, horizontal overflow, 200% text, no-JS, absent catalog/photo, reduced motion and keyboard. Run `node dev/verify.mjs` (G1–G11), PHP homepage suite, `python dev/tests/homepage.browser.py`, public-design, hours-public and portal browser suites with their fixtures. Record command, environment, SHA and pass/fail, not inferred coverage.

**Gate D — visual review + release decision:** Minh reviews the screenshots and real site-bound images. Only approved PRs may be merged. Because main may trigger GitHub/WordPress.com delivery, read the trigger and require an explicit release decision; no implicit publish from successful CI.

**Gate E — public runtime check:** Observe WordPress.com/site HTML and served assets GET-only with actual deployed commit/version evidence. Check navigation, today's kitchen hours, menu discovery, reservations link and live fallback on phone/desktop without synthetic checkout, reservation, order, payment or database writes. Do not claim deployment from merged PR or local screenshots.

Rollback, if later required, is **code/asset revert only**, never a WordPress DB restore/synchronisation. Keep Notion task and `COORDINATION.md` checkpoints separated into design, implementation, review and release states.

## 10. Explicit exclusions and review checklist

Out of scope: autoplay video, animated carousel, fake real-location media, synthetic menu product photos, product/allergen/pricing edits, booking/ordering logic, production WP options/plugins, Operations/billing/CRM, legal copy, multilingual overhaul, remote-font/CDN additions, Core Web Vitals certification and release without approval.

Review checklist for Minh:
- [ ] Food-first hero looks **warm, premium, recognisable and bright enough**, not dark-brown/muddy.
- [ ] Desktop has true editorial asymmetry; mobile shows image, welcome and actions without excessive scrolling.
- [ ] Hero's two **48px** paths to ordering and reservation remain equally important.
- [ ] Authentic/verified editorial images are not confused with exact WooCommerce products or current Steyr interiors.
- [ ] Menu, opening hours, City Point directions, dynamic states and staff functionality remain intact.
- [ ] The page reads as one coherent restaurant story rather than many uniform bordered cards.
- [ ] The spec can be approved as the input for an **independent** implementation plan, not a mandate to merge or deploy.

**Decision record:** Espresso & Gold / warmer photo direction was agreed during the photo-album work; this document adapts Valentin's **design workflow**, not its implementation or venue identity. Its detailed structure remains a **review candidate**, not yet accepted.
