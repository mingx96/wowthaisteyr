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

WordPress source `SKILL.md` paths were adapted to this repository's `.agents/skills/` layout. The full upstream support references/scripts and license notice are retained. A copy of the GPL text is at [third_party/licenses/wordpress-gpl.txt](../third_party/licenses/wordpress-gpl.txt). The Vercel skill no longer fetches unreviewed remote guidelines every time it runs; it uses a local snapshot.

## Compatibility and security

1. The WordPress upstream skills target **WordPress 7.0+**. Current `style.css` says `Tested up to: 6.7`, which does *not* confirm the deployed WordPress version. Verify core, WooCommerce and PHP before using version-specific recommendations.
2. `wp-performance` includes WP-CLI diagnostics. **Do not run on production** without explicit permission; even diagnostic commands may disclose environment data or affect server load.
3. This repo is **public**. Never add API keys, customer records, employee information, real orders, DB dumps or credentials.
4. `web-design-guidelines` provides code review, **not a browser E2E test**. Playwright + an isolated WooCommerce site are a separate future installation.
5. [UI UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) (large Python/data-powered CLI) and [Superpowers](https://github.com/obra/superpowers) (agent plugin) are *not* installed by this PR. Install them using the official agent-specific installer **locally**, only if needed. Avoid stacking competing workflow frameworks.
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
