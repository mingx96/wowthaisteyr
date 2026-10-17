---
name: web-design-guidelines
description: Review UI code for Web Interface Guidelines compliance. Use when asked to "review my UI", "check accessibility", "audit design", "review UX", or "check my site against best practices".
metadata:
  author: vercel
  version: "1.0.0"
  argument-hint: <file-or-pattern>
---

# Web Interface Guidelines

Review files for compliance with Web Interface Guidelines.

## How It Works

1. Read the checked-in snapshot at `references/web-interface-guidelines.md` (do not fetch remote instructions automatically).
2. Read the specified files (or prompt user for files/pattern)
3. Check against applicable rules in the local guidelines snapshot, adapting React-specific suggestions to the PHP/WordPress stack.
4. Output findings in the terse `file:line` format

## Guidelines Source

Use the pinned, audited local snapshot in `references/web-interface-guidelines.md` instead of fetching remote content at runtime. Upstream: https://github.com/vercel-labs/web-interface-guidelines

## Usage

When a user provides a file or pattern argument:
1. Read local `references/web-interface-guidelines.md`
2. Read the specified files
3. Apply applicable rules from the pinned local guidelines
4. Output findings as `file:line — issue — recommendation`, distinguish source checks from browser-tested behavior.

If no files specified, ask the user which files to review.
