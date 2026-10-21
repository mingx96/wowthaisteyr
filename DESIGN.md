# WoW Thai Steyr — design direction

Current decision (21 October 2026): the owner withdrew the proposed raw-photo/video gallery and requested one AI-edited image preview. That preview is not integrated into the website. These rules guide future public restaurant work. The dated [research record](docs/design-research-2026-10-21.md) explains the references and media decisions. Implementation and release evidence belong in [COORDINATION.md](COORDINATION.md).

## Experience

Make WoW Thai feel warm, alive, modern, and led by images. Show the pleasure of food and hospitality while helping visitors choose a dish, order, reserve, or plan a visit. Use short, natural German copy. Prefer a specific photograph and a useful sentence over repeated promotional claims.

The owner's primary references are Kei and NKORA: generous imagery, deliberate space, and a recognizable personality. Interpret these qualities through WoW Thai's own food and setting. Keep the familiar identity and practical restaurant functions.

## Typography and surfaces

Keep self-hosted **Playfair Display** for expressive headings and **DM Sans** for reading, prices, labels, and controls. Use scale and spacing to create hierarchy; a new reference or automated design suggestion does not justify another font replacement.

Retain the dark brand frame and restrained gold accents. A warm cream surface can introduce contrast and breathing room in a future approved composition. Use dark text on cream and keep text over photographs legible across the entire crop. Avoid turning every section into a bordered, shadowed card.

## Composition

The accepted homepage remains Hero, dark menu discovery, then practical visit information. The proposed cream gallery headed **“Ein kleiner Vorgeschmack.”** was withdrawn with its raw photo/video assets. Any future image section should first follow the owner's feedback on the single AI-edited preview.

Keep ordering and reservation equally prominent in the Hero, with the existing **48px minimum-height actions**. Preserve the reservation page's established layout and source order. Navigation and essential information must remain usable before any image or animation finishes loading.

Use deliberate crops at each viewport. A portrait photograph should feel intentional on a phone; a small square preview should not be stretched into a full-screen desktop image. Let mobile sections stack naturally, with readable margins and no page-level horizontal overflow.

## Media and meaning

Use verified WoW Thai media with recorded source and attribution. The gallery presents general food imagery; do not attach a managed product name, price, ingredient claim, or availability unless the association is established. Keep catalog data in its canonical menu source.

Do not present historical interiors as the current City Point restaurant or transfer St. Valentin imagery to Steyr. References from other restaurants guide composition only; their assets are not project assets.

Do not reinstate the withdrawn Facebook film or raw-photo gallery. For the current AI trial, use a real restaurant photograph as input and align background, lighting and color with the theme. Preserve the dish identity and check generative changes before considering any image for integration; the trial itself is a visual preview, not a published menu photograph.

## Motion and access

For future motion, prefer finite, quiet treatments. The withdrawn prototype used at most **12px over 350ms**, once per element; it is not part of the current accepted homepage. Keep content visible without JavaScript and remove nonessential movement for `prefers-reduced-motion`. Do not animate essential prices, hours, form fields, or primary action positions.

Keep visible keyboard focus, accessible control names, useful image alternatives, and touch-sized controls. Gallery movement must not trap vertical scrolling or keyboard focus. If a future film loops automatically, provide a persistent pause control and a static reduced-motion alternative.

## Functional boundaries

Preserve canonical opening hours, Monday sushi distinctions, managed menu data, reservation behavior, and operations. Presentation work does not change business data. Keep the existing WordPress/PHP theme architecture; no React migration or replacement ordering system is needed for this direction.
