# Install AI skills for WOW Thai Steyr (Windows)

This repo is a custom **WordPress PHP theme** with WooCommerce checkout and a staff portal. Never install remote agent skills straight onto a live WordPress server.

## 1. Prepare workspace

Run in PowerShell on a development machine, not the production server:

```powershell
git clone https://github.com/mingx96/wowthaisteyr.git
cd wowthaisteyr
git checkout -b setup/agent-skills-local
node --version
npm --version
```

If this repo is already cloned, start in a **clean working tree**. Do not replace local edits or branches.

## 2. Preview first

```powershell
npx skills@latest add WordPress/agent-skills --list
npx skills@latest add vercel-labs/agent-skills --list
```

Run `npm exec --yes skills@latest -- --help` if the package/CLI syntax changes. The installer may ask which agent to target; select the local Codex or Antigravity environment you actually use. Review permissions and upstream content before installation.

Recommended WordPress skills, **selectively**:
- `wp-project-triage` — classify this PHP/theme repository
- `wp-performance` — diagnose loading and queries
- `wp-wpcli-and-ops` — operational checks, staging only
- `wp-playground` — disposable test environment

Optional:
- `wp-plugin-development` when a task specifically involves extracting functionality into a plugin.
- `web-design-guidelines` from `vercel-labs/agent-skills` for visual and accessibility audit.

The upstream WordPress skills may assume a different WordPress core version; verify actual site compatibility first.

## 3. Install only selected skills

After listing skills and reviewing upstream licenses/contents:

```powershell
npx skills@latest add WordPress/agent-skills --skill wp-project-triage --skill wp-performance
npx skills@latest add vercel-labs/agent-skills --skill web-design-guidelines
```

The CLI may install a skill into a platform-specific location or generate files. Check `git status` and `git diff`; nothing is automatically reviewed or merged by this guide. Update Codex/Antigravity skill discovery according to your installed product version.

For [UI UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill), follow its **current** README/official CLI install for the specific agent; do not copy an entire repo tree over this WordPress theme.

For [Superpowers](https://github.com/obra/superpowers), use the official Codex plugin UI or Antigravity plugin instructions and test in one development workspace. Avoid overlapping comprehensive workflow systems.

## 4. WordPress and WooCommerce validation

Current repository: `node dev/verify.mjs` runs G1–G10 (PHP checks only if PHP is available). This is not an end-to-end WooCommerce checkout test. Check `php -v`, run the PHP suite with a valid PHP binary, and verify checkout in a local disposable WordPress/WooCommerce instance or staging with synthetic test data.

Useful upstream tools:
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) (PHPCS/Composer) — propose as a separate reviewed CI change.
- [Playwright](https://github.com/microsoft/playwright) — create browser tests when staging fixtures and non-live payment are available.
- [WordPress Playground](https://github.com/WordPress/wordpress-playground) — verify which WooCommerce features actually work in the target environment.

Never import/overwrite a production database or make live checkout/payment changes just to validate a skill.

## 5. Working safely with multiple agents

Read `COORDINATION.md` first. Use a feature branch for each agent and a PR for changes. The file is an instruction, **not an exclusive lock**; do not use a shared live site for parallel writes. Pull Requests and CI are not deployment approval.

Cross-project reference: [AI-System](https://github.com/mingx96/AI-System) stores Obsidian knowledge, generic workflows and templates. A local agent must explicitly access that separate repo; cloud agents do not automatically see your PC's sibling folders.
