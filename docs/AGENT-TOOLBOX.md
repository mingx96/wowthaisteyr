# Agent toolbox selection for WOW Thai

## For WordPress PHP changes
Prefer `WordPress/agent-skills` project triage and the repository's existing `dev/verify.mjs`. Verify WP core/PHP/WooCommerce versions before applying code patterns. Avoid applying React/Next.js instructions to this server-rendered theme.

## For web design
Start from customer flow and business rules. Use `UI UX Pro Max` for consistent visual direction, or `vercel-labs/agent-skills` `web-design-guidelines` for UX/a11y audit. They are **not installed** merely because mentioned in a documentation file. Review responsive cart, selected modifiers, allergen information, German copy, checkout and staff portal.

## For workflow
Use `AGENTS.md` from the existing workspace PR when approved and follow the existing `COORDINATION.md`. Optionally pilot Superpowers for planning/TDD in a development branch. Never let two agents write concurrently to a shared staging/live instance.

## For QA
- Existing `dev/verify.mjs`: static checks, PHP syntax and opening-hour unit assertions when PHP is installed.
- WP Coding Standards: future Composer/PHPCS integration, **not yet configured**.
- Playwright: future E2E harness for checkout and pickup, **not yet configured**.
- Deployment, order email and database migrations require a distinct reviewed plan.

Install instructions: [INSTALL-AGENT-SKILLS.md](INSTALL-AGENT-SKILLS.md).
