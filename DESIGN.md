# WoW Thai Steyr — design direction

These rules guide the public restaurant experience. The dated [research record](docs/design-research-2026-10-21.md) explains the references and media decisions. Implementation and release evidence belong in [COORDINATION.md](COORDINATION.md).

## Experience

Make WoW Thai feel warm, alive, modern, and led by images. Show the pleasure of food and hospitality while helping visitors choose a dish, order, reserve, or plan a visit. Use short, natural German copy. Prefer a specific photograph and a useful sentence over repeated promotional claims.

The owner's primary references are Kei and NKORA: generous imagery, deliberate space, and a recognizable personality. Interpret these qualities through WoW Thai's own food and setting. Keep the familiar identity and practical restaurant functions.

## Typography and surfaces

Keep self-hosted **Playfair Display** for expressive headings and **DM Sans** for reading, prices, labels, and controls. Use scale and spacing to create hierarchy; a new reference or automated design suggestion does not justify another font replacement.

Retain the dark brand frame and restrained gold accents. A warm cream editorial section introduces contrast and breathing room after the Hero. Use dark text on cream and keep text over photographs legible across the entire crop. Avoid turning every section into a bordered, shadowed card.

## Composition

The current direction is Hero, one cream editorial section, the existing dark menu discovery section, then practical visit information. The editorial headline is **“Ein kleiner Vorgeschmack.”** Give the gallery enough space to carry the atmosphere, with brief supporting copy.

Keep ordering and reservation equally prominent in the Hero, with the existing **48px minimum-height actions**. Preserve the reservation page's established layout and source order. Navigation and essential information must remain usable before any image or animation finishes loading.

Use deliberate crops at each viewport. A portrait photograph should feel intentional on a phone; a small square preview should not be stretched into a full-screen desktop image. Let mobile sections stack naturally, with readable margins and no page-level horizontal overflow.

## Media and meaning

Use verified WoW Thai media with recorded source and attribution. The gallery presents general food imagery; do not attach a managed product name, price, ingredient claim, or availability unless the association is established. Keep catalog data in its canonical menu source.

Do not present historical interiors as the current City Point restaurant or transfer St. Valentin imagery to Steyr. References from other restaurants guide composition only; their assets are not project assets.

The first gallery slide uses the verified Steyr Facebook film locally, started by the visitor. Keep native controls, `playsinline`, `preload="none"`, its genuine poster, and an audio-free file. Pause playback when the slide leaves view or the page becomes hidden. Retain the original Facebook link. Keep the gallery manually controlled by swipe and labeled arrow buttons, without automatic advancement.

## Motion and access

Use finite, quiet motion: the current reveal treatment moves at most **12px over 350ms**, once per element. Keep content visible without JavaScript and remove nonessential movement for `prefers-reduced-motion`. Do not animate essential prices, hours, form fields, or primary action positions.

Keep visible keyboard focus, accessible control names, useful image alternatives, and touch-sized controls. Gallery movement must not trap vertical scrolling or keyboard focus. If a future film loops automatically, provide a persistent pause control and a static reduced-motion alternative.

## Functional boundaries

Preserve canonical opening hours, Monday sushi distinctions, managed menu data, reservation behavior, and operations. Presentation work does not change business data. Keep the existing WordPress/PHP theme architecture; no React migration or replacement ordering system is needed for this direction.
