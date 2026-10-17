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
