---
name: wordpress-theme
description: Make a focused, reviewable WordPress theme change in the WOW Thai Steyr repository.
---

# WordPress theme workflow

1. Read root `AGENTS.md` and `COORDINATION.md`; check the current branch and working tree.
2. Identify affected templates, hooks, assets, and existing verification gates before editing.
3. Keep theme changes code-only. Do not add credentials, production data, database migrations, or unrelated redesigns.
4. Run `node dev/verify.mjs` and report gate output; run packaging only when a ZIP is requested.
5. Review the diff and open a PR. Do not upload, deploy, merge, or alter a database without explicit authorization.
