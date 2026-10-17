# Agent Instructions — WOW Thai Steyr

## Before changing files
1. Read `COORDINATION.md` and these instructions first.
2. Inspect the current branch, working tree, changed files, and existing tests.
3. Keep changes scoped to the requested theme work and use a feature branch plus pull request.

## Safety and scope
- This public repository contains WordPress/WooCommerce theme source. Never add credentials, tokens, private customer/order information, database exports, or production configuration.
- Do not import, export, sync, or overwrite a database as part of theme work.
- Do not deploy, upload to WordPress Studio, or change the live site unless explicitly requested. A GitHub PR is code review only.
- Preserve the active coordination protocol. A CI run or local test does not grant permission to write to a shared site.
- Do not change production payment, email, or order behavior without task-specific verification and review.

## Verification
- Run `node dev/verify.mjs` before proposing code changes. This is the repository's automated G1–G10 gate and PHP/hour suite when PHP is installed.
- CI installs Node.js and PHP so syntax and unit checks run on pull requests.
- `node dev/build.mjs` creates a local ZIP only after verification; it does not deploy. Run only when packaging is needed.
- For checkout changes, follow [docs/woocommerce-qa.md](docs/woocommerce-qa.md). State which checks are static, unit, or browser-level; do not claim end-to-end coverage from the current hours suite.

## Available repository skills
- Read [the skill inventory](docs/INSTALLED-AGENT-SKILLS.md) before installing or using third-party skills.
- All project skills live in `.agents/skills/`; current Codex and Antigravity workspace versions can discover this layout. Confirm actual discovery on each local/cloud agent.
- Use `wordpress-theme` and `woocommerce-checkout-qa` for task-specific behavior. `wp-project-triage`, `wp-performance` and `web-design-guidelines` are curated third-party skills with pinned provenance and references.
- WordPress upstream skills were authored for WordPress **7.0+**. The live core/WooCommerce versions are **unverified**: never apply core-7-only instructions without first confirming compatibility; prefer read-only analysis.
- Web design checks use a checked-in, versioned guideline snapshot, not live remote instructions.

## Optional installed design and engineering workflow skills (project-scoped)
- `ui-ux-pro-max`: use for UI design systems, page layout and visual decisions. It includes a local Python search and data catalogs; run only on local development or approved staging. WordPress PHP/WooCommerce business rules always take precedence.
- **Superpowers core (curated)**: `writing-plans`, `executing-plans`, `test-driven-development`, `systematic-debugging`, `verification-before-completion`, `using-git-worktrees`, `requesting-code-review`, `receiving-code-review`, `finishing-a-development-branch`. Activate only the relevant workflow for the task. This project install deliberately **does not** include Superpowers' auto-enforcing `using-superpowers` bootstrap.
- Never let brainstorming/design rules justify changing production menus, payments, tax rates, database or order logic without verification.
- Read [docs/INSTALLED-AGENT-SKILLS.md](docs/INSTALLED-AGENT-SKILLS.md) for pinned upstream commits, prerequisites and smoke tests. This branch installation does not activate global Codex plugins or Antigravity plugins outside the project.
