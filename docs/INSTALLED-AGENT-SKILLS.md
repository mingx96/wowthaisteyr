# Installed AI Agent Skills — repository scope

**Status:** This inventory describes files committed to the feature branch of `mingx96/wowthaisteyr`, *not* skills installed on a user's Windows PC, global Codex config, or Antigravity runtime. Files become available to new checkouts after merging the stacked PRs.

## Project-local skills

| Name | Scope | Provenance / content | Usage |
| --- | --- | --- | --- |
| `wordpress-theme` | Internal (PR #1) | Custom theme guardrails | Work on PHP theme with minimal changes |
| `woocommerce-checkout-qa` | Internal (PR #1) | Non-production checkout validation | Menu/cart/checkout/order tests |
| `wp-project-triage` | Vendored | WordPress/agent-skills at [55cd0f78](https://github.com/WordPress/agent-skills/tree/55cd0f78b996f453c194a1a6ecc5c9a2e0f6cf15/skills/wp-project-triage) (GPL-2.0-or-later), path adapted | Read-only repository classification |
| `wp-performance` | Vendored | WordPress/agent-skills at [55cd0f78](https://github.com/WordPress/agent-skills/tree/55cd0f78b996f453c194a1a6ecc5c9a2e0f6cf15/skills/wp-performance) (GPL-2.0-or-later), path adapted | Optional diagnosis; read-only local/staging only |
| `web-design-guidelines` | Vendored/adapted | [vercel-labs/agent-skills](https://github.com/vercel-labs/agent-skills/tree/063bee94c3f4df8453406c830b0a7df0f2860278/skills/web-design-guidelines), guidelines snapshot from [web-interface-guidelines command.md](https://github.com/vercel-labs/web-interface-guidelines/blob/main/command.md) (MIT) | Offline review of UI/a11y, German copy and forms |

WordPress source `SKILL.md` paths were adapted to this repository's `.agents/skills/` layout. The full upstream support references/scripts and license notice are retained. A copy of the GPL text is at [.agents/licenses/gpl-v2.txt](../.agents/licenses/gpl-v2.txt). The Vercel skill no longer fetches unreviewed remote guidelines every time it runs; it uses a local snapshot.

## Compatibility and security

1. The WordPress upstream skills target **WordPress 7.0+**. Current `style.css` says `Tested up to: 6.7`, which does *not* confirm the deployed WordPress version. Verify core, WooCommerce and PHP before using version-specific recommendations.
2. `wp-performance` includes WP-CLI diagnostics. **Do not run on production** without explicit permission; even diagnostic commands may disclose environment data or affect server load.
3. This repo is **public**. Never add API keys, customer records, employee information, real orders, DB dumps or credentials.
4. `web-design-guidelines` provides code review, **not a browser E2E test**. Playwright + an isolated WooCommerce site are a separate future installation.
5. UI UX Pro Max and nine Superpowers core workflows are included in the **new stacked feature branch** (see below); they are *not* installed globally as official plugins. Avoid stacking duplicate workflows.
6. No WordPress plugins, WP-CLI packages, PHPCS, Playwright dependencies or external server integrations have been installed.

## Verify after the PR is merged / branch is checked out

```powershell
git status --short
node dev/check-agent-skills.mjs
node .agents/skills/wp-project-triage/scripts/detect_wp_project.mjs
```

Check that each skill's `SKILL.md` appears under `.agents/skills`. **Ask each local agent to list recognized skills**, or use its skills UI. Visibility in GitHub does not guarantee that an agent in another directory or cloud task will see them.

## Updating pinned third-party skills

1. Review source changes and license upstream.
2. Update complete supporting files and the source commit in this inventory.
3. Run the validator and existing `node dev/verify.mjs` (with PHP installed for its PHP suite).
4. Open a new PR and review the diff; do not auto-update external skills into `main`.

## Added in this branch — UI UX Pro Max and Superpowers core

**UI UX Pro Max**: vendored **47 runtime files** from [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill/tree/56ea9cf174ed86786187daa9de0e6247cb0747e4/.claude/skills/ui-ux-pro-max) at commit `56ea9cf174ed86786187daa9de0e6247cb0747e4`, MIT; tests/fixtures omitted, runtime dataset, search, design system, references and Python scripts retained. Main `SKILL.md` has CLI command paths adapted to project-local `.agents/skills/ui-ux-pro-max/`. Python 3 is required; no npm package is installed.

**Superpowers core**: project-local selected workflow skills from [obra/superpowers](https://github.com/obra/superpowers/tree/bb92a77741419a4ab5f06e711a283343f1ada0c3/skills) at source tree `bb92a77741419a4ab5f06e711a283343f1ada0c3`, MIT:

- `writing-plans`
- `executing-plans`
- `test-driven-development`
- `systematic-debugging`
- `verification-before-completion`
- `using-git-worktrees`
- `requesting-code-review`
- `receiving-code-review`
- `finishing-a-development-branch`

The selected core includes necessary skill references and plan scripts. **It is not the full automatic Superpowers plugin installation**: the force-every-prompt `using-superpowers` bootstrap, visual brainstorm helper and subagent orchestration components are not installed. These work best when invoked deliberately per feature/bug. For full Codex/Antigravity plugin startup features, follow upstream's **official** plugin instructions locally, after reviewing its behavior; don't install duplicate skill copies alongside this project set.

**Licenses:** [UI UX Pro Max MIT](../.agents/licenses/ui-ux-pro-max-MIT.txt) and [Superpowers MIT](../.agents/licenses/superpowers-MIT.txt).

**Smoke tests after checking out this branch:**

```powershell
node dev/check-agent-skills.mjs
python .agents/skills/ui-ux-pro-max/scripts/search.py "restaurant checkout usability" --domain ux
python .agents/skills/ui-ux-pro-max/scripts/search.py "restaurant ordering site" --design-system
node dev/verify.mjs
```

CI checks skill structure and can run the Python search CLI without WordPress/network credentials. If your terminal calls Python `py` rather than `python`, use `py -3` locally. The existing PHP theme CI is separate; neither proves end-to-end WooCommerce checkout.

These GitHub project files **only become available** to Codex/Antigravity when the agent opens a checkout containing this branch (or `main` after merge). No claim is made about global skill installation on Windows, Codex plugins in the app or Antigravity plugins.
