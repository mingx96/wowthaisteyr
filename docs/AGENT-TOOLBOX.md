# AI agent toolbox — WOW Thai Steyr (curated)

This document describes **selection and installation**, not a claim that any upstream package is installed. Installation guide: [INSTALL-AGENT-SKILLS.md](INSTALL-AGENT-SKILLS.md).

## Tool selection (minimum viable stack)

| Task | Tool / skill | What is covered |
|---|---|---|
| Understand PHP theme before edits | Local `AGENTS.md` after PR #1 + `WordPress/agent-skills` `wp-project-triage` | Identify actual WP/Woo versions, PHP code and repo verification. Upstream WordPress skills target 7.0+; do not assume site compatibility. |
| Build UI direction | UI UX Pro Max **optional** | Layout, responsive typography and visual design; verify changes do not break checkout. |
| Audit UI/a11y | Vercel `web-design-guidelines` **optional** | Forms, focus, keyboard, mobile and errors; not React-specific dependencies. |
| Plan complex changes | Superpowers **optional** | Specification, plan, TDD and review; pilot in one feature branch instead of adding several overlapping workflow frameworks. |
| Catch WordPress style issues | WordPress Coding Standards | Separate Composer/PHPCS CI integration, **not yet installed**. |
| Test WooCommerce order flow | Playwright + staging | Separate browser E2E harness, **not yet configured**; use synthetic products and non-live payments. |

## Existing project verification

- `dev/verify.mjs` has G1–G10, JavaScript and conditional PHP checks. It does **not** prove purchase flow behavior.
- PR [#1](https://github.com/mingx96/wowthaisteyr/pull/1) proposes `AGENTS.md`, two internal skills and CI, with success reported on its PR run. It has **not** been merged into `main` at this revision.
- PR [#2](https://github.com/mingx96/wowthaisteyr/pull/2) is documentation-only: it does not deploy code, run installers on your PC or connect WordPress to AI.
- `COORDINATION.md` records handover only; it is not a technical write lock for shared environments.

## What to check before completion

1. Verify WP core / WooCommerce / PHP compatibility, not just theme header version.
2. Verify each installed skill directory appears in the **actual** agent environment (Codex desktop, Antigravity desktop, or Codex Cloud).
3. Record the commands **actually executed** and results; distinguish static/unit/CI from browser checkout tests.
4. Keep projects and branch histories separate. Never copy live customer or order data into a test fixture.
5. PR review does not grant authority to change production WordPress DB, configuration, email, payment processing or live site.
