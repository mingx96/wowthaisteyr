# Agent Instructions — WOW Thai Steyr

## Task identity, start and checkpoint
- GitHub is authoritative for source, refs, reviews and test evidence. Notion owns task priority, human decisions, acceptance and lifecycle. WordPress/runtime state is separate. Never infer live deployment from a merged PR, version string, ZIP or green CI.
- Before editing, read this file and `COORDINATION.md`, inspect actual Git root/remote, branch/HEAD and dirty/untracked files, fetch when available, then compare current main **and** the active branch/PR plus overlapping open PRs. Keep an explicitly assigned non-main base. Do not switch/pull over someone else's WIP.
- Find the existing Notion task rather than creating a duplicate. Keep the same non-sensitive Task ID in the PR/checkpoint; keep private task URLs, internal session links and business details out of this public repo.
- Confirm execution owner and file scope in the task before writing. Recheck for competing owners; an old timestamp is not permission to take over. An isolated code branch is not a shared-site write claim. If owner or permissions are unresolved, continue only authorized read-only/non-overlapping work.
- Checkpoint after meaningful changes and before pausing, long waits or handoff: timestamp in UTC, owner/scope, Task ID, base/branch/HEAD/PR, dirty/untracked WIP, changes, evidence with environment/SHA/result, unknowns, and one concrete next action. Use `COORDINATION.md`; do not add a parallel HANDOFF/STATUS registry.
- Keep historical entries, but label them historical. Preserve unfinished work and shared-site coordination; do not silently release a prior claim. If a local checkout or Notion cannot be inspected, record `unknown` / `sync pending`, not a guessed state.

## Before reporting completion
- Review the scoped diff and run the checks below. Report PASS, FAIL or NOT RUN accurately with command/job, environment, commit and link. A skill metadata check is not theme verification; unit tests are not live checkout evidence.
- Update the existing technical checkpoint and PR, then link the evidence in the existing Notion task when authorized. Notion remains the only task lifecycle authority.
- Reuse existing Notion statuses: `Chưa bắt đầu`, `Đang thực hiện`, `Cần xem xét`, `Tạm dừng`, `Đã hoàn thành`, `Đã hủy`. Under `Tạm dừng`, distinguish blocked dependency from deliberate pause and name the blocker/resume condition. Awaiting review/approval is `Cần xem xét`, not completed.
- Mark completed only against the agreed acceptance scope. A PR-only task can finish with release explicitly out of scope; a live-fix task cannot finish without separate live evidence. Say what changed, what was tested, what remains, where the work lives, and who takes the next step.
- Read the current deployment trigger before requesting a merge. Historical hub notes describe main auto-deploy; a docs-only merge may still trigger the service. No merge, deployment or database action is authorized merely by this protocol.

## Safety and scope
- This public repository contains WordPress/WooCommerce theme source. Never add credentials, tokens, private customer/order information, database exports, or production configuration.
- Do not import, export, sync, or overwrite a database as part of theme work.
- Do not deploy, upload to WordPress Studio, or change the live site unless explicitly requested. A GitHub PR is code review only.
- Preserve the active coordination protocol. A CI run or local test does not grant permission to write to a shared site.
- Do not change production payment, email, or order behavior without task-specific verification and review.

## Verification
- Run `node dev/verify.mjs` before proposing code changes. See `docs/TESTING.md` for G1–G11, PHP lint, hours and menu-description suites. If PHP is unavailable, report the skipped checks as NOT RUN.
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
